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
 * Builds the dashboard's Model 2 ("Question & PRT Quality") table: one row
 * per STACK quiz slot in the course (optionally narrowed to one quiz, via
 * the dashboard's quiz selector), combining question_needs_review's direct
 * pass-rate-threshold read with the four Model 2 indicators'
 * compute_for_sample() readings — see model1_report.php's docblock for why
 * these are direct reads rather than trained-model predictions.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\report;

use local_quizanalytics\stack\local\stack_course_helper;
use local_quizanalytics\stack\analytics\target\question_needs_review;
use local_quizanalytics\stack\analytics\indicator\question_difficulty_irt;
use local_quizanalytics\stack\analytics\indicator\syntax_error_rate;
use local_quizanalytics\stack\analytics\indicator\unreached_node_ratio;
use local_quizanalytics\stack\analytics\indicator\feedback_ineffectiveness;

defined('MOODLE_INTERNAL') || die();

/**
 * Assembles one row per STACK quiz slot for the Model 2 dashboard table.
 */
class model2_report {
    /** Soft cap on rows built, so a course with many STACK questions can't make the page unusably slow. */
    const MAX_ROWS = 100;

    /** @var array<string, string> indicator key => lang string suffix, in table-column order. */
    const INDICATORS = [
        'questiondifficultyirt' => 'questiondifficultyirt',
        'syntaxerrorrate' => 'syntaxerrorrate',
        'unreachednoderatio' => 'unreachednoderatio',
        'feedbackineffectiveness' => 'feedbackineffectiveness',
    ];

    /**
     * Builds one row per STACK quiz slot for the Model 2 dashboard table.
     *
     * @param int $courseid
     * @param int|null $quizid restrict to one quiz, or null for every quiz in the course
     * @return \stdClass {rows: \stdClass[], total: int, truncated: bool}
     */
    public static function build(int $courseid, ?int $quizid = null): \stdClass {
        global $DB;

        $slots = stack_course_helper::get_course_stack_slots($courseid);
        if ($quizid !== null) {
            $slots = array_filter($slots, fn($slot) => (int) $slot->quizid === $quizid);
        }

        $total = count($slots);
        $truncated = $total > self::MAX_ROWS;
        $slots = array_slice($slots, 0, self::MAX_ROWS, true);

        if (empty($slots)) {
            return (object) ['rows' => [], 'total' => $total, 'truncated' => $truncated];
        }

        $quiznames = $DB->get_records_menu('quiz', ['course' => $courseid], '', 'id, name');
        $questionids = array_unique(array_map(fn($slot) => (int) $slot->questionid, $slots));
        $questionnames = array_map(
            fn($question) => $question->name,
            $DB->get_records_list('question', 'id', $questionids, '', 'id, name')
        );

        $rows = [];
        foreach ($slots as $slot) {
            $rows[] = (object) [
                'slotid' => (int) $slot->id,
                'questionname' => $questionnames[$slot->questionid] ?? get_string('unknownquestion', 'local_quizanalytics'),
                'quizname' => $quiznames[$slot->quizid] ?? get_string('unknownquiz', 'local_quizanalytics'),
                'attemptcount' => stack_course_helper::get_attempt_count((int) $slot->quizid, (int) $slot->questionbankentryid),
                'needsreview' => question_needs_review::compute_for_sample((int) $slot->id),
                'indicators' => [
                    'questiondifficultyirt' => question_difficulty_irt::compute_for_sample((int) $slot->id),
                    'syntaxerrorrate' => syntax_error_rate::compute_for_sample((int) $slot->id),
                    'unreachednoderatio' => unreached_node_ratio::compute_for_sample((int) $slot->id),
                    'feedbackineffectiveness' => feedback_ineffectiveness::compute_for_sample((int) $slot->id),
                ],
            ];
        }

        return (object) ['rows' => $rows, 'total' => $total, 'truncated' => $truncated];
    }
}
