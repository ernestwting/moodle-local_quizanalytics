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

use local_quizanalytics\stack\analytics\analyser\stack_question_analyser;
use local_quizanalytics\stack\analytics\target\question_needs_review;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the Model 2 stack_question_analyser.
 *
 * The quiz/question fixture setup mirrors mod_quiz's own
 * question_helper_test_trait::create_test_quiz() /
 * add_two_regular_questions() (mod/quiz/tests/classes/); the positive-path
 * STACK question uses $questiongenerator->create_question('stack', 'test1', ...) —
 * 'test1' specifically because create_question() needs a
 * get_stack_question_form_data_*() method on qtype_stack_test_helper, which
 * (confirmed against question/type/stack/tests/helper.php) only some named
 * fixtures implement; others (like the simpler 'test0') only have a
 * make_stack_question_*() object-constructor for STACK's own internal tests,
 * which create_question() can't use — this was the exact cause of a real CI
 * failure ("Method get_stack_question_form_data_test0 does not exist")
 * before switching to 'test1'.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stack_question_analyser_test extends \advanced_testcase {
    public function test_get_all_samples_excludes_non_stack_questions(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();

        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);

        $questiongenerator = $dg->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $target = new question_needs_review();
        $analyser = new stack_question_analyser(1, $target, [], [], []);
        $analysable = \core_analytics\course::instance($course);

        [$sampleids, $samplesdata] = $analyser->get_all_samples($analysable);

        $this->assertEmpty($sampleids);
        $this->assertEmpty($samplesdata);
    }

    public function test_get_all_samples_includes_a_real_stack_question(): void {
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();

        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);

        $questiongenerator = $dg->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('stack', 'test1', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $target = new question_needs_review();
        $analyser = new stack_question_analyser(1, $target, [], [], []);
        $analysable = \core_analytics\course::instance($course);

        [$sampleids, $samplesdata] = $analyser->get_all_samples($analysable);

        $this->assertCount(1, $sampleids);
        $slotid = reset($sampleids);
        $this->assertEquals($question->id, $samplesdata[$slotid]['quiz_slots']->questionid);
        $this->assertEquals($quiz->id, $samplesdata[$slotid]['quiz_slots']->quizid);

        $this->assertEquals(
            \context_course::instance($course->id)->id,
            $analyser->sample_access_context($slotid)->id
        );
        $this->assertEquals($course->id, $analyser->get_sample_analysable($slotid)->get_id());
    }

    public function test_get_samples_origin(): void {
        $target = new question_needs_review();
        $analyser = new stack_question_analyser(1, $target, [], [], []);
        $this->assertEquals('quiz_slots', $analyser->get_samples_origin());
    }
}
