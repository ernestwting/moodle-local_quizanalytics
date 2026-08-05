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
 * PHP port of analytics-service/analytics/question_metrics.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Per-question participation (Pool A) and performance (Pool B) metrics, and the overall question summary.
 */
class question_metrics {
    /**
     * Per-question metrics using Pool A for participation and Pool B for
     * performance.
     *
     * @param array[] $responserows
     * @return array[]
     */
    public static function compute_question_metrics(array $responserows): array {
        if (empty($responserows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($responserows);
        $poola = $pools['pool_a'];
        $poolb = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($responserows, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $qa = array_values(array_filter($poola, fn($r) => $r['question'] === $q));
            $qb = array_values(array_filter($poolb, fn($r) => $r['question'] === $q));

            // Pool A metrics (participation / usage).
            $attemptsa = count($qa);
            $studentsa = count(array_unique(array_map(fn($r) => $r['student_id'], $qa)));
            $invalidcounta = count(array_filter($qa, fn($r) => $r['response_status'] === 'invalid'));
            $blankcounta = count(array_filter($qa, fn($r) => $r['response_status'] === 'blank'));
            $invalidratea = $attemptsa > 0 ? $invalidcounta / $attemptsa : 0.0;
            $blankratea = $attemptsa > 0 ? $blankcounta / $attemptsa : 0.0;
            $percentvalida = max(0.0, (1.0 - $invalidratea - $blankratea) * 100.0);
            $reattemptsharea = $attemptsa > 0 ? max(0.0, (($attemptsa - $studentsa) / $attemptsa) * 100.0) : 0.0;

            // Pool B metrics (performance / mastery).
            $numstudentsb = count($qb);
            $correctcountb = count(array_filter($qb, fn($r) => $r['grade'] === 1.0));
            $facilityb = $numstudentsb > 0 ? $correctcountb / $numstudentsb : 0.0;
            $gradesb = array_map(fn($r) => $r['grade'], $qb);
            $partialcreditmeanb = $numstudentsb > 0 ? stats::mean($gradesb) : 0.0;
            $avgscoreb = $partialcreditmeanb * 10.0;
            $scaledscoreb = $partialcreditmeanb * 10.0;

            $percentcorrectb = $facilityb * 100.0;
            $percentincorrectb = (1.0 - $facilityb) * 100.0;

            // Catch-all share over wrong attempts in Pool B.
            $wrongb = array_values(array_filter($qb, fn($r) => $r['grade'] !== null && $r['grade'] < 1.0));
            $catchallcountb = 0;
            $totalwrongprtsb = 0;
            foreach ($wrongb as $r) {
                foreach (($r['prt_list'] ?? []) as $prt) {
                    $fraction = $prt['fraction'] ?? null;
                    $answernote = trim((string) ($prt['answer_note'] ?? ''));
                    if ($fraction !== null && $fraction < 1.0) {
                        $totalwrongprtsb++;
                        // PRT name prefix varies by export (e.g. "prt1-1-T" vs
                        // "Result-0-T"), so match on the trailing
                        // "-<index>-<T/F>" shape rather than a literal "prt" prefix.
                        if (preg_match('/^\w+-\d+-[TF]$/', $answernote)) {
                            $catchallcountb++;
                        }
                    }
                }
            }
            $catchallshareb = $totalwrongprtsb > 0 ? ($catchallcountb / $totalwrongprtsb * 100.0) : 0.0;

            $rows[] = [
                'question' => $q,
                'attempts' => $attemptsa,
                'students' => $studentsa,
                'invalid_rate' => py_compat::round($invalidratea, 4),
                'blank_rate' => py_compat::round($blankratea, 4),
                'reattempt_share' => py_compat::round($reattemptsharea, 2),
                'facility' => py_compat::round($facilityb, 4),
                'partial_credit_mean' => py_compat::round($partialcreditmeanb, 4),
                'avg_score' => py_compat::round($avgscoreb, 2),
                'percent_correct' => py_compat::round($percentcorrectb, 2),
                'percent_incorrect' => py_compat::round($percentincorrectb, 2),
                'percent_valid' => py_compat::round($percentvalida, 2),
                'percent_invalid' => py_compat::round($invalidratea * 100.0, 2),
                'syntax_error_count' => $invalidcounta,
                'syntax_error_percent' => py_compat::round($invalidratea * 100.0, 2),
                'scaled_score' => py_compat::round($scaledscoreb, 2),
                'catch_all_share' => py_compat::round($catchallshareb, 2),
            ];
        }

        return $rows;
    }

    /**
     * High-level question analytics summary.
     *
     * @param array[] $responserows
     * @return array
     */
    public static function compute_question_summary(array $responserows): array {
        if (empty($responserows)) {
            return [
                'total_questions' => 0,
                'student_count' => 0,
                'average_score' => 0.0,
                'average_valid_submission_rate' => 0.0,
                'average_correct_rate' => 0.0,
                'syntax_error_count' => 0,
            ];
        }

        $pools = parser::get_attempt_pools($responserows);
        $poola = $pools['pool_a'];
        $poolb = $pools['pool_b'];
        $qm = self::compute_question_metrics($responserows);

        $totalquestions = count(array_unique(array_map(fn($r) => $r['question'], $qm)));
        $studentcount = count(array_unique(array_map(fn($r) => $r['student_id'], $poolb)));

        $averagescore = !empty($qm) ? stats::mean(array_map(fn($r) => $r['avg_score'], $qm)) : 0.0;
        $averagevalidsubmissionrate = !empty($qm) ? stats::mean(array_map(fn($r) => $r['percent_valid'], $qm)) : 0.0;
        $averagecorrectrate = !empty($qm) ? stats::mean(array_map(fn($r) => $r['percent_correct'], $qm)) : 0.0;
        $syntaxerrorcount = count(array_filter($poola, fn($r) => $r['response_status'] === 'invalid'));

        return [
            'total_questions' => $totalquestions,
            'student_count' => $studentcount,
            'average_score' => py_compat::round($averagescore, 2),
            'average_valid_submission_rate' => py_compat::round($averagevalidsubmissionrate, 2),
            'average_correct_rate' => py_compat::round($averagecorrectrate, 2),
            'syntax_error_count' => $syntaxerrorcount,
        ];
    }

    /**
     * Rank questions by average score (hardest first).
     *
     * @param array[] $questionmetricsrows
     * @return array[]
     */
    public static function compute_ranked_difficulty(array $questionmetricsrows): array {
        if (empty($questionmetricsrows)) {
            return [];
        }
        $rows = $questionmetricsrows;
        usort($rows, fn($a, $b) => $a['avg_score'] <=> $b['avg_score']);
        return $rows;
    }
}
