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
 * Assembles the Course-Wide Analytics payload — the PHP port's equivalent
 * of analytics-service's app.py::analyze_course() (POST /analyze-course)
 * route.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class course_analysis {
    const DEFAULT_QUIZ_STATS = ['student_count', 'attempt_rate', 'mean_grade', 'grade_variance', 'mean_highest_grade', 'attempt_count'];
    const DEFAULT_QUIZ_METRICS = ['student_count', 'attempt_rate', 'mean_grade', 'grade_variance'];
    const DEFAULT_GRADE_TYPE = 'Average Grade';

    /**
     * @param array<string, array[]> $quizzes quiz_name => records[]
     * @return array{summary: array, sections: array[]}
     */
    public static function build_analysis(
        string $course_name,
        array $quizzes,
        bool $colorblind_mode = false,
        ?array $selected_stats = null,
        ?array $selected_metrics = null,
        string $grade_type = self::DEFAULT_GRADE_TYPE
    ): array {
        $combined = [];
        foreach ($quizzes as $quiz_name => $records) {
            if (empty($records)) {
                continue;
            }
            $rows = parser::build_response_rows($records, $quiz_name);
            if (!empty($rows)) {
                $combined = array_merge($combined, $rows);
            }
        }

        if (empty($combined)) {
            throw new \InvalidArgumentException('No gradable attempts parsed for any quiz.');
        }

        $attempt_frame = quiz_metrics::build_quiz_attempt_frame($combined);

        $selected_stats = !empty($selected_stats) ? $selected_stats : self::DEFAULT_QUIZ_STATS;
        $selected_metrics = !empty($selected_metrics) ? $selected_metrics : self::DEFAULT_QUIZ_METRICS;

        $stats_rows = quiz_metrics::compute_quiz_stats($attempt_frame, $selected_stats);

        $sections = [];

        $sections[] = [
            'id' => 'attempt-list',
            'title' => '1. Merged List of Users and Files',
            'caption' => 'Every parsed quiz attempt row, combined across every STACK quiz in the course.',
            'table' => table_helpers::to_table($attempt_frame),
        ];

        $sections[] = [
            'id' => 'quiz-stats',
            'title' => '2. Summary of Quiz Stats',
            'caption' => 'Aggregated statistics per quiz, combined across the course.',
            'table' => table_helpers::to_table($stats_rows),
        ];

        $box_fig = quiz_metrics::build_boxplot_figure($attempt_frame, $colorblind_mode);
        $sections[] = [
            'id' => 'boxplot',
            'title' => '3. Quiz Grade Distribution (Box Plot)',
            'caption' => 'Spread of grades per quiz, with mean grade overlay.',
            'charts' => [['id' => 'boxplot-fig', 'title' => null, 'plotly_json' => $box_fig]],
        ];

        $engagement_fig = quiz_metrics::build_engagement_figure($attempt_frame, $colorblind_mode);
        if ($engagement_fig !== null) {
            $sections[] = [
                'id' => 'engagement',
                'title' => '4. Engagement Over Time',
                'caption' => 'Density of quiz attempt start times per quiz, combined across the course.',
                'charts' => [['id' => 'engagement-fig', 'title' => null, 'plotly_json' => $engagement_fig]],
            ];
        }

        $scatter_result = quiz_metrics::build_scatter_figure($attempt_frame, $grade_type, $colorblind_mode);
        if ($scatter_result !== null) {
            $correlation_str = is_nan($scatter_result['correlation']) ? 'nan' : sprintf('%.2f', $scatter_result['correlation']);
            $sections[] = [
                'id' => 'scatter',
                'title' => '5. Scatter Plot: Attempts vs Grades',
                'caption' => "Correlation between number of attempts and quiz {$scatter_result['y_label']}: r = {$correlation_str}",
                'charts' => [['id' => 'scatter-fig', 'title' => null, 'plotly_json' => $scatter_result['plotly_json']]],
            ];
        }

        $trend_data = quiz_metrics::build_metric_trend_data($attempt_frame, $selected_metrics);
        if (!empty($trend_data)) {
            $trend_fig = quiz_metrics::build_line_graph_figure($trend_data, $colorblind_mode);
            $sections[] = [
                'id' => 'trend',
                'title' => '6. Line Graph of Various Metrics',
                'caption' => 'Trend of selected metrics across quizzes.',
                'table' => table_helpers::to_table($trend_data),
                'charts' => [['id' => 'trend-fig', 'title' => null, 'plotly_json' => $trend_fig]],
            ];
        }

        return [
            'summary' => ['course_name' => $course_name],
            'sections' => $sections,
        ];
    }
}
