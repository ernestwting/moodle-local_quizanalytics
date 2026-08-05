<?php
/**
 * Assembles the Solution Process Visualization payloads — the PHP port's
 * equivalent of analytics-service's app.py::solution_process_meta() (POST
 * /solution-process/meta) and app.py::solution_process() (POST
 * /solution-process) routes.
 *
 * @package local_quizanalytics
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
    public static function build_meta(array $records, string $quiz_name): array {
        $response_rows = parser::build_response_rows($records, $quiz_name);
        $pools = parser::get_attempt_pools($response_rows);
        $pool_a = $pools['pool_a'];

        $question_names = table_helpers::unique_sorted_by_question($pool_a, 'question');
        $questions = array_map(fn($q) => [
            'name' => $q,
            'parts' => prt_transitions::count_question_parts($pool_a, $q),
        ], $question_names);

        $seen = [];
        foreach ($pool_a as $row) {
            $seen[$row['student_id']] = $row['student_name'];
        }
        $student_ids = array_keys($seen);
        usort($student_ids, fn($a, $b) => strcmp($seen[$a], $seen[$b]));
        $students = array_map(fn($sid) => ['id' => $sid, 'name' => $seen[$sid]], $student_ids);

        return ['questions' => $questions, 'students' => $students];
    }

    /**
     * Solution Process Visualization for one (question, part) of a quiz —
     * the class-wide transition graph, per-node network features, PRT/TED 3D
     * distance charts, and cross-attempt comparison. Optionally a single
     * student's own drill-down (their transition path + their own metric
     * trend across attempts) when $student_id is supplied.
     *
     * Uses Pool A (every attempt, not just each student's best) throughout —
     * unlike Question Analysis, seeing retries is the whole point here.
     *
     * @param array[] $records
     * @return array{question: string, part_index: int, sections: array[], student_drilldown: array|null}
     */
    public static function build_analysis(
        array $records,
        string $quiz_name,
        string $question,
        int $part_index = 1,
        ?string $student_id = null,
        bool $colorblind_mode = false
    ): array {
        $response_rows = parser::build_response_rows($records, $quiz_name);
        $pools = parser::get_attempt_pools($response_rows);
        $pool_a = $pools['pool_a'];

        $known_questions = array_unique(array_map(fn($r) => $r['question'], $pool_a));
        if (!in_array($question, $known_questions, true)) {
            throw new \InvalidArgumentException("Unknown question: {$question}");
        }

        // A part the caller asked for may not exist on this question — fall
        // back to the last one that does.
        $part_index = min($part_index, prt_transitions::count_question_parts($pool_a, $question));

        $sections = [];

        [$agg_nodes, $agg_edges] = prt_transitions::build_aggregate_graph($pool_a, $question, $part_index);
        if (!empty($agg_edges)) {
            $transition_fig = prt_transitions::build_transition_graph_figure(
                $agg_nodes, $agg_edges, $colorblind_mode,
                "Class-wide Answer Transitions — {$question} (part {$part_index})"
            );
            $sections[] = [
                'id' => 'transition-graph',
                'title' => 'Class-Wide Transition Graph',
                'caption' => 'Edge thickness and color scale with how many students made each transition.',
                'charts' => [['id' => 'agg-graph', 'title' => null, 'plotly_json' => $transition_fig]],
            ];

            $network_features = prt_transitions::compute_network_features($agg_nodes, $agg_edges);
            $centrality_charts = array_map(fn($c) => [
                'id' => $c['metric'], 'title' => $c['label'], 'plotly_json' => $c['plotly_json'],
            ], spv_charts::build_centrality_bar_figures($network_features));
            $sections[] = [
                'id' => 'network-features',
                'title' => 'Network Features per Node',
                'table' => table_helpers::to_table($network_features),
                'charts' => $centrality_charts,
            ];
        }

        $prt_fig = solution_distance::build_prt_distance_3d_figure($pool_a, $question, $part_index);
        $sections[] = [
            'id' => 'prt-distance-3d',
            'title' => 'PRT-Distance 3D Chart',
            'charts' => [['id' => 'prt-3d', 'title' => null, 'plotly_json' => $prt_fig]],
        ];

        $ted_fig = solution_distance::build_ted_distance_3d_figure($pool_a, $question, $part_index);
        $sections[] = [
            'id' => 'ted-distance-3d',
            'title' => 'Tree Edit Distance 3D Chart',
            'charts' => [['id' => 'ted-3d', 'title' => null, 'plotly_json' => $ted_fig]],
        ];

        // Grade — the report has no page-side control for which metric to
        // compare by, and Grade has the advantage of not depending on which
        // part is selected.
        $metric = 'Grade';
        $higher_is_better = solution_distance::CROSS_ATTEMPT_METRICS[$metric]['higher_is_better'];
        $comparison = solution_distance::compute_cross_attempt_comparison($pool_a, $question, $metric, $part_index);
        $trends = solution_distance::classify_cross_attempt_trends($comparison, $higher_is_better);
        if (!empty($comparison)) {
            $cross_fig = solution_distance::build_cross_attempt_figure($comparison, $trends, $metric, $colorblind_mode);
            $counts = ['Improved' => 0, 'Flat' => 0, 'Regressed' => 0];
            foreach ($trends as $t) {
                $counts[$t['trend']]++;
            }
            $ranking_rows = array_map(fn($t) => [
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
                'table' => table_helpers::to_table($ranking_rows),
                'charts' => [['id' => 'cross-attempt-fig', 'title' => null, 'plotly_json' => $cross_fig]],
                // Parallel to table["rows"] (same row order) — lets the PHP/
                // JS side turn each "Student Name" cell into a link that
                // reloads this same page with that student's drill-down
                // selected.
                'row_student_ids' => array_map(fn($t) => $t['student_id'], $trends),
            ];
        }

        $student_drilldown = null;
        if ($student_id !== null && $student_id !== '') {
            $student_name = $student_id;
            foreach ($pool_a as $row) {
                if ($row['student_id'] === $student_id) {
                    $student_name = $row['student_name'];
                    break;
                }
            }
            $student_sections = [];

            $seq = prt_transitions::build_student_node_sequence($pool_a, $question, $student_id, $part_index);
            if (!empty($seq)) {
                $seq_nodes = array_map(fn($r) => $r['node'], $seq);
                $student_edges = [];
                foreach (prt_transitions::build_transition_pairs($seq_nodes) as [$src, $dst]) {
                    $key = "{$src}|{$dst}";
                    $student_edges[$key] = ($student_edges[$key] ?? 0) + 1;
                }
                $student_nodes = array_values(array_unique(array_merge($seq_nodes, ['0', 'c'])));
                $student_fig = prt_transitions::build_transition_graph_figure(
                    $student_nodes, $student_edges, $colorblind_mode,
                    "{$student_name} — {$question} (part {$part_index})"
                );
                $student_sections[] = [
                    'id' => 'student-transition',
                    'title' => "This Student's Transition Path",
                    'charts' => [['id' => 'student-graph', 'title' => null, 'plotly_json' => $student_fig]],
                ];
            }

            $student_comparison = array_values(array_filter($comparison, fn($r) => $r['student_id'] === $student_id));
            if (count($student_comparison) >= 2) {
                $trend = 'Flat';
                foreach ($trends as $t) {
                    if ($t['student_id'] === $student_id) {
                        $trend = $t['trend'];
                        break;
                    }
                }
                $student_cross_fig = solution_distance::build_single_student_attempt_figure(
                    $student_comparison, $student_name, $metric, $trend, $colorblind_mode
                );
                $student_sections[] = [
                    'id' => 'student-cross-attempt',
                    'title' => "This Student's {$metric} Across Attempts",
                    'charts' => [['id' => 'student-cross-fig', 'title' => null, 'plotly_json' => $student_cross_fig]],
                ];
            }

            $student_drilldown = [
                'student_id' => $student_id,
                'student_name' => $student_name,
                'sections' => $student_sections,
            ];
        }

        return [
            'question' => $question,
            'part_index' => $part_index,
            'sections' => $sections,
            'student_drilldown' => $student_drilldown,
        ];
    }
}
