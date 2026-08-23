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
 * PRT (Potential Response Tree) structure and branch-coverage helpers shared
 * by Model 2's unreached_node_ratio indicator and the diagnostics dashboard's
 * bloated-tree report (architecture doc §3.4c / §3.4f).
 *
 * Verified directly against the real qtype_stack plugin source (question/type/stack
 * in a Moodle 4.5 checkout), since this is the one piece of the whole build
 * with no Moodle-core precedent to lean on:
 *
 * - `qtype_stack_prt_nodes` (question/type/stack/db/install.xml) has one row
 *   per node per PRT, with `truenextnode`/`falsenextnode` (the tree edges,
 *   a null/empty value meaning "this branch ends the PRT") and, critically,
 *   `trueanswernote`/`falseanswernote` — a teacher-authored string appended to
 *   a running list whenever that branch is taken.
 * - `qtype_stack\question::summarise_response()` (question/type/stack/question.php,
 *   ~line 887) builds Moodle's standard `question_attempts.responsesummary`
 *   field as `implode(' | ', $state->get_answernotes())` — i.e. those same
 *   answernote strings, for every PRT the question has. This is the same
 *   `responsesummary` column every question type populates, not a
 *   STACK-specific table, so reading it needs no extra qtype_stack API calls.
 *
 * That means a branch's reach is observable, after the fact, purely from
 * `question_attempts.responsesummary`: a branch was reached by some attempt
 * if-and-only-if its answernote string appears in that attempt's summary.
 * "Node" is therefore represented here at (nodename, true-or-false-branch)
 * granularity — each qtype_stack_prt_nodes row contributes up to two graph
 * nodes to V, matching the individually-numbered boxes in the architecture
 * doc's Figure 5 worked example more closely than the coarser "one row = one
 * node" reading would.
 *
 * Known limitation, stated plainly rather than silently swallowed: a branch
 * whose answernote is left blank (allowed by the schema) cannot be matched
 * against any responsesummary and is excluded from both V and the reached
 * count — such branches simply cannot be observed this way. This should be
 * rare in practice (STACK's own authoring UI encourages a note per branch for
 * exactly the teacher-facing reporting this indicator now reuses), but it is
 * a real edge case, not a hypothetical one, and is worth a mention in the
 * paper's limitations section alongside the other proxy-label caveats.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * PRT branch enumeration and coverage.
 */
class stack_prt_graph {
    /**
     * Pure ratio calculation — architecture doc §3.4c:
     * unreached_ratio = |V_unreached| / |V|.
     *
     * @param int $totalbranches |V|
     * @param int $reachedbranches
     * @return float|null null if there are no branches to judge coverage of
     */
    public static function unreached_ratio(int $totalbranches, int $reachedbranches): ?float {
        if ($totalbranches <= 0) {
            return null;
        }
        $reachedbranches = min($reachedbranches, $totalbranches);
        return 1.0 - ($reachedbranches / $totalbranches);
    }

    /**
     * Every observable (nodename, branch) pair for a question's PRTs, with
     * the answernote that identifies it in a responsesummary. Branches with
     * a blank answernote are omitted (see class docblock).
     *
     * @param int $questionid
     * @return \stdClass[] each with ->nodename, ->prtname, ->branch ('T'|'F'), ->answernote
     */
    public static function get_prt_branches(int $questionid): array {
        global $DB;

        $nodes = $DB->get_records('qtype_stack_prt_nodes', ['questionid' => $questionid]);

        $branches = [];
        foreach ($nodes as $node) {
            if (trim((string) $node->trueanswernote) !== '') {
                $branches[] = (object) [
                    'nodename'   => $node->nodename,
                    'prtname'    => $node->prtname,
                    'branch'     => 'T',
                    'answernote' => trim($node->trueanswernote),
                ];
            }
            if (trim((string) $node->falseanswernote) !== '') {
                $branches[] = (object) [
                    'nodename'   => $node->nodename,
                    'prtname'    => $node->prtname,
                    'branch'     => 'F',
                    'answernote' => trim($node->falseanswernote),
                ];
            }
        }
        return $branches;
    }

    /**
     * Response summaries for every attempt at a specific question within a
     * specific quiz (Model 2's sample grain — see stack_question_analyser's
     * docblock for why quiz-scoped rather than question-global).
     *
     * @param int $quizid
     * @param int[] $questionids every version's question.id for this slot's
     *              question bank entry (stack_course_helper::get_all_question_ids_for_entry())
     *              — see stack_attempt_reader::get_slot_finished_fractions()'s
     *              docblock for why a single current-version id would miss
     *              attempts made against an earlier version.
     * @return string[] one responsesummary per attempt (empty ones included; harmless for substring matching)
     */
    public static function get_response_summaries(int $quizid, array $questionids): array {
        global $DB;

        if (empty($questionids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;

        $sql = "SELECT qa.id, qa.responsesummary
                  FROM {question_attempts} qa
                  JOIN {quiz_attempts} quiza ON quiza.uniqueid = qa.questionusageid
                 WHERE quiza.quiz = :quizid
                   AND qa.questionid $insql";

        $records = $DB->get_records_sql($sql, $params);
        return array_map(fn($record) => (string) $record->responsesummary, $records);
    }

    /**
     * How many of $responsesummaries contain a single branch's answernote —
     * the traversal-count granularity the bloated-tree diagnostic needs to
     * distinguish "never reached" from "reached, but rarely" (architecture
     * doc Figure 5's Node 6/Node 7 distinction).
     *
     * @param \stdClass $branch one entry from get_prt_branches()
     * @param string[] $responsesummaries from get_response_summaries()
     * @return int
     */
    public static function count_branch_occurrences(\stdClass $branch, array $responsesummaries): int {
        $count = 0;
        foreach ($responsesummaries as $summary) {
            if ($summary !== '' && strpos($summary, $branch->answernote) !== false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * How many of $branches have their answernote appear in at least one of
     * $responsesummaries — pure string matching, no DB access, so it can be
     * exercised directly with synthetic data.
     *
     * @param \stdClass[] $branches from get_prt_branches()
     * @param string[] $responsesummaries from get_response_summaries()
     * @return int
     */
    public static function count_reached_branches(array $branches, array $responsesummaries): int {
        $reached = 0;
        foreach ($branches as $branch) {
            foreach ($responsesummaries as $summary) {
                if ($summary !== '' && strpos($summary, $branch->answernote) !== false) {
                    $reached++;
                    break;
                }
            }
        }
        return $reached;
    }
}
