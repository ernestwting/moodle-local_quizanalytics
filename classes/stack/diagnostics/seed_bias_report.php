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
 * Diagnostics dashboard: seed-bias one-way ANOVA (architecture doc §3.4e).
 *
 * Statistical/descriptive, deliberately outside the ML pipeline per the
 * doc's own triage (§3.1/Fig. 4) — there is no ground-truth label for "this
 * seed is biased", so this is reported directly rather than trained on.
 *
 * The per-attempt seed is read from question_attempt_step_data's '_seed'
 * name, set on the first step of every attempt by
 * qtype_stack\question::start_attempt() (question/type/stack/question.php)
 * — confirmed against the real qtype_stack source rather than guessed,
 * since STACK's variant/seed mechanism has no equivalent anywhere in core
 * Moodle to infer it from.
 *
 * This reports the F-statistic and η² (effect size) but deliberately does
 * not compute an exact p-value: that needs the F-distribution's CDF (the
 * regularized incomplete beta function), a numerical routine easy to get
 * subtly wrong without a reference implementation to check it against. η²
 * alone, read against Cohen's standard thresholds, is enough for the
 * dashboard's exploratory purpose — a precise p-value is better computed
 * with an actual stats package if the paper's evaluation needs one.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\diagnostics;

defined('MOODLE_INTERNAL') || die();

/**
 * One-way ANOVA of question score by STACK random seed.
 */
class seed_bias_report {
    /**
     * Groups final fractions by the STACK seed used for that attempt.
     *
     * @param int $quizid
     * @param int[] $questionids every version's question id for this slot's
     *              bank entry (stack_course_helper::get_all_question_ids_for_entry())
     *              — see stack_attempt_reader::get_slot_finished_fractions()'s
     *              docblock for why a single current-version id would miss
     *              attempts made against an earlier version
     * @return array seed => float[] fractions
     */
    public static function get_seed_score_groups(int $quizid, array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        $sql = "SELECT qa.id AS qaid, seeddata.value AS seed, finalstep.fraction
                  FROM {quiz_attempts} quiza
                  JOIN {question_attempts} qa ON qa.questionusageid = quiza.uniqueid
                                              AND qa.questionid $insql
                  JOIN {question_attempt_steps} firststep ON firststep.questionattemptid = qa.id
                                                          AND firststep.sequencenumber = 0
                  JOIN {question_attempt_step_data} seeddata ON seeddata.attemptstepid = firststep.id
                                                             AND seeddata.name = '_seed'
                  JOIN {question_attempt_steps} finalstep ON finalstep.questionattemptid = qa.id
                                                          AND finalstep.sequencenumber = (
                                                              SELECT MAX(s2.sequencenumber)
                                                                FROM {question_attempt_steps} s2
                                                               WHERE s2.questionattemptid = qa.id
                                                          )
                 WHERE quiza.quiz = :quizid
                   AND finalstep.fraction IS NOT NULL";

        $records = $DB->get_records_sql($sql, $params);

        $groups = [];
        foreach ($records as $record) {
            $groups[$record->seed][] = (float) $record->fraction;
        }
        return $groups;
    }

    /**
     * One-way ANOVA over pre-grouped samples — pure statistics, no DB
     * access, so it can be exercised directly with synthetic data.
     *
     * @param array $groups group key => float[] values
     * @return \stdClass|null null if fewer than 2 groups have data, or every
     *                        value is identical (zero total variance)
     */
    public static function anova(array $groups): ?\stdClass {
        $groups = array_filter($groups, fn($values) => !empty($values));
        $k = count($groups);
        if ($k < 2) {
            return null;
        }

        $allvalues = array_merge(...array_values($groups));
        $n = count($allvalues);
        $grandmean = array_sum($allvalues) / $n;

        $ssbetween = 0.0;
        $sswithin = 0.0;
        foreach ($groups as $values) {
            $groupmean = array_sum($values) / count($values);
            $ssbetween += count($values) * ($groupmean - $grandmean) ** 2;
            foreach ($values as $value) {
                $sswithin += ($value - $groupmean) ** 2;
            }
        }
        $sstotal = $ssbetween + $sswithin;
        if ($sstotal <= 0.0) {
            return null; // Every value identical — no variance to attribute anywhere.
        }

        $dfbetween = $k - 1;
        $dfwithin = $n - $k;

        $result = new \stdClass();
        $result->ngroups = $k;
        $result->n = $n;
        $result->dfbetween = $dfbetween;
        $result->dfwithin = $dfwithin;
        $result->etasquared = $ssbetween / $sstotal;
        $result->f = ($dfwithin > 0 && $sswithin > 0.0)
            ? ($ssbetween / $dfbetween) / ($sswithin / $dfwithin)
            : null;

        return $result;
    }

    /**
     * Cohen's conventional η² thresholds for effect size.
     *
     * @param float $etasquared
     * @return string 'negligible'|'small'|'medium'|'large'
     */
    public static function eta_squared_magnitude(float $etasquared): string {
        if ($etasquared < 0.01) {
            return 'negligible';
        }
        if ($etasquared < 0.06) {
            return 'small';
        }
        if ($etasquared < 0.14) {
            return 'medium';
        }
        return 'large';
    }
}
