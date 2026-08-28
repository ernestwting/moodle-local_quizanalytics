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

use local_quizanalytics\stack\diagnostics\seed_bias_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for seed_bias_report's pure one-way ANOVA math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class seed_bias_report_test extends \advanced_testcase {
    public function test_fewer_than_two_groups_returns_null(): void {
        $this->assertNull(seed_bias_report::anova(['seed1' => [1.0, 0.5]]));
        $this->assertNull(seed_bias_report::anova([]));
    }

    public function test_identical_values_everywhere_returns_null(): void {
        // Zero total variance: nothing to attribute to seed or noise.
        $this->assertNull(seed_bias_report::anova(['seed1' => [1.0, 1.0], 'seed2' => [1.0, 1.0]]));
    }

    public function test_no_between_group_difference_has_zero_eta_squared(): void {
        // Same mean (0.5) in both groups, only within-group scatter.
        $result = seed_bias_report::anova([
            'seed1' => [0.4, 0.6],
            'seed2' => [0.4, 0.6],
        ]);
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.0, $result->etasquared, 0.0001);
    }

    public function test_pure_between_group_difference_has_full_eta_squared(): void {
        // No within-group scatter at all, only a between-group mean difference.
        $result = seed_bias_report::anova([
            'seed1' => [1.0, 1.0],
            'seed2' => [0.0, 0.0],
        ]);
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(1.0, $result->etasquared, 0.0001);
        $this->assertNull($result->f); // Undefined: zero within-group variance.
    }

    public function test_known_textbook_style_grouping(): void {
        $result = seed_bias_report::anova([
            'seed1' => [2.0, 4.0, 6.0],
            'seed2' => [3.0, 5.0, 7.0],
            'seed3' => [8.0, 10.0, 12.0],
        ]);
        $this->assertNotNull($result);
        $this->assertEquals(3, $result->ngroups);
        $this->assertEquals(9, $result->n);
        $this->assertEquals(2, $result->dfbetween);
        $this->assertEquals(6, $result->dfwithin);
        $this->assertGreaterThan(0.0, $result->f);
        $this->assertGreaterThan(0.0, $result->etasquared);
        $this->assertLessThanOrEqual(1.0, $result->etasquared);
    }

    public function test_eta_squared_magnitude_thresholds(): void {
        $this->assertEquals('negligible', seed_bias_report::eta_squared_magnitude(0.005));
        $this->assertEquals('small', seed_bias_report::eta_squared_magnitude(0.03));
        $this->assertEquals('medium', seed_bias_report::eta_squared_magnitude(0.10));
        $this->assertEquals('large', seed_bias_report::eta_squared_magnitude(0.20));
    }
}
