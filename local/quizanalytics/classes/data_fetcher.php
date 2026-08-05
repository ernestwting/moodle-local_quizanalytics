<?php
/**
 * All database access for local_quizanalytics: detecting which quizzes in a
 * course (or a specific quiz) contain qtype_stack questions, and reading
 * finished attempts + responses straight out of the database — reconstructing
 * the same row shape Moodle's own "Responses report" CSV export produces
 * (matching what analytics/parser.py::build_response_rows already expects),
 * so the analytics engine itself needs no changes, only its input source.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

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

        $sql = "SELECT DISTINCT quiz.id, quiz.name, quiz.course, quiz.sumgrades
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
                 WHERE quiz.course = :courseid
              ORDER BY quiz.name";

        return $DB->get_records_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'courseid'      => $courseid,
        ]);
    }

    /**
     * Response records for a single quiz — one row per finished attempt,
     * with one set of question_N_text, response_N, right_answer_N (etc.)
     * columns per slot in that attempt.
     *
     * @param stdClass $quiz   row from mdl_quiz
     * @param stdClass $course course record
     * @return array
     */
    public static function get_response_records_for_quiz(stdClass $quiz, stdClass $course): array {
        global $DB;

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        $records = [];

        // Only finished attempts — matches what a teacher would get from the
        // "Responses" report with the default "Finished" state filter. Adjust
        // this if you also want in-progress/overdue attempts included.
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz'  => $quiz->id,
            'state' => 'finished',
        ], 'userid, attempt');

        if (!$attempts) {
            return [];
        }

        // Batch-load user records instead of querying per attempt.
        $userids = array_unique(array_map(fn($a) => $a->userid, $attempts));
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, email');

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
                'last_name'      => $user->lastname,
                'first_name'     => $user->firstname,
                'email'          => $user->email,
                'state'          => $attempt->state,
                'started_on'     => userdate($attempt->timestart, '%Y-%m-%d %H:%M:%S'),
                'completed'      => $attempt->timefinish
                    ? userdate($attempt->timefinish, '%Y-%m-%d %H:%M:%S') : '',
                'time_taken_secs' => $attempt->timefinish
                    ? ($attempt->timefinish - $attempt->timestart) : null,
                'grade'          => $attempt->sumgrades,
                'max_grade'      => $quiz->sumgrades,
                'attempt_number' => $attempt->attempt,
            ];

            $qnum = 1;
            foreach ($quba->get_slots() as $slot) {
                $question = $quba->get_question($slot);

                // get_response_summary() is the same method the core "Responses"
                // report calls to build its "Response N" column — for STACK
                // questions this includes the ansK/prtK trace the Python parser
                // already knows how to read (parse_response_cell).
                $row["question_{$qnum}_text"]    = self::render_stack_question_text($question);
                $row["response_{$qnum}"]         = $quba->get_response_summary($slot) ?? '';
                $row["right_answer_{$qnum}"]     = $quba->get_right_answer_summary($slot) ?? '';
                $row["question_{$qnum}_mark"]    = $quba->get_question_mark($slot);
                $row["question_{$qnum}_maxmark"] = $quba->get_question_max_mark($slot);

                $qnum++;
            }

            $records[] = $row;
        }

        return $records;
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
}
