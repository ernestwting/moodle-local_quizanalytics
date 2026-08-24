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
 * All database access for local_quizanalytics: detecting which quizzes in a
 * course (or a specific quiz) contain qtype_stack questions, and reading
 * finished attempts + responses straight out of the database — reconstructing
 * the same row shape Moodle's own "Responses report" CSV export produces
 * (matching what analytics/parser.py::build_response_rows already expects),
 * so the analytics engine itself needs no changes, only its input source.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * Fetches quiz/course attempt records from Moodle's own DB tables for the analytics package to consume.
 */
class local_quizanalytics_data_fetcher {
    /**
     * Cheap existence check: does this course contain at least one quiz with
     * at least one qtype_stack question in one of its slots?
     *
     * Only matches questions added directly to a slot (the normal way STACK
     * questions are added to a quiz). Quizzes that pull STACK questions in
     * only via "random question from category" slots are not detected here —
     * that's a deliberately narrow scope for a fast nav-gating check, not a
     * full quiz-structure walk.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_stack_quiz(int $courseid): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {quiz} quiz
                  JOIN {course_modules} cm ON cm.instance = quiz.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {context} ctx ON ctx.contextlevel = :contextmodule AND ctx.instanceid = cm.id
                  JOIN {quiz_slots} slot ON slot.quizid = quiz.id
                  JOIN {question_references} qr ON qr.usingcontextid = ctx.id
                                                AND qr.component = 'mod_quiz'
                                                AND qr.questionarea = 'slot'
                                                AND qr.itemid = slot.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'stack'
                 WHERE quiz.course = :courseid";

        return $DB->record_exists_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'courseid'      => $courseid,
        ]);
    }

    /**
     * Same matching rule as course_has_stack_quiz(), narrowed to one quiz —
     * used to gate the Analytics link this plugin adds to a quiz's own
     * settings/administration menu (see lib.php's
     * local_quizanalytics_extend_settings_navigation()), which runs on every
     * quiz page view so needs to stay just as cheap.
     *
     * @param int $quizid
     * @return bool
     */
    public static function quiz_has_stack_question(int $quizid): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {quiz} quiz
                  JOIN {course_modules} cm ON cm.instance = quiz.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {context} ctx ON ctx.contextlevel = :contextmodule AND ctx.instanceid = cm.id
                  JOIN {quiz_slots} slot ON slot.quizid = quiz.id
                  JOIN {question_references} qr ON qr.usingcontextid = ctx.id
                                                AND qr.component = 'mod_quiz'
                                                AND qr.questionarea = 'slot'
                                                AND qr.itemid = slot.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'stack'
                 WHERE quiz.id = :quizid";

