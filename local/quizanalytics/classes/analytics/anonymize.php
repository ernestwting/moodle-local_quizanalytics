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
 * PHP port of analytics-service/analytics/anonymize.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Replaces real student names/ids with stable per-student pseudonyms.
 */
class anonymize {
    /**
     * Replace real student names/emails with stable per-student pseudonyms.
     *
     * Applied once, immediately after parsing (see parser::build_response_rows()),
     * so every downstream table, chart, and PDF export - which all derive from these
     * same rows - is anonymized for free, with no need to touch each individual
     * display point separately. student_id keeps acting as a valid grouping/join key
     * since the mapping is a consistent 1:1 relabeling, not a shuffle.
     *
     * @param array[] $rows response rows as returned by parser::build_response_rows()
     * @return array[]
     */
    public static function anonymize_response_rows(array $rows): array {
        if (empty($rows)) {
            return $rows;
        }

        $uniqueids = array_unique(array_map(fn($r) => (string) $r['student_id'], $rows));
        sort($uniqueids);

        $namemap = [];
        $idmap = [];
        foreach ($uniqueids as $i => $studentid) {
            $namemap[$studentid] = 'Student ' . ($i + 1);
            $idmap[$studentid] = 'student' . ($i + 1) . '@anonymized.edu';
        }

        return array_map(function ($row) use ($namemap, $idmap) {
            $studentid = (string) $row['student_id'];
            if (array_key_exists('student_name', $row)) {
                $row['student_name'] = $namemap[$studentid];
            }
            $row['student_id'] = $idmap[$studentid];
            return $row;
        }, $rows);
    }
}
