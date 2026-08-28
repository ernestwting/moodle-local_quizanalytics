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
 * Model 2 indicator (d): feedback ineffectiveness.
 *
 * Architecture doc §3.4(d) specifies, per PRT branch, Δsuccess =
 * P(correct|attempt n+1) - P(correct|attempt n) conditioned on having
 * received that specific branch's feedback, tested for significance via
 * McNemar's test and normalized via the (clipped) log-odds ratio of the
 * effect size.
 *
 * This implementation is a deliberately coarser, documented simplification:
 * rather than attributing each transition to a specific branch (which would
 * need per-step responsesummary history — STACK/Moodle's question engine
 * only keeps responsesummary as the attempt's *current* value, not a
 * per-step history, so a specific-branch attribution isn't observable this
 * way — see stack_prt_graph's docblock for the same responsesummary
 * constraint), this measures, across every student's consecutive tries at
 * this question: P(correct on try n+1 | incorrect on try n), the empirical
 * "does feedback of any kind help" rate, and compares it via log-odds ratio
 * against this question's own first-try pass rate as the no-feedback
 * baseline. A full per-branch paired-McNemar version is flagged as a
 * refinement for future work, not silently pretended away — see the
 * project's CHANGELOG for this phase.
 *
 * "Try n" here means one of a student's own *quiz attempts* (a
 * {quiz_attempts} row), not a step within a single attempt — this was
 * originally read the other way (consecutive steps of one
 * {question_attempts} row), matching how STACK's "interactive with
 * multiple tries" behaviour would record it, but that reading returns no
 * signal at all under deferred feedback (Moodle's most common quiz
 * behaviour): a question_attempts row only carries one real graded
 * fraction there, on its last step, with every earlier step recording
 * 'todo'/'complete' bookkeeping rather than a separately-graded try.
 * Confirmed directly against this plugin's own test course, whose quizzes
 * all use deferred feedback: every attempt had exactly that null-then-
 * graded step shape, so the old within-attempt reading found zero
 * transitions to measure on any question. Reading across a student's own
 * repeated quiz attempts instead works under any behaviour, and matches
 * what a student re-attempting a deferred-feedback quiz actually
 * experiences: submit, see the graded result, try the whole quiz again.
 *
 * Normalize: clip(log_odds_ratio / 3, -1, 1), matching the doc's "effect
 * size clipped to a reasonable range" instruction and the same clip width
 * used by question_difficulty_irt's logit scale.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\indicator;

use local_quizanalytics\stack\local\stack_attempt_reader;
use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Log-odds effect size of "did the next try improve after an incorrect one",
 * relative to this question's first-try (no-feedback-yet) baseline.
 */
class feedback_ineffectiveness extends \core_analytics\local\indicator\linear {
    /** Clip bound for the log-odds ratio, matching the logit-scale clip used elsewhere in Model 2. */
    const LOG_ODDS_CLIP = 3.0;

    /** Odds are undefined at exactly 0 or 1; clamp rates into (epsilon, 1 - epsilon) first. */
    const RATE_EPSILON = 0.01;

    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:feedbackineffectiveness', 'local_quizanalytics');
    }

    /**
     * Declares which sample-data types this indicator needs.
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return ['quiz_slots'];
    }

    /**
     * Converts a proportion to its odds, clamped away from 0/1 to keep the result finite.
     *
     * @param float $rate in [0, 1]
     * @return float odds, always finite and positive
     */
    public static function rate_to_odds(float $rate): float {
        $rate = max(self::RATE_EPSILON, min(1.0 - self::RATE_EPSILON, $rate));
        return $rate / (1.0 - $rate);
    }

    /**
     * log(odds(p_afterfeedback) / odds(p_baseline)), clipped and scaled to
     * [-1, 1] — positive means feedback correlates with more improvement
     * than the no-feedback baseline (effective feedback); negative means
     * less (ineffective feedback).
     *
     * @param float $improverate P(correct on try n+1 | incorrect on try n)
     * @param float $baselinerate first-try pass rate
     * @return float
     */
    public static function log_odds_to_indicator(float $improverate, float $baselinerate): float {
        $logodds = log(self::rate_to_odds($improverate) / self::rate_to_odds($baselinerate));
        $clipped = max(-self::LOG_ODDS_CLIP, min(self::LOG_ODDS_CLIP, $logodds));
        return $clipped / self::LOG_ODDS_CLIP;
    }

    /**
     * Feeds this indicator's score to the Analytics API for one sample.
     *
     * @param int $sampleid a quiz_slots.id
     * @param string $sampleorigin
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime, $endtime) {
        return self::compute_for_sample((int) $sampleid)->indicator ?? null;
    }

    /**
     * Dashboard-facing computation — see grade_trajectory::compute_for_sample()
     * for the shared contract. High indicator = students who get this wrong
     * tend to improve on their next try more than the question's own
     * first-try baseline (feedback is working); low = the opposite.
     *
     * @param int $sampleid a quiz_slots.id
     * @return \stdClass|null null if there isn't enough data yet
     */
    public static function compute_for_sample(int $sampleid): ?\stdClass {
        $slots = stack_course_helper::get_stack_slots([$sampleid]);
        if (empty($slots[$sampleid])) {
            return null;
        }
        $slot = $slots[$sampleid];

        $questionids = stack_course_helper::get_all_question_ids_for_entry((int) $slot->questionbankentryid);
        $byuser = stack_attempt_reader::get_slot_attempts_by_user((int) $slot->quizid, $questionids);
        if (empty($byuser)) {
            return null;
        }

        $improved = 0;
        $incorrecttries = 0;
        $firstcorrect = 0;
        $firsttotal = 0;

        foreach ($byuser as $fractions) {
            if (empty($fractions)) {
                continue;
            }
            $firsttotal++;
            if ($fractions[0] >= 1.0) {
                $firstcorrect++;
            }

            for ($i = 1; $i < count($fractions); $i++) {
                if ($fractions[$i - 1] >= 1.0) {
                    continue; // Already correct on the previous attempt — nothing to "retry" from.
                }
                $incorrecttries++;
                if ($fractions[$i] >= 1.0) {
                    $improved++;
                }
            }
        }

        if ($incorrecttries === 0 || $firsttotal === 0) {
            // Not enough data yet: either everyone got it right on their
            // first attempt, or nobody re-attempted the quiz.
            return null;
        }

        $improverate = $improved / $incorrecttries;
        $baselinerate = $firstcorrect / $firsttotal;
        $indicator = self::log_odds_to_indicator($improverate, $baselinerate);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator <= -0.33 ? 'watch' : ($indicator >= 0.33 ? 'good' : 'neutral'),
            'summary' => [
                'improvepercent' => round(100.0 * $improverate, 0),
                'baselinepercent' => round(100.0 * $baselinerate, 0),
            ],
        ];
    }
}
