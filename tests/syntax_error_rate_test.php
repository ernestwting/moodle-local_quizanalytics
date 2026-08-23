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

use local_quizanalytics\stack\analytics\indicator\syntax_error_rate;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for syntax_error_rate's pure proportion math.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class syntax_error_rate_test extends \advanced_testcase {
    public function test_no_failures_returns_null(): void {
        $this->assertNull(syntax_error_rate::proportion_to_indicator(0, 0));
    }

    public function test_all_failures_are_syntax_errors(): void {
        $this->assertEqualsWithDelta(1.0, syntax_error_rate::proportion_to_indicator(5, 5), 0.0001);
    }

    public function test_no_failures_are_syntax_errors(): void {
        $this->assertEqualsWithDelta(-1.0, syntax_error_rate::proportion_to_indicator(0, 5), 0.0001);
    }

    public function test_half_syntax_half_math_errors(): void {
        $this->assertEqualsWithDelta(0.0, syntax_error_rate::proportion_to_indicator(5, 10), 0.0001);
    }
}
