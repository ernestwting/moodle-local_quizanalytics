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

/**
 * Diagnostics dashboard: bloated-PRT-tree report (architecture doc §3.4f).
 *
 * Same underlying graph representation as the unreached_node_ratio
 * indicator (classes/local/stack_prt_graph.php), but reported per-branch as
 * a maintenance dashboard metric rather than folded into a single [-1, 1]
 * ML feature — descriptive analytics, not a trained target, per the doc's
 * own triage (§3.1). Distinguishes "never reached" (a pruning candidate)
 * from "reached, but rarely" (Figure 5's Node 6 — needs a human judgment
 * call, not an automatic prune) using a configurable traversal-count floor.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\diagnostics;

use local_quizanalytics\stack\local\stack_prt_graph;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-branch traversal coverage for a question's PRTs.
 */
class bloated_tree_report {
    /**
     * Below this many observed traversals (but above zero), a branch is
     * "low-traffic" rather than "unreached". Default used when the
     * local_quizanalytics/lowtrafficfloor admin setting (Phase 6) is unset.
     */
    const LOW_TRAFFIC_FLOOR = 2;

    /**
     * Gets the branch-occurrence floor below which coverage counts as "low traffic".
     *
     * @return int the admin-configured floor, or LOW_TRAFFIC_FLOOR if unset
     */
    public static function get_low_traffic_floor(): int {
        $configured = get_config('local_quizanalytics', 'lowtrafficfloor');
        return $configured !== false && $configured !== '' ? (int) $configured : self::LOW_TRAFFIC_FLOOR;
    }

    /**
     * Classifies a branch's observed occurrence count into a coverage band.
     *
     * @param int $count
     * @return string 'unreached'|'low_traffic'|'adequate'
     */
    public static function classify(int $count): string {
        if ($count === 0) {
            return 'unreached';
        }
        if ($count < self::get_low_traffic_floor()) {
            return 'low_traffic';
        }
        return 'adequate';
    }

    /**
     * Per-branch traversal counts and classification for one question
     * within one quiz (Model 2's sample grain).
     *
     * @param int $quizid
     * @param int $questionid the *current* version's question id — used only to
     *            look up the PRT structure as authored right now
     * @param int[] $questionids every version's question id for this slot's
     *              bank entry (stack_course_helper::get_all_question_ids_for_entry())
     *              — used to find attempt history however old; see
     *              stack_attempt_reader::get_slot_finished_fractions()'s
     *              docblock for why a single current-version id would miss
     *              attempts made against an earlier version
     * @return \stdClass[] each with ->nodename, ->branch, ->answernote, ->count, ->classification
     */
    public static function build_report(int $quizid, int $questionid, array $questionids): array {
        $branches = stack_prt_graph::get_prt_branches($questionid);
        if (empty($branches)) {
            return [];
        }

        $summaries = stack_prt_graph::get_response_summaries($quizid, $questionids);
        if (empty($summaries)) {
            // No attempts recorded at all — every branch would classify()
            // as 'unreached' (occurrence count 0), which is a real coverage
            // gap when there's attempt data to have missed a branch in, but
            // a "no data yet" case rather than a diagnosis when there's no
            // attempt data at all. Same reasoning as
            // unreached_node_ratio::compute_for_sample()'s identical guard.
            return [];
        }

        $report = [];
        foreach ($branches as $branch) {
            $count = stack_prt_graph::count_branch_occurrences($branch, $summaries);
            $report[] = (object) [
                'nodename'       => $branch->nodename,
                'branch'         => $branch->branch,
                'answernote'     => $branch->answernote,
                'count'          => $count,
                'classification' => self::classify($count),
            ];
        }
        return $report;
    }
}
