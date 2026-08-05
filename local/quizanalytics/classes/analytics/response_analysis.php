<?php
/**
 * PHP port of analytics-service/analytics/response_analysis.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class response_analysis {

    /**
     * Response outcome percentages (Pool B for correct/incorrect, Pool A for
     * valid/invalid).
     *
     * @param array[] $response_rows
     * @return array[]
     */
    public static function compute_response_outcomes(array $response_rows): array {
        if (empty($response_rows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($response_rows);
        $pool_a = $pools['pool_a'];
        $pool_b = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($pool_a, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $q_a = array_values(array_filter($pool_a, fn($r) => $r['question'] === $q));
            $q_b = array_values(array_filter($pool_b, fn($r) => $r['question'] === $q));

            $len_b = count($q_b);
            $facility_b = $len_b > 0 ? count(array_filter($q_b, fn($r) => $r['grade'] === 1.0)) / $len_b : 0.0;
            $correct_pct = $facility_b * 100.0;
            $incorrect_pct = (1.0 - $facility_b) * 100.0;

            $len_a = count($q_a);
            $invalid_a = count(array_filter($q_a, fn($r) => $r['response_status'] === 'invalid'));
            $blank_a = count(array_filter($q_a, fn($r) => $r['response_status'] === 'blank'));
            $invalid_pct = $len_a > 0 ? ($invalid_a / $len_a * 100.0) : 0.0;
            $blank_pct = $len_a > 0 ? ($blank_a / $len_a * 100.0) : 0.0;
            $valid_pct = max(0.0, 100.0 - $invalid_pct - $blank_pct);

            $rows[] = [
                'question' => $q,
                'correct_percent' => py_compat::round($correct_pct, 2),
                'incorrect_percent' => py_compat::round($incorrect_pct, 2),
                'valid_percent' => py_compat::round($valid_pct, 2),
                'invalid_percent' => py_compat::round($invalid_pct, 2),
            ];
        }

        return $rows;
    }

    /**
     * Tally the most frequent wrong literal inputs, strictly from Pool B
     * (Best Attempt per Student).
     *
     * @param array[] $response_rows
     * @return array[]
     */
    public static function compute_repeated_wrong_answers(array $response_rows): array {
        if (empty($response_rows)) {
            return [];
        }

        $pools = parser::get_attempt_pools($response_rows);
        $pool_b = $pools['pool_b'];

        $questions = table_helpers::unique_sorted_by_question($pool_b, 'question');
        $rows = [];

        foreach ($questions as $q) {
            $wrong_b = array_values(array_filter(
                $pool_b,
                fn($r) => $r['question'] === $q && $r['grade'] !== null && $r['grade'] < 1.0
            ));

            // Counter-with-insertion-order-tiebreak, matching Python's
            // collections.Counter.most_common(): ties keep first-inserted order.
            $expr_counts = [];
            foreach ($wrong_b as $r) {
                foreach (($r['ans_list'] ?? []) as $ans) {
                    $tag = $ans['tag'] ?? null;
                    $expr = trim((string) ($ans['expression'] ?? ''));
                    if ($expr !== '' && in_array($tag, ['invalid', 'valid', 'score'], true)) {
                        if (!isset($expr_counts[$expr])) {
                            $expr_counts[$expr] = 0;
                        }
                        $expr_counts[$expr]++;
                    }
                }
            }

            if (!empty($expr_counts)) {
                $top_wrong = self::most_common($expr_counts, 5);
                $formatted = array_map(
                    fn($pair) => '$' . latex_utils::maxima_expr_to_latex($pair[0]) . '$ (' . $pair[1] . ')',
                    $top_wrong
                );
                $most_common_str = implode(', ', $formatted);
                $top_freq = $top_wrong[0][1];
            } else {
                $top_wrong = [];
                $most_common_str = 'None';
                $top_freq = 0;
            }

            $rows[] = [
                'question' => $q,
                'most_common_incorrect_answer' => $most_common_str,
                'frequency' => $top_freq,
                'top_wrong_expressions' => $top_wrong,
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
