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
 * Model 1 indicator (b): anomalous response latency.
 *
 * Architecture doc §2.2(b) — deliberately named for what it actually is, an
 * anomaly flag, not "cheating": a correlational anomaly, not evidence of
 * misconduct (see §7's ethics note). z = (Δt_student_mean − μ_cohort) /
 * σ_cohort, normalized via clip(-z / 3, -1, 1) so implausibly fast answers
 * (z < 0, i.e. faster than cohort) push toward +1.
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
 * Cohort-relative z-score of a student's own inter-step response timing.
 */
class response_latency_anomaly extends \core_analytics\local\indicator\linear {
    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:responselatencyanomaly', 'local_quizanalytics');
    }

    /**
     * Declares which sample-data types this indicator needs.
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return ['user_enrolments'];
    }

    /**
     * Computes the population mean and standard deviation of a list of values.
     *
     * @param float[] $values
     * @return array [float $mean, float $stddev] (population stddev; 0.0 if fewer than 2 values)
     */
    public static function mean_and_stddev(array $values): array {
        $n = count($values);
        if ($n === 0) {
            return [0.0, 0.0];
        }
        $mean = array_sum($values) / $n;
        if ($n < 2) {
            return [$mean, 0.0];
        }
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= $n;
        return [$mean, sqrt($variance)];
    }

    /**
     * Computes how many standard deviations a value sits from the cohort mean.
     *
     * @param float $value
     * @param float $mean
     * @param float $stddev
     * @return float|null null if stddev is 0 (no variation to compare against)
     */
    public static function zscore(float $value, float $mean, float $stddev): ?float {
        if ($stddev <= 0.0) {
            return null;
        }
        return ($value - $mean) / $stddev;
    }

    /**
     * clip(-z / 3, -1, 1) — faster-than-cohort (negative z) pushes toward +1.
     *
     * @param float $z
     * @return float
     */
    public static function zscore_to_indicator(float $z): float {
        return max(-1.0, min(1.0, -$z / 3.0));
    }

    /**
     * Feeds this indicator's score to the Analytics API for one sample.
     *
     * @param int $sampleid a user_enrolments.id
     * @param string $sampleorigin
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime, $endtime) {
        return self::compute_for_sample((int) $sampleid, $starttime, $endtime)->indicator ?? null;
    }

    /**
     * Dashboard-facing computation — see grade_trajectory::compute_for_sample()
     * for the shared contract. Only the "implausibly fast" direction is ever
     * flagged (see class docblock): a slower-than-cohort student isn't
     * anomalous by this measure, so 'good' is never returned here, only
     * 'watch' (anomalously fast) or 'neutral'.
     *
     * @param int $sampleid a user_enrolments.id
     * @param int|false $starttime
     * @param int|false $endtime
     * @return \stdClass|null null if there isn't enough data yet
     */
    public static function compute_for_sample(int $sampleid, $starttime = false, $endtime = false): ?\stdClass {
        $enrolment = stack_course_helper::get_enrolment_user_and_course($sampleid);
        if (!$enrolment) {
            return null;
        }

        $cohortdeltas = stack_attempt_reader::get_course_step_deltas(
            (int) $enrolment->courseid,
            $starttime,
            $endtime
        );
        $userdeltas = stack_attempt_reader::get_user_step_deltas(
            (int) $enrolment->userid,
            (int) $enrolment->courseid,
            $starttime,
            $endtime
        );

        if (empty($cohortdeltas) || empty($userdeltas)) {
            return null; // Not enough activity yet to compare against a cohort.
        }

        [$cohortmean, $cohortstddev] = self::mean_and_stddev($cohortdeltas);
        $usermean = array_sum($userdeltas) / count($userdeltas);

        $z = self::zscore($usermean, $cohortmean, $cohortstddev);
        if ($z === null) {
            return null; // No variation in the cohort to compare against (e.g. a single-attempt course).
        }

        $indicator = self::zscore_to_indicator($z);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : 'neutral',
            'summary' => [
                'userseconds' => round($usermean, 1),
                'cohortseconds' => round($cohortmean, 1),
            ],
        ];
    }
}
