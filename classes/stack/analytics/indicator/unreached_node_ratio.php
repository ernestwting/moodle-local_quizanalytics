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
 * Model 2 indicator (c): unreached-node ratio.
 *
 * Architecture doc §3.4(c): proportion of possible PRT paths never reached
 * by any student. Normalize: 2 * unreached_ratio - 1. See
 * classes/local/stack_prt_graph.php for how "reached" is determined (matching
 * a PRT node/branch's teacher-authored answernote against attempts'
 * responsesummary, verified against the real qtype_stack source) and the
 * (nodename, branch) granularity this operates at.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\indicator;

use local_quizanalytics\stack\local\stack_course_helper;
use local_quizanalytics\stack\local\stack_prt_graph;

defined('MOODLE_INTERNAL') || die();

/**
 * How much of a question's PRT tree has never been exercised by a real attempt.
 */
class unreached_node_ratio extends \core_analytics\local\indicator\linear {
    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:unreachednoderatio', 'local_quizanalytics');
    }

    /**
     * Declares which sample-data types this indicator needs.
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return ['quiz_slots'];
    }

    /**
     * Normalizes the unreached-node ratio to the indicator's [-1, 1] range.
     *
     * @param float $unreachedratio in [0, 1]
     * @return float
     */
    public static function ratio_to_indicator(float $unreachedratio): float {
        return max(-1.0, min(1.0, 2.0 * $unreachedratio - 1.0));
    }

    /**
     * Feeds this indicator's score to the Analytics API for one sample.
     *
     * @param int $sampleid a quiz_slots.id
     * @param string $sampleorigin
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime, $endtime) {
        return self::compute_for_sample((int) $sampleid)->indicator ?? null;
    }

    /**
     * Dashboard-facing computation — see grade_trajectory::compute_for_sample()
     * for the shared contract. High indicator = a large share of this
     * question's PRT branches have never been exercised by a real attempt
     * (pruning candidates).
     *
     * @param int $sampleid a quiz_slots.id
     * @return \stdClass|null null if there isn't enough data yet
     */
    public static function compute_for_sample(int $sampleid): ?\stdClass {
        $slots = stack_course_helper::get_stack_slots([$sampleid]);
        if (empty($slots[$sampleid])) {
            return null;
        }
        $slot = $slots[$sampleid];

        $branches = stack_prt_graph::get_prt_branches((int) $slot->questionid);
        if (empty($branches)) {
            return null; // No answernoted branches to judge coverage of (e.g. a trivial single-node PRT).
        }

        $questionids = stack_course_helper::get_all_question_ids_for_entry((int) $slot->questionbankentryid);
        $summaries = stack_prt_graph::get_response_summaries((int) $slot->quizid, $questionids);
        if (empty($summaries)) {
            // No attempts recorded at all — every other Model 2 indicator
            // for this same sample would also report "not enough data" at
            // this point (their own get_slot_final_states()/
            // get_slot_finished_fractions() queries are equally unfiltered
            // by attempt state, so an empty result there means the same
            // "zero attempts" as an empty $summaries here). Without this
            // check, 0 reached out of N branches still divides out to a
            // full 1.0 "100% unreached" ratio — a false "Worth a look" flag
            // built from no real data, not a genuine coverage gap.
            return null;
        }
        $reached = stack_prt_graph::count_reached_branches($branches, $summaries);

        $ratio = stack_prt_graph::unreached_ratio(count($branches), $reached);
        if ($ratio === null) {
            return null;
        }

        $indicator = self::ratio_to_indicator($ratio);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : ($indicator <= -0.33 ? 'good' : 'neutral'),
            'summary' => [
                'unreachedcount' => count($branches) - $reached,
                'totalbranches' => count($branches),
            ],
        ];
    }
}
