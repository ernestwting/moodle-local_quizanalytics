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
 * Builds the dashboard's Model 1 ("Student Risk & Behavior") table: one row
 * per enrolled student, combining a direct grade-vs-pass read (the same
 * comparison \core_course\analytics\target\course_gradetopass::calculate_sample()
 * makes — confirmed against a real Moodle 4.5 core checkout — student_at_risk
 * inherits that method unchanged, so this dashboard re-derives it directly
 * rather than reflecting into a core-owned protected method) with the five
 * Model 1 indicators' compute_for_sample() readings.
 *
 * The student list itself mirrors \core_analytics\course::get_students():
 * every user holding a role with the 'student' archetype in the course
 * context (or a parent context), one row per student even if they hold more
 * than one enrolment.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\report;

use local_quizanalytics\stack\analytics\indicator\grade_trajectory;
use local_quizanalytics\stack\analytics\indicator\response_latency_anomaly;
use local_quizanalytics\stack\analytics\indicator\disengagement_entropy;
use local_quizanalytics\stack\analytics\indicator\help_seeking_gap;
use local_quizanalytics\stack\analytics\indicator\feedback_revision_distance;

defined('MOODLE_INTERNAL') || die();

// Grade_item/grade_grade (lib/grade/*.php) aren't autoloaded — confirmed
// against a real Moodle 4.5 core checkout, lib/setup.php doesn't pull them
// in by default. index.php happens to load gradelib.php indirectly via
// other course-page setup, which is why this was missed until pdf.php (a
// deliberately minimal entry point) hit it directly.
require_once($CFG->libdir . '/gradelib.php');

/**
 * Assembles one row per enrolled student for the Model 1 dashboard table.
 */
class model1_report {
    /** Soft cap on rows built, so a very large course can't make the page unusably slow. */
    const MAX_ROWS = 100;

    /**
     * Builds one row per enrolled student for the Model 1 dashboard table.
     *
     * @param int $courseid
     * @return \stdClass {rows: \stdClass[], total: int, truncated: bool}
     */
    public static function build(int $courseid): \stdClass {
        $context = \context_course::instance($courseid);

        // Matches core_analytics\course::get_user_ids()'s exact pattern: role
        // ids must be prefixed with the unique 'ra.id' field when more than
        // one role id is passed, or get_role_users() raises a debugging()
        // warning.
        $studentroleids = array_keys(get_archetype_roles('student'));
        $roleassignments = get_role_users($studentroleids, $context, true, 'ra.id, u.id AS userid', 'ra.id ASC');
        $studentuserids = array_values(array_unique(array_map(fn($ra) => (int) $ra->userid, $roleassignments)));

        if (empty($studentuserids)) {
            return (object) ['rows' => [], 'total' => 0, 'truncated' => false];
        }

        $enrolments = enrol_get_course_users($courseid);
        $studentuseridslookup = array_flip($studentuserids);

        $bystudent = [];
        foreach ($enrolments as $enrolmentid => $user) {
            $userid = (int) $user->id;
            if (!isset($studentuseridslookup[$userid]) || isset($bystudent[$userid])) {
                // Either not a student, or a student we've already picked an
                // enrolment for (a student can hold more than one enrolment
                // — one dashboard row per student, not per enrolment).
                continue;
            }
            $bystudent[$userid] = (object) ['enrolmentid' => (int) $enrolmentid, 'user' => $user];
        }

        $total = count($bystudent);
        $truncated = $total > self::MAX_ROWS;
        $bystudent = array_slice($bystudent, 0, self::MAX_ROWS, true);

        [$gradeitemid, $grademax, $gradepasspercent] = self::get_course_gradepass($courseid);

        $rows = [];
        foreach ($bystudent as $userid => $source) {
            $rows[] = (object) [
                'userid' => $userid,
                'fullname' => fullname($source->user),
                'gradestatus' => self::get_grade_status($gradeitemid, $grademax, $userid, $gradepasspercent),
                'indicators' => [
                    'gradetrajectory' => grade_trajectory::compute_for_sample($source->enrolmentid),
                    'responselatencyanomaly' => response_latency_anomaly::compute_for_sample($source->enrolmentid),
                    'disengagemententropy' => disengagement_entropy::compute_for_sample($source->enrolmentid),
                    'helpseekinggap' => help_seeking_gap::compute_for_sample($source->enrolmentid),
                    'feedbackrevisiondistance' => feedback_revision_distance::compute_for_sample($source->enrolmentid),
                ],
            ];
        }

        return (object) ['rows' => $rows, 'total' => $total, 'truncated' => $truncated];
    }

    /**
     * The course's grade item id, grade max, and grade-to-pass expressed as
     * a percentage of that max (a course's own grade max is rarely 0-100,
     * so showing raw point values on the dashboard would be misleading —
     * percentage is the one course-agnostic unit worth displaying).
     * Grade-to-pass is null if the course grade item isn't a value-graded
     * item with a pass grade set — the same "is this even meaningful" check
     * course_gradetopass::get_course_gradetopass() makes. Fetched once for
     * the whole table rather than once per student row.
     *
     * @param int $courseid
     * @return array [int $gradeitemid, float $grademax, float|null $gradepasspercent]
     */
    private static function get_course_gradepass(int $courseid): array {
        $courseitem = \grade_item::fetch_course_item($courseid);
        $grademax = (float) $courseitem->grademax;
        if (
            $courseitem->gradetype != GRADE_TYPE_VALUE
            || !grade_floats_different($courseitem->gradepass, 0.0)
            || $grademax <= 0.0
        ) {
            return [(int) $courseitem->id, $grademax, null];
        }
        return [(int) $courseitem->id, $grademax, round(100.0 * (float) $courseitem->gradepass / $grademax, 1)];
    }

    /**
     * One student's grade-vs-pass status, expressed in the same percentage terms as $gradepasspercent.
     *
     * @param int $gradeitemid
     * @param float $grademax
     * @param int $userid
     * @param float|null $gradepasspercent
     * @return \stdClass {gradepasspercent: float|null, gradepercent: float|null, atrisk: bool|null}
     */
    private static function get_grade_status(int $gradeitemid, float $grademax, int $userid, ?float $gradepasspercent): \stdClass {
        if ($gradepasspercent === null) {
            return (object) ['gradepasspercent' => null, 'gradepercent' => null, 'atrisk' => null];
        }

        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitemid, 'userid' => $userid]);
        $gradepercent = ($gradegrade && $gradegrade->finalgrade !== null)
            ? round(100.0 * (float) $gradegrade->finalgrade / $grademax, 1)
            : null;

        return (object) [
            'gradepasspercent' => $gradepasspercent,
            'gradepercent' => $gradepercent,
            'atrisk' => $gradepercent !== null ? ($gradepercent < $gradepasspercent) : null,
        ];
    }
}
