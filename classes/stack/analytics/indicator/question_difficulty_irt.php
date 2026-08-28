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
 * Model 2 indicator (a): question difficulty.
 *
 * Architecture doc §3.4(a) specifies a full 2-parameter logistic IRT model,
 * P(correct|θ) = c + (1-c) / (1 + e^(-a(θ-b))), jointly estimating difficulty
 * (b), discrimination (a), and every student's latent ability (θ) together.
 * That joint estimation is a genuine architectural mismatch with how Moodle
 * Analytics indicators work: calculate_sample() is called once per sample in
 * isolation (per quiz_slots row here), with no hook for a batch step that
 * fits parameters jointly across the whole item bank and student population
 * first. A true 2PL fit needs that separate offline/scheduled calibration
 * step (writing fitted a/b/c into a cache table this indicator would then
 * just read) — flagged here as a concrete Phase 6+/future-work item, not
 * silently approximated away.
 *
 * What *is* computable per-sample, without joint estimation, is the
 * classical-test-theory equivalent: this question's empirical pass rate p,
 * converted to a logit-scale difficulty under the simplifying assumptions
 * a=1, c=0, and population-average ability θ=0 — i.e. reducing the 2PL
 * formula to p = 1 / (1 + e^b), so b = ln((1-p)/p). This is the same
 * difficulty figure a 1-parameter (Rasch) model would report for an
 * average-ability examinee, and is a standard, named simplification of IRT
 * difficulty estimation, not an arbitrary substitute.
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
 * Logit-scale difficulty (b) estimated from empirical pass rate.
 */
class question_difficulty_irt extends \core_analytics\local\indicator\linear {
    /** Clip bound for the logit-scale difficulty, matching the architecture doc's [-3, 3] logit-unit range. */
    const LOGIT_CLIP = 3.0;

    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:questiondifficultyirt', 'local_quizanalytics');
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
     * b = ln((1-p)/p), clipped to [-LOGIT_CLIP, LOGIT_CLIP] before the
     * caller normalizes it — a pass rate of 0 or 1 would otherwise produce
     * an infinite logit.
     *
     * @param float $passrate in [0, 1]
     * @return float
     */
    public static function passrate_to_logit(float $passrate): float {
        $passrate = max(0.0, min(1.0, $passrate));
        if ($passrate <= 0.0) {
            return self::LOGIT_CLIP;
        }
        if ($passrate >= 1.0) {
            return -self::LOGIT_CLIP;
        }
        $logit = log((1.0 - $passrate) / $passrate);
        return max(-self::LOGIT_CLIP, min(self::LOGIT_CLIP, $logit));
    }

    /**
     * clip(b / 3, -1, 1) — architecture doc §3.4(a).
     *
     * @param float $logit
     * @return float
     */
    public static function logit_to_indicator(float $logit): float {
        return max(-1.0, min(1.0, $logit / self::LOGIT_CLIP));
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
     * for the shared contract. High indicator = harder question (worth a
     * look); low = easier than most.
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
        $fractions = stack_attempt_reader::get_slot_finished_fractions((int) $slot->quizid, $questionids);
        if (empty($fractions)) {
            return null; // No finished attempts yet — nothing to estimate difficulty from.
        }

        $passrate = array_sum($fractions) / count($fractions);
        $indicator = self::logit_to_indicator(self::passrate_to_logit($passrate));

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : ($indicator <= -0.33 ? 'good' : 'neutral'),
            'summary' => [
                'passpercent' => round(100.0 * $passrate, 0),
                'attempts' => count($fractions),
            ],
        ];
    }
}
