<?php
/**
 * Course-scoped data access for local_quizanalytics.
 *
 * This class does two jobs that don't exist anywhere else:
 *   1. Detecting which quizzes in a course contain qtype_stack questions
 *      (used both to gate the "Analytics" nav node in lib.php, and to build
 *      the quiz selector on index.php).
 *   2. Fetching response records for one or many of those quizzes.
 *
 * For (2) it deliberately does NOT reimplement the attempt/response
 * extraction logic — it requires mod/quiz/report/quizanalytics's own
 * data_fetcher.php and calls quiz_quizanalytics_data_fetcher::
 * get_response_records() directly, so both plugins stay in sync with a
 * single proven implementation. See version.php for the hard dependency on
 * quiz_quizanalytics that makes this safe to assume.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

class local_quizanalytics_data_fetcher {

    /**
     * Pulls in the per-quiz report subplugin's fetcher class. Safe to call
     * repeatedly — require_once no-ops after the first include.
     */
    protected static function require_quiz_fetcher(): void {
        global $CFG;
        $path = $CFG->dirroot . '/mod/quiz/report/quizanalytics/classes/data_fetcher.php';
        if (!file_exists($path)) {
            // version.php's $plugin->dependencies should make this
            // unreachable in practice, but fail loudly rather than silently
            // if someone disables/removes quiz_quizanalytics after install.
            throw new \moodle_exception('missingquizreportplugin', 'local_quizanalytics');
        }
        require_once($path);
    }

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
     * Response records for a single quiz, reusing quiz_quizanalytics's own
     * proven extraction logic unchanged.
     *
     * @param stdClass $quiz   row from mdl_quiz
     * @param stdClass $course course record
     * @return array
     */
    public static function get_response_records_for_quiz(stdClass $quiz, stdClass $course): array {
        self::require_quiz_fetcher();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        return quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);
    }

    /**
     * Response records for every STACK quiz in the course, grouped by quiz
     * name — the shape local_quizanalytics_api_client::analyze_course()
     * expects, matching the /analyze-course request schema.
     *
     * @param stdClass $course
     * @param stdClass[] $stackquizzes as returned by get_course_stack_quizzes()
     * @return array [quiz_name => records[]]
     */
    public static function get_course_response_records(stdClass $course, array $stackquizzes): array {
        self::require_quiz_fetcher();

        $bycourse = [];
        foreach ($stackquizzes as $quiz) {
            $bycourse[$quiz->name] = self::get_response_records_for_quiz($quiz, $course);
        }
        return $bycourse;
    }
}
