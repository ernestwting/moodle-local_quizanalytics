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

use local_quizanalytics\task\parallel_course_fetcher;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// local_quizanalytics_quiz_data_fetcher is a legacy, non-namespaced class
// (classes/quiz/data_fetcher.php), so Moodle's own classes/ autoloader
// doesn't pick it up the way it does parallel_course_fetcher above — every
// real caller (index.php, questionanalytics.php, ...) requires this file
// explicitly, and this test calls the class directly (not only through
// parallel_course_fetcher::fetch(), whose own require of this same file
// happens too late, inside its method body, to help the calls below).
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/data_fetcher.php');

/**
 * Unit tests for parallel_course_fetcher's own dispatch/merge logic —
 * fallback thresholds, and that its result matches
 * data_fetcher.php's get_course_response_records() exactly.
 *
 * What this deliberately does NOT attempt: a fixture with real, finished,
 * CAS-graded STACK attempts to exercise the actual forked-worker path
 * end to end. This codebase's own existing STACK fixtures already document
 * that gap (see model2_report_test.php/model1_report_test.php: "no
 * finished attempts yet in this fixture... already documented in
 * CHANGELOG.md as deferred") rather than attempting a fragile synthetic
 * walkthrough — same call made here, for the same reason. The actual
 * parallel-vs-serial equivalence claim (byte-identical serialize() output)
 * was verified directly against a real, live, production-scale course
 * (48,445 real graded attempts across 38 quizzes) as part of this fix —
 * see CHANGELOG.md — which is a strictly stronger correctness check than
 * any synthetic fixture here could provide, just not one that can be
 * captured as an automated test.
 *
 * What IS covered here, and can regress silently without a test: the
 * dispatch logic itself — that fetch() with workers <= 1, with only one
 * quiz, or with no attempts at all, still returns exactly what
 * get_course_response_records() would, so a caller never has to care
 * whether a given call actually forked.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parallel_course_fetcher_test extends \advanced_testcase {
    /**
     * @return array{0: \stdClass, 1: \stdClass[]} [$course, $stackquizzes] a course with two
     *         empty (no finished attempts) STACK quizzes.
     */
    private function create_course_with_two_stack_quizzes(): array {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $quizgenerator = $dg->get_plugin_generator('mod_quiz');
        $questiongenerator = $dg->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $quizzes = [];
        foreach (['Quiz A', 'Quiz B'] as $name) {
            $quiz = $quizgenerator->create_instance(['course' => $course->id, 'name' => $name]);
            $question = $questiongenerator->create_question('stack', 'test1', ['category' => $cat->id]);
            quiz_add_quiz_question($question->id, $quiz);
            $quizzes[$quiz->id] = $quiz;
        }

        return [$course, $quizzes];
    }

    public function test_single_worker_matches_serial_fetch_exactly(): void {
        $this->resetAfterTest(true);
        [$course, $quizzes] = $this->create_course_with_two_stack_quizzes();

        $serial = \local_quizanalytics_quiz_data_fetcher::get_course_response_records($course, $quizzes);
        $parallel = parallel_course_fetcher::fetch($course, $quizzes, 1);

        $this->assertSame($serial, $parallel);
    }

    public function test_single_quiz_matches_serial_fetch_exactly(): void {
        // Only one quiz to fetch — fetch() should take its own "not worth
        // forking for one chunk" shortcut even when $workers > 1.
        $this->resetAfterTest(true);
        [$course, $quizzes] = $this->create_course_with_two_stack_quizzes();
        $onequiz = [array_key_first($quizzes) => reset($quizzes)];

        $serial = \local_quizanalytics_quiz_data_fetcher::get_course_response_records($course, $onequiz);
        $parallel = parallel_course_fetcher::fetch($course, $onequiz, 4);

        $this->assertSame($serial, $parallel);
    }

    public function test_multi_worker_matches_serial_fetch_exactly_with_no_attempts(): void {
        // Exercises the real fork path (workers > 1, multiple quizzes) —
        // with zero finished attempts each worker's own fetch is trivial
        // (get_response_records_for_quiz() returns [] immediately), so this
        // is a genuine, fast, CI-safe run of the actual fork/reconnect/
        // merge machinery, just not of the CAS-heavy inner loop itself.
        $this->resetAfterTest(true);
        [$course, $quizzes] = $this->create_course_with_two_stack_quizzes();

        $serial = \local_quizanalytics_quiz_data_fetcher::get_course_response_records($course, $quizzes);
        $parallel = parallel_course_fetcher::fetch($course, $quizzes, 4);

        $this->assertSame($serial, $parallel);
        $this->assertSame([], $parallel[array_key_first($serial)] ?? 'MISSING KEY');
    }

    public function test_empty_quiz_list_returns_empty_array(): void {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();

        $this->assertSame([], parallel_course_fetcher::fetch($course, [], 4));
    }
}
