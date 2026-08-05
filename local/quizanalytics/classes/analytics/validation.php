<?php
/**
 * PHP port of analytics-service/analytics/validation.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class validation {

    /**
     * Validate that the parsed response data contains the fields needed for
     * the dashboard, and cross-check each attempt's calculated average
     * against Moodle's own recorded overall grade.
     *
     * @param array[] $response_rows
     * @return array{checks: array, issues: string[], is_valid: bool}
     */
    public static function audit_question_data(array $response_rows): array {
        $total_attempts = count(array_unique(array_map(fn($r) => $r['attempt_idx'], $response_rows)));
        $question_count = count(array_unique(array_map(fn($r) => $r['question'], $response_rows)));

        $checks = [
            'row_count' => $total_attempts,
            'question_count' => $question_count,
            'has_question_column' => $question_count > 0 || !empty($response_rows),
            'has_grade_column' => true,
            'has_max_grade_column' => true,
            'has_response_status_column' => true,
            'has_response_text_column' => true,
        ];

        $issues = [];
        if ($total_attempts === 0) {
            $issues[] = 'No response rows were parsed from the uploaded export.';
        }

        if ($total_attempts > 0) {
            $checks['syntax_error_count'] = count(array_filter($response_rows, fn($r) => $r['response_status'] === 'invalid'));
            $checks['invalid_count'] = $checks['syntax_error_count'];
            $checks['blank_count'] = count(array_filter($response_rows, fn($r) => $r['response_status'] === 'blank'));
            $checks['ungraded_count'] = count(array_filter($response_rows, fn($r) => $r['response_status'] === 'ungraded'));
        }

        // Automated grade verification / cross-check: each attempt's
        // calculated average (mean of its question grades, skipping
        // ungraded/null ones, scaled to 0-10) against Moodle's own recorded
        // overall_grade for that same attempt.
        $mismatches = [];
        $has_ungraded_rows = [];
        if (!empty($response_rows)) {
            $by_attempt = table_helpers::group_by($response_rows, 'attempt_idx');
            // Iterate in ascending attempt_idx order, matching pandas
            // groupby()'s default sorted-key iteration.
            uksort($by_attempt, fn($a, $b) => ((int) $a) <=> ((int) $b));

            foreach ($by_attempt as $attempt_id => $group) {
                $grades = array_map(fn($r) => $r['grade'], $group);
                $calculated_grade = 10.0 * stats::mean($grades);
                $actual_grade = (float) $group[0]['overall_grade'];
                if (abs($calculated_grade - $actual_grade) >= 0.01) {
                    $student_name = $group[0]['student_name'];
                    $mismatches[] = sprintf(
                        'Student: %s (Row %s) - Calculated=%.2f, Moodle=%.2f',
                        $student_name, $attempt_id, $calculated_grade, $actual_grade
                    );
                    // grade is null (excluded from the mean above) for any
                    // question left in a "validated, not (re-)graded" state —
                    // see parser::build_response_rows(). A mismatch on a row
                    // that has one of these has a known, specific cause, not
                    // just the generic "manual override" guess.
                    $has_ungraded = false;
                    foreach ($group as $r) {
                        if ($r['response_status'] === 'ungraded') {
                            $has_ungraded = true;
                            break;
                        }
                    }
                    $has_ungraded_rows[] = $has_ungraded;
                }
            }
        }

        if (!empty($mismatches)) {
            if (in_array(true, $has_ungraded_rows, true)) {
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
