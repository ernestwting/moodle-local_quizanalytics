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
 * Shared question-engine data access for both models' indicators: Model 1's
 * per-student queries (scoped by userid+courseid) and Model 2's per-slot
 * queries (scoped by quizid+questionid, added in Phase 4). Centralising
 * these here, rather than duplicating a variant of the same STACK-question
 * join in every indicator class, means there is exactly one place to fix a
 * schema assumption if it turns out to be wrong against a real install.
 *
 * All queries join through mod_quiz's quiz_attempts + the question engine's
 * own question_attempts / question_attempt_steps / question_attempt_step_data
 * tables, which have been stable across Moodle versions for well over a
 * decade (see question/engine/lib.php) — the STACK-question filter itself
 * reuses the same quiz_slots -> question_references -> question_bank_entries
 * -> question_versions -> question join local_quizanalytics's data_fetcher
 * already relies on for the same purpose.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads a student's own finished/in-progress STACK question attempts within
 * a course and an optional [starttime, endtime] window.
 */
class stack_attempt_reader {
    /**
     * Per-process memo, keyed by a string built from each method's own
     * call parameters (see memoize() below) — added after confirming
     * directly why this class needed it: model1_report::build() calls
     * these once per student row (up to 100), and several of the queries
     * below are *course-wide* (no userid filter at all — get_course_step_
     * deltas(), and get_resource_access_timestamps()/get_stack_failure_
     * events() when called with $userid=null), meaning the exact same
     * expensive multi-table join was being re-run against the whole
     * course's data once per student, up to 100 times over, for a result
     * that's identical every time within one report. Measured directly on
     * a real 38-quiz/1,147-student course: model1_report::build()
     * took 299.4s before this fix. A static array is the right scope
     * here — one report build is one PHP process/request, and nothing
     * about the underlying data changes mid-request.
     *
     * @var array<string, mixed>
     */
    private static array $memo = [];

    /**
     * @param string $key unique across all callers — include the calling
     *        method's own name, not just its arguments, so two different
     *        methods can never collide even if called with coincidentally
     *        identical-looking parameters.
     * @param callable $compute run, and its result cached, only on a miss
     * @return mixed
     */
    private static function memoize(string $key, callable $compute) {
        if (!array_key_exists($key, self::$memo)) {
            self::$memo[$key] = $compute();
        }
        return self::$memo[$key];
    }

    /**
     * One row per finished STACK question attempt belonging to this student
     * in this course, with the attempt's final fraction and maxmark — the
     * raw signal grade_trajectory needs.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @return \stdClass[] each with ->fraction (0..1 or null) and ->maxmark
     */
    public static function get_finished_stack_grades(int $userid, int $courseid, $starttime, $endtime): array {
        return self::memoize(__FUNCTION__ . ":$userid:$courseid:$starttime:$endtime", function () use ($userid, $courseid, $starttime, $endtime) {
            global $DB;

            [$timesql, $params] = self::window_sql('qas.timecreated', $starttime, $endtime);
            $params['contextmodule'] = CONTEXT_MODULE;
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;

            $sql = "SELECT qa.id AS qaid, qa.maxmark, qas.fraction
                      FROM " . self::stack_slot_join_sql() . "
                      JOIN {quiz_attempts} quiza ON quiza.quiz = quiz.id
                                                 AND quiza.userid = :userid
                                                 AND quiza.state = 'finished'
                      JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                                  AND qa.slot = slot.slot
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                                                        AND qas.sequencenumber = (
                                                            SELECT MAX(s2.sequencenumber)
                                                              FROM {question_attempt_steps} s2
                                                             WHERE s2.questionattemptid = qa.id
                                                        )
                     WHERE quiz.course = :courseid
                           $timesql";

            return array_values($DB->get_records_sql($sql, $params));
        });
    }

