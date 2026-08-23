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
 * Builds the dashboard's Diagnostics section: one row per STACK quiz slot,
 * combining seed_bias_report/bloated_tree_report's existing raw results with
 * a plain-language 'good' / 'neutral' / 'watch' band for each — the same
 * badge convention the Model 1/2 indicators use — so the default view is a
 * scannable summary rather than a raw statistics table per question.
 *
 * The banding logic lives here rather than in seed_bias_report.php/
 * bloated_tree_report.php themselves: those two classes are pure
 * statistics/graph-analysis with their own existing pure-math test coverage,
 * and "is this reading worth a teacher's attention" is a presentation
 * judgement, not part of what either class computes.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\report;

use local_quizanalytics\stack\local\stack_course_helper;
use local_quizanalytics\stack\diagnostics\seed_bias_report;
use local_quizanalytics\stack\diagnostics\bloated_tree_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Assembles one row per STACK quiz slot for the Diagnostics dashboard section.
 */
class diagnostics_report {
    /** Soft cap on rows built, matching model1_report::MAX_ROWS/model2_report::MAX_ROWS. */
    const MAX_ROWS = 100;

    /**
     * Builds one row per STACK quiz slot for the Diagnostics section.
     *
     * @param int $courseid
     * @param int|null $quizid restrict to one quiz, or null for every quiz in the course
     * @return \stdClass {rows: \stdClass[], total: int, truncated: bool}
     */
    public static function build(int $courseid, ?int $quizid = null): \stdClass {
        global $DB;

        $slots = stack_course_helper::get_course_stack_slots($courseid);
        if ($quizid !== null) {
            $slots = array_filter($slots, fn($slot) => (int) $slot->quizid === $quizid);
        }

        $total = count($slots);
        $truncated = $total > self::MAX_ROWS;
        $slots = array_slice($slots, 0, self::MAX_ROWS, true);

        if (empty($slots)) {
            return (object) ['rows' => [], 'total' => $total, 'truncated' => $truncated];
        }

        $quiznames = $DB->get_records_menu('quiz', ['course' => $courseid], '', 'id, name');
        $questionids = array_unique(array_map(fn($slot) => (int) $slot->questionid, $slots));
        $questionnames = array_map(
            fn($question) => $question->name,
            $DB->get_records_list('question', 'id', $questionids, '', 'id, name')
        );

        $rows = [];
        foreach ($slots as $slot) {
            $allquestionids = stack_course_helper::get_all_question_ids_for_entry((int) $slot->questionbankentryid);
            $rows[] = (object) [
                'slotid' => (int) $slot->id,
                'questionname' => $questionnames[$slot->questionid] ?? get_string('unknownquestion', 'local_quizanalytics'),
                'quizname' => $quiznames[$slot->quizid] ?? get_string('unknownquiz', 'local_quizanalytics'),
                'seedbias' => self::build_seed_bias((int) $slot->quizid, $allquestionids),
                'bloatedtree' => self::build_bloated_tree((int) $slot->quizid, (int) $slot->questionid, $allquestionids),
            ];
        }

        return (object) ['rows' => $rows, 'total' => $total, 'truncated' => $truncated];
    }

    /**
     * The seed-bias ANOVA for one question, plus a band derived from its effect size.
     *
     * @param int $quizid
     * @param int[] $questionids every version's question id for this slot's bank entry
     * @return \stdClass|null {anova: \stdClass, band: string} — null if there's not enough data for an ANOVA
     */
    private static function build_seed_bias(int $quizid, array $questionids): ?\stdClass {
        $seedgroups = seed_bias_report::get_seed_score_groups($quizid, $questionids);
        $anova = seed_bias_report::anova($seedgroups);
        if ($anova === null) {
            return null;
        }

        $magnitude = seed_bias_report::eta_squared_magnitude($anova->etasquared);
        // Large effect is the one worth flagging; negligible/small/medium aren't.
        $band = match ($magnitude) {
            'large' => 'watch',
            'medium' => 'neutral',
            default => 'good',
        };

        return (object) ['anova' => $anova, 'magnitude' => $magnitude, 'band' => $band];
    }

    /**
     * The PRT branch-coverage report for one question, plus a band derived from its unreached/low-traffic counts.
     *
     * @param int $quizid
     * @param int $questionid the *current* version's question id (PRT structure lookup)
     * @param int[] $questionids every version's question id for this slot's bank entry (attempt-history lookup)
     * @return \stdClass|null {branches: \stdClass[], unreachedcount: int, lowtrafficcount: int, band: string}
     *         — null if there are no answernoted branches to judge coverage of
     */
    private static function build_bloated_tree(int $quizid, int $questionid, array $questionids): ?\stdClass {
        $branches = bloated_tree_report::build_report($quizid, $questionid, $questionids);
        if (empty($branches)) {
            return null;
        }

        $unreachedcount = count(array_filter($branches, fn($b) => $b->classification === 'unreached'));
        $lowtrafficcount = count(array_filter($branches, fn($b) => $b->classification === 'low_traffic'));
        $band = $unreachedcount > 0 ? 'watch' : ($lowtrafficcount > 0 ? 'neutral' : 'good');

        return (object) [
            'branches' => $branches,
            'unreachedcount' => $unreachedcount,
            'lowtrafficcount' => $lowtrafficcount,
            'totalbranches' => count($branches),
            'band' => $band,
        ];
    }
}
