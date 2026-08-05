<?php
/**
 * PHP port of analytics-service/analytics/solution_distance.py.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class solution_distance {

    const ANS_PATTERN = '/ans(\d+):\s*(.*?)\s*\[(score|valid|invalid)\]/';

    const MAX_TED_DISPLAY = 20;

    // Neon colorscale anchors for the 3D distance charts, in ascending
    // distance order: a response sitting exactly on the correct answer
    // (distance 0) is white, distance 1 is red, running up through orange,
    // yellow, green, and blue to black at the top of the observed range.
    const DISTANCE_COLOR_ANCHORS = [
        '#FFFFFF', '#FF1744', '#FF9100', '#FFEA00', '#39FF14', '#00B0FF', '#000000',
    ];

    const MARKER_OUTLINE = 'rgba(55, 65, 81, 0.9)';
    const SCENE_PANE = '#E5ECF6';
    const SCENE_GRID = '#FFFFFF';
    const SCENE_FONT = '#2A3F5F';
    const GRID_INSET = 0.004;
    const STUDENTS_AXIS_REVERSED = true;
    const BACKDROP_TRACE_NAME = '__scene_backdrop__';
    const DEFAULT_CAMERA_EYE = ['x' => 1.25, 'y' => 1.25, 'z' => 1.25];

    const GRADE_DISPLAY_SCALE = 10.0;

    const CROSS_ATTEMPT_METRICS = [
        'Grade' => ['axis_title' => 'Score (0-10)', 'higher_is_better' => true],
        'PRT Distance' => ['axis_title' => 'Type of Error (PRT distance)', 'higher_is_better' => false],
        'Tree Edit Distance' => ['axis_title' => 'Tree Edit Distance', 'higher_is_better' => false],
    ];

    const FLAT_TOLERANCE = 1e-6;

    const TREND_ORDER = ['Regressed', 'Flat', 'Improved'];

    /**
     * Distance from the correct answer for one response to one part, or null
     * if it belongs in the shared "other" sentinel bucket (filled in by
     * compute_prt_distance_series() once the per-question max classified
     * distance is known).
     */
    private static function raw_prt_distance(array $row, int $part_index = 1): ?int {
        $label = prt_transitions::classify_node($row, $part_index);
        if ($label === 'c') {
            return 0;
        }
        if ($label === '0') {
            return null;
        }
        return (int) $label;
    }

    /**
     * Adds a prt_distance field to the rows for one part of $question,
     * generalizing the teacher-authored "distance from correct answer" table:
     * 0 for a response with full marks on this part; node_number - 1 for a
     * response whose PRT trace terminated True at a named node; and one
     * shared "other" sentinel bucket — placed 3 past the question's largest
     * classified distance — for every response that terminated False, or has
     * no PRT trace at all (blank/invalid).
     *
     * @param array[] $response_rows
     * @return array[] rows for $question with a 'prt_distance' int field added
     */
    public static function compute_prt_distance_series(array $response_rows, string $question, int $part_index = 1): array {
        $subset = array_values(array_filter($response_rows, fn($r) => $r['question'] === $question));
        if (empty($subset)) {
            return [];
        }

        $raw = array_map(fn($r) => self::raw_prt_distance($r, $part_index), $subset);
        $classified = array_values(array_filter($raw, fn($v) => $v !== null));
        $sentinel = !empty($classified) ? (max($classified) + 3) : 3;

        foreach ($subset as $i => $row) {
            $subset[$i]['prt_distance'] = $raw[$i] ?? $sentinel;
        }
        return $subset;
    }

    /**
     * First ansN: <expr> [tag] expression from a raw response/right-answer
     * dump.
     */
    private static function extract_expression(string $text, int $ans_index = 1): ?string {
        if ($text === '') {
            return null;
        }
        if (preg_match_all(self::ANS_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if ((int) $m[1] === $ans_index) {
                    return trim($m[2]);
                }
            }
        }
        return null;
    }

    private static array $ted_cache = [];

    /**
     * TED for one (submitted, correct) expression-string pair, cached since
     * many students commonly submit the exact same wrong answer for a given
     * question.
     */
    private static function cached_tree_edit_distance(string $submitted_text, string $correct_text): ?int {
        $key = $submitted_text . "\x00" . $correct_text;
        if (array_key_exists($key, self::$ted_cache)) {
            return self::$ted_cache[$key];
        }
        $submitted_tree = expression_tree::parse_expression($submitted_text);
        $correct_tree = expression_tree::parse_expression($correct_text);
        $result = ($submitted_tree === null || $correct_tree === null)
            ? null
            : tree_edit_distance::tree_edit_distance($submitted_tree, $correct_tree);
        self::$ted_cache[$key] = $result;
        return $result;
    }

    /**
     * Adds a ted_distance (int or null) field to the rows for one part of
     * $question: the Zhang-Shasha tree edit distance between the expression
     * the student submitted for that part (ans{part_index}) and that part's
     * correct answer (the matching ans{part_index} in the first response row
     * with a parseable right_answer_text). Display values are clipped at
     * MAX_TED_DISPLAY. Rows whose submitted or correct expression can't be
     * parsed get ted_distance = null and should be excluded from any chart
     * built on this field.
     *
     * @param array[] $response_rows
     * @return array[] rows for $question with a 'ted_distance' field added
     */
    public static function compute_ted_distance_series(array $response_rows, string $question, int $part_index = 1): array {
        $subset = array_values(array_filter($response_rows, fn($r) => $r['question'] === $question));
        if (empty($subset)) {
            return [];
        }

        // Expression strings are checked with explicit null/empty-string
        // comparisons rather than PHP truthiness throughout this method: a
        // legitimate submitted or correct expression can be the literal
        // text "0", which is falsy in PHP (unlike Python, where a non-empty
        // string is always truthy) — `if ($expr)` would silently drop it.
        $correct_expr_text = null;
        foreach ($subset as $row) {
            $correct_expr_text = self::extract_expression((string) ($row['right_answer_text'] ?? ''), $part_index);
            if ($correct_expr_text !== null && $correct_expr_text !== '') {
                break;
            }
        }

        foreach ($subset as $i => $row) {
            $ted = null;
            if ($correct_expr_text !== null && $correct_expr_text !== '') {
                $submitted_expr = null;
                foreach (($row['ans_list'] ?? []) as $a) {
                    if (($a['index'] ?? null) === $part_index) {
                        $submitted_expr = $a['expression'] ?? null;
                        break;
                    }
                }
                if ($submitted_expr !== null && $submitted_expr !== '') {
                    $ted = self::cached_tree_edit_distance($submitted_expr, $correct_expr_text);
                }
            }
            $subset[$i]['ted_distance'] = $ted !== null ? min($ted, self::MAX_TED_DISPLAY) : null;
        }
        return $subset;
    }

    /**
     * Ordering for the "Students" axis: a nested sort where each attempt
     * breaks ties left by the previous one — a student's key is the full
     * ordered tuple of their per-attempt distances, compared
     * lexicographically (unparseable/null distances sort after every known
     * value, via PHP_INT_MAX standing in for Python's float('inf')).
     *
     * @param array[] $distance_subset rows with $distance_column present
     * @return array<string, int> student_id => 1-based rank
     */
    public static function compute_question_student_order(array $distance_subset, string $distance_column): array {
        if (empty($distance_subset)) {
            return [];
        }

        $rows = $distance_subset;
        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) $a['student_id'], (string) $b['student_id']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) $a['completed_dt'], (string) $b['completed_dt']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['attempt_idx'] <=> $b['attempt_idx'];
        });

        $by_student = [];
        foreach ($rows as $row) {
            $by_student[$row['student_id']][] = $row;
        }

        $entries = [];
        foreach ($by_student as $student_id => $group) {
            $sequence = array_map(function ($row) use ($distance_column) {
                $v = $row[$distance_column] ?? null;
                return $v !== null ? (float) $v : INF;
            }, $group);
            $entries[] = [$student_id, $sequence];
        }

        usort($entries, function ($a, $b) {
            [$sid_a, $seq_a] = $a;
            [$sid_b, $seq_b] = $b;
            $len = min(count($seq_a), count($seq_b));
            for ($i = 0; $i < $len; $i++) {
                if ($seq_a[$i] !== $seq_b[$i]) {
                    return $seq_a[$i] <=> $seq_b[$i];
                }
            }
            if (count($seq_a) !== count($seq_b)) {
                return count($seq_a) <=> count($seq_b);
            }
            return strcmp((string) $sid_a, (string) $sid_b);
        });

        $order = [];
        foreach ($entries as $rank => [$student_id, ]) {
            $order[$student_id] = $rank + 1;
        }
        return $order;
    }

    /**
     * Continuous Plotly colorscale anchored so that, regardless of
     * $max_value (the chart's own z-axis range), a distance of exactly 0
     * always renders white and exactly 1 always renders neon red, with the
     * remaining neon anchors (orange, yellow, green, blue, black) spread
     * evenly across whatever range is left up to $max_value.
     *
     * @return array<int, array{0: float, 1: string}>
     */
    private static function build_distance_colorscale(float $max_value): array {
        $anchors = self::DISTANCE_COLOR_ANCHORS;
        $white = $anchors[0];
        $red = $anchors[1];
        $remaining_anchors = array_slice($anchors, 2);

        if ($max_value <= 0) {
            return [[0.0, $white], [1.0, $white]];
        }

        $red_position = min(1.0, 1.0 / $max_value);
        $stops = [[0.0, $white], [$red_position, $red]];
        if ($red_position >= 1.0) {
            return $stops;
        }

        $span = 1.0 - $red_position;
        $last_index = count($remaining_anchors);
        foreach ($remaining_anchors as $i => $color) {
            $index = $i + 1;
            $position = ($index === $last_index) ? 1.0 : $red_position + $span * $index / $last_index;
            $stops[] = [$position, $color];
        }
        return $stops;
    }

    /**
     * Gridline/tick spacing for the Students axis: roughly 8 divisions,
     * snapped to a 1/2/5-times-power-of-ten step so the labels read as round
     * numbers.
     */
    private static function nice_step(float $span): int {
        if ($span <= 8) {
            return 1;
        }
        $rough = $span / 8;
        $magnitude = 10 ** floor(log10($rough));
        foreach ([1, 2, 5, 10] as $multiple) {
            if ($rough <= $multiple * $magnitude) {
                return (int) ($multiple * $magnitude);
            }
        }
        return (int) (10 * $magnitude);
    }

    /**
     * Whole-number tick positions inside $axis_range.
     *
     * @param array{0: float, 1: float} $axis_range
     * @return int[]
     */
    private static function integer_ticks(array $axis_range, int $step = 1): array {
        [$low, $high] = $axis_range;
        $start = (int) (ceil($low / $step)) * $step;
        $end = (int) floor($high);
        if ($start > $end) {
            return [];
        }
        return range($start, $end, $step);
    }

    /**
     * The three scene walls (floor, back, side) plus their gridlines, as
     * fixed geometry — see solution_distance.py's _static_backdrop_traces()
     * docstring for why these are drawn as traces rather than left to
     * Plotly's own (camera-relative, so unstable under rotation) 3D panes.
     *
     * @param array{0: float, 1: float} $x_range
     * @param array{0: float, 1: float} $y_range
     * @param array{0: float, 1: float} $z_range
     * @param int[] $x_ticks
     * @param int[] $y_ticks
     * @param int[] $z_ticks
     * @return array[] Plotly trace dicts (mesh3d walls + one scatter3d gridline trace)
     */
    private static function static_backdrop_traces(
        array $x_range, array $y_range, array $z_range,
        array $x_ticks, array $y_ticks, array $z_ticks
    ): array {
        [$x0, $x1] = $x_range;
        [$y0, $y1] = $y_range;
        [$z0, $z1] = $z_range;

        [$xw, $x_inward] = self::STUDENTS_AXIS_REVERSED ? [$x1, -1.0] : [$x0, 1.0];
        [$yw, $y_inward] = [$y0, 1.0];
        [$zw, $z_inward] = [$z0, 1.0];

        $quads = [
            ['x' => [$x0, $x1, $x1, $x0], 'y' => [$y0, $y0, $y1, $y1], 'z' => [$zw, $zw, $zw, $zw]],
            ['x' => [$x0, $x1, $x1, $x0], 'y' => [$yw, $yw, $yw, $yw], 'z' => [$z0, $z0, $z1, $z1]],
            ['x' => [$xw, $xw, $xw, $xw], 'y' => [$y0, $y1, $y1, $y0], 'z' => [$z0, $z0, $z1, $z1]],
        ];

        $traces = [];
        foreach ($quads as $quad) {
            $traces[] = array_merge($quad, [
                'type' => 'mesh3d',
                'i' => [0, 0], 'j' => [1, 2], 'k' => [2, 3],
                'color' => self::SCENE_PANE,
                'flatshading' => true,
                'lighting' => ['ambient' => 1.0, 'diffuse' => 0.0, 'specular' => 0.0, 'roughness' => 1.0, 'fresnel' => 0.0],
                'hoverinfo' => 'skip',
                'showscale' => false,
                'showlegend' => false,
                'name' => self::BACKDROP_TRACE_NAME,
            ]);
        }

        $xg = $xw + $x_inward * self::GRID_INSET * ($x1 - $x0);
        $yg = $yw + $y_inward * self::GRID_INSET * ($y1 - $y0);
        $zg = $zw + $z_inward * self::GRID_INSET * ($z1 - $z0);

        $grid_x = [];
        $grid_y = [];
        $grid_z = [];
        $segment = function ($start, $end) use (&$grid_x, &$grid_y, &$grid_z) {
            $grid_x[] = $start[0]; $grid_x[] = $end[0]; $grid_x[] = null;
            $grid_y[] = $start[1]; $grid_y[] = $end[1]; $grid_y[] = null;
            $grid_z[] = $start[2]; $grid_z[] = $end[2]; $grid_z[] = null;
        };

        foreach ($x_ticks as $x_value) {
            $segment([$x_value, $y0, $zg], [$x_value, $y1, $zg]);
            $segment([$x_value, $yg, $z0], [$x_value, $yg, $z1]);
        }
        foreach ($y_ticks as $y_value) {
            $segment([$x0, $y_value, $zg], [$x1, $y_value, $zg]);
            $segment([$xg, $y_value, $z0], [$xg, $y_value, $z1]);
        }
        foreach ($z_ticks as $z_value) {
            $segment([$x0, $yg, $z_value], [$x1, $yg, $z_value]);
            $segment([$xg, $y0, $z_value], [$xg, $y1, $z_value]);
        }
        $segment([$xw, $yw, $z0], [$xw, $yw, $z1]);

        $traces[] = [
            'type' => 'scatter3d',
            'x' => $grid_x, 'y' => $grid_y, 'z' => $grid_z,
            'mode' => 'lines',
            'line' => ['color' => self::SCENE_GRID, 'width' => 2],
            'hoverinfo' => 'skip',
            'showlegend' => false,
            'name' => self::BACKDROP_TRACE_NAME,
        ];
        return $traces;
    }

    /**
     * @param array[] $distance_subset
     */
    private static function build_distance_3d_figure(
        array $distance_subset, string $distance_column, string $z_title, string $title
    ): array {
        $student_order = self::compute_question_student_order($distance_subset, $distance_column);

        $plot_rows = array_values(array_filter($distance_subset, fn($r) => ($r[$distance_column] ?? null) !== null));
        usort($plot_rows, function ($a, $b) {
            $cmp = strcmp((string) $a['student_id'], (string) $b['student_id']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) $a['completed_dt'], (string) $b['completed_dt']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['attempt_idx'] <=> $b['attempt_idx'];
        });

        $max_value = 0.0;
        foreach ($plot_rows as $row) {
            $max_value = max($max_value, (float) $row[$distance_column]);
        }
        $colorscale = self::build_distance_colorscale($max_value);

        $groups = [];
        $group_order = [];
        foreach ($plot_rows as $row) {
            $sid = $row['student_id'];
            if (!isset($groups[$sid])) {
                $groups[$sid] = [];
                $group_order[] = $sid;
            }
            $groups[$sid][] = $row;
        }

        $data = [];
        $is_first_trace = true;
        $max_attempts = 1;
        foreach ($group_order as $student_id) {
            if (!isset($student_order[$student_id])) {
                continue;
            }
            $group = $groups[$student_id];
            $max_attempts = max($max_attempts, count($group));
            $student_name = $group[0]['student_name'] ?? $student_id;
            $z_values = array_map(fn($r) => (float) $r[$distance_column], $group);

            $marker = [
                'size' => 5, 'color' => $z_values, 'colorscale' => $colorscale,
                'cmin' => 0, 'cmax' => $max_value,
                'line' => ['width' => 1, 'color' => self::MARKER_OUTLINE],
                'showscale' => $is_first_trace,
            ];
            if ($is_first_trace) {
                $marker['colorbar'] = ['title' => ['text' => $z_title]];
            }

            $data[] = [
                'type' => 'scatter3d',
                'x' => array_fill(0, count($group), $student_order[$student_id]),
                'y' => range(0, count($group) - 1),
                'z' => $z_values,
                'mode' => 'lines+markers',
                'marker' => $marker,
                'line' => ['width' => 4, 'color' => $z_values, 'colorscale' => $colorscale, 'cmin' => 0, 'cmax' => $max_value],
                'showlegend' => false,
                'text' => array_fill(0, count($group), (string) $student_name),
                'hovertemplate' => '%{text}<br>Attempt %{y}<br>Rank %{x}<br>Distance %{z}<extra></extra>',
            ];
            $is_first_trace = false;
        }

        $student_count = count($student_order);
        $x_range = [0.5, max($student_count, 1) + 0.5];
        $y_range = [-0.5, max($max_attempts - 1, 1) + 0.5];
        $z_range = [-0.5, max($max_value, 1.0) + 0.5];

        $x_ticks = self::integer_ticks($x_range, self::nice_step(max($student_count, 1)));
        $y_ticks = self::integer_ticks($y_range);
        $z_ticks = self::integer_ticks($z_range);

        $students_axis_range = self::STUDENTS_AXIS_REVERSED ? array_reverse($x_range) : $x_range;

        foreach (self::static_backdrop_traces($x_range, $y_range, $z_range, $x_ticks, $y_ticks, $z_ticks) as $trace) {
            $data[] = $trace;
        }

        $axis_common = [
            'showbackground' => false, 'showgrid' => false, 'zeroline' => false,
            'showspikes' => false, 'color' => self::SCENE_FONT, 'tickmode' => 'array',
        ];

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'scene' => [
                    'xaxis' => array_merge(['title' => ['text' => 'Students'], 'range' => $students_axis_range, 'tickvals' => $x_ticks], $axis_common),
                    'yaxis' => array_merge(['title' => ['text' => 'Attempt'], 'range' => $y_range, 'tickvals' => $y_ticks], $axis_common),
                    'zaxis' => array_merge(['title' => ['text' => $z_title], 'range' => $z_range, 'tickvals' => $z_ticks], $axis_common),
                    'aspectmode' => 'cube',
                    'camera' => ['eye' => self::DEFAULT_CAMERA_EYE, 'up' => ['x' => 0, 'y' => 0, 'z' => 1]],
                ],
                'margin' => ['l' => 0, 'r' => 0, 't' => 50, 'b' => 0],
                // Without an explicit height, this fills its container's
                // width (Plotly's "responsive" sizing) but only Plotly's
                // default ~450px height — since aspectmode=cube constrains
                // all three scene axes equally, the shorter of the two
                // dimensions decides how big the cube can actually grow,
                // which left it rendering small with a lot of unused
                // horizontal space around it. A generous fixed height gives
                // the cube as much room as the width normally would, both
                // on screen and in the client-captured PDF chart image
                // (captured at this same container size).
                'height' => 650,
            ],
        ];
    }

    public static function build_prt_distance_3d_figure(array $response_rows, string $question, int $part_index = 1): array {
        $subset = self::compute_prt_distance_series($response_rows, $question, $part_index);
        return self::build_distance_3d_figure(
            $subset, 'prt_distance', 'Type of Error (PRT distance)',
            "PRT-Distance Solution Process — {$question} (part {$part_index})"
        );
    }

    public static function build_ted_distance_3d_figure(array $response_rows, string $question, int $part_index = 1): array {
        $subset = self::compute_ted_distance_series($response_rows, $question, $part_index);
        return self::build_distance_3d_figure(
            $subset, 'ted_distance', 'Tree Edit Distance',
            "TED Solution Process — {$question} (part {$part_index})"
        );
    }

    // -----------------------------------------------------------------
    // Cross-Attempt Comparison: per student, per question, how did their
    // score/distance on their own retakes of this question change from
    // their first attempt to their last?
    // -----------------------------------------------------------------

    /**
     * Per-attempt values of $metric (one of the keys in
     * CROSS_ATTEMPT_METRICS) for every student on $question who has 2+
     * qualifying attempts — one with a single attempt has no change to show,
     * and is dropped rather than plotted as a lone point.
     *
     * @param array[] $response_rows
     * @return array[] one row per qualifying (student, attempt): student_id,
     *         student_name, attempt_number (1-based, sequential within that
     *         student's own attempts on this question), value, completed_dt
     */
    public static function compute_cross_attempt_comparison(
        array $response_rows, string $question, string $metric, int $part_index = 1
    ): array {
        if (!array_key_exists($metric, self::CROSS_ATTEMPT_METRICS)) {
            throw new \InvalidArgumentException("Unknown Cross-Attempt Comparison metric: {$metric}");
        }
        if (empty($response_rows)) {
            return [];
        }

        if ($metric === 'Grade') {
            $subset = array_values(array_filter($response_rows, fn($r) => $r['question'] === $question));
            foreach ($subset as $i => $row) {
                $subset[$i]['value'] = $row['grade'] !== null ? $row['grade'] * self::GRADE_DISPLAY_SCALE : null;
            }
        } else if ($metric === 'PRT Distance') {
            $subset = self::compute_prt_distance_series($response_rows, $question, $part_index);
            foreach ($subset as $i => $row) {
                $subset[$i]['value'] = $row['prt_distance'];
            }
        } else {
            $subset = self::compute_ted_distance_series($response_rows, $question, $part_index);
            foreach ($subset as $i => $row) {
                $subset[$i]['value'] = $row['ted_distance'];
            }
        }

        $subset = array_values(array_filter($subset, fn($r) => $r['value'] !== null));
        if (empty($subset)) {
            return [];
        }

        usort($subset, function ($a, $b) {
            $cmp = strcmp((string) $a['student_id'], (string) $b['student_id']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) $a['completed_dt'], (string) $b['completed_dt']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['attempt_idx'] <=> $b['attempt_idx'];
        });

        $counts = [];
        foreach ($subset as $i => $row) {
            $sid = $row['student_id'];
            $counts[$sid] = ($counts[$sid] ?? 0) + 1;
            $subset[$i]['attempt_number'] = $counts[$sid];
        }

        $max_by_student = [];
        foreach ($subset as $row) {
            $sid = $row['student_id'];
            $max_by_student[$sid] = max($max_by_student[$sid] ?? 0, $row['attempt_number']);
        }
        $subset = array_values(array_filter($subset, fn($r) => $max_by_student[$r['student_id']] >= 2));

        return array_map(fn($r) => [
            'student_id' => $r['student_id'],
            'student_name' => $r['student_name'],
            'attempt_number' => $r['attempt_number'],
            'value' => $r['value'],
            'completed_dt' => $r['completed_dt'],
        ], $subset);
    }

    /**
     * One row per student: their first and last qualifying-attempt value,
     * how many qualifying attempts they made (attempt_count), a uniformly
     * improvement-positive change (positive = improved, negative =
     * regressed), and a trend label. Sorted by change descending, i.e. most
     * improved first.
     *
     * @param array[] $comparison
     * @return array[]
     */
    public static function classify_cross_attempt_trends(array $comparison, bool $higher_is_better): array {
        if (empty($comparison)) {
            return [];
        }

        $by_student = [];
        $order = [];
        foreach ($comparison as $row) {
            $sid = $row['student_id'];
            if (!isset($by_student[$sid])) {
                $by_student[$sid] = [];
                $order[] = $sid;
            }
            $by_student[$sid][] = $row;
        }

        $rows = [];
        foreach ($order as $student_id) {
            $group = $by_student[$student_id];
            usort($group, fn($a, $b) => $a['attempt_number'] <=> $b['attempt_number']);
            $first_value = (float) $group[0]['value'];
            $last_value = (float) end($group)['value'];
            $raw_delta = $last_value - $first_value;
            $change = $higher_is_better ? $raw_delta : -$raw_delta;
            if (abs($change) <= self::FLAT_TOLERANCE) {
                $trend = 'Flat';
            } else if ($change > 0) {
                $trend = 'Improved';
            } else {
                $trend = 'Regressed';
            }
            $max_attempt_number = max(array_map(fn($r) => $r['attempt_number'], $group));
            $rows[] = [
                'student_id' => $student_id,
                'student_name' => $group[0]['student_name'],
                'attempt_count' => $max_attempt_number,
                'first_value' => $first_value,
                'last_value' => $last_value,
                'change' => $change,
                'trend' => $trend,
            ];
        }

        usort($rows, fn($a, $b) => $b['change'] <=> $a['change']);
        return $rows;
    }

    /**
     * One line per qualifying student: x = attempt number within their own
     * attempts on this question, y = the selected metric, colored by whether
     * they improved, stayed flat, or regressed between their first and last
     * attempt. Traces are grouped by trend (not one legend entry per
     * student), so the legend stays exactly 3 entries however many students
     * are plotted.
     *
     * @param array[] $comparison
     * @param array[] $trends
     */
    public static function build_cross_attempt_figure(array $comparison, array $trends, string $metric, bool $colorblind_mode): array {
        $axis_title = self::CROSS_ATTEMPT_METRICS[$metric]['axis_title'];
        $palette = chart_helpers::pass_fail_scale($colorblind_mode);
        $trend_color = array_combine(self::TREND_ORDER, $palette);

        $trend_by_student = [];
        foreach ($trends as $t) {
            $trend_by_student[$t['student_id']] = $t['trend'];
        }

        $by_student = [];
        foreach ($comparison as $row) {
            $by_student[$row['student_id']][] = $row;
        }

        $data = [];
        foreach (self::TREND_ORDER as $trend) {
            $student_ids = array_keys(array_filter($trend_by_student, fn($t) => $t === $trend));
            $is_first_in_group = true;
            foreach ($student_ids as $student_id) {
                $group = $by_student[$student_id] ?? [];
                usort($group, fn($a, $b) => $a['attempt_number'] <=> $b['attempt_number']);
                if (empty($group)) {
                    continue;
                }
                $student_name = $group[0]['student_name'];
                $data[] = [
                    'type' => 'scatter',
                    'x' => array_map(fn($r) => $r['attempt_number'], $group),
                    'y' => array_map(fn($r) => $r['value'], $group),
                    'mode' => 'lines+markers',
                    'line' => ['color' => $trend_color[$trend], 'width' => 2],
                    'marker' => ['size' => 6, 'color' => $trend_color[$trend]],
                    'opacity' => 0.8,
                    'name' => $trend,
                    'legendgroup' => $trend,
                    'showlegend' => $is_first_in_group,
                    'text' => array_fill(0, count($group), (string) $student_name),
                    'hovertemplate' => "%{text}<br>Attempt %{x}<br>{$axis_title}: %{y}<extra></extra>",
                ];
                $is_first_in_group = false;
            }
        }

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => "Cross-Attempt Comparison — {$metric}"],
                'legend' => ['title' => ['text' => 'Trend (first → last attempt)']],
                'template' => 'plotly',
                'xaxis' => ['title' => ['text' => 'Attempt'], 'dtick' => 1],
                'yaxis' => ['title' => ['text' => $axis_title]],
            ],
        ];
    }

    /**
     * A focused view of exactly one student's own attempts on this question.
     * $detail is $comparison already filtered to one student_id.
     *
     * @param array[] $detail
     */
    public static function build_single_student_attempt_figure(
        array $detail, string $student_name, string $metric, string $trend, bool $colorblind_mode
    ): array {
        $axis_title = self::CROSS_ATTEMPT_METRICS[$metric]['axis_title'];
        $palette = chart_helpers::pass_fail_scale($colorblind_mode);
        $trend_color = array_combine(self::TREND_ORDER, $palette);
        $color = $trend_color[$trend] ?? $trend_color['Flat'];

        $ordered = $detail;
        usort($ordered, fn($a, $b) => $a['attempt_number'] <=> $b['attempt_number']);

        return [
            'data' => [[
                'type' => 'scatter',
                'x' => array_map(fn($r) => $r['attempt_number'], $ordered),
                'y' => array_map(fn($r) => $r['value'], $ordered),
                'mode' => 'lines+markers',
                'line' => ['color' => $color, 'width' => 3],
                'marker' => ['size' => 10, 'color' => $color],
                'hovertemplate' => "Attempt %{x}<br>{$axis_title}: %{y}<extra></extra>",
            ]],
            'layout' => [
                'title' => ['text' => "{$student_name} — {$metric} across attempts"],
                'template' => 'plotly',
                'showlegend' => false,
                'xaxis' => ['title' => ['text' => 'Attempt'], 'dtick' => 1],
                'yaxis' => ['title' => ['text' => $axis_title]],
            ],
        ];
    }
}
