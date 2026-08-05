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
 * PHP port of analytics-service/analytics/question_charts.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Question Analytics view's charts: difficulty ranking, response distribution, student performance matrix.
 */
class question_charts {
    /**
     * "Top Difficult Questions by Average Score" — hardest 10 questions;
     * $rankeddifficulty is already sorted ascending by avg_score (see
     * question_metrics::compute_ranked_difficulty()).
     *
     * @param array[] $rankeddifficulty
     */
    public static function build_difficulty_bar_figure(array $rankeddifficulty, bool $colorblindmode = false): array {
        $top10 = array_slice($rankeddifficulty, 0, 10);
        $categories = array_map(fn($r) => $r['question'], $top10);
        $values = array_map(fn($r) => $r['avg_score'], $top10);
        $palette = chart_helpers::qualitative_colors($colorblindmode, chart_helpers::PALETTE_SET2);
        return chart_helpers::build_bar_figure(
            $categories,
            $values,
            'Top Difficult Questions by Average Score',
            'Question',
            'Average score',
            $palette
        );
    }

    /**
     * "Score Distribution by Question (Best Attempt per Student)" — expects
     * $poolbrows to already have a scaled_score field (grade * 10.0).
     *
     * @param array[] $poolbrows
     */
    public static function build_score_boxplot_figure(array $poolbrows, bool $colorblindmode = false): array {
        // Px.box() keeps one y-entry per row (null for missing scaled_score) —
        // Plotly ignores nulls in the box statistics, but the trace's y-array
        // still has one entry per underlying row, so this doesn't filter them
        // out (matching the Python oracle's actual trace length).
        $questions = table_helpers::unique_sorted_by_question($poolbrows, 'question');
        $valuesbyquestion = [];
        foreach ($questions as $q) {
            $valuesbyquestion[$q] = array_values(array_map(
                fn($r) => $r['scaled_score'],
                array_filter($poolbrows, fn($r) => $r['question'] === $q)
            ));
        }
        $palette = chart_helpers::qualitative_colors($colorblindmode, chart_helpers::PALETTE_SET2);
        return chart_helpers::build_box_figure(
            $questions,
            $valuesbyquestion,
            'Score Distribution by Question (Best Attempt per Student)',
            'Question',
            'Score (0-10)',
            $palette
        );
    }

    /**
     * "Response Outcome Percentages (Best Attempts)" — correct_percent/
     * incorrect_percent grouped bars per question.
     *
     * @param array[] $responseoutcomes
     */
    public static function build_response_outcome_figure(array $responseoutcomes, bool $colorblindmode = false): array {
        $categories = array_map(fn($r) => $r['question'], $responseoutcomes);
        $series = [
            'correct_percent' => array_map(fn($r) => $r['correct_percent'], $responseoutcomes),
            'incorrect_percent' => array_map(fn($r) => $r['incorrect_percent'], $responseoutcomes),
        ];
        $palette = chart_helpers::qualitative_colors($colorblindmode, chart_helpers::PALETTE_VIVID);
        return chart_helpers::build_grouped_bar_figure(
            $categories,
            $series,
            'Response Outcome Percentages (Best Attempts)',
            'Question',
            'Percent',
            $palette
        );
    }

    /**
     * "Valid vs Invalid Attempts (All Attempts)" — percent_valid/
     * percent_invalid grouped bars per question, from question_metrics
     * (Pool A based).
     *
     * @param array[] $questionmetricsrows
     */
    public static function build_valid_invalid_figure(array $questionmetricsrows, bool $colorblindmode = false): array {
        $categories = array_map(fn($r) => $r['question'], $questionmetricsrows);
        $series = [
            'Valid %' => array_map(fn($r) => $r['percent_valid'], $questionmetricsrows),
            'Invalid/Syntax Error %' => array_map(fn($r) => $r['percent_invalid'], $questionmetricsrows),
        ];
        $palette = chart_helpers::qualitative_colors($colorblindmode, chart_helpers::PALETTE_VIVID);
        return chart_helpers::build_grouped_bar_figure(
            $categories,
            $series,
            'Valid vs Invalid Attempts (All Attempts)',
            'Question',
            'Percent',
            $palette
        );
    }

    /**
     * Student x Question pivot (grade, 0.0-1.0), used both for the heatmap
     * figure below and as the section's raw data table.
     *
     * @param array[] $poolbrows
     * @param string[] $questionorder
     * @return array{students: string[], questions: string[], grid: array<string, array<string, float>>}
     */
    public static function build_student_matrix(array $poolbrows, array $questionorder): array {
        // Pivot_table(aggfunc="first"): first-seen (student, question) pair wins.
        $pivot = [];
        $studentsseen = [];
        foreach ($poolbrows as $r) {
            $sid = $r['student_id'];
            $studentsseen[$sid] = true;
            if (!isset($pivot[$sid][$r['question']])) {
                $pivot[$sid][$r['question']] = $r['grade'] ?? 0.0;
            }
        }
        $students = array_keys($studentsseen);
        sort($students);

        $grid = [];
        foreach ($students as $sid) {
            $row = [];
            foreach ($questionorder as $q) {
                $row[$q] = $pivot[$sid][$q] ?? 0.0;
            }
            $grid[$sid] = $row;
        }

        return ['students' => $students, 'questions' => $questionorder, 'grid' => $grid];
    }

    /**
     * "Student-by-Question Performance Matrix (Best Attempts)" heatmap, from
     * the pivot build_student_matrix() produces.
     *
     * @param array{students: string[], questions: string[], grid: array} $studentmatrix
     */
    public static function build_student_matrix_figure(array $studentmatrix): array {
        $students = $studentmatrix['students'];
        $questions = $studentmatrix['questions'];
        $z = [];
        foreach ($students as $sid) {
            $row = [];
            foreach ($questions as $q) {
                $row[] = $studentmatrix['grid'][$sid][$q] ?? 0.0;
            }
            $z[] = $row;
        }
        $height = max(400, 24 * count($students));
        return chart_helpers::build_heatmap_figure(
            $z,
            $questions,
            $students,
            'Student-by-Question Performance Matrix (Best Attempts)',
            'Viridis',
            null,
            null,
            $height
        );
    }

    /**
     * The "6. Question Metrics" consolidated table — question_metrics merged
     * with a handful of difficulty_metrics columns, discrimination_index
     * renamed to discrimination.
     *
     * @param array[] $questionmetricsrows
     * @param array[] $difficultymetricsrows
     * @return array{columns: string[], rows: array[]}
     */
    public static function build_question_metrics_table(array $questionmetricsrows, array $difficultymetricsrows): array {
        $difficultybyquestion = [];
        foreach ($difficultymetricsrows as $r) {
            $difficultybyquestion[$r['question']] = $r;
        }

        $columns = [
            'question', 'attempts', 'students', 'invalid_rate', 'blank_rate',
            'reattempt_share', 'facility', 'partial_credit_mean',
            'discrimination', 'average_marks', 'median_marks', 'standard_deviation',
            'catch_all_share',
        ];

        $rows = [];
        foreach ($questionmetricsrows as $qm) {
            $d = $difficultybyquestion[$qm['question']] ?? null;
            $rows[] = [
                $qm['question'],
                $qm['attempts'],
                $qm['students'],
                $qm['invalid_rate'],
                $qm['blank_rate'],
                $qm['reattempt_share'],
                $qm['facility'],
                $qm['partial_credit_mean'],
                $d['discrimination_index'] ?? null,
                $d['average_marks'] ?? null,
                $d['median_marks'] ?? null,
                $d['standard_deviation'] ?? null,
                $qm['catch_all_share'],
            ];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }
}