        return $DB->record_exists_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'quizid'        => $quizid,
        ]);
    }

    /**
     * Lists every quiz in the course that contains at least one qtype_stack
     * question (same matching rule as course_has_stack_quiz()), for the quiz
     * selector on index.php.
     *
     * @param int $courseid
     * @return stdClass[] quiz records (id, name, course), keyed by quiz id
     */
    public static function get_course_stack_quizzes(int $courseid): array {
        global $DB;

        $sql = "SELECT DISTINCT quiz.id, quiz.name, quiz.course, quiz.sumgrades, quiz.grade
                  FROM {quiz} quiz
                  JOIN {course_modules} cm ON cm.instance = quiz.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {context} ctx ON ctx.contextlevel = :contextmodule AND ctx.instanceid = cm.id
                  JOIN {quiz_slots} slot ON slot.quizid = quiz.id
                  JOIN {question_references} qr ON qr.usingcontextid = ctx.id
                                                AND qr.component = 'mod_quiz'
                                                AND qr.questionarea = 'slot'
                                                AND qr.itemid = slot.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'stack'
                 WHERE quiz.course = :courseid";

        $quizzes = $DB->get_records_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'courseid'      => $courseid,
        ]);

        // Match Moodle's course page order: section order first, then the
        // activity order stored in each section's sequence. The SQL query
        // above deliberately does not sort by quiz name.
        $modinfo = get_fast_modinfo($courseid);
        $courseorder = [];
        $position = 0;
        foreach ($modinfo->get_sections() as $cmids) {
            foreach ($cmids as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname === 'quiz') {
                    $courseorder[(int) $cm->instance] = $position++;
                }
            }
        }

        uasort($quizzes, static function (stdClass $a, stdClass $b) use ($courseorder): int {
            $aorder = $courseorder[(int) $a->id] ?? PHP_INT_MAX;
            $border = $courseorder[(int) $b->id] ?? PHP_INT_MAX;
            if ($aorder !== $border) {
                return $aorder <=> $border;
            }
            return (int) $a->id <=> (int) $b->id;
        });

        return $quizzes;
    }

    /**
     * Response records for a single quiz — one row per finished attempt,
     * with one set of question_N_text, response_N, right_answer_N (etc.)
     * columns per slot in that attempt.
     *
     * @param stdClass $quiz   row from mdl_quiz
     * @param stdClass $course course record
     * @param bool $includeinprogress include Moodle's in-progress attempts
     * @return array
     */
    public static function get_response_records_for_quiz(
        stdClass $quiz,
        stdClass $course,
        bool $includeinprogress = false
    ): array {
        global $DB;

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        $records = [];

        // Only finished attempts — matches what a teacher would get from the
        // "Responses" report with the default "Finished" state filter. Adjust
        // this if you also want in-progress/overdue attempts included.
        if ($includeinprogress) {
            $attempts = $DB->get_records_select(
                'quiz_attempts',
                "quiz = :quizid AND state IN ('finished', 'inprogress')",
                ['quizid' => $quiz->id],
                'userid, attempt'
            );
        } else {
            $attempts = $DB->get_records('quiz_attempts', [
                'quiz'  => $quiz->id,
                'state' => 'finished',
            ], 'userid, attempt');
        }

        if (!$attempts) {
            return [];
        }

        // Batch-load user records instead of querying per attempt.
        $userids = array_unique(array_map(fn($a) => $a->userid, $attempts));
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, username, firstname, lastname, email');

        foreach ($attempts as $attempt) {
            $user = $users[$attempt->userid] ?? null;
            if (!$user) {
                continue; // Deleted/suspended user — skip rather than fail the whole report.
            }

            // This is the same question engine API Moodle's own quiz reports use
            // internally to build the Responses/Grades exports, so the text you
            // get here should match what a manual CSV download would contain.
            $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

            $row = [
                'attempt_id'     => $attempt->id,
                'cmid'           => $cm->id,
                'username'       => $user->username,
                'last_name'      => $user->lastname,
                'first_name'     => $user->firstname,
                'email'          => $user->email,
                'state'          => $attempt->state,
                // Passing fixday=false: userdate()'s default (true) strips the
                // leading zero from the day ("2026-6-3" instead of
                // "2026-06-03"), which breaks sections-renderer.js's
                // ISO_DATETIME_RE match for single-digit days — that row
                // then fell back to showing this raw string unformatted
                // while every other row displayed nicely, which is exactly
                // the inconsistent Completed On/Started On formatting
                // reported against this page.
                'started_on'     => userdate($attempt->timestart, '%Y-%m-%d %H:%M:%S', 99, false),
                'completed'      => $attempt->timefinish
                    ? userdate($attempt->timefinish, '%Y-%m-%d %H:%M:%S', 99, false) : '',
                'time_taken_secs' => $attempt->timefinish
                    ? ($attempt->timefinish - $attempt->timestart) : null,
                'grade'          => $attempt->sumgrades,
                'max_grade'      => $quiz->sumgrades,
                'attempt_number' => $attempt->attempt,
            ];

            $reachedslots = $DB->get_records_menu(
                'question_attempts',
                ['questionusageid' => $attempt->uniqueid],
                'slot',
                'slot, id'
            );
            $qnum = 1;
            foreach ($quba->get_slots() as $slot) {
                $question = $quba->get_question($slot);

                // Get_response_summary() is the same method the core "Responses"
                // report calls to build its "Response N" column — for STACK
                // questions this includes the ansK/prtK trace the Python parser
                // already knows how to read (parse_response_cell).
                $row["question_{$qnum}_text"]    = self::render_stack_question_text($question);
                $row["response_{$qnum}"]         = $quba->get_response_summary($slot) ?? '';
                $row["right_answer_{$qnum}"]     = $quba->get_right_answer_summary($slot) ?? '';
                $row["question_{$qnum}_mark"]    = $quba->get_question_mark($slot);
                $row["question_{$qnum}_maxmark"] = $quba->get_question_max_mark($slot);
                $row["question_{$qnum}_reached"] = array_key_exists($slot, $reachedslots);
                $row["question_{$qnum}_state"]   = (string) $quba->get_question_attempt($slot)->get_state();

                $qnum++;
            }

            $records[] = $row;
        }

        return $records;
    }

    /**
     * Build the compact Moodle-backed snapshot shown above an individual quiz
     * analysis. Attempt counts include every Moodle attempt state, while the
     * per-question student counts include finished and in-progress attempts.
     *
     * @param stdClass $quiz
     * @param stdClass $course
     * @return array
     */
    public static function get_quiz_snapshot(stdClass $quiz, stdClass $course): array {
        global $DB;

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        $slots = $DB->get_records_sql(
            "SELECT id, slot
               FROM {quiz_slots}
              WHERE quizid = :quizid AND slot > 0
           ORDER BY slot",
            ['quizid' => $quiz->id]
        );

        $statecounts = $DB->get_records_sql(
            "SELECT state, COUNT(*) AS attemptcount
               FROM {quiz_attempts}
              WHERE quiz = :quizid
           GROUP BY state",
            ['quizid' => $quiz->id]
        );
        $studentswithattempts = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid)
               FROM {quiz_attempts}
              WHERE quiz = :quizid",
            ['quizid' => $quiz->id]
        );
        $attempts = ['total' => 0, 'finished' => 0, 'inprogress' => 0, 'other' => 0];
        foreach ($statecounts as $statecount) {
            $count = (int) $statecount->attemptcount;
            $attempts['total'] += $count;
            if ($statecount->state === 'finished') {
                $attempts['finished'] = $count;
            } else if ($statecount->state === 'inprogress') {
                $attempts['inprogress'] = $count;
            } else {
                $attempts['other'] += $count;
            }
        }

        // Match Moodle's Quiz Overview "Overall average" row: average the
        // raw sumgrades for finished attempts with a grade, then rescale it
        // to the quiz grade shown by Moodle.
        $averagerecord = $DB->get_record_sql(
            "SELECT AVG(sumgrades) AS averagegrade, COUNT(sumgrades) AS gradedcount
               FROM {quiz_attempts}
              WHERE quiz = :quizid AND state = :finishedstate AND sumgrades IS NOT NULL",
            ['quizid' => $quiz->id, 'finishedstate' => 'finished']
        );
        $quizaverage = $averagerecord->averagegrade === null
            ? null
            : (float) quiz_rescale_grade((float) $averagerecord->averagegrade, $quiz, false);

        $studentcounts = $DB->get_records_sql(
            "SELECT slot.slot,
                    COUNT(DISTINCT CASE WHEN questionattempt.id IS NOT NULL THEN attempt.userid END) AS studentcount
               FROM {quiz_slots} slot
          LEFT JOIN {quiz_attempts} attempt
                 ON attempt.quiz = slot.quizid
                AND attempt.state IN ('finished', 'inprogress')
          LEFT JOIN {question_attempts} questionattempt
                 ON questionattempt.questionusageid = attempt.uniqueid
                AND questionattempt.slot = slot.slot
              WHERE slot.quizid = :quizid AND slot.slot > 0
           GROUP BY slot.slot
           ORDER BY slot.slot",
            ['quizid' => $quiz->id]
        );
        $studentsbyquestion = [];
        foreach ($studentcounts as $studentcount) {
            $studentsbyquestion[] = [
                'question' => 'Q' . (int) $studentcount->slot,
                'students' => (int) $studentcount->studentcount,
            ];
        }

        // Use the same Moodle Quiz Statistics Facility Index values shown in
        // Quiz -> Results -> Statistics. Do not recompute question means from
        // the plugin's parsed response rows.
        $questionmeans = [];
        $questionmeancounts = [];
        foreach (self::get_course_question_facility_data($course, [$quiz]) as $facilityrow) {
            $questionmeans[$facilityrow['question_number']] = $facilityrow['mark_average'];
            $questionmeancounts[$facilityrow['question_number']] = $facilityrow['mark_average_count'];
        }

        return [
            'question_count' => count($slots),
            'students_with_attempts' => $studentswithattempts,
            'attempts_total' => $attempts['total'],
            'attempts_finished' => $attempts['finished'],
            'attempts_inprogress' => $attempts['inprogress'],
            'attempts_other' => $attempts['other'],
            'quiz_average' => $quizaverage,
            'quiz_average_finished' => (int) $averagerecord->gradedcount,
            'students_per_question' => $studentsbyquestion,
            'question_means' => $questionmeans,
            'question_mean_counts' => $questionmeancounts,
            'quiz_report_url' => (new \moodle_url('/mod/quiz/report.php', [
                'id' => $cm->id,
                'mode' => 'overview',
            ]))->out(false),
            'quiz_responses_url' => (new \moodle_url('/mod/quiz/report.php', [
                'id' => $cm->id,
                'mode' => 'responses',
            ]))->out(false),
        ];
    }

    /**
     * $question->questiontext is the raw, author-written source — for STACK
     * questions that still contains unresolved CAS placeholders (@variable@)
     * and every language's [[lang code='xx']]...[[/lang]] block side by side,
     * none of which get processed until the question is actually rendered
     * through STACK's own CAS-text engine. This renders it the same way
     * STACK's own question renderer would (CAS variables substituted,
     * [[lang]] blocks resolved to only the current language), using the
     * "out of context" processor STACK itself uses for report/summary
     * generation (stack_question::get_question_summary() is the other
     * caller of this exact pattern) since there's no live question_attempt
     * here to render against.
     *
     * Falls back to the raw questiontext for non-STACK questions or if
     * anything above goes wrong — a report showing unresolved placeholders
     * is still more useful than one that fails outright for one bad question.
     *
     * @param question_definition $question
     * @return string
     */
    protected static function render_stack_question_text(question_definition $question): string {
        global $CFG;

        if (!($question instanceof \qtype_stack_question) || empty($question->questiontextinstantiated)) {
            return $question->questiontext;
        }

        try {
            require_once($CFG->dirroot . '/question/type/stack/locallib.php');
            $processor = new \castext2_qa_processor(new \stack_outofcontext_process());
            $rendered = $question->questiontextinstantiated->get_rendered($processor);
            if ($rendered !== null && $rendered !== '') {
                return $rendered;
            }
        } catch (\Throwable $e) {
            debugging('local_quizanalytics: could not render CAS question text for a STACK question: ' .
                $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $question->questiontext;
    }

    /**
     * Response records for every STACK quiz in the course, grouped by quiz
     * name — the shape local_quizanalytics_api_client::analyze_course()
     * expects.
     *
     * @param stdClass $course
     * @param stdClass[] $stackquizzes as returned by get_course_stack_quizzes()
     * @return array [quiz_name => records[]]
     */
    public static function get_course_response_records(stdClass $course, array $stackquizzes): array {
        $bycourse = [];
        foreach ($stackquizzes as $quiz) {
            $bycourse[$quiz->name] = self::get_response_records_for_quiz($quiz, $course);
        }
        return $bycourse;
    }

    /**
     * Reads Moodle's Quiz Statistics Facility Index for each direct STACK
     * question slot in the supplied quizzes. Moodle calculates and caches
     * missing statistics using its own question-mark and max-mark formula;
     * the plugin only reads the resulting Moodle statistic.
     *
     * @param stdClass $course
     * @param stdClass[] $stackquizzes
     * @return array[]
     */
    public static function get_course_question_facility_data(stdClass $course, array $stackquizzes): array {
        global $DB;

        $rows = [];
        $report = new \quiz_statistics_report();
        foreach ($stackquizzes as $quiz) {
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $stackslots = $DB->get_records_sql(
                "SELECT DISTINCT slot.slot, slot.maxmark, q.id AS questionid, q.name AS questionname
                   FROM {quiz_slots} slot
                   JOIN {question_references} qr ON qr.usingcontextid = :contextid
                                                 AND qr.component = 'mod_quiz'
                                                 AND qr.questionarea = 'slot'
                                                 AND qr.itemid = slot.id
                   JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                   JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'stack'
                  WHERE slot.quizid = :quizid
               ORDER BY slot.slot",
                ['contextid' => $context->id, 'quizid' => $quiz->id]
            );

            if (empty($stackslots)) {
                continue;
            }

            // This is the same raw question-average source used by Moodle's
            // Quiz overview report (the values shown in its final average row).
            $questions = $report->load_and_initialise_questions_for_calculations($quiz);
            $qubaids = new \qubaid_join(
                '{quiz_attempts} quiza',
                'quiza.uniqueid',
                'quiza.quiz = :quizid AND quiza.preview = 0 AND quiza.state = :finishedstate',
                ['quizid' => (int) $quiz->id, 'finishedstate' => 'finished']
            );
            $averagemarks = (new \question_engine_data_mapper())->load_average_marks(
                $qubaids,
                array_keys($questions)
            );

            $questionstats = $report->calculate_questions_stats_for_question_bank(
                (int) $quiz->id,
                true,
                false
            );
            foreach ($stackslots as $slot) {
                $facility = null;
                $markaverage = null;
                if (isset($averagemarks[(int) $slot->slot]) && (float) $slot->maxmark > 0) {
                    $rawmarkaverage = (float) $averagemarks[(int) $slot->slot]->averagefraction *
                        (float) $slot->maxmark;
                    $markaverage = (float) quiz_rescale_grade($rawmarkaverage, $quiz, false);
                }
                if ($questionstats !== null && $questionstats->has_slot((int) $slot->slot)) {
                    $stat = $questionstats->for_slot((int) $slot->slot);
                    if ($stat->facility !== null) {
                        $facility = (float) $stat->facility * 100.0;
                    }
                }
                $rows[] = [
                    'quiz_name' => $quiz->name,
                    'question_number' => 'Q' . (int) $slot->slot,
                    'question_slot' => (int) $slot->slot,
                    'question_name' => $slot->questionname,
                    'facility_index' => $facility,
                    'mark_average' => $markaverage,
                    'mark_average_count' => isset($averagemarks[(int) $slot->slot])
                        ? (int) $averagemarks[(int) $slot->slot]->numaveraged : 0,
                ];
            }
        }

        return $rows;
    }
}
