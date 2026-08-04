<?php
/**
 * Reconstructs the same "Responses report" rows Moodle's CSV export produces,
 * but reads them straight out of the database instead of generating a file.
 *
 * This intentionally mirrors the column shape your existing Python parser
 * (analytics/parser.py::build_response_rows) already expects, so the analytics
 * engine itself needs almost no changes — only its input source changes.
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

class quiz_quizanalytics_data_fetcher {

    /**
     * @param stdClass $quiz   row from mdl_quiz
     * @param stdClass|cm_info $cm course-module record — mod/quiz/report.php
     *        passes a cm_info object (from quiz_settings::get_cm()), not a
     *        plain DB record, so this can't be typed stdClass-only.
     * @param stdClass $course course record
     * @return array           list of associative arrays, one per attempt
     */
    public static function get_response_records(stdClass $quiz, stdClass|cm_info $cm, stdClass $course): array {
        global $DB;

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
                // questions this includes the ansK/prtK trace your parser.py
                // already knows how to read (parse_response_cell). VERIFY this
                // against a real quiz on your Moodle version/qtype_stack version
                // before trusting it in production — see the README note.
                $row["question_{$qnum}_text"]    = $question->questiontext;
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
     * Same idea as get_response_records(), but for every quiz in a course —
     * used by the course-level "compare quizzes" entry point. Returns
     * [quiz_name => records[]] so the caller/analytics engine can tell quizzes
     * apart, matching how multiple uploaded CSVs are grouped today.
     *
     * @param stdClass $course
     * @return array
     */
    public static function get_course_response_records(stdClass $course): array {
        global $DB;

        $bycourse = [];

        $quizzes = $DB->get_records('quiz', ['course' => $course->id]);
        foreach ($quizzes as $quiz) {
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
            $bycourse[$quiz->name] = self::get_response_records($quiz, $cm, $course);
        }

        return $bycourse;
    }
}
