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
 * PHP port of analytics-service/analytics/difficulty.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class difficulty {
    /**
     * Compute difficulty, marks stats, and discrimination index D using
     * Pool B (Best Attempt per Student).
     *
     * @param array[] $response_rows
     * @return array[] one row per question
     */
    public static function compute_difficulty_metrics(array $response_rows): array {
        if (empty($response_rows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($response_rows);
        $pool_b = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($pool_b, 'question');

        // Overall quiz performance per student in Pool B, for the top/bottom
        // 27% cohort ranking below — one overall_grade per student (all their
        // Pool B rows share the same attempt-level overall_grade).
        $student_scores = [];
        foreach ($pool_b as $row) {
            if (!array_key_exists($row['student_id'], $student_scores)) {
                $student_scores[$row['student_id']] = $row['overall_grade'];
            }
        }
        // Known, accepted divergence from the Python original: when several
        // students tie on overall_grade exactly at the top/bottom 27% cohort
        // cutoff, which of the tied students lands inside vs outside the
        // cohort depends there on pandas' Series.sort_values(), which uses an
        // unstable quicksort — its tie order is an internal numpy/C
        // implementation detail, not a documented or reproducible contract,
        // so it isn't something a from-scratch PHP port can match bit-for-
        // bit. PHP's arsort() is stable (guaranteed since PHP 8.0), so ties
        // here are broken by student appearance order in Pool B instead —
        // deterministic, but can select a different student than the Python
        // original at that exact boundary. Only affects discrimination_index
        // for questions where a tied student's pass/fail differs from the
        // rest of the tied group, and only when a tie actually straddles the
        // cutoff — everything else in this function is unaffected.
        arsort($student_scores, SORT_NUMERIC);
        $sorted_students = array_keys($student_scores);
        $n_students = count($sorted_students);

        $k = $n_students > 0 ? max(1, (int) py_compat::round(0.27 * $n_students)) : 0;
        $top_group = $k > 0 ? array_flip(array_slice($sorted_students, 0, $k)) : [];
        $bottom_group = $k > 0 ? array_flip(array_slice($sorted_students, -$k)) : [];

        $rows = [];
        foreach ($questions as $q) {
            $q_b = array_values(array_filter($pool_b, fn($r) => $r['question'] === $q));
            if (empty($q_b)) {
                continue;
            }

            $scores10 = array_map(fn($r) => $r['grade'] === null ? null : $r['grade'] * 10.0, $q_b);
            $avg_marks = stats::mean($scores10);
            $median_marks = stats::median($scores10);
            $non_null_count = count(array_filter($scores10, fn($v) => $v !== null));
            $std_marks = $non_null_count > 1 ? stats::sample_stdev($scores10) : 0.0;
            $var_marks = $non_null_count > 1 ? stats::sample_variance($scores10) : 0.0;

            $len_qb = count($q_b);
            $correct_count = count(array_filter($q_b, fn($r) => $r['grade'] === 1.0));
            $facility = $len_qb > 0 ? $correct_count / $len_qb : 0.0;
            $success_rate = $facility * 100.0;

            $top_q = array_values(array_filter($q_b, fn($r) => isset($top_group[$r['student_id']])));
            $bottom_q = array_values(array_filter($q_b, fn($r) => isset($bottom_group[$r['student_id']])));

            $f_top = count($top_q) > 0
                ? count(array_filter($top_q, fn($r) => $r['grade'] === 1.0)) / count($top_q)
                : 0.0;
            $f_bottom = count($bottom_q) > 0
                ? count(array_filter($bottom_q, fn($r) => $r['grade'] === 1.0)) / count($bottom_q)
                : 0.0;
            $d_index = $f_top - $f_bottom;

            $rows[] = [
                'question' => $q,
                'difficulty_index' => py_compat::round($success_rate, 2),
                'discrimination_index' => py_compat::round($d_index, 4),
                'average_marks' => py_compat::round($avg_marks, 2),
                'median_marks' => py_compat::round($median_marks, 2),
                'standard_deviation' => py_compat::round($std_marks, 2),
                'variance' => py_compat::round($var_marks, 2),
                'success_rate' => py_compat::round($success_rate, 2),
            ];
        }

        return $rows;
    }
}
