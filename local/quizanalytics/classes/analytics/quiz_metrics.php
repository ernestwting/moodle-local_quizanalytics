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
 * PHP port of analytics-service/analytics/quiz_metrics.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class quiz_metrics {
    // Okabe-Ito vermillion — a colorblind-safer stand-in for the default
    // reddish mean-grade overlay line/marker color.
    const COLORBLIND_ACCENT = '#D55E00';
    const DEFAULT_ACCENT = '#FF474C';

    const ATTEMPT_FRAME_COLUMNS = [
        'quiz_name', 'student_name', 'student_id', 'attempt_idx', 'overall_grade', 'completed_dt', 'started_on',
    ];

    // Marker size range for the Attempts vs Grades scatter's density cue: a
    // point on its own renders at the low end, a coordinate shared by
    // several students renders larger, clamped so the largest overlaps
    // don't balloon into a dominant bubble chart.
    const SCATTER_MARKER_SIZE_MIN = 12;
    const SCATTER_MARKER_SIZE_MAX = 22;
    const SCATTER_MARKER_SIZE_SATURATES_AT = 6;

    private static function label_tokens(string $label): array {
        preg_match_all('/[^\s_-]+[\s_-]*/', $label, $m);
        return $m[0];
    }

    /**
     * Wrap a long quiz name onto up to $max_lines short horizontal lines
     * (joined with <br>) instead of leaving Plotly to render it as one long
     * diagonal tick label that eats most of the chart's vertical space.
     * Tokenizes on underscores and hyphens as well as whitespace.
     */
    public static function wrap_category_label(string $label, int $max_chars = 22, int $max_lines = 2): string {
        $tokens = self::label_tokens($label);
        if (empty($tokens)) {
            return $label;
        }
        $lines = [];
        $current = '';
        $remaining = $tokens;
        while (!empty($remaining) && count($lines) < $max_lines) {
            $token = $remaining[0];
            if ($current === '' && strlen($token) > $max_chars) {
                $lines[] = rtrim(substr($token, 0, $max_chars - 1)) . "\u{2026}";
                array_shift($remaining);
                continue;
            }
            $candidate = $current . $token;
            if (strlen($candidate) > $max_chars && $current !== '') {
                $lines[] = rtrim($current);
                $current = '';
                continue;
            }
            $current = $candidate;
            array_shift($remaining);
        }
        if ($current !== '' && count($lines) < $max_lines) {
            $lines[] = rtrim($current);
        }
        if (!empty($remaining) && !(!empty($lines) && str_ends_with(end($lines), "\u{2026}"))) {
            if (!empty($lines)) {
                $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1]) . "\u{2026}";
            } else {
                $lines = [rtrim(substr($current, 0, $max_chars)) . "\u{2026}"];
            }
        }
        return implode('<br>', $lines);
    }

    /**
     * Collapse the long per-question response rows to one row per attempt,
     * across all uploaded quiz files. Dedupes on (quiz_name, attempt_idx)
     * rather than attempt_idx alone, since attempt_idx is only assigned
     * uniquely within a single quiz.
     *
     * @param array[] $response_rows
     * @return array[] one row per attempt, ATTEMPT_FRAME_COLUMNS fields only
     */
    public static function build_quiz_attempt_frame(array $response_rows): array {
        $seen = [];
        $out = [];
        foreach ($response_rows as $row) {
            $key = $row['quiz_name'] . '|' . $row['attempt_idx'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entry = [];
            foreach (self::ATTEMPT_FRAME_COLUMNS as $col) {
                $entry[$col] = $row[$col] ?? null;
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * Same formulas as the original per-file Quiz Analysis page, grouped by
     * quiz_name and reading overall_grade instead of a locally normalized
     * grade.
     *
     * @param array[] $attempt_frame
     * @param string[] $selected_stats
     * @return array[] one row per quiz_name
     */
    public static function compute_quiz_stats(array $attempt_frame, array $selected_stats): array {
        if (empty($attempt_frame)) {
            return [];
        }

        $by_quiz = table_helpers::group_by($attempt_frame, 'quiz_name');
        // pandas groupby("quiz_name") iterates groups in *sorted* key order
        // by default (sort=True) — every aggregation below derives from a
        // groupby, so the output row order needs the same plain string sort
        // (matching prt_analysis::compute_prt_pass_rates()'s identical note).
        $quiz_names = array_keys($by_quiz);
        sort($quiz_names);

        $stats_by_quiz = array_fill_keys($quiz_names, []);

        if (in_array('student_count', $selected_stats, true)) {
            foreach ($by_quiz as $quiz => $rows) {
                $stats_by_quiz[$quiz]['student_count'] = count(array_unique(array_map(fn($r) => $r['student_id'], $rows)));
            }
        }

        if (in_array('mean_grade', $selected_stats, true) || in_array('grade_variance', $selected_stats, true)) {
            foreach ($by_quiz as $quiz => $rows) {
                $grades = array_map(fn($r) => $r['overall_grade'], $rows);
                if (in_array('mean_grade', $selected_stats, true)) {
                    $stats_by_quiz[$quiz]['mean_grade'] = stats::mean($grades);
                }
                if (in_array('grade_variance', $selected_stats, true)) {
                    $stats_by_quiz[$quiz]['grade_variance'] = stats::sample_variance($grades);
                }
            }
        }

        if (in_array('mean_highest_grade', $selected_stats, true)) {
            foreach ($by_quiz as $quiz => $rows) {
                $max_by_student = [];
                foreach ($rows as $r) {
                    $sid = $r['student_id'];
                    $max_by_student[$sid] = isset($max_by_student[$sid])
                        ? max($max_by_student[$sid], $r['overall_grade'])
                        : $r['overall_grade'];
                }
                $stats_by_quiz[$quiz]['mean_highest_grade'] = stats::mean(array_values($max_by_student));
            }
        }

        if (in_array('attempt_count', $selected_stats, true)) {
            foreach ($by_quiz as $quiz => $rows) {
                $stats_by_quiz[$quiz]['attempt_count'] = count($rows);
            }
        }

        if (in_array('attempt_rate', $selected_stats, true)) {
            foreach ($by_quiz as $quiz => $rows) {
                $counts = [];
                foreach ($rows as $r) {
                    $counts[$r['student_id']] = ($counts[$r['student_id']] ?? 0) + 1;
                }
                $stats_by_quiz[$quiz]['attempt_rate'] = stats::mean(array_values($counts));
            }
        }

        $out = [];
        foreach ($quiz_names as $quiz) {
            if (empty($stats_by_quiz[$quiz])) {
                continue;
            }
            $row = ['quiz_name' => $quiz];
            foreach ($stats_by_quiz[$quiz] as $k => $v) {
                $row[$k] = py_compat::round($v, 2);
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Grade distribution per quiz, with an overlaid mean_grade line.
     *
     * @param array[] $attempt_frame
     */
    public static function build_boxplot_figure(array $attempt_frame, bool $colorblind_mode = false): array {
        $quiz_names = [];
        foreach ($attempt_frame as $row) {
            $quiz_names[$row['quiz_name']] = true;
        }
        $quiz_names = array_keys($quiz_names);
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_BOLD);

        $data = [];
        foreach ($quiz_names as $i => $quiz) {
            $rows = array_values(array_filter($attempt_frame, fn($r) => $r['quiz_name'] === $quiz));
            $data[] = [
                'type' => 'box',
                'y' => array_map(fn($r) => $r['overall_grade'], $rows),
                'x0' => $quiz,
                'name' => (string) $quiz,
                'boxpoints' => 'all',
                'jitter' => 0.3,
                'pointpos' => 0,
                'marker' => ['color' => $palette[$i % count($palette)], 'size' => 4, 'opacity' => 0.6],
            ];
        }

        $accent = $colorblind_mode ? self::COLORBLIND_ACCENT : self::DEFAULT_ACCENT;
        // Unlike the box traces above (plain px.box(color=...), which keeps
        // first-appearance order), Python builds this line from
        // attempt_frame.groupby("quiz_name")["overall_grade"].mean() — a
        // groupby, sorted alphabetically by default — so it needs its own,
        // separately-sorted quiz name list rather than reusing $quiz_names.
        $means_quiz_names = $quiz_names;
        sort($means_quiz_names);
        $means_x = [];
        $means_y = [];
        foreach ($means_quiz_names as $quiz) {
            $rows = array_values(array_filter($attempt_frame, fn($r) => $r['quiz_name'] === $quiz));
            $means_x[] = $quiz;
            $means_y[] = stats::mean(array_map(fn($r) => $r['overall_grade'], $rows));
        }
        $data[] = [
            'type' => 'scatter',
            'x' => $means_x, 'y' => $means_y,
            'mode' => 'lines+markers',
            'name' => chart_helpers::humanize_label('mean_grade'),
            'line' => ['color' => $accent, 'width' => 2],
            'marker' => ['size' => 8, 'color' => $accent],
        ];

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => 'Grade Distribution'],
                'template' => 'plotly',
                'xaxis' => ['showticklabels' => false, 'title' => null],
                'yaxis' => ['title' => ['text' => 'Grade']],
            ],
        ];
    }

    /**
     * A small, stable pseudo-random offset per key, in the range
     * [-$amplitude, $amplitude]. Seeded from each row's own key via an MD5
     * content hash (identical byte-for-byte to Python's hashlib.md5 for the
     * same string), so the same student gets the same offset on every
     * rerun and this matches the Python original exactly.
     */
    private static function deterministic_jitter(string $key, float $amplitude, string $salt): float {
        $digest = md5("{$salt}:{$key}");
        $fraction = hexdec(substr($digest, 0, 8)) / 0xFFFFFFFF;
        return ($fraction * 2 - 1) * $amplitude;
    }

    /**
     * Attempts-vs-grade scatter, keyed by quiz_name.
     *
     * @param array[] $attempt_frame
     * @return array{plotly_json: array, correlation: float, y_label: string, title: string}|null
     */
    public static function build_scatter_figure(array $attempt_frame, string $grade_type, bool $colorblind_mode = false): ?array {
        if (empty($attempt_frame)) {
            return null;
        }

        // attempt_count per (quiz_name, student_id).
        $attempt_counts = [];
        foreach ($attempt_frame as $r) {
            $key = $r['quiz_name'] . '|' . $r['student_id'];
            $attempt_counts[$key] = ($attempt_counts[$key] ?? 0) + 1;
        }

        // grade_data per (quiz_name, student_id), per $grade_type.
        $grades_by_key = [];
        foreach ($attempt_frame as $r) {
            $key = $r['quiz_name'] . '|' . $r['student_id'];
            $grades_by_key[$key][] = $r['overall_grade'];
        }

        if ($grade_type === 'Highest Grade') {
            $agg = fn($g) => max($g);
            $y_label = 'Highest Grade';
            $title = 'Attempts vs Highest Grade';
        } else if ($grade_type === 'Minimum Grade') {
            $agg = fn($g) => min($g);
            $y_label = 'Minimum Grade';
            $title = 'Attempts vs Minimum Grade';
        } else {
            $agg = fn($g) => stats::mean($g);
            $y_label = 'Average Grade';
            $title = 'Attempts vs Average Grade';
        }

        $merged = [];
        foreach ($grades_by_key as $key => $grades) {
            [$quiz_name, $student_id] = explode('|', $key, 2);
            $merged[] = [
                'quiz_name' => $quiz_name,
                'student_id' => $student_id,
                'attempt_count' => $attempt_counts[$key],
                'overall_grade' => $agg($grades),
            ];
        }

        // Correlation from the true, unjittered values.
        $xs = array_map(fn($r) => (float) $r['attempt_count'], $merged);
        $ys = array_map(fn($r) => (float) $r['overall_grade'], $merged);
        $correlation = self::pearson_correlation($xs, $ys);

        // Group sizes for marker-size saturation, keyed on
        // (quiz_name, attempt_count, overall_grade) — matches the Python
        // groupby key exactly (pre-jitter coordinates).
        $group_sizes = [];
        foreach ($merged as $r) {
            $gkey = "{$r['quiz_name']}\x00{$r['attempt_count']}\x00{$r['overall_grade']}";
            $group_sizes[$gkey] = ($group_sizes[$gkey] ?? 0) + 1;
        }

        foreach ($merged as $i => $r) {
            $jitter_key = $r['quiz_name'] . '|' . $r['student_id'];
            $merged[$i]['attempt_count_plot'] = $r['attempt_count'] + self::deterministic_jitter($jitter_key, 0.15, 'x');
            $merged[$i]['overall_grade_plot'] = $r['overall_grade'] + self::deterministic_jitter($jitter_key, 0.15, 'y');
            $gkey = "{$r['quiz_name']}\x00{$r['attempt_count']}\x00{$r['overall_grade']}";
            $size = $group_sizes[$gkey];
            $saturation = (min($size, self::SCATTER_MARKER_SIZE_SATURATES_AT) - 1) / (self::SCATTER_MARKER_SIZE_SATURATES_AT - 1);
            $merged[$i]['_marker_size'] = self::SCATTER_MARKER_SIZE_MIN + $saturation * (self::SCATTER_MARKER_SIZE_MAX - self::SCATTER_MARKER_SIZE_MIN);
        }

        // $merged's per-quiz trace order needs to match Python's: both
        // attempt_count and grade_data come from a groupby(["quiz_name",
        // "student_id"]) there, which sorts by quiz_name first (sort=True
        // default) — a plain px.scatter() call over already-grouped-and-
        // sorted data preserves that row order into the trace order.
        // Within a single quiz's trace, points aren't additionally sorted by
        // student_id (Python's secondary groupby key) — a scatter trace's
        // own point order has no visual effect (it's an unordered cloud of
        // markers), so this is a deliberately unmatched, invisible ordering
        // difference, confirmed by comparing point sets rather than order.
        $quiz_names = [];
        foreach ($merged as $r) {
            $quiz_names[$r['quiz_name']] = true;
        }
        $quiz_names = array_keys($quiz_names);
        sort($quiz_names);
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_SET2);

        $data = [];
        foreach ($quiz_names as $i => $quiz) {
            $rows = array_values(array_filter($merged, fn($r) => $r['quiz_name'] === $quiz));
            $data[] = [
                'type' => 'scatter',
                'mode' => 'markers',
                'x' => array_map(fn($r) => $r['attempt_count_plot'], $rows),
                'y' => array_map(fn($r) => $r['overall_grade_plot'], $rows),
                'name' => (string) $quiz,
                'marker' => [
                    'size' => array_map(fn($r) => $r['_marker_size'], $rows),
                    'sizemode' => 'diameter',
                    'color' => $palette[$i % count($palette)],
                    'opacity' => 0.65,
                    'line' => ['width' => 1, 'color' => 'white'],
                ],
                'customdata' => array_map(fn($r) => [$r['attempt_count'], $r['overall_grade']], $rows),
                'hovertemplate' => "Attempts: %{customdata[0]}<br>{$y_label}: %{customdata[1]}<extra></extra>",
            ];
        }

        return [
            'plotly_json' => [
                'data' => $data,
                'layout' => [
                    'title' => ['text' => $title],
                    'legend' => ['title' => ['text' => 'Quiz']],
                    'xaxis' => ['title' => ['text' => 'No. of Attempts'], 'tickmode' => 'linear', 'dtick' => 1],
                    'yaxis' => ['title' => ['text' => $y_label]],
                ],
            ],
            'correlation' => $correlation,
            'y_label' => $y_label,
            'title' => $title,
        ];
    }

    private static function pearson_correlation(array $xs, array $ys): float {
        $n = count($xs);
        if ($n < 2) {
            return 0.0;
        }
        $mean_x = stats::mean($xs);
        $mean_y = stats::mean($ys);
        $cov = 0.0;
        $var_x = 0.0;
        $var_y = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $mean_x;
            $dy = $ys[$i] - $mean_y;
            $cov += $dx * $dy;
            $var_x += $dx * $dx;
            $var_y += $dy * $dy;
        }
        if ($var_x == 0.0 || $var_y == 0.0) {
            return NAN;
        }
        return $cov / sqrt($var_x * $var_y);
    }

    /**
     * @param array[] $attempt_frame
     * @param string[] $selected_metrics
     * @return array[] one row per quiz_name
     */
    public static function build_metric_trend_data(array $attempt_frame, array $selected_metrics): array {
        $by_quiz = table_helpers::group_by($attempt_frame, 'quiz_name');
        // Every metric here comes from its own groupby("quiz_name") in the
        // Python original (sort=True default) — see compute_quiz_stats()'s
        // identical note.
        $quiz_names = array_keys($by_quiz);
        sort($quiz_names);

        $out = [];
        foreach ($quiz_names as $quiz) {
            $rows = $by_quiz[$quiz];
            $entry = ['quiz_name' => $quiz];
            if (in_array('student_count', $selected_metrics, true)) {
                $entry['student_count'] = count(array_unique(array_map(fn($r) => $r['student_id'], $rows)));
            }
            if (in_array('attempt_rate', $selected_metrics, true)) {
                $counts = [];
                foreach ($rows as $r) {
                    $counts[$r['student_id']] = ($counts[$r['student_id']] ?? 0) + 1;
                }
                $entry['attempt_rate'] = stats::mean(array_values($counts));
            }
            if (in_array('mean_grade', $selected_metrics, true)) {
                $entry['mean_grade'] = stats::mean(array_map(fn($r) => $r['overall_grade'], $rows));
            }
            if (in_array('grade_variance', $selected_metrics, true)) {
                $entry['grade_variance'] = stats::sample_variance(array_map(fn($r) => $r['overall_grade'], $rows));
            }
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * @param array[] $trend_data one row per quiz_name, metric fields as
     *        selected by build_metric_trend_data()
     */
    public static function build_line_graph_figure(array $trend_data, bool $colorblind_mode = false): array {
        if (empty($trend_data)) {
            return ['data' => [], 'layout' => ['title' => ['text' => 'Line Graph of Various Metrics']]];
        }

        $metric_names = array_values(array_diff(array_keys($trend_data[0]), ['quiz_name']));
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_SET1);

        $wrapped_labels = array_map(fn($r) => self::wrap_category_label((string) $r['quiz_name']), $trend_data);

        $data = [];
        foreach ($metric_names as $i => $metric) {
            $data[] = [
                'type' => 'scatter',
                'mode' => 'lines+markers',
                'x' => $wrapped_labels,
                'y' => array_map(fn($r) => $r[$metric], $trend_data),
                'name' => chart_helpers::humanize_label($metric),
                'line' => ['color' => $palette[$i % count($palette)]],
                'marker' => ['color' => $palette[$i % count($palette)]],
            ];
        }

        $n_categories = max(count(array_unique(array_map(fn($r) => $r['quiz_name'], $trend_data))), 1);

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => 'Line Graph of Various Metrics'],
                'template' => 'plotly',
                'legend' => ['title' => ['text' => 'Metric']],
                'xaxis' => ['type' => 'category', 'tickangle' => 0, 'tickfont' => ['size' => 10], 'title' => ['text' => 'Quiz']],
                'yaxis' => ['title' => ['text' => 'Value']],
                'width' => max(800, 220 * $n_categories),
            ],
        ];
    }

    /**
     * Per-quiz gaussian KDE (Scott's rule bandwidth — the same method
     * seaborn.kdeplot/scipy.stats.gaussian_kde use) of attempt start dates.
     * Returns null if there's nothing plottable: any row across every quiz
     * missing started_on, or no quiz ends up with a usable (2+ distinct
     * values) date series.
     *
     * @param array[] $attempt_frame
     */
    public static function build_engagement_figure(array $attempt_frame, bool $colorblind_mode = false): ?array {
        if (empty($attempt_frame)) {
            return null;
        }
        foreach ($attempt_frame as $row) {
            if (empty($row['started_on'])) {
                return null;
            }
        }

        $quiz_names = [];
        foreach ($attempt_frame as $row) {
            $quiz_names[$row['quiz_name']] = true;
        }
        $quiz_names = array_keys($quiz_names);
        $palette = chart_helpers::qualitative_colors($colorblind_mode, chart_helpers::PALETTE_PLOTLY);

        $data = [];
        foreach ($quiz_names as $i => $quiz) {
            $rows = array_values(array_filter($attempt_frame, fn($r) => $r['quiz_name'] === $quiz));
            if (empty($rows)) {
                continue;
            }
            $dates_numeric = array_map(fn($r) => self::date_to_days((string) $r['started_on']), $rows);

            $kde = self::gaussian_kde_scott($dates_numeric);
            if ($kde === null) {
                continue;
            }
            [$mean, $variance] = $kde;

            $min_d = min($dates_numeric);
            $max_d = max($dates_numeric);
            $n_grid = 200;
            $grid = [];
            for ($g = 0; $g < $n_grid; $g++) {
                $grid[] = $min_d + ($max_d - $min_d) * $g / ($n_grid - 1);
            }
            $density = array_map(fn($x) => self::kde_density($x, $dates_numeric, $variance), $grid);

            $data[] = [
                'type' => 'scatter',
                'mode' => 'lines',
                'x' => array_map(fn($d) => self::days_to_iso($d), $grid),
                'y' => $density,
                'name' => (string) $quiz,
                'fill' => 'tozeroy',
                'line' => ['color' => $palette[$i % count($palette)]],
            ];
        }

        if (empty($data)) {
            return null;
        }

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => 'Engagement Over Time'],
                'xaxis' => ['title' => ['text' => 'Date']],
                'yaxis' => ['title' => ['text' => 'Frequency Density']],
            ],
        ];
    }

    /**
     * Scott's rule bandwidth (matching scipy.stats.gaussian_kde's default):
     * factor = n^(-1/5) for 1D data; covariance = sample_variance(data,
     * ddof=1) * factor^2. Returns null if the KDE is singular (fewer than 2
     * points, or zero variance) — scipy raises LinAlgError in that case,
     * which the Python original catches and skips that quiz's trace for.
     *
     * @param float[] $dates_numeric
     * @return array{0: float, 1: float}|null [mean, covariance]
     */
    private static function gaussian_kde_scott(array $dates_numeric): ?array {
        $n = count($dates_numeric);
        if ($n < 2) {
            return null;
        }
        $variance = stats::sample_variance($dates_numeric);
        if ($variance <= 0.0 || !is_finite($variance)) {
            return null;
        }
        $factor = $n ** (-1.0 / 5.0);
        $covariance = $variance * $factor * $factor;
        return [stats::mean($dates_numeric), $covariance];
    }

    /** @param float[] $data_points */
    private static function kde_density(float $x, array $data_points, float $covariance): float {
        $n = count($data_points);
        $norm = 1.0 / sqrt(2 * M_PI * $covariance);
        $sum = 0.0;
        foreach ($data_points as $xi) {
            $d = $x - $xi;
            $sum += $norm * exp(-($d * $d) / (2 * $covariance));
        }
        return $sum / $n;
    }

    /** Days since the Unix epoch (any fixed epoch works: only relative
     * differences between values matter for KDE bandwidth/shape). */
    private static function date_to_days(string $datetime_str): float {
        $ts = strtotime($datetime_str);
        return $ts !== false ? $ts / 86400.0 : 0.0;
    }

    /**
     * Sub-second digits can differ from the Python original's own
     * num2date()-based labels by a few hundred milliseconds — an inherent
     * floating-point precision artifact of matplotlib's date2num() using an
     * epoch of year 1 (a much larger day-count integer part, leaving less
     * float64 precision for the time-of-day fraction) vs. this using the
     * Unix epoch. The underlying density curve these label is identical;
     * only the displayed hover instant can drift by well under a second.
     */
    private static function days_to_iso(float $days): string {
        $ts = (int) round($days * 86400.0);
        return gmdate('Y-m-d\TH:i:s', $ts) . '+00:00';
    }
}
