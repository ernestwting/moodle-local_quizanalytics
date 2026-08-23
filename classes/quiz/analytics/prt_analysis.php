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
 * PHP port of analytics-service/analytics/prt_analysis.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Per-PRT pass rates, wrong-answer catch-all shares, and answer-note extraction from response text.
 */
class prt_analysis {
    /**
     * PRT names are author-defined in STACK (default "prt1"/"prt2", but Moodle
     * exports commonly show custom names like "Result"/"Result2" instead), so
     * a segment is recognized as a PRT field by process of elimination rather
     * than a literal "prt" prefix: exclude the "Seed: ..." metadata field and
     * "ansK: ... [tag]" fields, and treat anything else shaped like
     * "<name>: value" as a PRT.
     */
    // \w* (zero or more), not \w+: a single-input question can genuinely
    // name its own STACK input bare "ans" with no numeric/named suffix at
    // all (confirmed on real production data) — a `+` here failed to
    // recognize that field as an ans field, so it fell through to the
    // generic "<name>: value" PRT-detection check below (line 59) instead,
    // getting misread as a bogus PRT that then hit the "unrecognized value
    // shape" catch-all and was recorded as a hard-failed PRT node for
    // every single response to that question — see parser.php's identical
    // ans-field regex for the matching fix on the response-classification
    // side of this same bug.
    const ANS_FIELD_RE = '/^\s*ans\w*\s*:\s*.*\[(?:score|valid|invalid)\]\s*$/i';

    /** @var string Matches the "Seed: ..." metadata line so it's excluded from PRT-field detection. */
    const SEED_FIELD_RE = '/^\s*seed\s*:/i';

    /**
     * Extract PRT values from a response string.
     *
     * @return array<int, array{0: string, 1: float, 2: string}> [prt_name, prt_score, status]
     */
    protected static function parse_prt_values(string $responsetext): array {
        if ($responsetext === '') {
            return [];
        }

        $prts = [];
        foreach (explode(';', $responsetext) as $part) {
            if (preg_match(self::ANS_FIELD_RE, $part) || preg_match(self::SEED_FIELD_RE, $part)) {
                continue;
            }
            if (!preg_match('/^\s*(\w+)\s*:\s*(.+)$/', $part, $m)) {
                continue;
            }
            $prtname = strtolower($m[1]);
            $value = trim($m[2]);

            if ($value === '!') {
                $prts[] = [$prtname, 0.0, 'syntax_error'];
                continue;
            }

            if (preg_match('/#\s*=\s*([\d.]+)/', $value, $sm)) {
                $score = (float) $sm[1];
                $status = $score >= 0.5 ? 'correct' : 'incorrect';
                $prts[] = [$prtname, $score, $status];
                continue;
            }

            $lowervalue = strtolower($value);
            if (self::str_contains_any($lowervalue, ['correct', 'true', 'pass'])) {
                $prts[] = [$prtname, 1.0, 'correct'];
            } else if (self::str_contains_any($lowervalue, ['incorrect', 'false', 'fail'])) {
                $prts[] = [$prtname, 0.0, 'incorrect'];
            } else {
                $prts[] = [$prtname, 0.0, 'incorrect'];
            }
        }

        return $prts;
    }

