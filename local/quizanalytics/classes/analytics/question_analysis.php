<?php
/**
 * Assembles the full Question Analytics {summary, sections, questions, audit}
 * payload — the PHP port's equivalent of analytics-service's app.py::analyze()
 * route (POST /analyze) plus the parts of question_analytics.py::
 * build_question_analytics() that route actually reads from (syntax_analysis
 * and export_summary are computed there but never read by the route, so
 * they're not ported at all — see this port's own commit history for the
 * confirmation of that before committing to skipping them).
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class question_analysis {

    /**
     * @param array[] $records as returned by
     *        local_quizanalytics_data_fetcher::get_response_records_for_quiz()
     * @param string $quiz_name
     * @param bool $colorblind_mode
     * @return array {quiz_name, summary, sections, questions, audit}
     */
    public static function build_analysis(array $records, string $quiz_name, bool $colorblind_mode = false): array {
        $response_rows = parser::build_response_rows($records, $quiz_name);

        $pools = parser::get_attempt_pools($response_rows);
        $pool_b = $pools['pool_b'];

        $question_metrics_rows = question_metrics::compute_question_metrics($response_rows);
        $prt_frame_rows = prt_analysis::build_prt_frame($response_rows); // Pool A === response_rows unchanged.
        $question_summary = question_metrics::compute_question_summary($response_rows);
        $response_outcomes_rows = response_analysis::compute_response_outcomes($response_rows);
        $difficulty_metrics_rows = difficulty::compute_difficulty_metrics($response_rows);
        $prt_pass_rates_rows = prt_analysis::compute_prt_pass_rates($prt_frame_rows);
        $repeated_wrong_answers_rows = response_analysis::compute_repeated_wrong_answers($response_rows);
        $ranked_difficulty_rows = question_metrics::compute_ranked_difficulty($question_metrics_rows);

        $summary = array_merge(['quiz_name' => $quiz_name], $question_summary);

        $sections = [];
        $question_order = array_map(fn($r) => $r['question'], $question_metrics_rows);

        // --- 2. Question Difficulty Analysis -------------------------------
        // Adds scaled_score (grade * 10.0) to every Pool B row, matching the
        // Python route's pool_b_df["scaled_score"] = pool_b_df["grade"] * 10.0
        // — needed by the boxplot chart below.
        $pool_b_scaled = array_map(function ($r) {
            $r['scaled_score'] = $r['grade'] === null ? null : $r['grade'] * 10.0;
            return $r;
        }, $pool_b);

        $difficulty_charts = [];
        if (!empty($ranked_difficulty_rows)) {
            $difficulty_charts[] = [
                'id' => 'difficulty-bar', 'title' => 'Top Difficult Questions by Average Score',
                'plotly_json' => question_charts::build_difficulty_bar_figure($ranked_difficulty_rows, $colorblind_mode),
            ];
        }
        if (!empty($pool_b_scaled)) {
            $difficulty_charts[] = [
                'id' => 'score-box', 'title' => 'Score Distribution by Question (Best Attempt per Student)',
                'plotly_json' => question_charts::build_score_boxplot_figure($pool_b_scaled, $colorblind_mode),
            ];
        }
        $sections[] = [
            'id' => 'difficulty',
            'title' => '2. Question Difficulty Analysis',
            'table' => table_helpers::to_table($difficulty_metrics_rows),
            'charts' => $difficulty_charts,
        ];

        // --- 4. Question Response Distribution -----------------------------
        $has_prt_data = false;
        foreach ($response_rows as $r) {
            if (trim((string) ($r['response_text'] ?? '')) !== '') {
                $has_prt_data = true;
                break;
            }
        }

        $distribution_charts = [];
        if ($has_prt_data && !empty($response_outcomes_rows)) {
            $distribution_charts[] = [
                'id' => 'response-outcomes', 'title' => 'Response Outcome Percentages (Best Attempts)',
                'plotly_json' => question_charts::build_response_outcome_figure($response_outcomes_rows, $colorblind_mode),
            ];
            $distribution_charts[] = [
                'id' => 'valid-invalid', 'title' => 'Valid vs Invalid Attempts (All Attempts)',
                'plotly_json' => question_charts::build_valid_invalid_figure($question_metrics_rows, $colorblind_mode),
            ];
            $heatmap = prt_analysis::build_prt_pass_heatmap($prt_pass_rates_rows, $question_order, $prt_frame_rows);
            if (!empty($heatmap['prt_names'])) {
                $distribution_charts[] = [
                    'id' => 'prt-pass-heatmap', 'title' => 'PRT Pass Heatmap',
                    'plotly_json' => prt_analysis::build_prt_pass_heatmap_figure($heatmap, $colorblind_mode),
                ];
            }
        }

        // Left join response_outcomes with repeated_wrong_answers (minus its
        // top_wrong_expressions column) on "question".
        $repeated_by_question = [];
        foreach ($repeated_wrong_answers_rows as $r) {
            $repeated_by_question[$r['question']] = $r;
        }
        $distribution_table_rows = array_map(function ($r) use ($repeated_by_question) {
            $extra = $repeated_by_question[$r['question']] ?? null;
            if ($extra !== null) {
                $r['most_common_incorrect_answer'] = $extra['most_common_incorrect_answer'];
                $r['frequency'] = $extra['frequency'];
            }
            return $r;
        }, $response_outcomes_rows);

        $sections[] = [
            'id' => 'response-distribution',
            'title' => '4. Question Response Distribution',
            'table' => table_helpers::to_table($distribution_table_rows),
            'charts' => $distribution_charts,
        ];

        // --- 5. Student Performance Matrix ----------------------------------
        $student_matrix_charts = [];
        $student_matrix_table = ['columns' => [], 'rows' => []];
        if (!empty($pool_b_scaled) && !empty($question_order)) {
            $student_matrix = question_charts::build_student_matrix($pool_b_scaled, $question_order);
            $student_matrix_table = [
                'columns' => array_merge(['student_id'], $question_order),
                'rows' => array_map(
                    fn($sid) => array_merge([$sid], array_map(fn($q) => $student_matrix['grid'][$sid][$q], $question_order)),
                    $student_matrix['students']
                ),
            ];
            $student_matrix_charts[] = [
                'id' => 'student-heatmap', 'title' => null,
                'plotly_json' => question_charts::build_student_matrix_figure($student_matrix),
            ];
        }
        $sections[] = [
            'id' => 'student-matrix',
            'title' => '5. Student Performance Matrix',
            'table' => $student_matrix_table,
            'charts' => $student_matrix_charts,
        ];

        // --- 6. Question Metrics ---------------------------------------------
        $metrics_table = ['columns' => [], 'rows' => []];
        if (!empty($question_metrics_rows) && !empty($difficulty_metrics_rows)) {
            $metrics_table = question_charts::build_question_metrics_table($question_metrics_rows, $difficulty_metrics_rows);
        }
        $sections[] = [
            'id' => 'metrics',
            'title' => '6. Question Metrics',
            'table' => $metrics_table,
        ];

        // --- Per-question detail (drives the PHP question <select>) --------
        $questions = [];
        foreach ($question_order as $q) {
            $detail = question_details::build_question_detail($pool_b, $q);
            [$clean_text, ] = latex_utils::split_stack_debug_dump($detail['question_text']);
            $clean_text = latex_utils::strip_stack_input_placeholders($clean_text);
            $clean_answer = latex_utils::extract_stack_answer_latex($detail['right_answer_text']);

            $drilldown = question_details::build_error_drilldown($pool_b, $q);
            if (!empty($drilldown['rows'])) {
                $submitted_idx = array_search('Submitted Response', $drilldown['columns'], true);
                $right_answer_idx = array_search('Right Answer', $drilldown['columns'], true);
                $drilldown['rows'] = array_map(function ($row) use ($submitted_idx, $right_answer_idx) {
                    $row[$submitted_idx] = latex_utils::extract_stack_answer_latex((string) $row[$submitted_idx]);
                    $row[$right_answer_idx] = latex_utils::extract_stack_answer_latex((string) $row[$right_answer_idx]);
                    return $row;
                }, $drilldown['rows']);
            }

            $questions[$q] = [
                'question_text_html' => $clean_text,
                'right_answer_html' => $clean_answer,
                'error_drilldown' => $drilldown,
            ];
        }

        $audit = validation::audit_question_data($response_rows);

        return [
            'quiz_name' => $quiz_name,
            'summary' => $summary,
            'sections' => $sections,
            'questions' => $questions,
            'audit' => $audit,
        ];
    }
}
