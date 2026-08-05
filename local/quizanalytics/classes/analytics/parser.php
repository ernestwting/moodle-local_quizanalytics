<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHP port of analytics-service/analytics/parser.py — scoped to what the Moodle
 * plugin's data path actually needs. The Python module also handles parsing raw
 * uploaded Moodle CSV/Excel exports (column-alias detection, mojibake repair,
 * "grades breakdown" vs "responses" export-format detection, a whole second
 * "grades breakdown" row builder) — none of that applies here: this plugin never
 * receives a file, it reads finished attempts straight out of Moodle's own
 * database via data_fetcher.php, which already produces one flat, canonically-
 * shaped row per attempt. This class starts from that shape directly, producing
 * the same per-question-response row structure build_response_rows() does, and
 * the same Pool A / Pool B split get_attempt_pools() does.
 *
 * See app.py's _records_to_moodle_df() in analytics-service (the bridge the
 * Python-backed version of this plugin used) for the authoritative field-mapping
 * contract this was cross-checked against: data_fetcher.php's `grade` maps to
 * Python's `overall_grade` unchanged (the raw sumgrades value, not a 0-1
 * fraction), `question_N_text`/`right_answer_N` both get HTML-tag-stripped via
 * clean_html_text() before display, `response_N` stays raw (it's parsed for
 * ansK/prtK tags, not displayed), and `attempt_idx` is the 0-based position of
 * the attempt within $records — globally unique per quiz, which is what makes
 * get_attempt_pools()'s plain `isin($bestIndices)` filter (no per-student
 * scoping needed) correct in both the Python original and this port.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class parser {
    /**
     * Strip HTML tags/entities from a Moodle question/answer cell, leaving LaTeX
     * delimiters intact (they're backslash-escape sequences, not HTML tags, so
     * the tag-stripping regex below never touches them).
     *
     * Elements with an inline `display: none` style are dropped entirely
     * (tag and content) before the generic tag-stripping pass below —
     * some STACK question authoring leaves hidden markers like
     * `<p style="display: none;">\({\mathbf{True}}\)</p>` in the rendered
     * question HTML (seemingly an authoring/versioning artifact, invisible
     * in a real browser), which the plain "strip the tags, keep the text"
     * approach below would otherwise surface as visible, confusing bold
     * "True"/"False" text with no connection to the actual question.
     */
    public static function clean_html_text(?string $text): string {
        if ($text === null || $text === '') {
            return '';
        }
        $cleaned = preg_replace(
            '/<(\w+)[^>]*\bstyle\s*=\s*"[^"]*display\s*:\s*none[^"]*"[^>]*>.*?<\/\1>/is',
            ' ',
            $text
        );
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5);
        $cleaned = preg_replace('/<[^>]+>/', ' ', $cleaned);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Parse a single Response cell to extract ansK and prtK values.
     *
     * @return array{ans_list: array, prt_list: array}
     */
    public static function parse_response_cell(?string $cell_text): array {
        if ($cell_text === null || $cell_text === '') {
            return ['ans_list' => [], 'prt_list' => []];
        }

        // 1. Parse ans fields: ansK: expression [tag]
        preg_match_all('/ans(\d+):\s*(.*?)\s*\[(score|valid|invalid)\]/', $cell_text, $ans_matches, PREG_SET_ORDER);
        $ans_list = [];
        foreach ($ans_matches as $m) {
            $ans_list[] = [
                'index' => (int) $m[1],
                'expression' => trim($m[2]),
                'tag' => $m[3],
            ];
        }

        // 2. Parse PRT fields: <name>: ! OR <name>: # = fraction | note1 | note2...
        // PRT names are author-defined in STACK (default "prt1"/"prt2", but Moodle
        // exports commonly show custom names like "Result"/"Result2" instead) —
        // matching depends on the "!" / "# = fraction" value shape, not on any
        // literal "prt" prefix or embedded digit, so an index is assigned by order
        // of appearance rather than parsed from the name.
        preg_match_all('/(\w+):\s*(!|# = ([0-9.]+))(?:\s*\|\s*([^;]*))?/', $cell_text, $prt_matches, PREG_SET_ORDER);
        $prt_list = [];
        $idx = 1;
        foreach ($prt_matches as $m) {
            $val = $m[2];
            $fraction = null;
            $answer_note = '(invalid/blank input)';
            $answer_notes = [];

            if ($val !== '!') {
                $fraction = (float) $m[3];
                $notes_str = $m[4] ?? '';
                if ($notes_str !== '') {
                    $tokens = array_values(array_filter(
                        array_map('trim', explode('|', $notes_str)),
                        fn($t) => $t !== ''
                    ));
                    $answer_notes = $tokens;
                    $answer_note = !empty($tokens) ? end($tokens) : '';
                } else {
                    $answer_note = '';
                }
            }

            // Terminal answer note only — the node the PRT traversal ended at,
            // kept as the primary field because that's what every existing
            // consumer expects. answer_notes carries the full traversal trace.
            $prt_list[] = [
                'index' => $idx,
                'fraction' => $fraction,
                'answer_note' => $answer_note,
                'answer_notes' => $answer_notes,
            ];
            $idx++;
        }

        return ['ans_list' => $ans_list, 'prt_list' => $prt_list];
    }

    /**
     * Best-effort parse of a Moodle-formatted datetime string ("Y-m-d H:i:s",
     * data_fetcher.php's userdate() format) into a Unix timestamp, for the Pool B
     * tie-break comparison in get_attempt_pools() — 0 (the Unix epoch, always the
     * oldest/smallest possible value) is the "unparseable, always loses a
     * tie-break" fallback, mirroring pd.Timestamp.min's role in parser.py.
     */
    public static function parse_completed_dt(?string $val): int {
        if ($val === null || $val === '') {
            return 0;
        }
        $ts = strtotime($val);
        return $ts !== false ? $ts : 0;
    }

    /**
     * Convert data_fetcher.php's flat per-attempt $records into a flattened
     * question-response table — one row per (attempt, question) pair, matching
     * parser.py's build_response_rows() row shape exactly.
     *
     * @param array $records  as returned by
     *              local_quizanalytics_data_fetcher::get_response_records_for_quiz()
     * @param string $quiz_name
     * @return array[] list of associative arrays (the "response rows")
     */
    public static function build_response_rows(array $records, string $quiz_name): array {
        if (empty($records)) {
            return [];
        }

        // Discover which question numbers are present by scanning keys, matching
        // app.py's _records_to_moodle_df() question_numbers derivation.
        $question_numbers = [];
        foreach ($records as $rec) {
            foreach ($rec as $key => $_) {
                if (preg_match('/^question_(\d+)_text$/', $key, $m)) {
                    $question_numbers[(int) $m[1]] = true;
                }
            }
        }
        $question_numbers = array_keys($question_numbers);
        sort($question_numbers);

        // Determine M (number of PRT parts) for each question, scanning every
        // record's response cell first — mirrors build_response_rows()'s M_dict.
        $m_by_question = [];
        foreach ($question_numbers as $n) {
            $max_k = 1;
            foreach ($records as $rec) {
                $cell = $rec["response_{$n}"] ?? null;
                if ($cell === null || $cell === '') {
                    continue;
                }
                $parsed = self::parse_response_cell((string) $cell);
                foreach ($parsed['prt_list'] as $prt) {
                    if ($prt['index'] > $max_k) {
                        $max_k = $prt['index'];
                    }
                }
            }
            $m_by_question[$n] = $max_k;
        }

        $rows = [];
        foreach (array_values($records) as $index => $rec) {
            $email = trim((string) ($rec['email'] ?? ''));
            $first_name = trim((string) ($rec['first_name'] ?? ''));
            $last_name = trim((string) ($rec['last_name'] ?? ''));
            $full_name = trim("{$first_name} {$last_name}");

            // Moodle's own user table always has real names/emails for these
            // records (unlike an anonymized CSV export, the case parser.py's
            // fuller fallback logic exists for) — these are just a defensive
            // backstop, not expected to fire in practice.
            $student_id = $email !== '' ? $email : ('student' . ($index + 1) . '@anonymized.local');
            $student_name = $full_name !== '' ? $full_name : ('Student ' . ($index + 1));

            $overall_grade = (isset($rec['grade']) && $rec['grade'] !== '') ? (float) $rec['grade'] : 0.0;
            $completed_dt = (string) ($rec['completed'] ?? '');
            $started_on = (string) ($rec['started_on'] ?? $completed_dt);

            foreach ($question_numbers as $n) {
                $question_label = "Q{$n}";
                $cell_text = (string) ($rec["response_{$n}"] ?? '');

                $question_text = self::clean_html_text((string) ($rec["question_{$n}_text"] ?? ''));
                $right_answer_text = self::clean_html_text((string) ($rec["right_answer_{$n}"] ?? ''));

                $parsed = self::parse_response_cell($cell_text);
                $ans_list = $parsed['ans_list'];
                $prt_list = $parsed['prt_list'];

                $is_blank = empty($ans_list);
                $is_invalid = false;
                foreach ($ans_list as $ans) {
                    if ($ans['tag'] === 'invalid') {
                        $is_invalid = true;
                        break;
                    }
                }

                // A response can be left in a "validated, not (re-)graded" state
                // — see parser.py's own extensive comment on this for the full
                // story (STACK re-validating an answer after the attempt was
                // already scored, so this cell no longer carries the PRT result
                // that actually graded it). Distinct from blank/invalid.
                $is_ungraded = false;
                if (!$is_blank && !$is_invalid && !empty($prt_list)) {
                    $is_ungraded = true;
                    foreach ($prt_list as $prt) {
                        if ($prt['fraction'] !== null) {
                            $is_ungraded = false;
                            break;
                        }
                    }
                }

                // Score computation: mean over all PRTs K of (prtK.fraction or 0.0).
                $m = $m_by_question[$n];
                $prt_by_index = [];
                foreach ($prt_list as $prt) {
                    $prt_by_index[$prt['index']] = $prt;
                }
                $prt_fractions = [];
                for ($k = 1; $k <= $m; $k++) {
                    if (isset($prt_by_index[$k]) && $prt_by_index[$k]['fraction'] !== null) {
                        $prt_fractions[] = $prt_by_index[$k]['fraction'];
                    } else {
                        $prt_fractions[] = 0.0;
                    }
                }
                $q_score = $m > 0 ? array_sum($prt_fractions) / $m : 0.0;

                if ($is_blank) {
                    $response_status = 'blank';
                } else if ($is_invalid) {
                    $response_status = 'invalid';
                } else if ($is_ungraded) {
                    $response_status = 'ungraded';
                } else if ($q_score == 1.0) {
                    $response_status = 'correct';
                } else {
                    $response_status = 'incorrect';
                }

                $rows[] = [
                    'student_id' => $student_id,
                    'student_name' => $student_name,
                    'question' => $question_label,
                    'grade' => $is_ungraded ? null : $q_score,
                    'max_grade' => 1.0,
                    'response_status' => $response_status,
                    'response_text' => $cell_text,
                    'quiz_name' => $quiz_name,
                    'ans_list' => $ans_list,
                    'prt_list' => $prt_list,
                    'overall_grade' => $overall_grade,
                    'completed_dt' => $completed_dt,
                    'started_on' => $started_on,
                    'attempt_idx' => $index,
                    'source_type' => 'responses',
                    'question_text' => $question_text,
                    'right_answer_text' => $right_answer_text,
                ];
            }
        }

        return $rows;
    }

    /**
     * Separate response rows into (Pool A, Pool B):
     * - Pool A ("All Attempts"): every row unchanged.
     * - Pool B ("Best Attempt per Student"): exactly one attempt per student,
     *   selected as the row with the highest overall_grade (ties: latest
     *   completed_dt, then highest attempt_idx).
     *
     * @param array[] $response_rows
     * @return array{pool_a: array[], pool_b: array[]}
     */
    public static function get_attempt_pools(array $response_rows): array {
        if (empty($response_rows)) {
            return ['pool_a' => $response_rows, 'pool_b' => $response_rows];
        }

        $pool_a = $response_rows;

        // Deduplicate to one entry per (student_id, attempt_idx) — response_rows
        // has one row per question per attempt, so this collapses back to one
        // entry per actual attempt.
        $attempt_meta = [];
        $seen = [];
        foreach ($response_rows as $row) {
            $key = $row['student_id'] . '|' . $row['attempt_idx'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $attempt_meta[] = [
                'student_id' => $row['student_id'],
                'attempt_idx' => $row['attempt_idx'],
                'overall_grade' => $row['overall_grade'],
                'completed_ts' => self::parse_completed_dt($row['completed_dt']),
            ];
        }

        // For each student, keep the attempt that sorts first under (overall_grade
        // desc, completed_dt desc, attempt_idx desc) — mirrors sort_values(...,
        // ascending=[False, False, False]).groupby('student_id').first().
        $best_by_student = [];
        foreach ($attempt_meta as $meta) {
            $sid = $meta['student_id'];
            if (!isset($best_by_student[$sid]) || self::is_better_attempt($meta, $best_by_student[$sid])) {
                $best_by_student[$sid] = $meta;
            }
        }

        $best_indices = [];
        foreach ($best_by_student as $meta) {
            $best_indices[(string) $meta['attempt_idx']] = true;
        }

        $pool_b = array_values(array_filter(
            $response_rows,
            fn($row) => isset($best_indices[(string) $row['attempt_idx']])
        ));

        return ['pool_a' => $pool_a, 'pool_b' => $pool_b];
    }

    protected static function is_better_attempt(array $candidate, array $current): bool {
        if ($candidate['overall_grade'] !== $current['overall_grade']) {
            return $candidate['overall_grade'] > $current['overall_grade'];
        }
        if ($candidate['completed_ts'] !== $current['completed_ts']) {
            return $candidate['completed_ts'] > $current['completed_ts'];
        }
        return $candidate['attempt_idx'] > $current['attempt_idx'];
    }
}
