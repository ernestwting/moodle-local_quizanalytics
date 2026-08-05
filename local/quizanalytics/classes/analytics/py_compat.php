<?php
/**
 * PHP equivalents for a handful of Python built-in behaviors this port relies
 * on repeatedly, where PHP's native equivalent doesn't actually match.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class py_compat {

    /**
     * Replicates Python 3's round(value, ndigits): correctly-rounded
     * (round-half-to-even) against the value's TRUE underlying double
     * representation, not its nearest "nice" decimal literal.
     *
     * PHP's own round() — in every rounding mode, including
     * PHP_ROUND_HALF_EVEN — applies a deliberate pre-rounding correction
     * intended to match "human" decimal expectations, which gives a
     * DIFFERENT answer than Python for values whose true binary
     * representation sits just below a decimal boundary. Confirmed
     * empirically, not assumed: round(2.675, 2) is 2.67 in Python (matching
     * 2.675's true stored value,
     * 2.67499999999999982236431605997495353221893310546875) but PHP's
     * round(2.675, 2) — with ANY rounding mode — gives 2.68, treating the
     * input as if it were exactly the decimal 2.675.
     *
     * sprintf('%.30f', ...) does not apply that correction — it reveals the
     * double's actual decimal expansion (verified to match Python's own
     * Decimal(value) expansion digit-for-digit) — so rounding is done here
     * as plain string/digit arithmetic against that true expansion instead
     * of trusting any of PHP's built-in rounding.
     *
     * Only ndigits >= 0 is needed (and tested) by this codebase — every
     * round() call here is round(x, 2), round(x, 4), or round(x) (ndigits
     * defaulting to 0).
     */
    public static function round(float $value, int $ndigits = 0): float {
        if (!is_finite($value) || $value == 0.0) {
            return $value;
        }

        $negative = $value < 0;
        $abs = abs($value);

        $str = sprintf('%.30f', $abs);
        [$int_part, $frac_part] = explode('.', $str);

        if ($ndigits <= 0) {
            $kept_digits = $int_part;
            $round_digit = $frac_part[0] ?? '0';
            $rest_is_zero = ltrim(substr($frac_part, 1), '0') === '';
        } else {
            $kept_digits = $int_part . substr($frac_part, 0, $ndigits);
            $remainder = substr($frac_part, $ndigits);
            $round_digit = $remainder[0] ?? '0';
            $rest_is_zero = ltrim(substr($remainder, 1), '0') === '';
        }
        $last_kept_digit = (int) substr($kept_digits, -1);

        $round_up = false;
        if ($round_digit > '5') {
            $round_up = true;
        } else if ($round_digit === '5') {
            // Exact tie (nothing nonzero beyond the 5) -> round to even;
            // otherwise the true value is past the midpoint -> round up.
            $round_up = !$rest_is_zero || ($last_kept_digit % 2) !== 0;
        }

        if ($round_up) {
            $kept_digits = self::increment_digit_string($kept_digits);
        }

        if ($ndigits <= 0) {
            $result = (float) $kept_digits;
        } else {
            // $kept_digits may have grown by one digit if incrementing
            // carried all the way through (e.g. "1999" -> "2000") — the
            // decimal point still belongs $ndigits from the right regardless.
            $int_len = strlen($kept_digits) - $ndigits;
            $result = (float) (substr($kept_digits, 0, $int_len) . '.' . substr($kept_digits, $int_len));
        }

        return $negative ? -$result : $result;
    }

    /**
     * Increments a string of decimal digits by 1, propagating carries
     * (e.g. "1299" -> "1300", "999" -> "1000").
     */
    protected static function increment_digit_string(string $digits): string {
        $chars = str_split($digits);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            if ($chars[$i] === '9') {
                $chars[$i] = '0';
            } else {
                $chars[$i] = (string) ((int) $chars[$i] + 1);
                return implode('', $chars);
            }
        }
        return '1' . implode('', $chars);
    }
}
