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
 * PHP equivalents for a handful of Python built-in behaviors this port relies
 * on repeatedly, where PHP's native equivalent doesn't actually match.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        [$intpart, $fracpart] = explode('.', $str);

        if ($ndigits <= 0) {
            $keptdigits = $intpart;
            $rounddigit = $fracpart[0] ?? '0';
            $restiszero = ltrim(substr($fracpart, 1), '0') === '';
        } else {
            $keptdigits = $intpart . substr($fracpart, 0, $ndigits);
            $remainder = substr($fracpart, $ndigits);
            $rounddigit = $remainder[0] ?? '0';
            $restiszero = ltrim(substr($remainder, 1), '0') === '';
        }
        $lastkeptdigit = (int) substr($keptdigits, -1);

        $roundup = false;
        if ($rounddigit > '5') {
            $roundup = true;
        } else if ($rounddigit === '5') {
            // Exact tie (nothing nonzero beyond the 5) -> round to even;
            // otherwise the true value is past the midpoint -> round up.
            $roundup = !$restiszero || ($lastkeptdigit % 2) !== 0;
        }

        if ($roundup) {
            $keptdigits = self::increment_digit_string($keptdigits);
        }

        if ($ndigits <= 0) {
            $result = (float) $keptdigits;
        } else {
            // $keptdigits may have grown by one digit if incrementing
            // carried all the way through (e.g. "1999" -> "2000") — the
            // decimal point still belongs $ndigits from the right regardless.
            $intlen = strlen($keptdigits) - $ndigits;
            $result = (float) (substr($keptdigits, 0, $intlen) . '.' . substr($keptdigits, $intlen));
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
