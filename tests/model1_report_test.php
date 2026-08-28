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

namespace local_quizanalytics;

use local_quizanalytics\stack\analytics\report\model1_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the dashboard's Model 1 report builder.
 *
 * The grade-status fixture (two students, final grades 60/30, gradepass 50)
 * mirrors tests/student_at_risk_test.php's own
 * test_calculate_sample_matches_gradetopass_outcome() exactly, since
 * model1_report::get_grade_status() deliberately re-derives the same
 * comparison course_gradetopass::calculate_sample() makes (see that class's
 * docblock) rather than reflecting into core's protected method.
 *
 * Indicator values themselves aren't asserted on here — like every other
 * calculate_sample()-level test in this plugin, exercising them for real
 * needs a full quiz-attempt walkthrough fixture (question responses, seeds,
 * PRT traversal), which CHANGELOG.md already documents as deferred pending
 * qtype_stack's own walkthrough_interactive_test.php mechanism. What these
 * tests cover is model1_report's own orchestration: who ends up in the
 * table, and whether the grade-status read is correct.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class model1_report_test extends \advanced_testcase {
    public function test_no_students_returns_empty_report(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $report = model1_report::build($course->id);

        $this->assertSame([], $report->rows);
        $this->assertSame(0, $report->total);
        $this->assertFalse($report->truncated);
    }

    public function test_non_student_role_is_excluded(): void {
        $this->resetAfterTest(true);
        global $DB;

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $dg->enrol_user($dg->create_user()->id, $course->id, $teacherrole->id);

        $report = model1_report::build($course->id);

        $this->assertSame([], $report->rows);
    }

    public function test_grade_status_reflects_pass_threshold(): void {
        $this->resetAfterTest(true);
        global $DB;

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $passingstudent = $dg->create_user();
        $failingstudent = $dg->create_user();
        $dg->enrol_user($passingstudent->id, $course->id, $studentrole->id);
        $dg->enrol_user($failingstudent->id, $course->id, $studentrole->id);

        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($passingstudent->id, 60);
        $courseitem->update_final_grade($failingstudent->id, 30);
        $courseitem->gradepass = 50;
        $DB->update_record('grade_items', $courseitem);

        $report = model1_report::build($course->id);

        $this->assertSame(2, $report->total);
        $byuser = [];
        foreach ($report->rows as $row) {
            $byuser[$row->userid] = $row;
        }

        $this->assertFalse($byuser[$passingstudent->id]->gradestatus->atrisk);
        $this->assertEqualsWithDelta(60.0, $byuser[$passingstudent->id]->gradestatus->gradepercent, 0.01);

        $this->assertTrue($byuser[$failingstudent->id]->gradestatus->atrisk);
        $this->assertEqualsWithDelta(30.0, $byuser[$failingstudent->id]->gradestatus->gradepercent, 0.01);

        $this->assertEqualsWithDelta(50.0, $byuser[$passingstudent->id]->gradestatus->gradepasspercent, 0.01);
    }

    public function test_no_gradepass_set_leaves_status_unknown(): void {
        $this->resetAfterTest(true);
        global $DB;

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $student = $dg->create_user();
        $dg->enrol_user($student->id, $course->id, $studentrole->id);

        $report = model1_report::build($course->id);

        $this->assertCount(1, $report->rows);
        $row = reset($report->rows);
        $this->assertNull($row->gradestatus->gradepasspercent);
        $this->assertNull($row->gradestatus->gradepercent);
        $this->assertNull($row->gradestatus->atrisk);

        foreach ($row->indicators as $result) {
            $this->assertNull($result); // No STACK activity at all in this course.
        }
    }
}
