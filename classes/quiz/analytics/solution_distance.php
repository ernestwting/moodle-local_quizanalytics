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
 * PHP port of analytics-service/analytics/solution_distance.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * PRT-distance and tree-edit-distance computations and their 3D/cross-attempt charts.
 */
class solution_distance {
    /** @var string Matches one "ansK: ... [tag]" PRT answer-note segment. */
    const ANS_PATTERN = '/ans(\w+):\s*(.*?)\s*\[(score|valid|invalid)\]/';

    /** @var int Cap on distinct tree-edit-distance values shown before bucketing the rest into "other". */
    const MAX_TED_DISPLAY = 20;

    /**
     * Neon colorscale anchors for the 3D distance charts, in ascending
     * distance order: a response sitting exactly on the correct answer
     * (distance 0) is white, distance 1 is red, running up through orange,
     * yellow, green, and blue to black at the top of the observed range.
     *
     * @var string[]
     */
    const DISTANCE_COLOR_ANCHORS = [
        '#FFFFFF', '#FF1744', '#FF9100', '#FFEA00', '#39FF14', '#00B0FF', '#000000',
    ];

    /** @var string Marker border color for the 3D distance chart scatter points. */
    const MARKER_OUTLINE = 'rgba(55, 65, 81, 0.9)';

    /** @var string 3D scene background pane color. */
    const SCENE_PANE = '#E5ECF6';

    /** @var string 3D scene grid line color. */
    const SCENE_GRID = '#FFFFFF';

    /** @var string 3D scene axis label/tick font color. */
    const SCENE_FONT = '#2A3F5F';

    /** @var float Small inset so axis gridlines don't visually clip at the scene boundary. */
    const GRID_INSET = 0.004;

    /** @var bool Whether the students axis renders in reverse order in the 3D scene. */
    const STUDENTS_AXIS_REVERSED = true;

    /** @var string Internal trace name for the invisible backdrop mesh used to size the 3D scene. */
    const BACKDROP_TRACE_NAME = '__scene_backdrop__';

    /** @var array{x: float, y: float, z: float} Default initial camera position for the 3D scene. */
    const DEFAULT_CAMERA_EYE = ['x' => 1.25, 'y' => 1.25, 'z' => 1.25];

    /** @var float Multiplier converting a 0-1 grade to the displayed 0-10 scale. */
    const GRADE_DISPLAY_SCALE = 10.0;

    /** @var array<string, array{axis_title: string, higher_is_better: bool}> Cross-attempt comparison metric definitions. */
    const CROSS_ATTEMPT_METRICS = [
        'Grade' => ['axis_title' => 'Score (0-10)', 'higher_is_better' => true],
        'PRT Distance' => ['axis_title' => 'Type of Error (PRT distance)', 'higher_is_better' => false],
        'Tree Edit Distance' => ['axis_title' => 'Tree Edit Distance', 'higher_is_better' => false],
    ];

    /** @var float Threshold below which a metric's change between attempts counts as "Flat" rather than a trend. */
    const FLAT_TOLERANCE = 1e-6;

    /** @var string[] Display order for the cross-attempt trend classification. */
    const TREND_ORDER = ['Regressed', 'Flat', 'Improved'];

