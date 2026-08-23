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
 * Model 2 indicator (b): syntax-error rate.
 *
 * Architecture doc §3.4(b): proportion of failed attempts whose STACK
 * AnswerTest result is an input-validation/syntax failure rather than a
 * mathematical-equivalence failure. Rather than parsing STACK's own
 * AnswerTest validation output, this reuses a standard Moodle question-engine
 * distinction that already carries exactly that meaning for every question
 * type, STACK included: question_state_invalid (question/engine/states.php,
 * stored as the literal string 'invalid') is the state the engine assigns
 * when a response cannot be graded because it fails input validation —
 * confirmed against a real Moodle 4.5 core checkout — as opposed to a
 * 'gradedwrong'/'gradedpartial' state, which means the input parsed fine but
 * was mathematically incorrect.
 *
 * Normalize: 2 * proportion - 1.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\indicator;

use local_quizanalytics\stack\local\stack_attempt_reader;
use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Proportion of a question's failed attempts that failed on syntax/input
 * validation rather than mathematical equivalence.
 */
class syntax_error_rate extends \core_analytics\local\indicator\linear {
    /** The question-engine state assigned to input-validation/syntax failures. */
    const INVALID_STATE = 'invalid';

    /** Final states other than 'invalid' that still count as a failure (not fully correct). */
    const INCORRECT_STATES = ['gradedwrong', 'gradedpartial'];

    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:syntaxerrorrate', 'local_quizanalytics');
    }

    /**
     * Declares which sample-data types this indicator needs.
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return ['quiz_slots'];
    }

    /**
     * Normalizes the proportion of syntax-error failures to the indicator's [-1, 1] range.
     *
     * @param int $invalidcount attempts that failed on syntax/input validation
     * @param int $totalfailedcount all failed attempts (invalid + mathematically incorrect)
     * @return float|null null if there were no failed attempts to judge a proportion from
     */
    public static function proportion_to_indicator(int $invalidcount, int $totalfailedcount): ?float {
        if ($totalfailedcount <= 0) {
            return null;
        }
        $proportion = $invalidcount / $totalfailedcount;
        return max(-1.0, min(1.0, 2.0 * $proportion - 1.0));
    }

    /**
     * Feeds this indicator's score to the Analytics API for one sample.
     *
     * @param int $sampleid a quiz_slots.id
     * @param string $sampleorigin
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime, $endtime) {
        return self::compute_for_sample((int) $sampleid)->indicator ?? null;
    }

    /**
     * Dashboard-facing computation — see grade_trajectory::compute_for_sample()
     * for the shared contract. High indicator = most of this question's
     * failures were input/syntax errors rather than wrong maths (worth a
     * look at the input format, not the maths).
     *
     * @param int $sampleid a quiz_slots.id
     * @return \stdClass|null null if there isn't enough data yet
     */
    public static function compute_for_sample(int $sampleid): ?\stdClass {
        $slots = stack_course_helper::get_stack_slots([$sampleid]);
        if (empty($slots[$sampleid])) {
            return null;
        }
        $slot = $slots[$sampleid];

        $questionids = stack_course_helper::get_all_question_ids_for_entry((int) $slot->questionbankentryid);
        $finalstates = stack_attempt_reader::get_slot_final_states((int) $slot->quizid, $questionids);
        if (empty($finalstates)) {
            return null;
        }

        $invalidcount = 0;
        $totalfailedcount = 0;
        foreach ($finalstates as $final) {
            if ($final->state === self::INVALID_STATE) {
                $invalidcount++;
                $totalfailedcount++;
            } else if (in_array($final->state, self::INCORRECT_STATES, true)) {
                $totalfailedcount++;
            }
        }

        $indicator = self::proportion_to_indicator($invalidcount, $totalfailedcount);
        if ($indicator === null) {
            return null; // No failed attempts to judge a proportion from.
        }

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : ($indicator <= -0.33 ? 'good' : 'neutral'),
            'summary' => [
                'syntaxerrorcount' => $invalidcount,
                'totalfailed' => $totalfailedcount,
            ],
        ];
    }
}
