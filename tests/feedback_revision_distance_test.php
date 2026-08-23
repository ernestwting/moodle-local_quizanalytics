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

use local_quizanalytics\stack\analytics\indicator\feedback_revision_distance;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the feedback_revision_distance indicator's pure edit-distance
 * and normalization math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_revision_distance_test extends \advanced_testcase {
    public function test_identical_strings_have_zero_distance(): void {
        $this->assertEqualsWithDelta(0.0, feedback_revision_distance::normalized_edit_distance('x^2+1', 'x^2+1'), 0.0001);
    }

    public function test_both_empty_strings_have_zero_distance(): void {
        $this->assertEqualsWithDelta(0.0, feedback_revision_distance::normalized_edit_distance('', ''), 0.0001);
    }

    public function test_completely_different_same_length_strings(): void {
        // Edit distance 2 over max length 2 normalizes to 1.0.
        $this->assertEqualsWithDelta(1.0, feedback_revision_distance::normalized_edit_distance('ab', 'cd'), 0.0001);
    }

    public function test_single_character_change(): void {
        // Edit distance 1 over max length 3.
        $this->assertEqualsWithDelta(1 / 3, feedback_revision_distance::normalized_edit_distance('x^2', 'x^3'), 0.0001);
    }

    public function test_no_revision_scales_to_positive_one(): void {
        $this->assertEqualsWithDelta(1.0, feedback_revision_distance::distance_to_indicator(0.0), 0.0001);
    }

    public function test_complete_revision_scales_to_negative_one(): void {
        $this->assertEqualsWithDelta(-1.0, feedback_revision_distance::distance_to_indicator(1.0), 0.0001);
    }

    public function test_half_revision_scales_to_zero(): void {
        $this->assertEqualsWithDelta(0.0, feedback_revision_distance::distance_to_indicator(0.5), 0.0001);
    }

    public function test_response_keys_exclude_behavior_internal_prefixes(): void {
        $this->assertTrue(feedback_revision_distance::is_response_key('ans1'));
        $this->assertFalse(feedback_revision_distance::is_response_key('-submit'));
        $this->assertFalse(feedback_revision_distance::is_response_key('_order'));
        $this->assertFalse(feedback_revision_distance::is_response_key(''));
    }
}
