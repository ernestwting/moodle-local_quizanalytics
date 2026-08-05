<?php
/**
 * PHP port of analytics-service/analytics/question_metrics.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class question_metrics {

    /**
     * Per-question metrics using Pool A for participation and Pool B for
     * performance.
     *
     * @param array[] $response_rows
     * @return array[]
     */
    public static function compute_question_metrics(array $response_rows): array {
        if (empty($response_rows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($response_rows);
        $pool_a = $pools['pool_a'];
        $pool_b = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($response_rows, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $q_a = array_values(array_filter($pool_a, fn($r) => $r['question'] === $q));
            $q_b = array_values(array_filter($pool_b, fn($r) => $r['question'] === $q));

            // --- Pool A metrics (participation / usage) ---
            $attempts_a = count($q_a);
            $students_a = count(array_unique(array_map(fn($r) => $r['student_id'], $q_a)));
            $invalid_count_a = count(array_filter($q_a, fn($r) => $r['response_status'] === 'invalid'));
            $blank_count_a = count(array_filter($q_a, fn($r) => $r['response_status'] === 'blank'));
            $invalid_rate_a = $attempts_a > 0 ? $invalid_count_a / $attempts_a : 0.0;
            $blank_rate_a = $attempts_a > 0 ? $blank_count_a / $attempts_a : 0.0;
            $percent_valid_a = max(0.0, (1.0 - $invalid_rate_a - $blank_rate_a) * 100.0);
            $reattempt_share_a = $attempts_a > 0 ? max(0.0, (($attempts_a - $students_a) / $attempts_a) * 100.0) : 0.0;

            // --- Pool B metrics (performance / mastery) ---
            $num_students_b = count($q_b);
            $correct_count_b = count(array_filter($q_b, fn($r) => $r['grade'] === 1.0));
            $facility_b = $num_students_b > 0 ? $correct_count_b / $num_students_b : 0.0;
            $grades_b = array_map(fn($r) => $r['grade'], $q_b);
            $partial_credit_mean_b = $num_students_b > 0 ? stats::mean($grades_b) : 0.0;
            $avg_score_b = $partial_credit_mean_b * 10.0;
            $scaled_score_b = $partial_credit_mean_b * 10.0;

            $percent_correct_b = $facility_b * 100.0;
            $percent_incorrect_b = (1.0 - $facility_b) * 100.0;

            // Catch-all share over wrong attempts in Pool B.
            $wrong_b = array_values(array_filter($q_b, fn($r) => $r['grade'] !== null && $r['grade'] < 1.0));
            $catch_all_count_b = 0;
            $total_wrong_prts_b = 0;
            foreach ($wrong_b as $r) {
                foreach (($r['prt_list'] ?? []) as $prt) {
                    $fraction = $prt['fraction'] ?? null;
                    $answer_note = trim((string) ($prt['answer_note'] ?? ''));
                    if ($fraction !== null && $fraction < 1.0) {
                        $total_wrong_prts_b++;
                        // PRT name prefix varies by export (e.g. "prt1-1-T" vs
                        // "Result-0-T"), so match on the trailing
                        // "-<index>-<T/F>" shape rather than a literal "prt" prefix.
                        if (preg_match('/^\w+-\d+-[TF]$/', $answer_note)) {
                            $catch_all_count_b++;
                        }
                    }
                }
            }
            $catch_all_share_b = $total_wrong_prts_b > 0 ? ($catch_all_count_b / $total_wrong_prts_b * 100.0) : 0.0;

            $rows[] = [
                'question' => $q,
                'attempts' => $attempts_a,
                'students' => $students_a,
                'invalid_rate' => py_compat::round($invalid_rate_a, 4),
                'blank_rate' => py_compat::round($blank_rate_a, 4),
                'reattempt_share' => py_compat::round($reattempt_share_a, 2),
                'facility' => py_compat::round($facility_b, 4),
                'partial_credit_mean' => py_compat::round($partial_credit_mean_b, 4),
                'avg_score' => py_compat::round($avg_score_b, 2),
                'percent_correct' => py_compat::round($percent_correct_b, 2),
                'percent_incorrect' => py_compat::round($percent_incorrect_b, 2),
                'percent_valid' => py_compat::round($percent_valid_a, 2),
                'percent_invalid' => py_compat::round($invalid_rate_a * 100.0, 2),
                'syntax_error_count' => $invalid_count_a,
                'syntax_error_percent' => py_compat::round($invalid_rate_a * 100.0, 2),
                'scaled_score' => py_compat::round($scaled_score_b, 2),
                'catch_all_share' => py_compat::round($catch_all_share_b, 2),
            ];
        }

        return $rows;
    }

    /**
     * High-level question analytics summary.
     *
     * @param array[] $response_rows
     * @return array
     */
    public static function compute_question_summary(array $response_rows): array {
        if (empty($response_rows)) {
            return [
                'total_questions' => 0,
                'student_count' => 0,
                'average_score' => 0.0,
                'average_valid_submission_rate' => 0.0,
                'average_correct_rate' => 0.0,
                'syntax_error_count' => 0,
            ];
        }

        $pools = parser::get_attempt_pools($response_rows);
        $pool_a = $pools['pool_a'];
        $pool_b = $pools['pool_b'];
        $qm = self::compute_question_metrics($response_rows);

        $total_questions = count(array_unique(array_map(fn($r) => $r['question'], $qm)));
        $student_count = count(array_unique(array_map(fn($r) => $r['student_id'], $pool_b)));

        $average_score = !empty($qm) ? stats::mean(array_map(fn($r) => $r['avg_score'], $qm)) : 0.0;
        $average_valid_submission_rate = !empty($qm) ? stats::mean(array_map(fn($r) => $r['percent_valid'], $qm)) : 0.0;
        $average_correct_rate = !empty($qm) ? stats::mean(array_map(fn($r) => $r['percent_correct'], $qm)) : 0.0;
        $syntax_error_count = count(array_filter($pool_a, fn($r) => $r['response_status'] === 'invalid'));

        return [
            'total_questions' => $total_questions,
            'student_count' => $student_count,
            'average_score' => py_compat::round($average_score, 2),
            'average_valid_submission_rate' => py_compat::round($average_valid_submission_rate, 2),
            'average_correct_rate' => py_compat::round($average_correct_rate, 2),
            'syntax_error_count' => $syntax_error_count,
        ];
    }

    /**
     * Rank questions by average score (hardest first).
     *
     * @param array[] $question_metrics_rows
     * @return array[]
     */
    public static function compute_ranked_difficulty(array $question_metrics_rows): array {
        if (empty($question_metrics_rows)) {
            return [];
        }
        $rows = $question_metrics_rows;
        usort($rows, fn($a, $b) => $a['avg_score'] <=> $b['avg_score']);
        return $rows;
    }
}
