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
 * Model 2's analyser: STACK questions, sampled per quiz slot, within courses.
 *
 * The architecture doc's §3.2 calls for a genuinely custom "question unit"
 * analysable, reasoning that "Moodle has no built-in 'question' or 'PRT node'
 * analysable." That is true, but reading the real Analytics API source
 * (analytics/classes/local/analyser/by_course.php and sitewide.php in a
 * Moodle 4.5 checkout) during this phase's planning showed that core ships
 * exactly two analyser base classes, and both hardcode their analysable:
 * by_course::get_analysables_iterator() always yields \core_analytics\course
 * instances, sitewide always yields the single \core_analytics\site. A truly
 * custom analysable is possible in principle (by extending
 * \core_analytics\local\analyser\base directly, bypassing both), but nothing
 * in core does this — there is no precedent to verify that contract against,
 * only the two by-course/sitewide implementations, which is a materially
 * larger, higher-risk undertaking for a benefit (individually-refreshable
 * per-question analysables) the architecture doc doesn't actually depend on.
 *
 * This class instead does exactly what \core\analytics\analyser\student_enrolments
 * does for Model 1: extends by_course, reuses \core_analytics\course as the
 * analysable unchanged, and makes the STACK question the *sample* within
 * that course rather than the analysable itself. This still delivers the
 * architecture doc's actual stated rationale for by_course — "course-scoped
 * processing avoids the memory blowup of a site-wide question analyser" —
 * since by_course's inherited get_analysables_iterator() already walks
 * courses via a memory-safe recordset.
 *
 * Sample grain: one quiz_slots row, i.e. "this STACK question as it appears
 * in this specific quiz" — the architecture doc's own "likely question-in-
 * course, not question-globally" grain (§3.2), made concrete as the one
 * real, stable, unique-per-course-offering database id that naturally
 * carries that meaning (a question added to a category can appear in
 * multiple quizzes/courses; a quiz_slots row cannot).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\analyser;

use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * STACK question (quiz-slot-grained) analyser, course-scoped like student_enrolments.
 */
class stack_question_analyser extends \core_analytics\local\analyser\by_course {
    /**
     * @var array Cache for quiz_slots id -> course id, populated as samples are built.
     */
    private $samplecourses = [];

    /**
     * Declares the sample-data table this analyser's sample ids are keyed against.
     *
     * @return string
     */
    public function get_samples_origin() {
        return 'quiz_slots';
    }

    /**
     * Declares which sample-data types this analyser provides to targets/indicators.
     *
     * @return string[]
     */
    protected function provided_sample_data() {
        return ['quiz_slots', 'context', 'course'];
    }

    /**
     * Gets the context a user needs access to in order to see this sample.
     *
     * @param int $sampleid a quiz_slots.id
     * @return \context
     */
    public function sample_access_context($sampleid) {
        return \context_course::instance($this->get_sample_courseid($sampleid));
    }

    /**
     * Gets the analysable (course) this sample belongs to.
     *
     * @param int $sampleid a quiz_slots.id
     * @return \core_analytics\analysable
     */
    public function get_sample_analysable($sampleid) {
        return \core_analytics\course::instance($this->get_sample_courseid($sampleid));
    }

    /**
     * Every STACK-backed quiz slot in this course.
     *
     * @param \core_analytics\analysable $course
     * @return array [int[] $sampleids, array $samplesdata]
     */
    public function get_all_samples(\core_analytics\analysable $course) {
        $slots = stack_course_helper::get_course_stack_slots($course->get_id());
        return $this->build_samples_data($slots, $course);
    }

    /**
     * Resolves an arbitrary set of sample ids (quiz_slots.id values, which
     * may span multiple courses) back to their full sample data.
     *
     * @param int[] $sampleids
     * @return array [int[] $sampleids, array $samplesdata]
     */
    public function get_samples($sampleids) {
        $slots = stack_course_helper::get_stack_slots($sampleids);

        $samplesdata = [];
        foreach ($slots as $slotid => $slot) {
            $samplesdata[$slotid] = [
                'quiz_slots' => $slot,
                'context'    => \context_course::instance($slot->courseid),
                'course'     => get_course($slot->courseid),
            ];
        }

        $slotids = array_keys($samplesdata);
        return [array_combine($slotids, $slotids), $samplesdata];
    }

    /**
     * Builds the human-readable description and icon shown for this sample in Analytics API UI.
     *
     * @param int $sampleid
     * @param int $contextid
     * @param array $sampledata
     * @return array [string $description, \renderable]
     */
    public function sample_description($sampleid, $contextid, $sampledata) {
        global $DB;

        $slot = $sampledata['quiz_slots'];
        $questionname = $DB->get_field('question', 'name', ['id' => $slot->questionid]);
        $quizname = $DB->get_field('quiz', 'name', ['id' => $slot->quizid]);

        $description = format_string($questionname) . ' (' . format_string($quizname) . ')';
        $icon = new \pix_icon('i/report', get_string('pluginname', 'local_quizanalytics'));

        return [$description, $icon];
    }

    /**
     * Common sample-building logic for get_all_samples()/get_all_samples-like
     * queries scoped to a single already-known course.
     *
     * @param \stdClass[] $slots keyed by slot id
     * @param \core_analytics\analysable $course
     * @return array [int[] $sampleids, array $samplesdata]
     */
    private function build_samples_data(array $slots, \core_analytics\analysable $course): array {
        $samplesdata = [];
        foreach ($slots as $slotid => $slot) {
            $samplesdata[$slotid] = [
                'quiz_slots' => $slot,
                'context'    => $course->get_context(),
                'course'     => $course->get_course_data(),
            ];
            // Cache the course id for sample_access_context()/get_sample_analysable(),
            // which the Analytics API calls with only a sampleid, matching the
            // caching pattern student_enrolments::get_sample_courseid() uses.
            $this->samplecourses[$slotid] = $course->get_id();
        }

        $slotids = array_keys($samplesdata);
        return [array_combine($slotids, $slotids), $samplesdata];
    }

    /**
     * Resolves a sample id to its course id, caching the lookup.
     *
     * @param int $sampleid a quiz_slots.id
     * @return int
     */
    private function get_sample_courseid(int $sampleid): int {
        if (!isset($this->samplecourses[$sampleid])) {
            $slots = stack_course_helper::get_stack_slots([$sampleid]);
            if (empty($slots[$sampleid])) {
                throw new \coding_exception('Unknown quiz_slots sample id', $sampleid);
            }
            $this->samplecourses[$sampleid] = $slots[$sampleid]->courseid;
        }
        return $this->samplecourses[$sampleid];
    }
}
