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
 * PHP port of analytics-service/analytics/validation.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Sanity checks on parsed response data before it's handed to the rest of the analytics pipeline.
 */
class validation {
    /**
     * Validate that the parsed response data contains the fields needed for
     * the dashboard, and cross-check each attempt's calculated average
     * against Moodle's own recorded overall grade.
     *
     * @param array[] $responserows
     * @return array{checks: array, issues: string[], is_valid: bool}
     */
    public static function audit_question_data(array $responserows): array {
        $totalattempts = count(array_unique(array_map(fn($r) => $r['attempt_idx'], $responserows)));
        $questioncount = count(array_unique(array_map(fn($r) => $r['question'], $responserows)));

        $checks = [
            'row_count' => $totalattempts,
            'question_count' => $questioncount,
            'has_question_column' => $questioncount > 0 || !empty($responserows),
            'has_grade_column' => true,
            'has_max_grade_column' => true,
            'has_response_status_column' => true,
            'has_response_text_column' => true,
        ];

        $issues = [];
        if ($totalattempts === 0) {
            $issues[] = 'No response rows were parsed from the uploaded export.';
        }

        if ($totalattempts > 0) {
            $checks['syntax_error_count'] = count(array_filter($responserows, fn($r) => $r['response_status'] === 'invalid'));
            $checks['invalid_count'] = $checks['syntax_error_count'];
            $checks['blank_count'] = count(array_filter($responserows, fn($r) => $r['response_status'] === 'blank'));
            $checks['ungraded_count'] = count(array_filter($responserows, fn($r) => $r['response_status'] === 'ungraded'));
        }

        // Automated grade verification / cross-check: each attempt's
        // calculated average (mean of its question grades, skipping
        // ungraded/null ones, scaled to 0-10) against Moodle's own recorded
        // overall_grade for that same attempt.
        $mismatches = [];
        $hasungradedrows = [];
        if (!empty($responserows)) {
            $byattempt = table_helpers::group_by($responserows, 'attempt_idx');
            // Iterate in ascending attempt_idx order, matching pandas
            // groupby()'s default sorted-key iteration.
            uksort($byattempt, fn($a, $b) => ((int) $a) <=> ((int) $b));

            foreach ($byattempt as $attemptid => $group) {
                $grades = array_map(fn($r) => $r['grade'], $group);
                $calculatedgrade = 10.0 * stats::mean($grades);
                $actualgrade = (float) $group[0]['overall_grade'];
                if (abs($calculatedgrade - $actualgrade) >= 0.01) {
                    $studentname = $group[0]['student_name'];
                    $mismatches[] = sprintf(
                        'Student: %s (Row %s) - Calculated=%.2f, Moodle=%.2f',
                        $studentname,
                        $attemptid,
                        $calculatedgrade,
                        $actualgrade
                    );
                    // Grade is null (excluded from the mean above) for any
                    // question left in a "validated, not (re-)graded" state —
                    // see parser::build_response_rows(). A mismatch on a row
                    // that has one of these has a known, specific cause, not
                    // just the generic "manual override" guess.
                    $hasungraded = false;
                    foreach ($group as $r) {
                        if ($r['response_status'] === 'ungraded') {
                            $hasungraded = true;
                            break;
                        }
                    }
                    $hasungradedrows[] = $hasungraded;
                }
            }
        }

        if (!empty($mismatches)) {
            if (in_array(true, $hasungradedrows, true)) {
                $issues[] = "Grade validation notice: Mismatches between calculated question-average scores and " .
                    "Moodle's overall attempt grades were found. Some are on rows with one or more " .
                    "'ungraded' responses (STACK re-validated an answer after it was already scored, so " .
                    "this export's Response column no longer shows a PRT result for it) -- the calculated " .
                    "average excludes those questions entirely rather than guessing, so it won't exactly " .
                    "match Moodle's own total for that row. Others may be due to manual grading overrides " .
                    "or regrades in Moodle:";
            } else {
                $issues[] = 'Grade validation notice: Mismatches between calculated question-average scores and ' .
                    "Moodle's overall attempt grades were found (likely due to manual grading overrides or regrades in Moodle):";
            }
            // Full list, not just the first 10 — the caller's UI caps this to
            // a scrollable viewport itself.
            foreach ($mismatches as $m) {
                $issues[] = "  • {$m}";
            }
        }

        return ['checks' => $checks, 'issues' => $issues, 'is_valid' => empty($issues)];
    }
}
