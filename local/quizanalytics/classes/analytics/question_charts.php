<?php
/**
 * PHP port of analytics-service/analytics/question_charts.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class question_charts {

    /**
     * "Top Difficult Questions by Average Score" — hardest 10 questions;
     * $ranked_difficulty is already sorted ascending by avg_score (see
     * question_metrics::compute_ranked_difficulty()).
     *
     * @param array[] $ranked_difficulty
     */
    public static function build_difficulty_bar_figure(array $ranked_difficulty, bool $colorblind_mode = false): array {
        $top10 = array_slice($ranked_difficulty, 0, 10);
        $categories = array_map(fn($r) => $r['question'], $top10);
        $values = array_map(fn($r) => $r['avg_score'], $top10);
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_SET2);
        return chart_helpers::build_bar_figure(
            $categories, $values, 'Top Difficult Questions by Average Score', 'Question', 'Average score', $palette
        );
    }

    /**
     * "Score Distribution by Question (Best Attempt per Student)" — expects
     * $pool_b_rows to already have a scaled_score field (grade * 10.0).
     *
     * @param array[] $pool_b_rows
     */
    public static function build_score_boxplot_figure(array $pool_b_rows, bool $colorblind_mode = false): array {
        // px.box() keeps one y-entry per row (null for missing scaled_score) —
        // Plotly ignores nulls in the box statistics, but the trace's y-array
        // still has one entry per underlying row, so this doesn't filter them
        // out (matching the Python oracle's actual trace length).
        $questions = table_helpers::unique_sorted_by_question($pool_b_rows, 'question');
        $values_by_question = [];
        foreach ($questions as $q) {
            $values_by_question[$q] = array_values(array_map(
                fn($r) => $r['scaled_score'],
                array_filter($pool_b_rows, fn($r) => $r['question'] === $q)
            ));
        }
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_SET2);
        return chart_helpers::build_box_figure(
            $questions, $values_by_question,
            'Score Distribution by Question (Best Attempt per Student)', 'Question', 'Score (0-10)', $palette
        );
    }

    /**
     * "Response Outcome Percentages (Best Attempts)" — correct_percent/
     * incorrect_percent grouped bars per question.
     *
     * @param array[] $response_outcomes
     */
    public static function build_response_outcome_figure(array $response_outcomes, bool $colorblind_mode = false): array {
        $categories = array_map(fn($r) => $r['question'], $response_outcomes);
        $series = [
            'correct_percent' => array_map(fn($r) => $r['correct_percent'], $response_outcomes),
            'incorrect_percent' => array_map(fn($r) => $r['incorrect_percent'], $response_outcomes),
        ];
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_VIVID);
        return chart_helpers::build_grouped_bar_figure(
            $categories, $series, 'Response Outcome Percentages (Best Attempts)', 'Question', 'Percent', $palette
        );
    }

    /**
     * "Valid vs Invalid Attempts (All Attempts)" — percent_valid/
     * percent_invalid grouped bars per question, from question_metrics
     * (Pool A based).
     *
     * @param array[] $question_metrics_rows
     */
    public static function build_valid_invalid_figure(array $question_metrics_rows, bool $colorblind_mode = false): array {
        $categories = array_map(fn($r) => $r['question'], $question_metrics_rows);
        $series = [
            'Valid %' => array_map(fn($r) => $r['percent_valid'], $question_metrics_rows),
            'Invalid/Syntax Error %' => array_map(fn($r) => $r['percent_invalid'], $question_metrics_rows),
        ];
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_VIVID);
        return chart_helpers::build_grouped_bar_figure(
            $categories, $series, 'Valid vs Invalid Attempts (All Attempts)', 'Question', 'Percent', $palette
        );
    }

    /**
     * Student x Question pivot (grade, 0.0-1.0), used both for the heatmap
     * figure below and as the section's raw data table.
     *
     * @param array[] $pool_b_rows
     * @param string[] $question_order
     * @return array{students: string[], questions: string[], grid: array<string, array<string, float>>}
     */
    public static function build_student_matrix(array $pool_b_rows, array $question_order): array {
        // pivot_table(aggfunc="first"): first-seen (student, question) pair wins.
        $pivot = [];
        $students_seen = [];
        foreach ($pool_b_rows as $r) {
            $sid = $r['student_id'];
            $students_seen[$sid] = true;
            if (!isset($pivot[$sid][$r['question']])) {
                $pivot[$sid][$r['question']] = $r['grade'] ?? 0.0;
            }
        }
        $students = array_keys($students_seen);
        sort($students);

        $grid = [];
        foreach ($students as $sid) {
            $row = [];
            foreach ($question_order as $q) {
                $row[$q] = $pivot[$sid][$q] ?? 0.0;
            }
            $grid[$sid] = $row;
        }

        return ['students' => $students, 'questions' => $question_order, 'grid' => $grid];
    }

    /**
     * "Student-by-Question Performance Matrix (Best Attempts)" heatmap, from
     * the pivot build_student_matrix() produces.
     *
     * @param array{students: string[], questions: string[], grid: array} $student_matrix
     */
    public static function build_student_matrix_figure(array $student_matrix): array {
        $students = $student_matrix['students'];
        $questions = $student_matrix['questions'];
        $z = [];
        foreach ($students as $sid) {
            $row = [];
            foreach ($questions as $q) {
                $row[] = $student_matrix['grid'][$sid][$q] ?? 0.0;
            }
            $z[] = $row;
        }
        $height = max(400, 24 * count($students));
        return chart_helpers::build_heatmap_figure(
            $z, $questions, $students,
            'Student-by-Question Performance Matrix (Best Attempts)', 'Viridis', null, null, $height
        );
    }

    /**
     * The "6. Question Metrics" consolidated table — question_metrics merged
     * with a handful of difficulty_metrics columns, discrimination_index
     * renamed to discrimination.
     *
     * @param array[] $question_metrics_rows
     * @param array[] $difficulty_metrics_rows
     * @return array{columns: string[], rows: array[]}
     */
    public static function build_question_metrics_table(array $question_metrics_rows, array $difficulty_metrics_rows): array {
        $difficulty_by_question = [];
        foreach ($difficulty_metrics_rows as $r) {
            $difficulty_by_question[$r['question']] = $r;
        }

        $columns = [
            'question', 'attempts', 'students', 'invalid_rate', 'blank_rate',
            'reattempt_share', 'facility', 'partial_credit_mean',
            'discrimination', 'average_marks', 'median_marks', 'standard_deviation',
            'catch_all_share',
        ];

        $rows = [];
        foreach ($question_metrics_rows as $qm) {
            $d = $difficulty_by_question[$qm['question']] ?? null;
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