    /**
     * Distance from the correct answer for one response to one part, or null
     * if it belongs in the shared "other" sentinel bucket (filled in by
     * compute_prt_distance_series() once the per-question max classified
     * distance is known).
     */
    private static function raw_prt_distance(array $row, int $partindex = 1): ?int {
        $label = prt_transitions::classify_node($row, $partindex);
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
     * @param array[] $responserows
     * @return array[] rows for $question with a 'prt_distance' int field added
     */
    public static function compute_prt_distance_series(array $responserows, string $question, int $partindex = 1): array {
        $subset = array_values(array_filter($responserows, fn($r) => $r['question'] === $question));
        if (empty($subset)) {
            return [];
        }

        $raw = array_map(fn($r) => self::raw_prt_distance($r, $partindex), $subset);
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
    private static function extract_expression(string $text, int $ansindex = 1): ?string {
        if ($text === '') {
            return null;
        }
        if (preg_match_all(self::ANS_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if ((int) $m[1] === $ansindex) {
                    return trim($m[2]);
                }
            }
        }
        return null;
    }

    /** @var array<string, int|null> Memoized tree_edit_distance() results, keyed by submitted+correct answer pair. */
    private static array $tedcache = [];

    /**
     * TED for one (submitted, correct) expression-string pair, cached since
     * many students commonly submit the exact same wrong answer for a given
     * question.
     */
    private static function cached_tree_edit_distance(string $submittedtext, string $correcttext): ?int {
        $key = $submittedtext . "\x00" . $correcttext;
        if (array_key_exists($key, self::$tedcache)) {
            return self::$tedcache[$key];
        }
        $submittedtree = expression_tree::parse_expression($submittedtext);
        $correcttree = expression_tree::parse_expression($correcttext);
        $result = ($submittedtree === null || $correcttree === null)
            ? null
            : tree_edit_distance::compute($submittedtree, $correcttree);
        self::$tedcache[$key] = $result;
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
     * @param array[] $responserows
     * @return array[] rows for $question with a 'ted_distance' field added
     */
    public static function compute_ted_distance_series(array $responserows, string $question, int $partindex = 1): array {
        $subset = array_values(array_filter($responserows, fn($r) => $r['question'] === $question));
        if (empty($subset)) {
            return [];
        }

        // Expression strings are checked with explicit null/empty-string
        // comparisons rather than PHP truthiness throughout this method: a
        // legitimate submitted or correct expression can be the literal
        // text "0", which is falsy in PHP (unlike Python, where a non-empty
        // string is always truthy) — `if ($expr)` would silently drop it.
        $correctexprtext = null;
        foreach ($subset as $row) {
            $correctexprtext = self::extract_expression((string) ($row['right_answer_text'] ?? ''), $partindex);
            if ($correctexprtext !== null && $correctexprtext !== '') {
                break;
            }
        }

        foreach ($subset as $i => $row) {
            $ted = null;
            if ($correctexprtext !== null && $correctexprtext !== '') {
                $submittedexpr = null;
                foreach (($row['ans_list'] ?? []) as $a) {
                    if (($a['index'] ?? null) === $partindex) {
                        $submittedexpr = $a['expression'] ?? null;
                        break;
                    }
                }
                if ($submittedexpr !== null && $submittedexpr !== '') {
                    $ted = self::cached_tree_edit_distance($submittedexpr, $correctexprtext);
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
     * @param array[] $distancesubset rows with $distancecolumn present
     * @return array<string, int> student_id => 1-based rank
     */
    public static function compute_question_student_order(array $distancesubset, string $distancecolumn): array {
        if (empty($distancesubset)) {
            return [];
        }

        $rows = $distancesubset;
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

        $bystudent = [];
        foreach ($rows as $row) {
            $bystudent[$row['student_id']][] = $row;
        }

        $entries = [];
        foreach ($bystudent as $studentid => $group) {
            $sequence = array_map(function ($row) use ($distancecolumn) {
                $v = $row[$distancecolumn] ?? null;
                return $v !== null ? (float) $v : INF;
            }, $group);
            $entries[] = [$studentid, $sequence];
        }

        usort($entries, function ($a, $b) {
            [$sida, $seqa] = $a;
            [$sidb, $seqb] = $b;
            $len = min(count($seqa), count($seqb));
            for ($i = 0; $i < $len; $i++) {
                if ($seqa[$i] !== $seqb[$i]) {
                    return $seqa[$i] <=> $seqb[$i];
                }
            }
            if (count($seqa) !== count($seqb)) {
                return count($seqa) <=> count($seqb);
            }
            return strcmp((string) $sida, (string) $sidb);
        });

        $order = [];
        foreach ($entries as $rank => [$studentid, ]) {
            $order[$studentid] = $rank + 1;
        }
        return $order;
    }

    /**
     * Continuous Plotly colorscale anchored so that, regardless of
     * $maxvalue (the chart's own z-axis range), a distance of exactly 0
     * always renders white and exactly 1 always renders neon red, with the
     * remaining neon anchors (orange, yellow, green, blue, black) spread
     * evenly across whatever range is left up to $maxvalue.
     *
     * @return array<int, array{0: float, 1: string}>
     */
    private static function build_distance_colorscale(float $maxvalue): array {
        $anchors = self::DISTANCE_COLOR_ANCHORS;
        $white = $anchors[0];
        $red = $anchors[1];
        $remaininganchors = array_slice($anchors, 2);

        if ($maxvalue <= 0) {
            return [[0.0, $white], [1.0, $white]];
        }

        $redposition = min(1.0, 1.0 / $maxvalue);
        $stops = [[0.0, $white], [$redposition, $red]];
        if ($redposition >= 1.0) {
            return $stops;
        }

        $span = 1.0 - $redposition;
        $lastindex = count($remaininganchors);
        foreach ($remaininganchors as $i => $color) {
            $index = $i + 1;
            $position = ($index === $lastindex) ? 1.0 : $redposition + $span * $index / $lastindex;
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
     * Whole-number tick positions inside $axisrange.
     *
     * @param array{0: float, 1: float} $axisrange
     * @return int[]
     */
    private static function integer_ticks(array $axisrange, int $step = 1): array {
        [$low, $high] = $axisrange;
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
     * @param array{0: float, 1: float} $xrange
     * @param array{0: float, 1: float} $yrange
     * @param array{0: float, 1: float} $zrange
     * @param int[] $xticks
     * @param int[] $yticks
     * @param int[] $zticks
     * @return array[] Plotly trace dicts (mesh3d walls + one scatter3d gridline trace)
     */
    private static function static_backdrop_traces(
        array $xrange,
        array $yrange,
        array $zrange,
        array $xticks,
        array $yticks,
        array $zticks
    ): array {
        [$x0, $x1] = $xrange;
        [$y0, $y1] = $yrange;
        [$z0, $z1] = $zrange;

        [$xw, $xinward] = self::STUDENTS_AXIS_REVERSED ? [$x1, -1.0] : [$x0, 1.0];
        [$yw, $yinward] = [$y0, 1.0];
        [$zw, $zinward] = [$z0, 1.0];

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

        $xg = $xw + $xinward * self::GRID_INSET * ($x1 - $x0);
        $yg = $yw + $yinward * self::GRID_INSET * ($y1 - $y0);
        $zg = $zw + $zinward * self::GRID_INSET * ($z1 - $z0);

        $gridx = [];
        $gridy = [];
        $gridz = [];
        $segment = function ($start, $end) use (&$gridx, &$gridy, &$gridz) {
            $gridx[] = $start[0];
            $gridx[] = $end[0];
            $gridx[] = null;
            $gridy[] = $start[1];
            $gridy[] = $end[1];
            $gridy[] = null;
            $gridz[] = $start[2];
            $gridz[] = $end[2];
            $gridz[] = null;
        };

        foreach ($xticks as $xvalue) {
            $segment([$xvalue, $y0, $zg], [$xvalue, $y1, $zg]);
            $segment([$xvalue, $yg, $z0], [$xvalue, $yg, $z1]);
        }
        foreach ($yticks as $yvalue) {
            $segment([$x0, $yvalue, $zg], [$x1, $yvalue, $zg]);
            $segment([$xg, $yvalue, $z0], [$xg, $yvalue, $z1]);
        }
        foreach ($zticks as $zvalue) {
            $segment([$x0, $yg, $zvalue], [$x1, $yg, $zvalue]);
            $segment([$xg, $y0, $zvalue], [$xg, $y1, $zvalue]);
        }
        $segment([$xw, $yw, $z0], [$xw, $yw, $z1]);

        $traces[] = [
            'type' => 'scatter3d',
            'x' => $gridx, 'y' => $gridy, 'z' => $gridz,
            'mode' => 'lines',
            'line' => ['color' => self::SCENE_GRID, 'width' => 2],
            'hoverinfo' => 'skip',
            'showlegend' => false,
            'name' => self::BACKDROP_TRACE_NAME,
        ];
        return $traces;
    }

    /**
     * Shared 3D scatter builder for the PRT-distance and tree-edit-distance charts.
     *
     * @param array[] $distancesubset
     */
    private static function build_distance_3d_figure(
        array $distancesubset,
        string $distancecolumn,
        string $ztitle,
        string $title
    ): array {
        $studentorder = self::compute_question_student_order($distancesubset, $distancecolumn);

        $plotrows = array_values(array_filter($distancesubset, fn($r) => ($r[$distancecolumn] ?? null) !== null));
        usort($plotrows, function ($a, $b) {
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

        $maxvalue = 0.0;
        foreach ($plotrows as $row) {
            $maxvalue = max($maxvalue, (float) $row[$distancecolumn]);
        }
        $colorscale = self::build_distance_colorscale($maxvalue);

        $groups = [];
        $grouporder = [];
        foreach ($plotrows as $row) {
            $sid = $row['student_id'];
            if (!isset($groups[$sid])) {
                $groups[$sid] = [];
                $grouporder[] = $sid;
            }
            $groups[$sid][] = $row;
        }

        $data = [];
        $isfirsttrace = true;
        $maxattempts = 1;
        foreach ($grouporder as $studentid) {
            if (!isset($studentorder[$studentid])) {
                continue;
            }
            $group = $groups[$studentid];
            $maxattempts = max($maxattempts, count($group));
            $studentname = $group[0]['student_name'] ?? $studentid;
            $zvalues = array_map(fn($r) => (float) $r[$distancecolumn], $group);

            $marker = [
                'size' => 5, 'color' => $zvalues, 'colorscale' => $colorscale,
                'cmin' => 0, 'cmax' => $maxvalue,
                'line' => ['width' => 1, 'color' => self::MARKER_OUTLINE],
                'showscale' => $isfirsttrace,
            ];
            if ($isfirsttrace) {
                $marker['colorbar'] = ['title' => ['text' => $ztitle]];
            }

            $data[] = [
                'type' => 'scatter3d',
                'x' => array_fill(0, count($group), $studentorder[$studentid]),
                'y' => range(0, count($group) - 1),
                'z' => $zvalues,
                'mode' => 'lines+markers',
                'marker' => $marker,
                'line' => ['width' => 4, 'color' => $zvalues, 'colorscale' => $colorscale, 'cmin' => 0, 'cmax' => $maxvalue],
                'showlegend' => false,
                'text' => array_fill(0, count($group), (string) $studentname),
                'hovertemplate' => '%{text}<br>Attempt %{y}<br>Rank %{x}<br>Distance %{z}<extra></extra>',
            ];
            $isfirsttrace = false;
        }

        $studentcount = count($studentorder);
        $xrange = [0.5, max($studentcount, 1) + 0.5];
        $yrange = [-0.5, max($maxattempts - 1, 1) + 0.5];
        $zrange = [-0.5, max($maxvalue, 1.0) + 0.5];

        $xticks = self::integer_ticks($xrange, self::nice_step(max($studentcount, 1)));
        $yticks = self::integer_ticks($yrange);
        $zticks = self::integer_ticks($zrange);

        $studentsaxisrange = self::STUDENTS_AXIS_REVERSED ? array_reverse($xrange) : $xrange;

        foreach (self::static_backdrop_traces($xrange, $yrange, $zrange, $xticks, $yticks, $zticks) as $trace) {
            $data[] = $trace;
        }

        $axiscommon = [
            'showbackground' => false, 'showgrid' => false, 'zeroline' => false,
            'showspikes' => false, 'color' => self::SCENE_FONT, 'tickmode' => 'array',
        ];

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'scene' => [
                    'xaxis' => array_merge(
                        ['title' => ['text' => 'Students'], 'range' => $studentsaxisrange, 'tickvals' => $xticks],
                        $axiscommon
                    ),
                    'yaxis' => array_merge(
                        ['title' => ['text' => 'Attempt'], 'range' => $yrange, 'tickvals' => $yticks],
                        $axiscommon
                    ),
                    'zaxis' => array_merge(
                        ['title' => ['text' => $ztitle], 'range' => $zrange, 'tickvals' => $zticks],
                        $axiscommon
                    ),
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

    /**
     * 3D scatter of PRT distance per attempt for one question/part.
     */
    public static function build_prt_distance_3d_figure(array $responserows, string $question, int $partindex = 1): array {
        $subset = self::compute_prt_distance_series($responserows, $question, $partindex);
        return self::build_distance_3d_figure(
            $subset,
            'prt_distance',
            'Type of Error (PRT distance)',
            "PRT-Distance Solution Process — {$question} (part {$partindex})"
        );
    }

    /**
     * 3D scatter of tree edit distance per attempt for one question/part.
     */
    public static function build_ted_distance_3d_figure(array $responserows, string $question, int $partindex = 1): array {
        $subset = self::compute_ted_distance_series($responserows, $question, $partindex);
        return self::build_distance_3d_figure(
            $subset,
            'ted_distance',
            'Tree Edit Distance',
            "TED Solution Process — {$question} (part {$partindex})"
        );
    }

    // -----------------------------------------------------------------
    // Cross-Attempt Comparison: per student, per question, how did their
    // score/distance on their own retakes of this question change from
    // their first attempt to their last?

    /**
     * Per-attempt values of $metric (one of the keys in
     * CROSS_ATTEMPT_METRICS) for every student on $question who has 2+
     * qualifying attempts — one with a single attempt has no change to show,
     * and is dropped rather than plotted as a lone point.
     *
     * @param array[] $responserows
     * @return array[] one row per qualifying (student, attempt): student_id,
     *         student_name, attempt_number (1-based, sequential within that
     *         student's own attempts on this question), value, completed_dt
     */
    public static function compute_cross_attempt_comparison(
        array $responserows,
        string $question,
        string $metric,
        int $partindex = 1
    ): array {
        if (!array_key_exists($metric, self::CROSS_ATTEMPT_METRICS)) {
            throw new \InvalidArgumentException("Unknown Cross-Attempt Comparison metric: {$metric}");
        }
        if (empty($responserows)) {
            return [];
        }

        if ($metric === 'Grade') {
            $subset = array_values(array_filter($responserows, fn($r) => $r['question'] === $question));
            foreach ($subset as $i => $row) {
                $subset[$i]['value'] = $row['grade'] !== null ? $row['grade'] * self::GRADE_DISPLAY_SCALE : null;
            }
        } else if ($metric === 'PRT Distance') {
            $subset = self::compute_prt_distance_series($responserows, $question, $partindex);
            foreach ($subset as $i => $row) {
                $subset[$i]['value'] = $row['prt_distance'];
            }
        } else {
            $subset = self::compute_ted_distance_series($responserows, $question, $partindex);
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

        $maxbystudent = [];
        foreach ($subset as $row) {
            $sid = $row['student_id'];
            $maxbystudent[$sid] = max($maxbystudent[$sid] ?? 0, $row['attempt_number']);
        }
        $subset = array_values(array_filter($subset, fn($r) => $maxbystudent[$r['student_id']] >= 2));

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
    public static function classify_cross_attempt_trends(array $comparison, bool $higherisbetter): array {
        if (empty($comparison)) {
            return [];
        }

        $bystudent = [];
        $order = [];
        foreach ($comparison as $row) {
            $sid = $row['student_id'];
            if (!isset($bystudent[$sid])) {
                $bystudent[$sid] = [];
                $order[] = $sid;
            }
            $bystudent[$sid][] = $row;
        }

        $rows = [];
        foreach ($order as $studentid) {
            $group = $bystudent[$studentid];
            usort($group, fn($a, $b) => $a['attempt_number'] <=> $b['attempt_number']);
            $firstvalue = (float) $group[0]['value'];
            $lastvalue = (float) end($group)['value'];
            $rawdelta = $lastvalue - $firstvalue;
            $change = $higherisbetter ? $rawdelta : -$rawdelta;
            if (abs($change) <= self::FLAT_TOLERANCE) {
                $trend = 'Flat';
            } else if ($change > 0) {
                $trend = 'Improved';
            } else {
                $trend = 'Regressed';
            }
            $maxattemptnumber = max(array_map(fn($r) => $r['attempt_number'], $group));
            $rows[] = [
                'student_id' => $studentid,
                'student_name' => $group[0]['student_name'],
                'attempt_count' => $maxattemptnumber,
                'first_value' => $firstvalue,
                'last_value' => $lastvalue,
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
    public static function build_cross_attempt_figure(
        array $comparison,
        array $trends,
        string $metric,
        bool $colorblindmode
    ): array {
        $axistitle = self::CROSS_ATTEMPT_METRICS[$metric]['axis_title'];
        $palette = chart_helpers::pass_fail_scale($colorblindmode);
        $trendcolor = array_combine(self::TREND_ORDER, $palette);

        $trendbystudent = [];
        foreach ($trends as $t) {
            $trendbystudent[$t['student_id']] = $t['trend'];
        }

        $bystudent = [];
        foreach ($comparison as $row) {
            $bystudent[$row['student_id']][] = $row;
        }

        $data = [];
        foreach (self::TREND_ORDER as $trend) {
            $studentids = array_keys(array_filter($trendbystudent, fn($t) => $t === $trend));
            $isfirstingroup = true;
            foreach ($studentids as $studentid) {
                $group = $bystudent[$studentid] ?? [];
                usort($group, fn($a, $b) => $a['attempt_number'] <=> $b['attempt_number']);
                if (empty($group)) {
                    continue;
                }
                $studentname = $group[0]['student_name'];
                $data[] = [
                    'type' => 'scatter',
                    'x' => array_map(fn($r) => $r['attempt_number'], $group),
                    'y' => array_map(fn($r) => $r['value'], $group),
                    'mode' => 'lines+markers',
                    'line' => ['color' => $trendcolor[$trend], 'width' => 2],
                    'marker' => ['size' => 6, 'color' => $trendcolor[$trend]],
                    'opacity' => 0.8,
                    'name' => $trend,
                    'legendgroup' => $trend,
                    'showlegend' => $isfirstingroup,
                    'text' => array_fill(0, count($group), (string) $studentname),
                    'hovertemplate' => "%{text}<br>Attempt %{x}<br>{$axistitle}: %{y}<extra></extra>",
                ];
                $isfirstingroup = false;
            }
        }

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => "Cross-Attempt Comparison — {$metric}"],
                'legend' => ['title' => ['text' => 'Trend (first → last attempt)']],
                'template' => 'plotly',
                'xaxis' => ['title' => ['text' => 'Attempt'], 'dtick' => 1],
                'yaxis' => ['title' => ['text' => $axistitle]],
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
        array $detail,
        string $studentname,
        string $metric,
        string $trend,
        bool $colorblindmode
    ): array {
        $axistitle = self::CROSS_ATTEMPT_METRICS[$metric]['axis_title'];
        $palette = chart_helpers::pass_fail_scale($colorblindmode);
        $trendcolor = array_combine(self::TREND_ORDER, $palette);
        $color = $trendcolor[$trend] ?? $trendcolor['Flat'];

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
                'hovertemplate' => "Attempt %{x}<br>{$axistitle}: %{y}<extra></extra>",
            ]],
            'layout' => [
                'title' => ['text' => "{$studentname} — {$metric} across attempts"],
                'template' => 'plotly',
                'showlegend' => false,
                'xaxis' => ['title' => ['text' => 'Attempt'], 'dtick' => 1],
                'yaxis' => ['title' => ['text' => $axistitle]],
            ],
        ];
    }
}
