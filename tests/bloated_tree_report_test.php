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

use local_quizanalytics\stack\diagnostics\bloated_tree_report;
use local_quizanalytics\stack\local\stack_prt_graph;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for bloated_tree_report's pure classification logic and
 * stack_prt_graph::count_branch_occurrences().
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bloated_tree_report_test extends \advanced_testcase {
    public function test_zero_traversals_is_unreached(): void {
        $this->assertEquals('unreached', bloated_tree_report::classify(0));
    }

    public function test_one_traversal_is_low_traffic(): void {
        $this->assertEquals('low_traffic', bloated_tree_report::classify(1));
    }

    public function test_many_traversals_is_adequate(): void {
        $this->assertEquals('adequate', bloated_tree_report::classify(21));
    }

    public function test_count_branch_occurrences(): void {
        $branch = (object) ['nodename' => '7', 'branch' => 'F', 'answernote' => 'prt1-7-F'];
        $summaries = ['x | prt1-7-F', 'y | prt1-7-T', 'z | prt1-7-F'];

        $this->assertEquals(2, stack_prt_graph::count_branch_occurrences($branch, $summaries));
    }

    public function test_count_branch_occurrences_zero_when_never_seen(): void {
        $branch = (object) ['nodename' => '7', 'branch' => 'F', 'answernote' => 'prt1-7-F'];
        $summaries = ['y | prt1-7-T'];

        $this->assertEquals(0, stack_prt_graph::count_branch_occurrences($branch, $summaries));
    }
}
