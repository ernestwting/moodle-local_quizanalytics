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

use local_quizanalytics\stack\analytics\indicator\help_seeking_gap;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the help_seeking_gap indicator's pure conditional-rate and
 * normalization math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class help_seeking_gap_test extends \advanced_testcase {
    public function test_no_failures_returns_null(): void {
        $this->assertNull(help_seeking_gap::conditional_access_rate([], [1000], 3600));
    }

    public function test_every_failure_followed_by_access_is_full_rate(): void {
        $failures = [1000, 5000];
        $access = [1500, 5300];
        $this->assertEqualsWithDelta(1.0, help_seeking_gap::conditional_access_rate($failures, $access, 3600), 0.0001);
    }

    public function test_access_outside_lookback_window_does_not_count(): void {
        $failures = [1000];
        $access = [1000 + 7200]; // Two hours later, outside a one-hour lookback.
        $this->assertEqualsWithDelta(0.0, help_seeking_gap::conditional_access_rate($failures, $access, 3600), 0.0001);
    }

    public function test_access_before_failure_does_not_count(): void {
        $failures = [1000];
        $access = [500]; // Before the failure happened.
        $this->assertEqualsWithDelta(0.0, help_seeking_gap::conditional_access_rate($failures, $access, 3600), 0.0001);
    }

    public function test_partial_match_rate(): void {
        $failures = [1000, 2000, 3000, 4000];
        $access = [1100]; // Only the first failure has a follow-up access.
        $this->assertEqualsWithDelta(0.25, help_seeking_gap::conditional_access_rate($failures, $access, 3600), 0.0001);
    }

    public function test_at_baseline_rate_indicator_is_zero(): void {
        $this->assertEqualsWithDelta(0.0, help_seeking_gap::conditional_probability_to_indicator(0.4, 0.4), 0.0001);
    }

    public function test_double_baseline_rate_clips_to_one(): void {
        $this->assertEqualsWithDelta(1.0, help_seeking_gap::conditional_probability_to_indicator(0.8, 0.4), 0.0001);
    }

    public function test_far_above_double_baseline_still_clips_to_one(): void {
        $this->assertEqualsWithDelta(1.0, help_seeking_gap::conditional_probability_to_indicator(1.0, 0.1), 0.0001);
    }

    public function test_zero_rate_at_positive_baseline_is_negative_one(): void {
        $this->assertEqualsWithDelta(-1.0, help_seeking_gap::conditional_probability_to_indicator(0.0, 0.4), 0.0001);
    }

    public function test_zero_baseline_with_no_student_access_is_zero(): void {
        $this->assertEqualsWithDelta(0.0, help_seeking_gap::conditional_probability_to_indicator(0.0, 0.0), 0.0001);
    }

    public function test_zero_baseline_with_student_access_is_one(): void {
        $this->assertEqualsWithDelta(1.0, help_seeking_gap::conditional_probability_to_indicator(0.5, 0.0), 0.0001);
    }
}
