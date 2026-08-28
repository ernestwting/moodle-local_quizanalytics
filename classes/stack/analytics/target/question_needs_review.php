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
 * Model 2 target: "this question/PRT needs instructor review".
 *
 * Architecture doc §3.3: no direct ground truth exists for "needs review", so
 * this uses proxy-label option (2) — a question is labeled 1 (needs review)
 * if its empirical pass rate falls below a threshold, 0 otherwise. The doc is
 * explicit that this risks circularity against question_difficulty_irt (both
 * ultimately read the same pass rate) — that is a genuine validity threat,
 * not fixed by this implementation, and belongs in the paper's methodology
 * section as stated there, not hidden here. This target computes the pass
 * rate independently via stack_attempt_reader rather than retrieving
 * question_difficulty_irt's already-computed value, which avoids literally
 * reusing the same float but does not remove the underlying statistical
 * circularity of both signals ultimately deriving from the same pass rate.
 *
 * No core target to extend here (unlike Model 1's student_at_risk) — nothing
 * in core operates on a question-as-sample, so this extends
 * \core_analytics\local\target\binary directly.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\target;

use local_quizanalytics\stack\local\stack_attempt_reader;
use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Pass-rate-threshold proxy label for "needs review".
 */
class question_needs_review extends \core_analytics\local\target\binary {
    /**
     * Below this pass rate, a question is labeled "needs review". Default
     * used when the local_quizanalytics/questionneedsreviewthreshold admin
     * setting (Phase 6) is unset.
     */
    const DEFAULT_PASSRATE_THRESHOLD = 0.5;

    /**
     * Gets the pass-rate threshold below which a question is labeled "needs review".
     *
     * @return float the admin-configured threshold, or DEFAULT_PASSRATE_THRESHOLD if unset
     */
    public static function get_passrate_threshold(): float {
        $configured = get_config('local_quizanalytics', 'questionneedsreviewthreshold');
        return $configured !== false && $configured !== '' ? (float) $configured : self::DEFAULT_PASSRATE_THRESHOLD;
    }

    /**
     * Gets this target's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('target:questionneedsreview', 'local_quizanalytics');
    }

    /**
     * Gets the analyser class that supplies this target's samples.
     *
     * @return string
     */
    public function get_analyser_class() {
        return '\local_quizanalytics\stack\analytics\analyser\stack_question_analyser';
    }

    /**
     * Architecture doc §3.5: single_range or no_splitting, since PRT/question
     * quality doesn't have Model 1's within-course temporal structure —
     * cumulative behavior across all historical attempts, refreshed
     * periodically, matching the same restriction pattern core's own
     * no_teaching target uses for its own single_range-only static model.
     *
     * @param \core_analytics\local\time_splitting\base $timesplitting
     * @return bool
     */
    public function can_use_timesplitting(\core_analytics\local\time_splitting\base $timesplitting): bool {
        return (get_class($timesplitting) === \core\analytics\time_splitting\single_range::class);
    }

    /**
     * Checks whether a course has any STACK activity for this target to analyse.
     *
     * @param \core_analytics\analysable $course
     * @param bool $fortraining
     * @return true|string
     */
    public function is_valid_analysable(\core_analytics\analysable $course, $fortraining = true) {
        if (!stack_course_helper::course_has_stack_activity($course->get_id())) {
            return get_string('errornostackactivity', 'local_quizanalytics');
        }
        return true;
    }

    /**
     * Checks whether a sample (quiz_slots.id) resolves to a real STACK question slot.
     *
     * @param int $sampleid
     * @param \core_analytics\analysable $course
     * @param bool $fortraining
     * @return bool
     */
    public function is_valid_sample($sampleid, \core_analytics\analysable $course, $fortraining = true) {
        return !empty(stack_course_helper::get_stack_slots([(int) $sampleid]));
    }

    /**
     * Feeds this target's label to the Analytics API for one sample.
     *
     * @param int $sampleid a quiz_slots.id
     * @param \core_analytics\analysable $course
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null 0 -> pass rate at/above threshold, 1 -> needs review
     */
    protected function calculate_sample($sampleid, \core_analytics\analysable $course, $starttime = false, $endtime = false) {
        return self::compute_for_sample((int) $sampleid)->needsreview ?? null;
    }

    /**
     * Dashboard-facing computation: the same pass-rate-threshold read
     * calculate_sample() feeds to the Analytics API, plus the real-world
     * pass rate so a teacher isn't just shown a bare 0/1.
     *
     * @param int $sampleid a quiz_slots.id
     * @return \stdClass|null null if there isn't enough data yet (no basis for a label)
     */
    public static function compute_for_sample(int $sampleid): ?\stdClass {
        $slots = stack_course_helper::get_stack_slots([$sampleid]);
        if (empty($slots[$sampleid])) {
            return null;
        }
        $slot = $slots[$sampleid];

        $questionids = stack_course_helper::get_all_question_ids_for_entry((int) $slot->questionbankentryid);
        $fractions = stack_attempt_reader::get_slot_finished_fractions((int) $slot->quizid, $questionids);
        if (empty($fractions)) {
            return null; // No finished attempts yet — no basis for a label.
        }

        $passrate = array_sum($fractions) / count($fractions);
        $threshold = self::get_passrate_threshold();

        return (object) [
            'needsreview' => $passrate < $threshold ? 1 : 0,
            'passpercent' => round(100.0 * $passrate, 0),
            'thresholdpercent' => round(100.0 * $threshold, 0),
        ];
    }
}
