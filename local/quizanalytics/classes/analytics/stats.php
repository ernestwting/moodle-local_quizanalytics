<?php
/**
 * Small array-based statistics helpers replacing the pandas Series methods
 * this port's ported modules call (.mean(), .median(), .std(ddof=1),
 * .var(ddof=1)) — all of which skip null/NaN values by default in pandas
 * (skipna=True), which every function here replicates by simply filtering
 * nulls out first.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class stats {

    /**
     * @param array $values may contain null (skipped, matching pandas' skipna=True default)
     * @return float[]
     */
    protected static function non_null(array $values): array {
        return array_values(array_filter($values, fn($v) => $v !== null));
    }

    public static function mean(array $values): float {
        $clean = self::non_null($values);
        if (empty($clean)) {
            return 0.0;
        }
        return array_sum($clean) / count($clean);
    }

    public static function median(array $values): float {
        $clean = self::non_null($values);
        $n = count($clean);
        if ($n === 0) {
            return 0.0;
        }
        sort($clean, SORT_NUMERIC);
        $mid = intdiv($n, 2);
        if ($n % 2 === 1) {
            return (float) $clean[$mid];
        }
        return ($clean[$mid - 1] + $clean[$mid]) / 2.0;
    }

    /**
     * Sample variance (ddof=1, Bessel's correction) — matches pandas' .var()
     * default. Returns 0.0 for fewer than 2 values (pandas returns NaN, but
     * every caller in this codebase already special-cases "len <= 1" before
     * calling, matching the ported Python's own `if len(...) > 1 else 0.0`
     * guards).
     */
    public static function sample_variance(array $values): float {
        $clean = self::non_null($values);
        $n = count($clean);
        if ($n < 2) {
            return 0.0;
        }
        $mean = self::mean($clean);
        $sumsq = 0.0;
        foreach ($clean as $v) {
            $sumsq += ($v - $mean) ** 2;
        }
        return $sumsq / ($n - 1);
    }

    public static function sample_stdev(array $values): float {
        return sqrt(self::sample_variance($values));
    }
}
