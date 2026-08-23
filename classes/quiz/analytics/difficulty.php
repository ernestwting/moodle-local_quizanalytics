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

namespace local_quizanalytics\quiz\analytics;

/**
 * Difficulty, marks statistics, and top/bottom-group discrimination index per question.
 */
class difficulty {
    /**
     * Compute difficulty, marks stats, and discrimination index D using
     * Pool B (Best Attempt per Student).
     *
     * @param array[] $responserows
     * @return array[] one row per question
     */
    public static function compute_difficulty_metrics(array $responserows): array {
        if (empty($responserows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($responserows);
        $poolb = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($poolb, 'question');

        // Overall quiz performance per student in Pool B, for the top/bottom
        // 27% cohort ranking below — one overall_grade per student (all their
        // Pool B rows share the same attempt-level overall_grade).
        $studentscores = [];
        foreach ($poolb as $row) {
            if (!array_key_exists($row['student_id'], $studentscores)) {
                $studentscores[$row['student_id']] = $row['overall_grade'];
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
        arsort($studentscores, SORT_NUMERIC);
        $sortedstudents = array_keys($studentscores);
        $nstudents = count($sortedstudents);

        $k = $nstudents > 0 ? max(1, (int) py_compat::round(0.27 * $nstudents)) : 0;
        $topgroup = $k > 0 ? array_flip(array_slice($sortedstudents, 0, $k)) : [];
        $bottomgroup = $k > 0 ? array_flip(array_slice($sortedstudents, -$k)) : [];

        $rows = [];
        foreach ($questions as $q) {
            $qb = array_values(array_filter($poolb, fn($r) => $r['question'] === $q));
            if (empty($qb)) {
                continue;
            }

            // Blank (never attempted) and ungraded (STACK re-validated,
            // no PRT result left to read) rows carry no real outcome for
            // this question — see parser::is_graded_response()'s own
            // comment. Every stat below is computed over graded rows only,
            // so a question several students never reached doesn't drag
            // down its own average marks or facility with zero-credit
            // rows for attempts that never happened.
            $gradedqb = array_values(array_filter($qb, fn($r) => parser::is_graded_response($r)));

            $scores10 = array_map(fn($r) => $r['grade'] * 10.0, $gradedqb);
            $avgmarks = stats::mean($scores10);
            $medianmarks = stats::median($scores10);
            $nonnullcount = count($scores10);
            $stdmarks = $nonnullcount > 1 ? stats::sample_stdev($scores10) : 0.0;
            $varmarks = $nonnullcount > 1 ? stats::sample_variance($scores10) : 0.0;

            $lenqb = count($gradedqb);
            $correctcount = count(array_filter($gradedqb, fn($r) => $r['grade'] === 1.0));
            $facility = $lenqb > 0 ? $correctcount / $lenqb : 0.0;
            $successrate = $facility * 100.0;

            $topq = array_values(array_filter($gradedqb, fn($r) => isset($topgroup[$r['student_id']])));
            $bottomq = array_values(array_filter($gradedqb, fn($r) => isset($bottomgroup[$r['student_id']])));

            $ftop = count($topq) > 0
                ? count(array_filter($topq, fn($r) => $r['grade'] === 1.0)) / count($topq)
                : 0.0;
            $fbottom = count($bottomq) > 0
                ? count(array_filter($bottomq, fn($r) => $r['grade'] === 1.0)) / count($bottomq)
                : 0.0;
            $dindex = $ftop - $fbottom;

            $rows[] = [
                'question' => $q,
                'difficulty_index' => py_compat::round($successrate, 2),
                'discrimination_index' => py_compat::round($dindex, 4),
                'average_marks' => py_compat::round($avgmarks, 2),
                'median_marks' => py_compat::round($medianmarks, 2),
                'standard_deviation' => py_compat::round($stdmarks, 2),
                'variance' => py_compat::round($varmarks, 2),
                'success_rate' => py_compat::round($successrate, 2),
            ];
        }

        return $rows;
    }
}
