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
     * Builds the individual Question Analytics payload: response overview,
     * student performance matrix, and per-question drill-down.
     *
     * @param array[] $records as returned by
     *        local_quizanalytics_data_fetcher::get_response_records_for_quiz()
     * @param string $quizname
     * @param bool $colorblindmode
     * @param bool $anonymize
     * @param array|null $snapshot Moodle-backed quiz snapshot
     * @return array {quiz_name, summary, snapshot, sections, questions, audit}
     */
    public static function build_analysis(
        array $records,
        string $quizname,
        bool $colorblindmode = false,
        bool $anonymize = false,
        ?array $snapshot = null
    ): array {
        $responserows = parser::build_response_rows($records, $quizname, $anonymize);

        $pools = parser::get_attempt_pools($responserows);
        $poolb = $pools['pool_b'];

        $questionmetricsrows = question_metrics::compute_question_metrics($responserows);
        $snapshotquestions = array_column($snapshot['students_per_question'] ?? [], 'question');
        $questionstudents = [];
        foreach ($snapshot['students_per_question'] ?? [] as $questionstudent) {
            $questionstudents[$questionstudent['question']] = (int) $questionstudent['students'];
        }
        $responseoverviewrows = response_analysis::compute_response_statuses(
            $responserows,
            $snapshotquestions,
            $questionstudents,
            $snapshot['question_means'] ?? [],
            $snapshot['question_mean_counts'] ?? []
        );

        $sections = [];
        $questionorder = array_map(fn($r) => $r['question'], $questionmetricsrows);

        // 2. Question Response Overview.
        // Adds scaled_score (grade * 10.0) to every Pool B row, matching the
        // Python route's pool_b_df["scaled_score"] = pool_b_df["grade"] * 10.0
        // — needed by the boxplot chart below.
        $poolbscaled = array_map(function ($r) {
            $r['scaled_score'] = $r['grade'] === null ? null : $r['grade'] * 10.0;
            return $r;
        }, $poolb);

        $responsecharts = [];
        if (!empty($responseoverviewrows)) {
            $responsecharts[] = [
                'id' => 'response-status', 'title' => null,
                'plotly_json' => question_charts::build_response_status_figure($responseoverviewrows, $colorblindmode),
            ];
        }
        $sections[] = [
            'id' => 'question-response-overview',
            'title' => 'Question Response Overview',
            'caption' => 'Shows how students responded to each question. Select a question to inspect the responses in more detail.',
            'charts' => $responsecharts,
        ];

        // Per-question detail (drives the PHP question <select>).
        $questions = [];
        foreach ($questionorder as $q) {
            $versions = question_details::build_versioned_review($poolb, $q);
            foreach ($versions as &$version) {
                [$cleantext, ] = latex_utils::split_stack_debug_dump($version['question_text']);
                $version['question_text_html'] = latex_utils::strip_stack_input_placeholders($cleantext);
                $version['right_answer_html'] = latex_utils::extract_stack_answer_latex($version['right_answer_text']);
                foreach ($version['common_responses'] as &$commonresponse) {
                    $commonresponse['response'] = latex_utils::extract_stack_answer_latex(
                        (string) $commonresponse['response']);
                }
                unset($commonresponse);
                unset($version['question_text'], $version['right_answer_text']);
            }
            unset($version);
            $questions[$q] = [
                'versions' => $versions,
            ];
        }

        return [
            'quiz_name' => $quizname,
            'summary' => [],
            'snapshot' => $snapshot,
            'sections' => $sections,
            'questions' => $questions,
            'audit' => null,
        ];
    }
}
