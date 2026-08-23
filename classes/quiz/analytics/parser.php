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

namespace local_quizanalytics\quiz\analytics;

/**
 * Turns raw Moodle attempt records into the per-question "response rows" every other analytics module consumes.
 */
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
        // PHP's default (non-Unicode) \s never matches U+00A0 (the decoded
        // form of &nbsp;), so a WYSIWYG editor's "empty" trailing paragraph
        // (commonly just `<p>&nbsp;</p>`) survives every whitespace-collapse
        // pass below untouched and shows up as extra dangling <br> breaks at
        // the end of the question text. Folding it to a plain space here
        // lets those paragraphs collapse away like any other blank one.
        $cleaned = str_replace("\xc2\xa0", ' ', $cleaned);

        // A multi-part STACK question's own authored HTML almost always uses
        // <br>/<p>/<table><tr> to separate its parts visually — stripping
        // every tag down to a single space (as the pass below does) threw
        // that structure away entirely, collapsing a whole multi-part
        // question into one unbroken run of text with no visual separation
        // between parts at all. \x02 is a placeholder (not whitespace, so
        // the \s+ collapse below can't eat it) standing in for a line break
        // at each of these boundaries, turned into a real <br> right before
        // returning — the caller assigns this text via innerHTML (see
        // sections-renderer.js), so an actual <br> tag, not a bare "\n"
        // character a browser would just render as whitespace, is what's
        // needed for it to show as a visible break.
        $cleaned = preg_replace('/<\s*br\s*\/?\s*>/i', "\x02", $cleaned);
        $cleaned = preg_replace('/<\/\s*(p|tr|table|div|li)\s*>/i', "\x02", $cleaned);
        $cleaned = preg_replace('/<[^>]+>/', ' ', $cleaned);
        $cleaned = trim(preg_replace('/[^\S\x02]+/', ' ', $cleaned));
        // Several structural boundaries in a row (e.g. </tr><tr>, or a
        // blank line in the source) would otherwise become a run of several
        // consecutive breaks — collapsed to exactly one, since a report is
        // for scanning quickly, not preserving an author's exact blank-line
        // spacing.
        $cleaned = preg_replace('/\s*(?:\x02\s*)+/', "\x02", $cleaned);
        $cleaned = trim($cleaned, " \x02");
        return str_replace("\x02", '<br>', $cleaned);
    }

    /**
     * Parse a single Response cell to extract ansK and prtK values.
     *
     * @return array{ans_list: array, prt_list: array}
     */
    public static function parse_response_cell(?string $celltext): array {
        if ($celltext === null || $celltext === '') {
            return ['ans_list' => [], 'prt_list' => []];
        }

        // 1. Parse ans fields: ansK: expression [tag]. STACK input names are
        // author-defined too (default "ans1"/"ans2", but a multi-part
        // question commonly renames them to something descriptive like
        // "ans_mcq"/"ans_fx", or — a single-input question, confirmed on
        // real production data — leaves off any suffix at all: just "ans")
        // — matched on the "ans...: ... [tag]" shape rather than a literal
        // numeric suffix, same reasoning as the PRT matching below. The
        // suffix group is `\w*` (zero or more), not `\w+`: a `+` here
        // silently failed to match a bare "ans:" field at all, so a
        // genuinely answered, correctly graded response (e.g. "ans:
        // sin(2*x) [score]") had an empty parsed ans_list and was
        // misclassified as 'blank' — a real bug, not a hypothetical one.
        // 'index' stays an int (and is used for numeric part-lookups
        // elsewhere) only when the suffix genuinely is one; a named suffix
        // or no suffix at all leaves it null rather than colliding on 0.
        preg_match_all('/ans(\w*):\s*(.*?)\s*\[(score|valid|invalid)\]/', $celltext, $ansmatches, PREG_SET_ORDER);
        $anslist = [];
        foreach ($ansmatches as $m) {
            $anslist[] = [
                'index' => ctype_digit($m[1]) ? (int) $m[1] : null,
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
        preg_match_all('/(\w+):\s*(!|# = ([0-9.]+))(?:\s*\|\s*([^;]*))?/', $celltext, $prtmatches, PREG_SET_ORDER);
        $prtlist = [];
        $idx = 1;
        foreach ($prtmatches as $m) {
            $val = $m[2];
            $fraction = null;
            $answernote = '(invalid/blank input)';
            $answernotes = [];

            if ($val !== '!') {
                $fraction = (float) $m[3];
                $notesstr = $m[4] ?? '';
                if ($notesstr !== '') {
                    $tokens = array_values(array_filter(
                        array_map('trim', explode('|', $notesstr)),
                        fn($t) => $t !== ''
                    ));
                    $answernotes = $tokens;
                    $answernote = !empty($tokens) ? end($tokens) : '';
                } else {
                    $answernote = '';
                }
            }

            // Terminal answer note only — the node the PRT traversal ended at,
            // kept as the primary field because that's what every existing
            // consumer expects. answer_notes carries the full traversal trace.
            $prtlist[] = [
                'index' => $idx,
                'fraction' => $fraction,
                'answer_note' => $answernote,
                'answer_notes' => $answernotes,
            ];
            $idx++;
        }

        return ['ans_list' => $anslist, 'prt_list' => $prtlist];
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
     *              local_quizanalytics_quiz_data_fetcher::get_response_records_for_quiz()
     * @param string $quizname
     * @param bool $anonymize replace real student names/emails with stable per-student
     *        pseudonyms - see anonymize::anonymize_response_rows()
     * @return array[] list of associative arrays (the "response rows")
     */
    public static function build_response_rows(array $records, string $quizname, bool $anonymize = false): array {
        if (empty($records)) {
            return [];
        }

        // Discover which question numbers are present by scanning keys, matching
        // app.py's _records_to_moodle_df() question_numbers derivation.
        $questionnumbers = [];
        foreach ($records as $rec) {
            foreach ($rec as $key => $unused) {
                if (preg_match('/^question_(\d+)_text$/', $key, $m)) {
                    $questionnumbers[(int) $m[1]] = true;
                }
            }
        }
        $questionnumbers = array_keys($questionnumbers);
        sort($questionnumbers);

        // Determine M (number of PRT parts) for each question, scanning every
        // record's response cell first — mirrors build_response_rows()'s M_dict.
        $mbyquestion = [];
        foreach ($questionnumbers as $n) {
            $maxk = 1;
            foreach ($records as $rec) {
                $cell = $rec["response_{$n}"] ?? null;
                if ($cell === null || $cell === '') {
                    continue;
                }
                $parsed = self::parse_response_cell((string) $cell);
                foreach ($parsed['prt_list'] as $prt) {
                    if ($prt['index'] > $maxk) {
                        $maxk = $prt['index'];
                    }
                }
            }
            $mbyquestion[$n] = $maxk;
        }

        $rows = [];
        foreach (array_values($records) as $index => $rec) {
            $email = trim((string) ($rec['email'] ?? ''));
            $firstname = trim((string) ($rec['first_name'] ?? ''));
            $lastname = trim((string) ($rec['last_name'] ?? ''));
            $fullname = trim("{$firstname} {$lastname}");

            // Moodle's own user table always has real names/emails for these
            // records (unlike an anonymized CSV export, the case parser.py's
            // fuller fallback logic exists for) — these are just a defensive
            // backstop, not expected to fire in practice.
            $studentid = $email !== '' ? $email : ('student' . ($index + 1) . '@anonymized.local');
            $studentname = $fullname !== '' ? $fullname : ('Student ' . ($index + 1));

            $overallgrade = (isset($rec['grade']) && $rec['grade'] !== '') ? (float) $rec['grade'] : 0.0;
            $completeddt = (string) ($rec['completed'] ?? '');
            $startedon = (string) ($rec['started_on'] ?? $completeddt);

            foreach ($questionnumbers as $n) {
                $questionlabel = "Q{$n}";
                $celltext = (string) ($rec["response_{$n}"] ?? '');

                $questiontext = self::clean_html_text((string) ($rec["question_{$n}_text"] ?? ''));
                $rightanswertext = self::clean_html_text((string) ($rec["right_answer_{$n}"] ?? ''));

                $parsed = self::parse_response_cell($celltext);
                $anslist = $parsed['ans_list'];
                $prtlist = $parsed['prt_list'];

                $isblank = empty($anslist);
                $isinvalid = false;
                foreach ($anslist as $ans) {
                    if ($ans['tag'] === 'invalid') {
                        $isinvalid = true;
                        break;
                    }
                }

                // A response can be left in a "validated, not (re-)graded" state
                // — see parser.py's own extensive comment on this for the full
                // story (STACK re-validating an answer after the attempt was
                // already scored, so this cell no longer carries the PRT result
                // that actually graded it). Distinct from blank/invalid.
                $isungraded = false;
                if (!$isblank && !$isinvalid && !empty($prtlist)) {
                    $isungraded = true;
                    foreach ($prtlist as $prt) {
                        if ($prt['fraction'] !== null) {
                            $isungraded = false;
                            break;
                        }
                    }
                }

                // Score computation: mean over all PRTs K of (prtK.fraction or 0.0).
                $m = $mbyquestion[$n];
                $prtbyindex = [];
                foreach ($prtlist as $prt) {
                    $prtbyindex[$prt['index']] = $prt;
                }
                $prtfractions = [];
                for ($k = 1; $k <= $m; $k++) {
                    if (isset($prtbyindex[$k]) && $prtbyindex[$k]['fraction'] !== null) {
                        $prtfractions[] = $prtbyindex[$k]['fraction'];
                    } else {
                        $prtfractions[] = 0.0;
                    }
                }
                $qscore = $m > 0 ? array_sum($prtfractions) / $m : 0.0;

                if ($isblank) {
                    $responsestatus = 'blank';
                } else if ($isinvalid) {
                    $responsestatus = 'invalid';
                } else if ($isungraded) {
                    $responsestatus = 'ungraded';
                } else if ($qscore == 1.0) {
                    $responsestatus = 'correct';
                } else {
                    $responsestatus = 'incorrect';
                }

                $rows[] = [
                    'student_id' => $studentid,
                    'student_name' => $studentname,
                    'question' => $questionlabel,
                    'grade' => $isungraded ? null : $qscore,
                    'max_grade' => 1.0,
                    'response_status' => $responsestatus,
                    'response_text' => $celltext,
                    'quiz_name' => $quizname,
                    'ans_list' => $anslist,
                    'prt_list' => $prtlist,
                    'overall_grade' => $overallgrade,
                    'completed_dt' => $completeddt,
                    'started_on' => $startedon,
                    'attempt_idx' => $index,
                    'source_type' => 'responses',
                    'question_text' => $questiontext,
                    'right_answer_text' => $rightanswertext,
                ];
            }
        }

        return $anonymize ? anonymize::anonymize_response_rows($rows) : $rows;
    }

    /**
     * True if $row has a genuine correct/incorrect outcome worth counting
     * toward a facility/success-rate/average-score denominator — i.e. its
     * response_status is 'correct' or 'incorrect', not 'blank' (never
     * attempted at all) or 'ungraded' (STACK re-validated the answer after
     * the attempt was already scored, so no PRT result survives to read).
     * 'invalid' stays counted as a real outcome (toward "incorrect"): the
     * student did submit something, STACK just couldn't parse it as a
     * well-formed expression, which is the same 0-mark outcome Moodle's own
     * gradebook records for it.
     *
     * Every facility/percent-correct/average-score calculation across this
     * plugin's analytics needs this same exclusion, or a question nobody
     * ever reached (blank) silently divides its "correct" count by a
     * denominator that includes those never-attempted rows anyway —
     * producing a *lower*, not "no data", facility, and in the extreme
     * case (nobody attempted the question at all) a flat 0% facility /
     * 100% incorrect that looks like verified universal failure rather
     * than what it actually is: no attempts to measure at all. Confirmed
     * as a real, live bug this way (not just a theoretical risk): a real
     * course had several consecutive questions nobody had ever attempted,
     * each showing exactly 100% incorrect on the Response Outcome
     * Percentages chart before this fix, which dragged the course-wide
     * "average correct rate" summary down with it since that stat is
     * itself just a mean of every question's own (buggy) percent_correct.
     */
    public static function is_graded_response(array $row): bool {
        return $row['response_status'] === 'correct' || $row['response_status'] === 'incorrect';
    }

    /**
     * Separate response rows into (Pool A, Pool B):
     * - Pool A ("All Attempts"): every row unchanged.
     * - Pool B ("Best Attempt per Student"): exactly one attempt per student,
     *   selected as the row with the highest overall_grade (ties: latest
     *   completed_dt, then highest attempt_idx).
     *
     * @param array[] $responserows
     * @return array{pool_a: array[], pool_b: array[]}
     */
    public static function get_attempt_pools(array $responserows): array {
        if (empty($responserows)) {
            return ['pool_a' => $responserows, 'pool_b' => $responserows];
        }

        $poola = $responserows;

        // Deduplicate to one entry per (student_id, attempt_idx) — response_rows
        // has one row per question per attempt, so this collapses back to one
        // entry per actual attempt.
        $attemptmeta = [];
        $seen = [];
        foreach ($responserows as $row) {
            $key = $row['student_id'] . '|' . $row['attempt_idx'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $attemptmeta[] = [
                'student_id' => $row['student_id'],
                'attempt_idx' => $row['attempt_idx'],
                'overall_grade' => $row['overall_grade'],
                'completed_ts' => self::parse_completed_dt($row['completed_dt']),
            ];
        }

        // For each student, keep the attempt that sorts first under (overall_grade
        // desc, completed_dt desc, attempt_idx desc) — mirrors sort_values(...,
        // ascending=[False, False, False]).groupby('student_id').first().
        $bestbystudent = [];
        foreach ($attemptmeta as $meta) {
            $sid = $meta['student_id'];
            if (!isset($bestbystudent[$sid]) || self::is_better_attempt($meta, $bestbystudent[$sid])) {
                $bestbystudent[$sid] = $meta;
            }
        }

        $bestindices = [];
        foreach ($bestbystudent as $meta) {
            $bestindices[(string) $meta['attempt_idx']] = true;
        }

        $poolb = array_values(array_filter(
            $responserows,
            fn($row) => isset($bestindices[(string) $row['attempt_idx']])
        ));

        return ['pool_a' => $poola, 'pool_b' => $poolb];
    }

    /**
     * True if $candidate should replace $current as a student's "best attempt" (higher grade,
     * ties broken by earlier attempt).
     */
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