    /**
     * True if $haystack contains any one of $needles.
     */
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
     * @param array[] $prtframerows as returned by build_prt_frame()
     * @return array[]
     */
    public static function compute_prt_pass_rates(array $prtframerows): array {
        if (empty($prtframerows)) {
            return [];
        }

        $byquestion = table_helpers::group_by($prtframerows, 'question');
        $rows = [];
        // Pandas groupby("question") iterates groups in *sorted* key order by
        // default (sort=True) — plain string sort, matching PHP's ksort()
        // here (question labels are compared as strings either way).
        ksort($byquestion);

        foreach ($byquestion as $question => $group) {
            $prtnames = array_unique(array_map(fn($r) => (string) $r['prt_name'], $group));
            sort($prtnames);
            foreach ($prtnames as $prtname) {
                $prtrows = array_values(array_filter($group, fn($r) => $r['prt_name'] === $prtname));
                $attempts = count($prtrows);
                $passrate = 0.0;
                if ($attempts > 0) {
                    $passing = count(array_filter($prtrows, fn($r) => $r['prt_score'] >= 0.5));
                    $passrate = py_compat::round(($passing / $attempts) * 100.0, 2);
                }
                $rows[] = [
                    'question' => $question,
                    'prt_name' => $prtname,
                    'attempts' => $attempts,
                    'pass_rate' => $passrate,
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
     * @param array[] $responserows
     * @return array[]
     */
    public static function build_prt_frame(array $responserows): array {
        if (empty($responserows)) {
            return [];
        }

        $rows = [];
        foreach ($responserows as $row) {
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
            foreach ($parsed as [$prtname, $prtscore, $status]) {
                $rows[] = [
                    'question' => $row['question'],
                    'prt_name' => $prtname,
                    'prt_score' => $prtscore,
                    'response_status' => $status,
                    'has_prt' => true,
                ];
            }
        }

        return $rows;
    }

    /**
     * Cells for a question with no PRT at all — Plotly renders null cells as
     * transparent, so painting the plot area this color is what makes them
     * read as "not applicable" rather than a zero pass rate.
     *
     * @var string
     */
    const NO_PRT_CELL_COLOR = '#d4d4d8';

    /**
     * Question x PRT pass-rate matrix for the heatmap. Every question in
     * $questionorder gets a row even with no PRT data; missing cells stay
     * null rather than 0 (see NO_PRT_CELL_COLOR). Pass $prtframerows to
     * blank out questions with no Potential Response Tree at all.
     *
     * @param array[] $prtpassratesrows
     * @param string[] $questionorder
     * @param array[]|null $prtframerows
     * @return array{prt_names: string[], rows: array<string, array<string, float|null>>}
     */
    public static function build_prt_pass_heatmap(
        array $prtpassratesrows,
        array $questionorder,
        ?array $prtframerows = null
    ): array {
        if (empty($prtpassratesrows)) {
            return ['prt_names' => [], 'rows' => array_fill_keys($questionorder, [])];
        }

        $prtnames = array_values(array_unique(array_map(fn($r) => $r['prt_name'], $prtpassratesrows)));
        sort($prtnames);

        // Pivot_table(aggfunc="first"): first-seen (question, prt_name) pair wins.
        $pivot = [];
        foreach ($prtpassratesrows as $r) {
            if (!isset($pivot[$r['question']][$r['prt_name']])) {
                $pivot[$r['question']][$r['prt_name']] = $r['pass_rate'];
            }
        }

        $grid = [];
        foreach ($questionorder as $q) {
            $row = [];
            foreach ($prtnames as $prtname) {
                $row[$prtname] = $pivot[$q][$prtname] ?? null;
            }
            $grid[$q] = $row;
        }

        if ($prtframerows !== null) {
            $withprt = [];
            foreach ($prtframerows as $r) {
                if (!empty($r['has_prt'])) {
                    $withprt[$r['question']] = true;
                }
            }
            foreach ($questionorder as $q) {
                if (!isset($withprt[$q])) {
                    foreach ($prtnames as $prtname) {
                        $grid[$q][$prtname] = null;
                    }
                }
            }
        }

        return ['prt_names' => $prtnames, 'rows' => $grid];
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
    public static function build_prt_pass_heatmap_figure(array $heatmap, bool $colorblindmode): array {
        $questions = array_keys($heatmap['rows']);
        $prtnames = $heatmap['prt_names'];

        $z = [];
        foreach ($questions as $q) {
            $row = [];
            foreach ($prtnames as $prtname) {
                $row[] = $heatmap['rows'][$q][$prtname] ?? null;
            }
            $z[] = $row;
        }

        return chart_helpers::build_heatmap_figure(
            $z,
            $prtnames,
            $questions,
            'PRT Pass Heatmap',
            chart_helpers::pass_fail_scale($colorblindmode),
            0.0,
            100.0,
            null,
            self::NO_PRT_CELL_COLOR
        );
    }
}
