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
 * Shared "does this course/enrolment involve STACK?" checks used by both
 * models' target classes (is_valid_analysable()) and by indicators that need
 * to resolve a Model 1 sample id (a user_enrolments.id) back to a user/course
 * pair.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course/enrolment level helpers shared across targets and indicators.
 */
class stack_course_helper {
    /**
     * Does this course contain at least one quiz slot backed by a
     * qtype_stack question?
     *
     * Reuses the exact join pattern local_quizanalytics's data_fetcher uses
     * (quiz -> quiz_slots -> question_references -> question_bank_entries ->
     * question_versions -> question, the Moodle 4.0+ question bank schema),
     * rather than re-deriving it — that query is already proven against a
     * real Moodle checkout for this same "which quizzes have STACK
     * questions" problem.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_stack_activity(int $courseid): bool {
        global $DB;

        $sql = "SELECT 1 FROM " . self::stack_slot_join_sql() . " WHERE quiz.course = :courseid";

        return $DB->record_exists_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'courseid'      => $courseid,
        ]);
    }

    /**
     * Every course the current user can view this plugin's dashboard in and
     * that actually has STACK activity — the dashboard's course-selector
     * list. Deliberately excludes courses the user can access the dashboard
     * for but that have no STACK questions at all, since there would be
     * nothing to show.
     *
     * get_user_capability_course() (lib/accesslib.php) is core's own
     * "list every course this user has capability X in" API — confirmed
     * against a real Moodle 4.5 core checkout rather than assumed, since it
     * returns false (not an empty array) when the user has the capability
     * nowhere.
     *
     * @return \stdClass[] each with ->id, ->shortname, ->fullname, sorted by fullname
     */
    public static function get_viewable_courses(): array {
        $courses = get_user_capability_course('local/quizanalytics:view', null, true, 'shortname,fullname', 'fullname');
        if ($courses === false) {
            return [];
        }

        return array_values(array_filter($courses, fn($course) => self::course_has_stack_activity((int) $course->id)));
    }

    /**
     * Resolves a Model 1 sample id back to the (userid, courseid) pair it
     * represents.
     *
     * Model 1 runs on \core\analytics\analyser\student_enrolments, whose
     * get_samples_origin() returns 'user_enrolments' and whose sample ids are
     * user_enrolments.id — confirmed by reading that analyser's source
     * directly against a real Moodle 4.5 core checkout while planning Phase 2
     * (see classes/analytics/target/student_at_risk.php's docblock).
     *
     * @param int $enrolmentid a user_enrolments.id
     * @return \stdClass|null object with ->userid and ->courseid, or null if not found
     */
    public static function get_enrolment_user_and_course(int $enrolmentid): ?\stdClass {
        global $DB;

        $sql = "SELECT ue.id, ue.userid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.id = :id";

        $record = $DB->get_record_sql($sql, ['id' => $enrolmentid]);
        return $record ?: null;
    }

    /**
     * Every STACK-backed quiz slot in a course — Model 2's sample grain
     * ("this STACK question as it appears in this specific quiz", i.e. one
     * quiz_slots row; see stack_question_analyser's docblock for why this
     * grain was chosen over a bare question id).
     *
     * @param int $courseid
     * @return \stdClass[] keyed by slot id, each with ->id, ->quizid, ->questionid, ->questionbankentryid, ->slot (slot number)
     */
    public static function get_course_stack_slots(int $courseid): array {
        global $DB;

        $sql = "SELECT slot.id, slot.quizid, slot.slot, q.id AS questionid, qbe.id AS questionbankentryid
                  FROM " . self::stack_slot_join_sql() . "
                 WHERE quiz.course = :courseid";

        return $DB->get_records_sql($sql, [
            'contextmodule' => CONTEXT_MODULE,
            'courseid'      => $courseid,
        ]);
    }

