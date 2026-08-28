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

use local_quizanalytics\stack\analytics\target\question_needs_review;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the Model 2 question_needs_review target.
 *
 * is_valid_analysable() only needs a STACK question to exist, so it's
 * exercised here with a real one via
 * $questiongenerator->create_question('stack', 'test1', ...) (a named
 * qtype_stack_test_helper fixture — see stack_question_analyser_test.php's
 * docblock for the generator API this relies on). calculate_sample()'s
 * pass-rate-threshold logic additionally needs real *attempt* data (finished
 * quiz attempts at that question, not just the question existing), which
 * needs a full quiz-attempt walkthrough fixture — qtype_stack's own
 * tests/walkthrough_interactive_test.php is the real mechanism to build that
 * from, left for a future pass rather than risking an unverified attempt
 * simulation here.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_needs_review_test extends \advanced_testcase {
    public function test_rejects_course_without_stack_activity(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $analysable = \core_analytics\course::instance($course);
        $target = new question_needs_review();

        $this->assertEquals(
            get_string('errornostackactivity', 'local_quizanalytics'),
            $target->is_valid_analysable($analysable)
        );
    }

    public function test_accepts_course_with_stack_activity(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();

        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);

        $questiongenerator = $dg->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('stack', 'test1', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $analysable = \core_analytics\course::instance($course);
        $target = new question_needs_review();

        $this->assertTrue($target->is_valid_analysable($analysable));
    }

    public function test_analyser_class(): void {
        $target = new question_needs_review();
        $this->assertEquals(
            '\local_quizanalytics\stack\analytics\analyser\stack_question_analyser',
            $target->get_analyser_class()
        );
    }

    public function test_can_use_timesplitting_restricted_to_single_range(): void {
        $target = new question_needs_review();

        $this->assertTrue($target->can_use_timesplitting(new \core\analytics\time_splitting\single_range()));
        $this->assertFalse($target->can_use_timesplitting(new \core\analytics\time_splitting\quarters_accum()));
    }
}