    /**
     * Every step of every STACK question attempt this student made in this
     * course/window, ordered by attempt then sequence — the raw material for
     * the response-latency, disengagement-entropy, and feedback-revision
     * indicators, which all reason about the sequence of tries within a
     * single question attempt rather than just its final grade.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @return array keyed by question_attempts.id, each value an ordered
     *               array of stdClass step rows (sequencenumber, state,
     *               fraction, timecreated)
     */
    public static function get_attempt_step_sequences(int $userid, int $courseid, $starttime, $endtime): array {
        return self::memoize(__FUNCTION__ . ":$userid:$courseid:$starttime:$endtime", function () use ($userid, $courseid, $starttime, $endtime) {
            global $DB;

            [$timesql, $params] = self::window_sql('qas.timecreated', $starttime, $endtime);
            $params['contextmodule'] = CONTEXT_MODULE;
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;

            $sql = "SELECT qas.id AS stepid, qa.id AS qaid, qas.sequencenumber, qas.state,
                           qas.fraction, qas.timecreated
                      FROM " . self::stack_slot_join_sql() . "
                      JOIN {quiz_attempts} quiza ON quiza.quiz = quiz.id
                                                 AND quiza.userid = :userid
                      JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                                  AND qa.slot = slot.slot
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                     WHERE quiz.course = :courseid
                           $timesql
                  ORDER BY qa.id, qas.sequencenumber";

            $rows = $DB->get_records_sql($sql, $params);

            $byattempt = [];
            foreach ($rows as $row) {
                $byattempt[$row->qaid][] = $row;
            }
            return $byattempt;
        });
    }

    /**
     * The submitted response values recorded at a single step (STACK's
     * input names, e.g. 'ans1', vary per question and are not enumerated
     * here — callers compare whichever keys are present at both of two
     * consecutive steps).
     *
     * @param int $stepid a question_attempt_steps.id
     * @return array name => value
     */
    public static function get_step_response_data(int $stepid): array {
        global $DB;

        $records = $DB->get_records('question_attempt_step_data', ['attemptstepid' => $stepid], '', 'name, value');
        $data = [];
        foreach ($records as $record) {
            $data[$record->name] = $record->value;
        }
        return $data;
    }

    /**
     * Course-wide cohort of view-to-submit deltas for STACK question steps,
     * used as the comparison distribution for the response-latency-anomaly
     * indicator's z-score. Deliberately not scoped to "algebraically complex"
     * questions yet (that needs the PRT-complexity data classes/local/
     * prt_graph.php builds in Phase 3) — computed over all STACK attempts in
     * the course for now, a superset of the architecture doc's target
     * population that leaves the z-score math itself unaffected.
     *
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float[] inter-step deltas in seconds, one per step transition
     */
    public static function get_course_step_deltas(int $courseid, $starttime, $endtime): array {
        // The highest-value memoize() in this class: this specific query is
        // course-wide (no userid filter at all) and identical for every
        // student in one report, but was previously being re-run in full
        // once per student row regardless — confirmed directly as the
        // dominant cost behind model1_report::build() taking 299.4s on a
        // real 1,147-student course (see this class's own $memo docblock).
        return self::memoize(__FUNCTION__ . ":$courseid:$starttime:$endtime", function () use ($courseid, $starttime, $endtime) {
            global $DB;

            [$timesql, $params] = self::window_sql('qas.timecreated', $starttime, $endtime);
            $params['contextmodule'] = CONTEXT_MODULE;
            $params['courseid'] = $courseid;

            $sql = "SELECT qas.id AS stepid, qa.id AS qaid, qas.sequencenumber, qas.timecreated
                      FROM " . self::stack_slot_join_sql() . "
                      JOIN {quiz_attempts} quiza ON quiza.quiz = quiz.id
                      JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                                  AND qa.slot = slot.slot
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                     WHERE quiz.course = :courseid
                           $timesql
                  ORDER BY qa.id, qas.sequencenumber";

            $rows = $DB->get_records_sql($sql, $params);

            $byattempt = [];
            foreach ($rows as $row) {
                $byattempt[$row->qaid][] = (int) $row->timecreated;
            }

            $deltas = [];
            foreach ($byattempt as $timestamps) {
                for ($i = 1; $i < count($timestamps); $i++) {
                    $deltas[] = (float) ($timestamps[$i] - $timestamps[$i - 1]);
                }
            }
            return $deltas;
        });
    }

