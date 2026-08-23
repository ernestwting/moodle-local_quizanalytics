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

use local_quizanalytics\stack\analytics\indicator\unreached_node_ratio;
use local_quizanalytics\stack\local\stack_prt_graph;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for stack_prt_graph's pure coverage math and
 * unreached_node_ratio's normalization.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unreached_node_ratio_test extends \advanced_testcase {
    public function test_all_branches_reached_is_zero_ratio(): void {
        $this->assertEqualsWithDelta(0.0, stack_prt_graph::unreached_ratio(7, 7), 0.0001);
    }

    public function test_none_reached_is_full_ratio(): void {
        $this->assertEqualsWithDelta(1.0, stack_prt_graph::unreached_ratio(7, 0), 0.0001);
    }

    public function test_figure_5_worked_example(): void {
        // Architecture doc Figure 5: 7 nodes, 1 unreached -> ratio ~ 0.14.
        $this->assertEqualsWithDelta(1 / 7, stack_prt_graph::unreached_ratio(7, 6), 0.0001);
    }

    public function test_no_branches_returns_null(): void {
        $this->assertNull(stack_prt_graph::unreached_ratio(0, 0));
    }

    public function test_count_reached_branches_matches_answernote_substring(): void {
        $branches = [
            (object) ['nodename' => '1', 'branch' => 'T', 'answernote' => 'prt1-1-T'],
            (object) ['nodename' => '1', 'branch' => 'F', 'answernote' => 'prt1-1-F'],
        ];
        $summaries = ['ans1: x^2 | prt1-1-T'];

        $this->assertEquals(1, stack_prt_graph::count_reached_branches($branches, $summaries));
    }

    public function test_count_reached_branches_none_matched(): void {
        $branches = [
            (object) ['nodename' => '1', 'branch' => 'T', 'answernote' => 'prt1-1-T'],
        ];
        $summaries = ['ans1: x^2 | prt1-1-F'];

        $this->assertEquals(0, stack_prt_graph::count_reached_branches($branches, $summaries));
    }

    public function test_ratio_to_indicator_scales_linearly(): void {
        $this->assertEqualsWithDelta(1.0, unreached_node_ratio::ratio_to_indicator(1.0), 0.0001);
        $this->assertEqualsWithDelta(-1.0, unreached_node_ratio::ratio_to_indicator(0.0), 0.0001);
        $this->assertEqualsWithDelta(0.0, unreached_node_ratio::ratio_to_indicator(0.5), 0.0001);
    }
}