    /**
     * Resolves an arbitrary set of Model 2 sample ids (quiz_slots.id values,
     * which may span multiple courses) back to their slot/quiz/question data.
     *
     * @param int[] $slotids
     * @return \stdClass[] keyed by slot id, each with ->id, ->quizid, ->courseid, ->slot, ->questionid, ->questionbankentryid
     */
    public static function get_stack_slots(array $slotids): array {
        global $DB;

        if (empty($slotids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($slotids, SQL_PARAMS_NAMED);
        $sql = "SELECT slot.id, slot.quizid, quiz.course AS courseid, slot.slot, q.id AS questionid,
                       qbe.id AS questionbankentryid
                  FROM " . self::stack_slot_join_sql() . "
                 WHERE slot.id $insql";
        $params['contextmodule'] = CONTEXT_MODULE;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Every question id that has ever been a live (non-draft) version of a
     * question bank entry — what Model 2's attempt-matching queries need to
     * find a question's full attempt history, since editing a STACK question
     * creates a new {question_versions} row (and a new question.id) without
     * retroactively updating {question_attempts}.questionid on attempts
     * already made against an earlier version. stack_slot_join_sql() pins a
     * slot to exactly one *current* version's question.id for structural
     * purposes (which PRT is authored right now); this instead resolves
     * every version's id, for matching attempts however old.
     *
     * Confirmed against this plugin's own test data: a question edited 13
     * times had real attempts recorded only against version 11's question
     * id, not version 13's (the currently-resolved one) — the exact case
     * this method exists to cover.
     *
     * @param int $questionbankentryid
     * @return int[]
     */
    public static function get_all_question_ids_for_entry(int $questionbankentryid): array {
        global $DB;

        $ids = $DB->get_fieldset_select(
            'question_versions',
            'questionid',
            'questionbankentryid = :entry AND status <> :draft',
            ['entry' => $questionbankentryid, 'draft' => 'draft']
        );
        return array_map('intval', $ids);
    }

    /**
     * Raw count of every attempt at a question (any version, any state —
     * finished or not) within one quiz, regardless of what any specific
     * Model 2 indicator needs to compute a real reading. Lets the dashboard
     * distinguish "genuinely no attempts yet" from "some attempts exist, but
     * not the specific kind (finished, multi-step, etc.) this indicator
     * needs" instead of collapsing both into the same generic "not enough
     * data" message.
     *
     * @param int $quizid
     * @param int $questionbankentryid
     * @return int
     */
    public static function get_attempt_count(int $quizid, int $questionbankentryid): int {
        global $DB;

        $questionids = self::get_all_question_ids_for_entry($questionbankentryid);
        if (empty($questionids)) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {question_attempts} qa
               JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
              WHERE quiza.quiz = :quizid
                AND qa.questionid $insql",
            $params
        );
    }

    /**
     * The shared "STACK question slots" join fragment used by both the
     * course-activity check above and Model 2's sample queries. Matches the
     * join local_quizanalytics's data_fetcher and this plugin's
     * stack_attempt_reader both already rely on for the same "which quiz
     * slots are backed by a qtype_stack question" problem — except this one
     * additionally pins {question_versions} to exactly one row per slot.
     *
     * A question_bank_entry accumulates one {question_versions} row per edit
     * (all normally 'ready'), and a bare `qv.questionbankentryid = qbe.id`
     * join fans a slot out to one row per version — harmless where the
     * result is de-duplicated by DISTINCT over version-invariant columns (as
     * local_quizanalytics's own copy of this join happens to be), but
     * get_course_stack_slots()/get_stack_slots() below select `q.id AS
     * questionid`, which *does* vary per version. Confirmed via this
     * plugin's own test data (a question_bank_entries row with 6 accumulated
     * 'ready' versions): get_records_sql() silently collapsed the resulting
     * duplicate-keyed rows to whichever version the DB happened to return
     * last, discovered via a `debugging()` warning flood under
     * DEBUG_DEVELOPER, not from any functional symptom under normal
     * settings — meaning Model 2 could have been silently computing
     * indicators against a stale version of a question. The correlated
     * subquery below resolves the same "pinned version, or else latest
     * non-draft" semantics mod_quiz's own qbank_helper::get_question_structure()
     * uses (confirmed by reading that method directly), simplified since
     * this plugin doesn't need that method's Oracle-11.2 workaround.
     */
    private static function stack_slot_join_sql(): string {
        return "{quiz} quiz
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
                                              AND qv.version = COALESCE(qr.version, (
                                                  SELECT MAX(latest.version)
                                                    FROM {question_versions} latest
                                                   WHERE latest.questionbankentryid = qbe.id
                                                     AND latest.status <> 'draft'
                                              ))
                  JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'stack'";
    }
}