    /**
     * Same inter-step deltas as get_course_step_deltas(), scoped to one
     * student — the "raw signal" side of the response-latency-anomaly
     * indicator, compared against the course-wide cohort distribution that
     * method returns.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float[] inter-step deltas in seconds
     */
    public static function get_user_step_deltas(int $userid, int $courseid, $starttime, $endtime): array {
        return self::memoize(__FUNCTION__ . ":$userid:$courseid:$starttime:$endtime", function () use ($userid, $courseid, $starttime, $endtime) {
            global $DB;

            [$timesql, $params] = self::window_sql('qas.timecreated', $starttime, $endtime);
            $params['contextmodule'] = CONTEXT_MODULE;
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;

            $sql = "SELECT qas.id AS stepid, qa.id AS qaid, qas.sequencenumber, qas.timecreated
                      FROM " . self::stack_slot_join_sql() . "
                      JOIN {quiz_attempts} quiza ON quiza.quiz = quiz.id AND quiza.userid = :userid
                      JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                                  AND qa.slot = slot.slot
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                     WHERE quiz.course = :courseid
                           $timesql
                  ORDER BY qa.id, qas.sequencenumber";

            $rows = $DB->get_records_sql($sql, $params);

            $byattempt = [];
            foreach ($rows as $row) {
                $byattempt[$row->qaid][] = (int) $row->timecreated;
            }

            $deltas = [];
            foreach ($byattempt as $timestamps) {
                for ($i = 1; $i < count($timestamps); $i++) {
                    $deltas[] = (float) ($timestamps[$i] - $timestamps[$i - 1]);
                }
            }
            return $deltas;
        });
    }

    /** Components treated as "help resources" for the help-seeking-gap indicator. */
    const HELP_RESOURCE_COMPONENTS = ['mod_forum', 'mod_glossary', 'mod_resource', 'mod_page', 'mod_url', 'mod_book'];

    /**
     * Timestamps of STACK question steps graded below full marks (fraction
     * < 1.0) — the "recent failure" events the help-seeking-gap indicator
     * looks for a follow-up resource access after.
     *
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @param int|null $userid restrict to one student, or null for the whole course (the baseline population)
     * @return \stdClass[] each with ->userid and ->timecreated
     */
    public static function get_stack_failure_events(int $courseid, $starttime, $endtime, ?int $userid = null): array {
        // help_seeking_gap calls this with $userid left null (the whole
        // course's baseline population) for every student row it builds —
        // another instance of the redundant-course-wide-query pattern this
        // class's own $memo docblock explains; see get_course_step_deltas()
        // for the one confirmed to dominate model1_report::build()'s cost.
        return self::memoize(__FUNCTION__ . ":$courseid:$starttime:$endtime:$userid", function () use ($courseid, $starttime, $endtime, $userid) {
            global $DB;

            [$timesql, $params] = self::window_sql('qas.timecreated', $starttime, $endtime);
            $params['contextmodule'] = CONTEXT_MODULE;
            $params['courseid'] = $courseid;

            $usersql = '';
            if ($userid !== null) {
                $usersql = ' AND quiza.userid = :userid';
                $params['userid'] = $userid;
            }

            $sql = "SELECT qas.id AS stepid, quiza.userid, qas.timecreated
                      FROM " . self::stack_slot_join_sql() . "
                      JOIN {quiz_attempts} quiza ON quiza.quiz = quiz.id$usersql
                      JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                                  AND qa.slot = slot.slot
                      JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                                                        AND qas.fraction IS NOT NULL
                                                        AND qas.fraction < 1.0
                     WHERE quiz.course = :courseid
                           $timesql";

            return array_values($DB->get_records_sql($sql, $params));
        });
    }

