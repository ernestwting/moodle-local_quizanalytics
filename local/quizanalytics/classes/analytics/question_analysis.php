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
 * Assembles the full Question Analytics {summary, sections, questions, audit}
 * payload — the PHP port's equivalent of analytics-service's app.py::analyze()
 * route (POST /analyze) plus the parts of question_analytics.py::
 * build_question_analytics() that route actually reads from (syntax_analysis
 * and export_summary are computed there but never read by the route, so
 * they're not ported at all — see this port's own commit history for the
 * confirmation of that before committing to skipping them).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Assembles the full Question Analytics {summary, sections, questions, audit} payload for one quiz.
 */
class question_analysis {
    /**
     * Builds the full Question Analytics payload: difficulty, response distribution,
     * student performance matrix, and question metrics sections, plus per-question drill-down.
     *
     * @param array[] $records as returned by
     *        local_quizanalytics_data_fetcher::get_response_records_for_quiz()
     * @param string $quizname
     * @param bool $colorblindmode
     * @param bool $anonymize
     * @return array {quiz_name, summary, sections, questions, audit}
     */
    public static function build_analysis(
        array $records,
        string $quizname,
        bool $colorblindmode = false,
        bool $anonymize = false
    ): array {
        $responserows = parser::build_response_rows($records, $quizname, $anonymize);

        $pools = parser::get_attempt_pools($responserows);
        $poolb = $pools['pool_b'];

        $questionmetricsrows = question_metrics::compute_question_metrics($responserows);
        $prtframerows = prt_analysis::build_prt_frame($responserows); // Pool A === response_rows unchanged.
        $questionsummary = question_metrics::compute_question_summary($responserows);
        $responseoutcomesrows = response_analysis::compute_response_outcomes($responserows);
        $difficultymetricsrows = difficulty::compute_difficulty_metrics($responserows);
        $prtpassratesrows = prt_analysis::compute_prt_pass_rates($prtframerows);
        $repeatedwronganswersrows = response_analysis::compute_repeated_wrong_answers($responserows);
        $rankeddifficultyrows = question_metrics::compute_ranked_difficulty($questionmetricsrows);

        $summary = array_merge(['quiz_name' => $quizname], $questionsummary);

        $sections = [];
        $questionorder = array_map(fn($r) => $r['question'], $questionmetricsrows);

        // 2. Question Difficulty Analysis.
        // Adds scaled_score (grade * 10.0) to every Pool B row, matching the
        // Python route's pool_b_df["scaled_score"] = pool_b_df["grade"] * 10.0
        // — needed by the boxplot chart below.
        $poolbscaled = array_map(function ($r) {
            $r['scaled_score'] = $r['grade'] === null ? null : $r['grade'] * 10.0;
            return $r;
        }, $poolb);

        $difficultycharts = [];
        if (!empty($rankeddifficultyrows)) {
            $difficultycharts[] = [
                'id' => 'difficulty-bar', 'title' => 'Top Difficult Questions by Average Score',
                'plotly_json' => question_charts::build_difficulty_bar_figure($rankeddifficultyrows, $colorblindmode),
            ];
        }
        if (!empty($poolbscaled)) {
            $difficultycharts[] = [
                'id' => 'score-box', 'title' => 'Score Distribution by Question (Best Attempt per Student)',
                'plotly_json' => question_charts::build_score_boxplot_figure($poolbscaled, $colorblindmode),
            ];
        }
        $sections[] = [
            'id' => 'difficulty',
            'title' => '2. Question Difficulty Analysis',
            'table' => table_helpers::to_table($difficultymetricsrows),
            'charts' => $difficultycharts,
        ];

        // 4. Question Response Distribution.
        $hasprtdata = false;
        foreach ($responserows as $r) {
            if (trim((string) ($r['response_text'] ?? '')) !== '') {
                $hasprtdata = true;
                break;
            }
        }

        $distributioncharts = [];
        if ($hasprtdata && !empty($responseoutcomesrows)) {
            $distributioncharts[] = [
                'id' => 'response-outcomes', 'title' => 'Response Outcome Percentages (Best Attempts)',
                'plotly_json' => question_charts::build_response_outcome_figure($responseoutcomesrows, $colorblindmode),
            ];
            $distributioncharts[] = [
                'id' => 'valid-invalid', 'title' => 'Valid vs Invalid Attempts (All Attempts)',
                'plotly_json' => question_charts::build_valid_invalid_figure($questionmetricsrows, $colorblindmode),
            ];
            $heatmap = prt_analysis::build_prt_pass_heatmap($prtpassratesrows, $questionorder, $prtframerows);
            if (!empty($heatmap['prt_names'])) {
                $distributioncharts[] = [
                    'id' => 'prt-pass-heatmap', 'title' => 'PRT Pass Heatmap',
                    'plotly_json' => prt_analysis::build_prt_pass_heatmap_figure($heatmap, $colorblindmode),
                ];
            }
        }

        // Left join response_outcomes with repeated_wrong_answers (minus its
        // top_wrong_expressions column) on "question".
        $repeatedbyquestion = [];
        foreach ($repeatedwronganswersrows as $r) {
            $repeatedbyquestion[$r['question']] = $r;
        }
        $distributiontablerows = array_map(function ($r) use ($repeatedbyquestion) {
            $extra = $repeatedbyquestion[$r['question']] ?? null;
            if ($extra !== null) {
                $r['most_common_incorrect_answer'] = $extra['most_common_incorrect_answer'];
                $r['frequency'] = $extra['frequency'];
            }
            return $r;
        }, $responseoutcomesrows);

        $sections[] = [
            'id' => 'response-distribution',
            'title' => '4. Question Response Distribution',
            'table' => table_helpers::to_table($distributiontablerows),
            'charts' => $distributioncharts,
        ];

        // 5. Student Performance Matrix.
        $studentmatrixcharts = [];
        $studentmatrixtable = ['columns' => [], 'rows' => []];
        if (!empty($poolbscaled) && !empty($questionorder)) {
            $studentmatrix = question_charts::build_student_matrix($poolbscaled, $questionorder);
            $studentmatrixtable = [
                'columns' => array_merge(['student_id'], $questionorder),
                'rows' => array_map(
                    fn($sid) => array_merge([$sid], array_map(fn($q) => $studentmatrix['grid'][$sid][$q], $questionorder)),
                    $studentmatrix['students']
                ),
            ];
            $studentmatrixcharts[] = [
                'id' => 'student-heatmap', 'title' => null,
                'plotly_json' => question_charts::build_student_matrix_figure($studentmatrix),
            ];
        }
        $sections[] = [
            'id' => 'student-matrix',
            'title' => '5. Student Performance Matrix',
            'table' => $studentmatrixtable,
            'charts' => $studentmatrixcharts,
        ];

        // 6. Question Metrics.
        $metricstable = ['columns' => [], 'rows' => []];
        if (!empty($questionmetricsrows) && !empty($difficultymetricsrows)) {
            $metricstable = question_charts::build_question_metrics_table($questionmetricsrows, $difficultymetricsrows);
        }
        $sections[] = [
            'id' => 'metrics',
            'title' => '6. Question Metrics',
            'table' => $metricstable,
        ];

        // Per-question detail (drives the PHP question <select>).
        $questions = [];
        foreach ($questionorder as $q) {
            $detail = question_details::build_question_detail($poolb, $q);
            [$cleantext, ] = latex_utils::split_stack_debug_dump($detail['question_text']);
            $cleantext = latex_utils::strip_stack_input_placeholders($cleantext);
            $cleananswer = latex_utils::extract_stack_answer_latex($detail['right_answer_text']);

            $drilldown = question_details::build_error_drilldown($poolb, $q);
            if (!empty($drilldown['rows'])) {
                $submittedidx = array_search('Submitted Response', $drilldown['columns'], true);
                $rightansweridx = array_search('Right Answer', $drilldown['columns'], true);
                $drilldown['rows'] = array_map(function ($row) use ($submittedidx, $rightansweridx) {
                    $row[$submittedidx] = latex_utils::extract_stack_answer_latex((string) $row[$submittedidx]);
                    $row[$rightansweridx] = latex_utils::extract_stack_answer_latex((string) $row[$rightansweridx]);
                    return $row;
                }, $drilldown['rows']);
            }

            $questions[$q] = [
                'question_text_html' => $cleantext,
                'right_answer_html' => $cleananswer,
                'error_drilldown' => $drilldown,
            ];
        }

        $audit = validation::audit_question_data($responserows);

        return [
            'quiz_name' => $quizname,
            'summary' => $summary,
            'sections' => $sections,
            'questions' => $questions,
            'audit' => $audit,
        ];
    }
}
