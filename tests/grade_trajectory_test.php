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

use local_quizanalytics\stack\analytics\indicator\grade_trajectory;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the grade_trajectory indicator's pure normalization math.
 *
 * These exercise scale_to_indicator() directly with synthetic values rather
 * than through calculate_sample(), which needs a live question-engine
 * fixture — that DB-backed coverage is a Phase 7 addition (see CHANGELOG).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grade_trajectory_test extends \advanced_testcase {
    public function test_full_marks_scales_to_one(): void {
        $this->assertEqualsWithDelta(1.0, grade_trajectory::scale_to_indicator(10.0, 10.0), 0.0001);
    }

    public function test_zero_marks_scales_to_negative_one(): void {
        $this->assertEqualsWithDelta(-1.0, grade_trajectory::scale_to_indicator(0.0, 10.0), 0.0001);
    }

    public function test_half_marks_scales_to_zero(): void {
        $this->assertEqualsWithDelta(0.0, grade_trajectory::scale_to_indicator(5.0, 10.0), 0.0001);
    }

    public function test_zero_max_grade_returns_zero_rather_than_dividing_by_zero(): void {
        $this->assertEqualsWithDelta(0.0, grade_trajectory::scale_to_indicator(0.0, 0.0), 0.0001);
    }

    public function test_out_of_range_ratio_is_clipped(): void {
        // A mean grade above the max grade (e.g. extra credit) must still
        // land inside [-1, 1], not overshoot it.
        $this->assertEqualsWithDelta(1.0, grade_trajectory::scale_to_indicator(15.0, 10.0), 0.0001);
    }
}
