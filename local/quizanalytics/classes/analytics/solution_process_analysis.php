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
 * Assembles the Solution Process Visualization payloads — the PHP port's
 * equivalent of analytics-service's app.py::solution_process_meta() (POST
 * /solution-process/meta) and app.py::solution_process() (POST
 * /solution-process) routes.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class solution_process_analysis {
    /**
     * Cheap metadata for populating the question/part/student selectors —
     * no graph or tree-edit-distance computation, safe to call on every page
     * load unlike build_analysis() itself.
     *
     * @param array[] $records
     * @return array{questions: array{name: string, parts: int}[], students: array{id: string, name: string}[]}
     */
    public static function build_meta(array $records, string $quizname): array {
        $responserows = parser::build_response_rows($records, $quizname);
        $pools = parser::get_attempt_pools($responserows);
        $poola = $pools['pool_a'];

        $questionnames = table_helpers::unique_sorted_by_question($poola, 'question');
        $questions = array_map(fn($q) => [
            'name' => $q,
            'parts' => prt_transitions::count_question_parts($poola, $q),
        ], $questionnames);

        $seen = [];
        foreach ($poola as $row) {
            $seen[$row['student_id']] = $row['student_name'];
        }
        $studentids = array_keys($seen);
        usort($studentids, fn($a, $b) => strcmp($seen[$a], $seen[$b]));
        $students = array_map(fn($sid) => ['id' => $sid, 'name' => $seen[$sid]], $studentids);

        return ['questions' => $questions, 'students' => $students];
    }

    /**
     * Solution Process Visualization for one (question, part) of a quiz —
     * the class-wide transition graph, per-node network features, PRT/TED 3D
     * distance charts, and cross-attempt comparison. Optionally a single
     * student's own drill-down (their transition path + their own metric
     * trend across attempts) when $studentid is supplied.
     *
     * Uses Pool A (every attempt, not just each student's best) throughout —
     * unlike Question Analysis, seeing retries is the whole point here.
     *
     * @param array[] $records
     * @return array{question: string, part_index: int, sections: array[], student_drilldown: array|null}
     */
    public static function build_analysis(
        array $records,
        string $quizname,
        string $question,
        int $partindex = 1,
        ?string $studentid = null,
        bool $colorblindmode = false
    ): array {
        $responserows = parser::build_response_rows($records, $quizname);
        $pools = parser::get_attempt_pools($responserows);
        $poola = $pools['pool_a'];

        $knownquestions = array_unique(array_map(fn($r) => $r['question'], $poola));
        if (!in_array($question, $knownquestions, true)) {
            throw new \InvalidArgumentException("Unknown question: {$question}");
        }

        // A part the caller asked for may not exist on this question — fall
        // back to the last one that does.
        $partindex = min($partindex, prt_transitions::count_question_parts($poola, $question));

        $sections = [];

        [$aggnodes, $aggedges] = prt_transitions::build_aggregate_graph($poola, $question, $partindex);
        if (!empty($aggedges)) {
            $transitionfig = prt_transitions::build_transition_graph_figure(
                $aggnodes,
                $aggedges,
                $colorblindmode,
                "Class-wide Answer Transitions — {$question} (part {$partindex})"
            );
            $sections[] = [
                'id' => 'transition-graph',
                'title' => 'Class-Wide Transition Graph',
                'caption' => 'Edge thickness and color scale with how many students made each transition.',
                'charts' => [['id' => 'agg-graph', 'title' => null, 'plotly_json' => $transitionfig]],
            ];

            $networkfeatures = prt_transitions::compute_network_features($aggnodes, $aggedges);
            $centralitycharts = array_map(fn($c) => [
                'id' => $c['metric'], 'title' => $c['label'], 'plotly_json' => $c['plotly_json'],
            ], spv_charts::build_centrality_bar_figures($networkfeatures));
            $sections[] = [
                'id' => 'network-features',
                'title' => 'Network Features per Node',
                'table' => table_helpers::to_table($networkfeatures),
                'charts' => $centralitycharts,
            ];
        }

        $prtfig = solution_distance::build_prt_distance_3d_figure($poola, $question, $partindex);
        $sections[] = [
            'id' => 'prt-distance-3d',
            'title' => 'PRT-Distance 3D Chart',
            'charts' => [['id' => 'prt-3d', 'title' => null, 'plotly_json' => $prtfig]],
        ];

        $tedfig = solution_distance::build_ted_distance_3d_figure($poola, $question, $partindex);
        $sections[] = [
            'id' => 'ted-distance-3d',
            'title' => 'Tree Edit Distance 3D Chart',
            'charts' => [['id' => 'ted-3d', 'title' => null, 'plotly_json' => $tedfig]],
        ];

        // Grade — the report has no page-side control for which metric to
        // compare by, and Grade has the advantage of not depending on which
        // part is selected.
        $metric = 'Grade';
        $higherisbetter = solution_distance::CROSS_ATTEMPT_METRICS[$metric]['higher_is_better'];
        $comparison = solution_distance::compute_cross_attempt_comparison($poola, $question, $metric, $partindex);
        $trends = solution_distance::classify_cross_attempt_trends($comparison, $higherisbetter);
        if (!empty($comparison)) {
            $crossfig = solution_distance::build_cross_attempt_figure($comparison, $trends, $metric, $colorblindmode);
            $counts = ['Improved' => 0, 'Flat' => 0, 'Regressed' => 0];
            foreach ($trends as $t) {
                $counts[$t['trend']]++;
            }
            $rankingrows = array_map(fn($t) => [
                'Student Name' => $t['student_name'],
                'Attempts' => $t['attempt_count'],
                'First Attempt' => $t['first_value'],
                'Last Attempt' => $t['last_value'],
                'Change' => $t['change'],
                'Trend' => $t['trend'],
            ], $trends);
            $sections[] = [
                'id' => 'cross-attempt',
                'title' => "Cross-Attempt Comparison ({$metric})",
                'caption' => "{$counts['Improved']} improved, {$counts['Flat']} flat, {$counts['Regressed']} regressed " .
                    'among students with 2+ attempts. Click a student\'s name for their own ' .
                    'attempt-by-attempt drill-down.',
                'table' => table_helpers::to_table($rankingrows),
                'charts' => [['id' => 'cross-attempt-fig', 'title' => null, 'plotly_json' => $crossfig]],
                // Parallel to table["rows"] (same row order) — lets the PHP/
                // JS side turn each "Student Name" cell into a link that
                // reloads this same page with that student's drill-down
                // selected.
                'row_student_ids' => array_map(fn($t) => $t['student_id'], $trends),
            ];
        }

        $studentdrilldown = null;
        if ($studentid !== null && $studentid !== '') {
            $studentname = $studentid;
            foreach ($poola as $row) {
                if ($row['student_id'] === $studentid) {
                    $studentname = $row['student_name'];
                    break;
                }
            }
            $studentsections = [];

            $seq = prt_transitions::build_student_node_sequence($poola, $question, $studentid, $partindex);
            if (!empty($seq)) {
                $seqnodes = array_map(fn($r) => $r['node'], $seq);
                $studentedges = [];
                foreach (prt_transitions::build_transition_pairs($seqnodes) as [$src, $dst]) {
                    $key = "{$src}|{$dst}";
                    $studentedges[$key] = ($studentedges[$key] ?? 0) + 1;
                }
                $studentnodes = array_values(array_unique(array_merge($seqnodes, ['0', 'c'])));
                $studentfig = prt_transitions::build_transition_graph_figure(
                    $studentnodes,
                    $studentedges,
                    $colorblindmode,
                    "{$studentname} — {$question} (part {$partindex})"
                );
                $studentsections[] = [
                    'id' => 'student-transition',
                    'title' => "This Student's Transition Path",
                    'charts' => [['id' => 'student-graph', 'title' => null, 'plotly_json' => $studentfig]],
                ];
            }

            $studentcomparison = array_values(array_filter($comparison, fn($r) => $r['student_id'] === $studentid));
            if (count($studentcomparison) >= 2) {
                $trend = 'Flat';
                foreach ($trends as $t) {
                    if ($t['student_id'] === $studentid) {
                        $trend = $t['trend'];
                        break;
                    }
                }
                $studentcrossfig = solution_distance::build_single_student_attempt_figure(
                    $studentcomparison,
                    $studentname,
                    $metric,
                    $trend,
                    $colorblindmode
                );
                $studentsections[] = [
                    'id' => 'student-cross-attempt',
                    'title' => "This Student's {$metric} Across Attempts",
                    'charts' => [['id' => 'student-cross-fig', 'title' => null, 'plotly_json' => $studentcrossfig]],
                ];
            }

            $studentdrilldown = [
                'student_id' => $studentid,
                'student_name' => $studentname,
                'sections' => $studentsections,
            ];
        }

        return [
            'question' => $question,
            'part_index' => $partindex,
            'sections' => $sections,
            'student_drilldown' => $studentdrilldown,
        ];
    }
}