    /**
     * Resource-access log timestamps (forum/glossary/resource/page/url/book),
     * grouped by user — the help-seeking side of the same indicator.
     *
     * @param int $courseid
     * @param int|false $starttime
     * @param int|false $endtime
     * @param int|null $userid restrict to one student, or null for the whole course
     * @return array userid => int[] timestamps, ordered
     */
    public static function get_resource_access_timestamps(int $courseid, $starttime, $endtime, ?int $userid = null): array {
        // Same as get_stack_failure_events() above — help_seeking_gap calls
        // this with $userid left null once per student row too.
        return self::memoize(__FUNCTION__ . ":$courseid:$starttime:$endtime:$userid", function () use ($courseid, $starttime, $endtime, $userid) {
            global $DB;

            [$componentsql, $params] = $DB->get_in_or_equal(self::HELP_RESOURCE_COMPONENTS, SQL_PARAMS_NAMED, 'comp');
            $params['courseid'] = $courseid;

            $usersql = '';
            if ($userid !== null) {
                $usersql = ' AND userid = :userid';
                $params['userid'] = $userid;
            }

            [$timesql, $timeparams] = self::window_sql('timecreated', $starttime, $endtime);
            $params = array_merge($params, $timeparams);

            $sql = "SELECT id, userid, timecreated
                      FROM {logstore_standard_log}
                     WHERE courseid = :courseid
                       AND component $componentsql
                           $usersql
                           $timesql
                  ORDER BY userid, timecreated";

            $rows = $DB->get_records_sql($sql, $params);

            $byuser = [];
            foreach ($rows as $row) {
                $byuser[$row->userid][] = (int) $row->timecreated;
            }
            return $byuser;
        });
    }

    /**
     * Final-step fraction for every finished attempt at one specific
     * question, within one specific quiz — Model 2's sample grain (a
     * quiz_slots row). Used by question_difficulty_irt.
     *
     * @param int $quizid
     * @param int[] $questionids every version's question.id for this slot's
     *              question bank entry (stack_course_helper::get_all_question_ids_for_entry())
     *              — editing a STACK question after it's been attempted
     *              leaves old attempts pointing at the old version's id, not
     *              the currently-resolved one, so matching only the current
     *              id would silently miss that attempt history.
     * @return float[] one fraction (0..1) per finished attempt
     */
    public static function get_slot_finished_fractions(int $quizid, array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        $sql = "SELECT qas.fraction
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                              AND qa.questionid $insql
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                                                    AND qas.sequencenumber = (
                                                        SELECT MAX(s2.sequencenumber)
                                                          FROM {question_attempt_steps} s2
                                                         WHERE s2.questionattemptid = qa.id
                                                    )
                 WHERE quiza.quiz = :quizid
                   AND quiza.state = 'finished'
                   AND qas.fraction IS NOT NULL";

        // Uses get_fieldset_sql(), not get_records_sql() — the query selects only
        // one non-unique column (many attempts legitimately share the same
        // fraction), and get_records_sql() keys its return array by the
        // first selected column regardless, silently collapsing every
        // attempt that happened to share a fraction value down to one.
        // Caught via a `debugging()` warning flood under DEBUG_DEVELOPER
        // against this plugin's own test data — 21 rows' worth of finished
        // attempts were silently shrinking to a handful of distinct
        // fraction values, corrupting question_difficulty_irt's distribution
        // math well beyond the version-join fan-out bug fixed alongside
        // this one.
        $fractions = $DB->get_fieldset_sql($sql, $params);
        return array_map(fn($fraction) => (float) $fraction, $fractions);
    }

