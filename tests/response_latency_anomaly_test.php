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

namespace local_quizanalytics;

use local_quizanalytics\stack\analytics\indicator\response_latency_anomaly;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the response_latency_anomaly indicator's pure
 * mean/stddev/z-score/normalization math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_latency_anomaly_test extends \advanced_testcase {
    public function test_mean_and_stddev_of_uniform_values_is_zero_variance(): void {
        [$mean, $stddev] = response_latency_anomaly::mean_and_stddev([10.0, 10.0, 10.0]);
        $this->assertEqualsWithDelta(10.0, $mean, 0.0001);
        $this->assertEqualsWithDelta(0.0, $stddev, 0.0001);
    }

    public function test_mean_and_stddev_known_distribution(): void {
        // Population stddev of [2, 4, 4, 4, 5, 5, 7, 9] is 2.0 (textbook example).
        [$mean, $stddev] = response_latency_anomaly::mean_and_stddev([2, 4, 4, 4, 5, 5, 7, 9]);
        $this->assertEqualsWithDelta(5.0, $mean, 0.0001);
        $this->assertEqualsWithDelta(2.0, $stddev, 0.0001);
    }

    public function test_zscore_of_mean_is_zero(): void {
        $this->assertEqualsWithDelta(0.0, response_latency_anomaly::zscore(5.0, 5.0, 2.0), 0.0001);
    }

    public function test_zscore_null_when_no_variation(): void {
        $this->assertNull(response_latency_anomaly::zscore(5.0, 5.0, 0.0));
    }

    public function test_faster_than_cohort_pushes_toward_positive_one(): void {
        // Z of -3 (three standard deviations faster than the cohort) should clip to +1, not overshoot.
        $this->assertEqualsWithDelta(1.0, response_latency_anomaly::zscore_to_indicator(-3.0), 0.0001);
    }

    public function test_slower_than_cohort_pushes_toward_negative_one(): void {
        $this->assertEqualsWithDelta(-1.0, response_latency_anomaly::zscore_to_indicator(3.0), 0.0001);
    }

    public function test_typical_timing_sits_near_zero(): void {
        $this->assertEqualsWithDelta(0.0, response_latency_anomaly::zscore_to_indicator(0.0), 0.0001);
    }
}
