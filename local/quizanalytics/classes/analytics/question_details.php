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
 * PHP port of analytics-service/analytics/question_details.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class question_details {
    const NOT_AVAILABLE = 'Not available in this export';

    /**
     * Question text / right answer for a question, taken from Pool B (Best
     * Attempt per Student).
     *
     * @param array[] $pool_b_rows
     * @return array{question_text: string, right_answer_text: string}
     */
    public static function build_question_detail(array $pool_b_rows, string $question): array {
        $rows = array_values(array_filter($pool_b_rows, fn($r) => $r['question'] === $question));

        $first_non_empty = function (string $field) use ($rows): string {
            foreach ($rows as $r) {
                $v = $r[$field] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    return $v;
                }
            }
            return self::NOT_AVAILABLE;
        };

        return [
            'question_text' => $first_non_empty('question_text'),
            'right_answer_text' => $first_non_empty('right_answer_text'),
        ];
    }

    /**
     * Best-attempt rows for a question where the student didn't get full
     * credit — lets a teacher scan submitted responses against the right
     * answer to spot common misconceptions.
     *
     * @param array[] $pool_b_rows
     * @return array{columns: string[], rows: array[]}
     */
    public static function build_error_drilldown(array $pool_b_rows, string $question): array {
        $columns = ['Student Name', 'Email', 'Submitted Response', 'Right Answer', 'Score', 'Status'];

        $wrong = array_values(array_filter(
            $pool_b_rows,
            fn($r) => $r['question'] === $question && $r['grade'] !== null && $r['grade'] < 1.0
        ));

        $rows = array_map(fn($r) => [
            $r['student_name'] ?? '',
            $r['student_id'] ?? '',
            $r['response_text'] ?? '',
            $r['right_answer_text'] ?? '',
            $r['grade'] ?? null,
            $r['response_status'] ?? '',
        ], $wrong);

        return ['columns' => $columns, 'rows' => $rows];
    }
}
