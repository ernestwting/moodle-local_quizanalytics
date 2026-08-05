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
 * PHP port of analytics-service/analytics/response_analysis.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class response_analysis {
    /**
     * Response outcome percentages (Pool B for correct/incorrect, Pool A for
     * valid/invalid).
     *
     * @param array[] $responserows
     * @return array[]
     */
    public static function compute_response_outcomes(array $responserows): array {
        if (empty($responserows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($responserows);
        $poola = $pools['pool_a'];
        $poolb = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($poola, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $qa = array_values(array_filter($poola, fn($r) => $r['question'] === $q));
            $qb = array_values(array_filter($poolb, fn($r) => $r['question'] === $q));

            $lenb = count($qb);
            $facilityb = $lenb > 0 ? count(array_filter($qb, fn($r) => $r['grade'] === 1.0)) / $lenb : 0.0;
            $correctpct = $facilityb * 100.0;
            $incorrectpct = (1.0 - $facilityb) * 100.0;

            $lena = count($qa);
            $invalida = count(array_filter($qa, fn($r) => $r['response_status'] === 'invalid'));
            $blanka = count(array_filter($qa, fn($r) => $r['response_status'] === 'blank'));
            $invalidpct = $lena > 0 ? ($invalida / $lena * 100.0) : 0.0;
            $blankpct = $lena > 0 ? ($blanka / $lena * 100.0) : 0.0;
            $validpct = max(0.0, 100.0 - $invalidpct - $blankpct);

            $rows[] = [
                'question' => $q,
                'correct_percent' => py_compat::round($correctpct, 2),
                'incorrect_percent' => py_compat::round($incorrectpct, 2),
                'valid_percent' => py_compat::round($validpct, 2),
                'invalid_percent' => py_compat::round($invalidpct, 2),
            ];
        }

        return $rows;
    }

    /**
     * Tally the most frequent wrong literal inputs, strictly from Pool B
     * (Best Attempt per Student).
     *
     * @param array[] $responserows
     * @return array[]
     */
    public static function compute_repeated_wrong_answers(array $responserows): array {
        if (empty($responserows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($responserows);
        $poolb = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($poolb, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $wrongb = array_values(array_filter(
                $poolb,
                fn($r) => $r['question'] === $q && $r['grade'] !== null && $r['grade'] < 1.0
            ));

            // Counter-with-insertion-order-tiebreak, matching Python's
            // collections.Counter.most_common(): ties keep first-inserted order.
            $exprcounts = [];
            foreach ($wrongb as $r) {
                foreach (($r['ans_list'] ?? []) as $ans) {
                    $tag = $ans['tag'] ?? null;
                    $expr = trim((string) ($ans['expression'] ?? ''));
                    if ($expr !== '' && in_array($tag, ['invalid', 'valid', 'score'], true)) {
                        if (!isset($exprcounts[$expr])) {
                            $exprcounts[$expr] = 0;
                        }
                        $exprcounts[$expr]++;
                    }
                }
            }

            if (!empty($exprcounts)) {
                $topwrong = self::most_common($exprcounts, 5);
                $formatted = array_map(
                    fn($pair) => '$' . latex_utils::maxima_expr_to_latex($pair[0]) . '$ (' . $pair[1] . ')',
                    $topwrong
                );
                $mostcommonstr = implode(', ', $formatted);
                $topfreq = $topwrong[0][1];
            } else {
                $topwrong = [];
                $mostcommonstr = 'None';
                $topfreq = 0;
            }

            $rows[] = [
                'question' => $q,
                'most_common_incorrect_answer' => $mostcommonstr,
                'frequency' => $topfreq,
                'top_wrong_expressions' => $topwrong,
            ];
        }

        return $rows;
    }

    /**
     * Top $n [expression, count] pairs by count descending, ties broken by
     * first-insertion order — matches collections.Counter.most_common(n).
     *
     * @param array<string, int> $counts
     * @return array<int, array{0: string, 1: int}>
     */
    protected static function most_common(array $counts, int $n): array {
        $pairs = [];
        $order = 0;
        foreach ($counts as $expr => $count) {
            $pairs[] = ['expr' => $expr, 'count' => $count, 'order' => $order++];
        }
        usort($pairs, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            return $a['order'] <=> $b['order'];
        });
        $top = array_slice($pairs, 0, $n);
        return array_map(fn($p) => [$p['expr'], $p['count']], $top);
    }
}
