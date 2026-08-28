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

use local_quizanalytics\stack\analytics\indicator\disengagement_entropy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the disengagement_entropy indicator's pure entropy and
 * composite-scoring math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class disengagement_entropy_test extends \advanced_testcase {
    public function test_shannon_entropy_of_certain_outcome_is_zero(): void {
        $this->assertEqualsWithDelta(0.0, disengagement_entropy::shannon_entropy([1.0, 0.0, 0.0]), 0.0001);
    }

    public function test_shannon_entropy_of_uniform_distribution_is_log2_n(): void {
        // Four equally likely bins: H = log2(4) = 2.0 bits.
        $this->assertEqualsWithDelta(2.0, disengagement_entropy::shannon_entropy([0.25, 0.25, 0.25, 0.25]), 0.0001);
    }

    public function test_normalized_gap_entropy_of_identical_gaps_is_zero(): void {
        // Every gap identical -> everything falls in one bin -> minimal (zero) entropy ratio.
        $ratio = disengagement_entropy::normalized_gap_entropy([30.0, 30.0, 30.0, 30.0], 5);
        $this->assertEqualsWithDelta(0.0, $ratio, 0.0001);
    }

    public function test_normalized_gap_entropy_of_spread_gaps_is_near_one(): void {
        $ratio = disengagement_entropy::normalized_gap_entropy([1.0, 20.0, 40.0, 60.0, 80.0], 5);
        $this->assertEqualsWithDelta(1.0, $ratio, 0.0001);
    }

    public function test_normalized_gap_entropy_needs_at_least_two_gaps(): void {
        $this->assertNull(disengagement_entropy::normalized_gap_entropy([30.0], 5));
        $this->assertNull(disengagement_entropy::normalized_gap_entropy([], 5));
    }

    public function test_composite_mechanical_and_abandoning_is_disengaged(): void {
        // Low entropy (mechanical rhythm) + high abandonment -> should push toward +1.
        $this->assertEqualsWithDelta(1.0, disengagement_entropy::composite_to_indicator(0.0, 1.0), 0.0001);
    }

    public function test_composite_varied_and_completing_is_engaged(): void {
        // High entropy (varied, human pacing) + no abandonment -> should push toward -1.
        $this->assertEqualsWithDelta(-1.0, disengagement_entropy::composite_to_indicator(1.0, 0.0), 0.0001);
    }

    public function test_composite_midpoint_is_zero(): void {
        $this->assertEqualsWithDelta(0.0, disengagement_entropy::composite_to_indicator(0.5, 0.5), 0.0001);
    }
}
