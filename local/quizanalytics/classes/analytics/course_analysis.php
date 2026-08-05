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
        string $coursename,
        array $quizzes,
        bool $colorblindmode = false,
        ?array $selectedstats = null,
        ?array $selectedmetrics = null,
        string $gradetype = self::DEFAULT_GRADE_TYPE
    ): array {
        $combined = [];
        foreach ($quizzes as $quizname => $records) {
            if (empty($records)) {
                continue;
            }
            $rows = parser::build_response_rows($records, $quizname);
            if (!empty($rows)) {
                $combined = array_merge($combined, $rows);
            }
        }

        if (empty($combined)) {
            throw new \InvalidArgumentException('No gradable attempts parsed for any quiz.');
        }

        $attemptframe = quiz_metrics::build_quiz_attempt_frame($combined);

        $selectedstats = !empty($selectedstats) ? $selectedstats : self::DEFAULT_QUIZ_STATS;
        $selectedmetrics = !empty($selectedmetrics) ? $selectedmetrics : self::DEFAULT_QUIZ_METRICS;

        $statsrows = quiz_metrics::compute_quiz_stats($attemptframe, $selectedstats);

        $sections = [];

        $sections[] = [
            'id' => 'attempt-list',
            'title' => '1. Merged List of Users and Files',
            'caption' => 'Every parsed quiz attempt row, combined across every STACK quiz in the course.',
            'table' => table_helpers::to_table($attemptframe),
        ];

        $sections[] = [
            'id' => 'quiz-stats',
            'title' => '2. Summary of Quiz Stats',
            'caption' => 'Aggregated statistics per quiz, combined across the course.',
            'table' => table_helpers::to_table($statsrows),
        ];

        $boxfig = quiz_metrics::build_boxplot_figure($attemptframe, $colorblindmode);
        $sections[] = [
            'id' => 'boxplot',
            'title' => '3. Quiz Grade Distribution (Box Plot)',
            'caption' => 'Spread of grades per quiz, with mean grade overlay.',
            'charts' => [['id' => 'boxplot-fig', 'title' => null, 'plotly_json' => $boxfig]],
        ];

        $engagementfig = quiz_metrics::build_engagement_figure($attemptframe, $colorblindmode);
        if ($engagementfig !== null) {
            $sections[] = [
                'id' => 'engagement',
                'title' => '4. Engagement Over Time',
                'caption' => 'Density of quiz attempt start times per quiz, combined across the course.',
                'charts' => [['id' => 'engagement-fig', 'title' => null, 'plotly_json' => $engagementfig]],
            ];
        }

        $scatterresult = quiz_metrics::build_scatter_figure($attemptframe, $gradetype, $colorblindmode);
        if ($scatterresult !== null) {
            $correlationstr = is_nan($scatterresult['correlation']) ? 'nan' : sprintf('%.2f', $scatterresult['correlation']);
            $sections[] = [
                'id' => 'scatter',
                'title' => '5. Scatter Plot: Attempts vs Grades',
                'caption' => "Correlation between number of attempts and quiz {$scatterresult['y_label']}: r = {$correlationstr}",
                'charts' => [['id' => 'scatter-fig', 'title' => null, 'plotly_json' => $scatterresult['plotly_json']]],
            ];
        }

        $trenddata = quiz_metrics::build_metric_trend_data($attemptframe, $selectedmetrics);
        if (!empty($trenddata)) {
            $trendfig = quiz_metrics::build_line_graph_figure($trenddata, $colorblindmode);
            $sections[] = [
                'id' => 'trend',
                'title' => '6. Line Graph of Various Metrics',
                'caption' => 'Trend of selected metrics across quizzes.',
                'table' => table_helpers::to_table($trenddata),
                'charts' => [['id' => 'trend-fig', 'title' => null, 'plotly_json' => $trendfig]],
            ];
        }

        return [
            'summary' => ['course_name' => $coursename],
            'sections' => $sections,
        ];
    }
}
