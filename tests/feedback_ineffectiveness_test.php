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

use local_quizanalytics\stack\analytics\indicator\feedback_ineffectiveness;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for feedback_ineffectiveness's pure log-odds math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_ineffectiveness_test extends \advanced_testcase {
    public function test_equal_rates_have_zero_effect(): void {
        $this->assertEqualsWithDelta(0.0, feedback_ineffectiveness::log_odds_to_indicator(0.5, 0.5), 0.0001);
    }

    public function test_improve_rate_above_baseline_is_positive(): void {
        $indicator = feedback_ineffectiveness::log_odds_to_indicator(0.9, 0.5);
        $this->assertGreaterThan(0.0, $indicator);
    }

    public function test_improve_rate_below_baseline_is_negative(): void {
        $indicator = feedback_ineffectiveness::log_odds_to_indicator(0.1, 0.5);
        $this->assertLessThan(0.0, $indicator);
    }

    public function test_extreme_rates_clip_to_bounds(): void {
        $this->assertEqualsWithDelta(1.0, feedback_ineffectiveness::log_odds_to_indicator(1.0, 0.0), 0.0001);
        $this->assertEqualsWithDelta(-1.0, feedback_ineffectiveness::log_odds_to_indicator(0.0, 1.0), 0.0001);
    }

    public function test_rate_to_odds_of_half_is_one(): void {
        $this->assertEqualsWithDelta(1.0, feedback_ineffectiveness::rate_to_odds(0.5), 0.0001);
    }

    public function test_rate_to_odds_clamps_extremes(): void {
        // Rate of exactly 0 or 1 would otherwise give odds of 0 or infinity.
        $this->assertGreaterThan(0.0, feedback_ineffectiveness::rate_to_odds(0.0));
        $this->assertLessThan(INF, feedback_ineffectiveness::rate_to_odds(1.0));
    }
}