    /**
     * Final step (state, fraction) for every attempt at one specific
     * question within one specific quiz. Used by syntax_error_rate to
     * distinguish 'invalid' (syntax/input-validation failure, a standard
     * question-engine state — see question/engine/states.php's
     * question_state_invalid, confirmed against a real Moodle 4.5 checkout)
     * from other incorrect final states (mathematical-equivalence failure).
     *
     * @param int $quizid
     * @param int[] $questionids see get_slot_finished_fractions()'s docblock for why this is a list, not one id
     * @return \stdClass[] each with ->state and ->fraction
     */
    public static function get_slot_final_states(int $quizid, array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        $sql = "SELECT qas.id, qas.state, qas.fraction
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                              AND qa.questionid $insql
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                                                    AND qas.sequencenumber = (
                                                        SELECT MAX(s2.sequencenumber)
                                                          FROM {question_attempt_steps} s2
                                                         WHERE s2.questionattemptid = qa.id
                                                    )
                 WHERE quiza.quiz = :quizid";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Each student's own sequence of finished quiz attempts at one specific
     * question within one specific quiz, ordered oldest-first — the "did
     * trying the whole quiz again help" signal feedback_ineffectiveness
     * needs.
     *
     * Originally this read *steps within a single question_attempts row*
     * instead (STACK's "interactive with multiple tries" behaviour records
     * one step per try, each with its own graded fraction, matching the
     * architecture doc's literal "attempt n, attempt n+1" framing). That
     * breaks down completely under deferred feedback — Moodle's most common
     * quiz behaviour, and the one this plugin's own test course uses —
     * where a question_attempts row has exactly one real grade (recorded on
     * its *last* step only; every earlier step is 'todo'/'complete' with a
     * null fraction, question-engine bookkeeping rather than a separate
     * graded try). Confirmed directly against this plugin's own test data:
     * every single question_attempts row had a null-then-null-then-graded
     * step shape, so the old query's "did the fraction improve between
     * consecutive steps" comparison never found a single real transition —
     * feedback_ineffectiveness returned null for literally every question
     * in the course, not because there wasn't enough data, but because the
     * signal it was looking for structurally doesn't exist under deferred
     * feedback. Under deferred feedback the real retry signal is a student
     * re-attempting the *quiz itself* after seeing their graded result, so
     * this reads across {quiz_attempts} rows (one per attempt number) for
     * the same student instead of across steps within one.
     *
     * @param int $quizid
     * @param int[] $questionids see get_slot_finished_fractions()'s docblock for why this is a list, not one id
     * @return array userid => float[] one fraction per finished attempt, ordered oldest attempt first
     */
    public static function get_slot_attempts_by_user(int $quizid, array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        $sql = "SELECT quiza.id AS quizattemptid, quiza.userid, quiza.attempt, finalstep.fraction
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                              AND qa.questionid $insql
                  JOIN {question_attempt_steps} finalstep ON finalstep.questionattemptid = qa.id
                                                          AND finalstep.sequencenumber = (
                                                              SELECT MAX(s2.sequencenumber)
                                                                FROM {question_attempt_steps} s2
                                                               WHERE s2.questionattemptid = qa.id
                                                          )
                 WHERE quiza.quiz = :quizid
                   AND quiza.state = 'finished'
                   AND finalstep.fraction IS NOT NULL
              ORDER BY quiza.userid, quiza.attempt";

        $rows = $DB->get_records_sql($sql, $params);

        $byuser = [];
        foreach ($rows as $row) {
            $byuser[(int) $row->userid][] = (float) $row->fraction;
        }
        return $byuser;
    }

    /**
     * The shared "STACK question slots in this quiz's course" join fragment
     * every method above builds on.
     *
     * Pins {question_versions} to exactly one row per slot (the referenced
     * version, or else the latest non-draft one) rather than a bare
     * `qv.questionbankentryid = qbe.id` join — a question_bank_entry
     * accumulates one row per edit, and every method above joins straight
     * on to {quiz_attempts}/{question_attempts}/{question_attempt_steps},
     * so an unfiltered join here doesn't just risk a wrong questionid (as
     * in stack_course_helper's own copy of this join) but genuinely
     * duplicates every attempt step once per accumulated version — silently
     * feeding grade_trajectory/disengagement_entropy/response_latency_anomaly/
     * feedback_revision_distance/help_seeking_gap inflated, duplicated
     * observations. Found via a `debugging()` warning flood under
     * DEBUG_DEVELOPER against this plugin's own test data (a question with 6
     * accumulated versions), not from any functional symptom under normal
     * settings. Same resolution semantics as mod_quiz's own
     * qbank_helper::get_question_structure() (confirmed by reading that
     * method directly), simplified since this plugin doesn't need that
     * method's Oracle-11.2 workaround.
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

    /**
     * Builds a "AND field BETWEEN ..." SQL fragment plus its bound
     * parameters for an optional [starttime, endtime] window, matching how
     * the Analytics API passes false rather than null for "no bound".
     *
     * @param string $field the fully-qualified column to bound
     * @param int|false $starttime
     * @param int|false $endtime
     * @return array [string $sql, array $params]
     */
    private static function window_sql(string $field, $starttime, $endtime): array {
        $sql = '';
        $params = [];
        if ($starttime) {
            $sql .= " AND $field >= :starttime";
            $params['starttime'] = $starttime;
        }
        if ($endtime) {
            $sql .= " AND $field <= :endtime";
            $params['endtime'] = $endtime;
        }
        return [$sql, $params];
    }
}
