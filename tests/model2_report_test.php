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

use local_quizanalytics\stack\analytics\report\model2_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the dashboard's Model 2 report builder.
 *
 * The quiz/question fixture setup mirrors tests/stack_question_analyser_test.php's
 * own fixtures exactly ('test1', not 'test0' — see that file's docblock for
 * why). As with model1_report_test.php, indicator values themselves aren't
 * asserted on here — that needs a full attempt-walkthrough fixture, already
 * documented in CHANGELOG.md as deferred. What's covered is model2_report's
 * own orchestration: which quiz slots end up in the table, and whether the
 * quiz filter narrows correctly.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class model2_report_test extends \advanced_testcase {
    /**
     * Adds a real, DB-backed STACK question to a quiz.
     *
     * @param \stdClass $course
     * @param \stdClass $quiz
     * @return \stdClass the created question
     */
    private function add_stack_question(\stdClass $course, \stdClass $quiz): \stdClass {
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('stack', 'test1', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);
        return $question;
    }

    public function test_no_stack_questions_returns_empty_report(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);

        $questiongenerator = $dg->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $report = model2_report::build($course->id);

        $this->assertSame([], $report->rows);
        $this->assertSame(0, $report->total);
    }

    public function test_real_stack_question_appears_in_the_report(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'name' => 'Quiz A']);
        $question = $this->add_stack_question($course, $quiz);

        $report = model2_report::build($course->id);

        $this->assertSame(1, $report->total);
        $this->assertCount(1, $report->rows);
        $row = reset($report->rows);
        $this->assertSame($question->name, $row->questionname);
        $this->assertSame('Quiz A', $row->quizname);
        // No finished attempts yet in this fixture, so nothing to base a read on.
        $this->assertNull($row->needsreview);
    }

    public function test_quiz_filter_narrows_to_one_quiz(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $quizgenerator = $dg->get_plugin_generator('mod_quiz');

        $quiza = $quizgenerator->create_instance(['course' => $course->id, 'name' => 'Quiz A']);
        $quizb = $quizgenerator->create_instance(['course' => $course->id, 'name' => 'Quiz B']);
        $this->add_stack_question($course, $quiza);
        $this->add_stack_question($course, $quizb);

        $allreport = model2_report::build($course->id);
        $this->assertSame(2, $allreport->total);

        $filteredreport = model2_report::build($course->id, (int) $quiza->id);
        $this->assertSame(1, $filteredreport->total);
        $filteredrow = reset($filteredreport->rows);
        $this->assertSame('Quiz A', $filteredrow->quizname);
    }
}
