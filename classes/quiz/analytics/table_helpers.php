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
 * Small array-of-rows helpers used across the ported analytics modules —
 * PHP's answer to the handful of pandas DataFrame operations (unique+sort,
 * groupby) those modules lean on repeatedly. Deliberately minimal: only what's
 * actually used, not a general-purpose DataFrame reimplementation.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Minimal array-of-rows helpers (unique+sort, groupby) standing in for the pandas operations the ported modules need.
 */
class table_helpers {
    /**
     * The leading integer in a question label ("Q10" -> 10), matching every
     * Python module's identically-behaved local get_q_num() helper — 0 if
     * no digits are found.
     */
    public static function question_number(string $q): int {
        if (preg_match('/\d+/', $q, $m)) {
            return (int) $m[0];
        }
        return 0;
    }

    /**
     * Unique values of $field across $rows, sorted by natural question
     * number (matching sorted(df[field].unique(), key=get_q_num)).
     *
     * @param array[] $rows
     * @return string[]
     */
    public static function unique_sorted_by_question(array $rows, string $field): array {
        $seen = [];
        foreach ($rows as $row) {
            $seen[$row[$field]] = true;
        }
        $values = array_keys($seen);
        usort($values, fn($a, $b) => self::question_number($a) <=> self::question_number($b));
        return $values;
    }

    /**
     * Converts an array of associative rows into the {columns, rows}
     * contract the JS renderer expects (this port's equivalent of app.py's
     * _df_to_table(), which does the same conversion from a pandas
     * DataFrame). Column order is taken from the first row's keys.
     *
     * @param array[] $rows associative arrays, all sharing the same keys
     * @return array{columns: string[], rows: array[]}
     */
    public static function to_table(array $rows): array {
        if (empty($rows)) {
            return ['columns' => [], 'rows' => []];
        }
        $columns = array_keys($rows[0]);
        $outrows = array_map(fn($row) => array_values($row), $rows);
        return ['columns' => $columns, 'rows' => $outrows];
    }

    /**
     * Groups $rows by $field, preserving first-seen group order (matching
     * pandas groupby()'s default sort=True — sorted keys — for question
     * labels specifically call unique_sorted_by_question() over this
     * instead; this is for groupings that don't need question-number order,
     * e.g. by attempt_idx).
     *
     * @param array[] $rows
     * @return array<string, array[]>
     */
    public static function group_by(array $rows, string $field): array {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) $row[$field];
            $groups[$key][] = $row;
        }
        return $groups;
    }
}
