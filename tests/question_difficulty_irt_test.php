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

use local_quizanalytics\stack\analytics\indicator\question_difficulty_irt;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for question_difficulty_irt's pure logit-difficulty math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_difficulty_irt_test extends \advanced_testcase {
    public function test_half_passrate_has_zero_logit(): void {
        $this->assertEqualsWithDelta(0.0, question_difficulty_irt::passrate_to_logit(0.5), 0.0001);
    }

    public function test_low_passrate_is_a_hard_question(): void {
        // P = 1/(1+e^b), so b = ln((1-p)/p); a low pass rate should give a large positive logit (hard).
        $logit = question_difficulty_irt::passrate_to_logit(0.1);
        $this->assertGreaterThan(0.0, $logit);
    }

    public function test_high_passrate_is_an_easy_question(): void {
        $logit = question_difficulty_irt::passrate_to_logit(0.9);
        $this->assertLessThan(0.0, $logit);
    }

    public function test_zero_passrate_clips_rather_than_diverging(): void {
        $this->assertEqualsWithDelta(3.0, question_difficulty_irt::passrate_to_logit(0.0), 0.0001);
    }

    public function test_full_passrate_clips_rather_than_diverging(): void {
        $this->assertEqualsWithDelta(-3.0, question_difficulty_irt::passrate_to_logit(1.0), 0.0001);
    }

    public function test_logit_to_indicator_scales_linearly_within_clip(): void {
        $this->assertEqualsWithDelta(1.0, question_difficulty_irt::logit_to_indicator(3.0), 0.0001);
        $this->assertEqualsWithDelta(-1.0, question_difficulty_irt::logit_to_indicator(-3.0), 0.0001);
        $this->assertEqualsWithDelta(0.0, question_difficulty_irt::logit_to_indicator(0.0), 0.0001);
    }
}
