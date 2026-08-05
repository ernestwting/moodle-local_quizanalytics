<?php
/**
 * PHP port of analytics-service/analytics/prt_analysis.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class prt_analysis {

    // PRT names are author-defined in STACK (default "prt1"/"prt2", but Moodle
    // exports commonly show custom names like "Result"/"Result2" instead), so
    // a segment is recognized as a PRT field by process of elimination rather
    // than a literal "prt" prefix: exclude the "Seed: ..." metadata field and
    // "ansK: ... [tag]" fields, and treat anything else shaped like
    // "<name>: value" as a PRT.
    const ANS_FIELD_RE = '/^\s*ans\d+\s*:\s*.*\[(?:score|valid|invalid)\]\s*$/i';
    const SEED_FIELD_RE = '/^\s*seed\s*:/i';

    /**
     * Extract PRT values from a response string.
     *
     * @return array<int, array{0: string, 1: float, 2: string}> [prt_name, prt_score, status]
     */
    protected static function parse_prt_values(string $response_text): array {
        if ($response_text === '') {
            return [];
        }

        $prts = [];
        foreach (explode(';', $response_text) as $part) {
            if (preg_match(self::ANS_FIELD_RE, $part) || preg_match(self::SEED_FIELD_RE, $part)) {
                continue;
            }
            if (!preg_match('/^\s*(\w+)\s*:\s*(.+)$/', $part, $m)) {
                continue;
            }
            $prt_name = strtolower($m[1]);
            $value = trim($m[2]);

            if ($value === '!') {
                $prts[] = [$prt_name, 0.0, 'syntax_error'];
                continue;
            }

            if (preg_match('/#\s*=\s*([\d.]+)/', $value, $sm)) {
                $score = (float) $sm[1];
                $status = $score >= 0.5 ? 'correct' : 'incorrect';
                $prts[] = [$prt_name, $score, $status];
                continue;
            }

            $lower_value = strtolower($value);
            if (self::str_contains_any($lower_value, ['correct', 'true', 'pass'])) {
                $prts[] = [$prt_name, 1.0, 'correct'];
            } else if (self::str_contains_any($lower_value, ['incorrect', 'false', 'fail'])) {
                $prts[] = [$prt_name, 0.0, 'incorrect'];
            } else {
                $prts[] = [$prt_name, 0.0, 'incorrect'];
            }
        }

        return $prts;
    }

    protected static function str_contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * PRT pass rates by question and PRT name.
     *
     * @param array[] $prt_frame_rows as returned by build_prt_frame()
     * @return array[]
     */
    public static function compute_prt_pass_rates(array $prt_frame_rows): array {
        if (empty($prt_frame_rows)) {
            return [];
        }

        $by_question = table_helpers::group_by($prt_frame_rows, 'question');
        $rows = [];
        // pandas groupby("question") iterates groups in *sorted* key order by
        // default (sort=True) — plain string sort, matching PHP's ksort()
        // here (question labels are compared as strings either way).
        ksort($by_question);

        foreach ($by_question as $question => $group) {
            $prt_names = array_unique(array_map(fn($r) => (string) $r['prt_name'], $group));
            sort($prt_names);
            foreach ($prt_names as $prt_name) {
                $prt_rows = array_values(array_filter($group, fn($r) => $r['prt_name'] === $prt_name));
                $attempts = count($prt_rows);
                $pass_rate = 0.0;
                if ($attempts > 0) {
                    $passing = count(array_filter($prt_rows, fn($r) => $r['prt_score'] >= 0.5));
                    $pass_rate = py_compat::round(($passing / $attempts) * 100.0, 2);
                }
                $rows[] = [
                    'question' => $question,
                    'prt_name' => $prt_name,
                    'attempts' => $attempts,
                    'pass_rate' => $pass_rate,
                ];
            }
        }

        return $rows;
    }

    /**
     * Per-question, per-PRT frame for downstream charts — response_rows here
     * never already has prt_name/prt_score columns (that shape doesn't occur
     * in this plugin's pipeline), so this always re-parses response_text.
     *
     * @param array[] $response_rows
     * @return array[]
     */
    public static function build_prt_frame(array $response_rows): array {
        if (empty($response_rows)) {
            return [];
        }

        $rows = [];
        foreach ($response_rows as $row) {
            $parsed = self::parse_prt_values((string) ($row['response_text'] ?? ''));
            if (empty($parsed)) {
                // A response with no PRT trace still contributes a scored-zero
                // row, because pass rates are per *attempt* and a blank/
                // invalid response is a failed attempt. has_prt=false marks it
                // as synthesized so the heatmap can tell a question with no
                // PRT at all from one whose PRT everybody failed.
                $rows[] = [
                    'question' => $row['question'],
                    'prt_name' => 'prt1',
                    'prt_score' => 0.0,
                    'response_status' => $row['response_status'] ?? 'incorrect',
                    'has_prt' => false,
                ];
                continue;
            }
            foreach ($parsed as [$prt_name, $prt_score, $status]) {
                $rows[] = [
                    'question' => $row['question'],
                    'prt_name' => $prt_name,
                    'prt_score' => $prt_score,
                    'response_status' => $status,
                    'has_prt' => true,
                ];
            }
        }

        return $rows;
    }

    // Cells for a question with no PRT at all — Plotly renders null cells as
    // transparent, so painting the plot area this color is what makes them
    // read as "not applicable" rather than a zero pass rate.
    const NO_PRT_CELL_COLOR = '#d4d4d8';

    /**
     * Question x PRT pass-rate matrix for the heatmap. Every question in
     * $question_order gets a row even with no PRT data; missing cells stay
     * null rather than 0 (see NO_PRT_CELL_COLOR). Pass $prt_frame_rows to
     * blank out questions with no Potential Response Tree at all.
     *
     * @param array[] $prt_pass_rates_rows
     * @param string[] $question_order
     * @param array[]|null $prt_frame_rows
     * @return array{prt_names: string[], rows: array<string, array<string, float|null>>}
     */
    public static function build_prt_pass_heatmap(
        array $prt_pass_rates_rows,
        array $question_order,
        ?array $prt_frame_rows = null
    ): array {
        if (empty($prt_pass_rates_rows)) {
            return ['prt_names' => [], 'rows' => array_fill_keys($question_order, [])];
        }

        $prt_names = array_values(array_unique(array_map(fn($r) => $r['prt_name'], $prt_pass_rates_rows)));
        sort($prt_names);

        // pivot_table(aggfunc="first"): first-seen (question, prt_name) pair wins.
        $pivot = [];
        foreach ($prt_pass_rates_rows as $r) {
            if (!isset($pivot[$r['question']][$r['prt_name']])) {
                $pivot[$r['question']][$r['prt_name']] = $r['pass_rate'];
            }
        }

        $grid = [];
        foreach ($question_order as $q) {
            $row = [];
            foreach ($prt_names as $prt_name) {
                $row[$prt_name] = $pivot[$q][$prt_name] ?? null;
            }
            $grid[$q] = $row;
        }

        if ($prt_frame_rows !== null) {
            $with_prt = [];
            foreach ($prt_frame_rows as $r) {
                if (!empty($r['has_prt'])) {
                    $with_prt[$r['question']] = true;
                }
            }
            foreach ($question_order as $q) {
                if (!isset($with_prt[$q])) {
                    foreach ($prt_names as $prt_name) {
                        $grid[$q][$prt_name] = null;
                    }
                }
            }
        }

        return ['prt_names' => $prt_names, 'rows' => $grid];
    }

    /**
     * The PRT Pass Heatmap Plotly figure JSON.
     *
     * zmin/zmax are pinned to [0,100] (pass rate is always a percentage —
     * without pinning, a quiz where every question passes 40-60% of the time
     * would paint 40% red and 60% green, the same colors 0%/100% get on an
     * easier quiz) and the axis ranges are pinned to exactly
     * [-0.5, n-0.5] per axis (Plotly's default categorical-axis autorange
     * padding would otherwise show through as a border in NO_PRT_CELL_COLOR).
     *
     * @param array{prt_names: string[], rows: array<string, array<string, float|null>>} $heatmap
     */
    public static function build_prt_pass_heatmap_figure(array $heatmap, bool $colorblind_mode): array {
        $questions = array_keys($heatmap['rows']);
        $prt_names = $heatmap['prt_names'];

        $z = [];
        foreach ($questions as $q) {
            $row = [];
            foreach ($prt_names as $prt_name) {
                $row[] = $heatmap['rows'][$q][$prt_name] ?? null;
            }
            $z[] = $row;
        }

        return chart_helpers::build_heatmap_figure(
            $z,
            $prt_names,
            $questions,
            'PRT Pass Heatmap',
            chart_helpers::pass_fail_scale($colorblind_mode),
            0.0,
            100.0,
            null,
            self::NO_PRT_CELL_COLOR
        );
    }
}
