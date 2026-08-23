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
 * Model 1 target: student at risk of not achieving course success.
 *
 * Architecture doc §2.1: binary target, 1 = student will not achieve course
 * success (fails a defined grade threshold), 0 = success; analyser samples
 * course enrolments; is_valid_analysable() must additionally restrict to
 * courses with STACK activity.
 *
 * Rather than re-deriving "did this student meet the course's grade-to-pass
 * threshold?" from scratch, this extends
 * \core_course\analytics\target\course_gradetopass — read directly from a
 * real Moodle 4.5 core checkout while planning this phase — which already
 * implements exactly that as its calculate_sample(), plus all the enrolment-
 * window and course-validity checks a course-enrolment-sampled target needs
 * (course_enrolments::is_valid_analysable()/is_valid_sample(), the
 * before_now-only can_use_timesplitting() restriction, and
 * get_analyser_class() returning \core\analytics\analyser\student_enrolments,
 * confirmed as the correct "sample = enrolment" analyser). The only behavior
 * this class adds is the architecture doc's "STACK courses only" restriction.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\target;

use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-grade-to-pass-based risk target, restricted to STACK courses.
 */
class student_at_risk extends \core_course\analytics\target\course_gradetopass {
    /**
     * Gets this target's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('target:studentatrisk', 'local_quizanalytics');
    }

    /**
     * Adds the "course must have STACK activity" restriction from the
     * architecture doc's §2.1 on top of course_gradetopass's own checks
     * (grade-to-pass set, course started/finished appropriately for
     * training vs. prediction, sane start/end dates).
     *
     * @param \core_analytics\analysable $course
     * @param bool $fortraining
     * @return true|string
     */
    public function is_valid_analysable(\core_analytics\analysable $course, $fortraining = true) {
        $isvalid = parent::is_valid_analysable($course, $fortraining);
        if (is_string($isvalid)) {
            return $isvalid;
        }

        if (!stack_course_helper::course_has_stack_activity($course->get_id())) {
            return get_string('errornostackactivity', 'local_quizanalytics');
        }

        return true;
    }
}
