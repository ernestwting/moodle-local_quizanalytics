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
 * Generic Plotly {data, layout} JSON builders — the PHP side's equivalent of
 * calling plotly.express in Python. Deliberately not a pixel-identical
 * reproduction of every plotly.express default (hover templates, exact legend
 * positioning, etc.) — the same Plotly.js already loaded client-side renders
 * whatever JSON this produces, so what matters is the chart type, the data,
 * and the visual properties this codebase's own chart functions actually rely
 * on (colors, titles, axis labels, pinned ranges, chart height).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Chart-building helpers shared across the ported analytics modules: palettes, label formatting, figure assembly.
 */
class chart_helpers {
    /** @var string[] Plotly.express "Set2" qualitative palette (px.colors.qualitative.Set2), RGB values pulled directly from plotly.express. */
    const PALETTE_SET2 = [
        'rgb(102,194,165)', 'rgb(252,141,98)', 'rgb(141,160,203)', 'rgb(231,138,195)',
        'rgb(166,216,84)', 'rgb(255,217,47)', 'rgb(229,196,148)', 'rgb(179,179,179)',
    ];
    /** @var string[] Plotly.express "Vivid" qualitative palette (px.colors.qualitative.Vivid). */
    const PALETTE_VIVID = [
        'rgb(229, 134, 6)', 'rgb(93, 105, 177)', 'rgb(82, 188, 163)', 'rgb(153, 201, 69)',
        'rgb(204, 97, 176)', 'rgb(36, 121, 108)', 'rgb(218, 165, 27)', 'rgb(47, 138, 196)',
        'rgb(118, 78, 159)', 'rgb(237, 100, 90)', 'rgb(165, 170, 153)',
    ];
    /** @var string[] Plotly.express "Safe" qualitative palette (px.colors.qualitative.Safe) — colorblind-safe. */
    const PALETTE_SAFE = [
        'rgb(136, 204, 238)', 'rgb(204, 102, 119)', 'rgb(221, 204, 119)', 'rgb(17, 119, 51)',
        'rgb(51, 34, 136)', 'rgb(170, 68, 153)', 'rgb(68, 170, 153)', 'rgb(153, 153, 51)',
        'rgb(136, 34, 85)', 'rgb(102, 17, 0)', 'rgb(136, 136, 136)',
    ];
    /** @var string[] Plotly.express "Bold" qualitative palette (px.colors.qualitative.Bold). */
    const PALETTE_BOLD = [
        'rgb(127, 60, 141)', 'rgb(17, 165, 121)', 'rgb(57, 105, 172)', 'rgb(242, 183, 1)',
        'rgb(231, 63, 116)', 'rgb(128, 186, 90)', 'rgb(230, 131, 16)', 'rgb(0, 134, 149)',
        'rgb(207, 28, 144)', 'rgb(249, 123, 114)', 'rgb(165, 170, 153)',
    ];
    /** @var string[] Plotly's default qualitative palette (px.colors.qualitative.Plotly). */
    const PALETTE_PLOTLY = [
        '#636EFA', '#EF553B', '#00CC96', '#AB63FA', '#FFA15A',
        '#19D3F3', '#FF6692', '#B6E880', '#FF97FF', '#FECB52',
    ];
    /** @var string[] Plotly.express "Set1" qualitative palette (px.colors.qualitative.Set1). */
    const PALETTE_SET1 = [
        'rgb(228,26,28)', 'rgb(55,126,184)', 'rgb(77,175,74)', 'rgb(152,78,163)', 'rgb(255,127,0)',
        'rgb(255,255,51)', 'rgb(166,86,40)', 'rgb(247,129,191)', 'rgb(153,153,153)',
    ];

    /** @var string[] Diverging red/yellow/green pass-rate scale (low/mid/high). */
    const PASS_FAIL_SCALE_DEFAULT = ['#ef4444', '#fde68a', '#22c55e'];

    /**
     * Blue/yellow/vermillion (Okabe-Ito) — red vs. green (the default scale's
     * pass/fail encoding) is the one combination red-green colorblind users
     * can't reliably tell apart.
     *
     * @var string[]
     */
    const PASS_FAIL_SCALE_COLORBLIND = ['#0072B2', '#F0E442', '#D55E00'];

    /**
     * A handful of keys/columns come through as raw Python/PHP identifiers
     * (student_count, mean_grade, invalid_rate, ...) since they double as
     * array keys/column names throughout the analytics code — this turns
     * those into presentable labels for chart legends and PDF table headers,
     * matching sections-renderer.js's own humanizeLabel() (used for the
     * on-screen table headers/summary) exactly, so a key reads identically
     * wherever it's shown. Applying it to an already-nice string ("Valid %")
     * is a no-op.
     *
     * @var array<string, string> lowercased word => override
     */
    const LABEL_OVERRIDES = [
        'id' => 'ID', 'ids' => 'IDs', 'prt' => 'PRT', 'prts' => 'PRTs',
        'ted' => 'TED', 'stack' => 'STACK', 'url' => 'URL',
    ];

    /** @var array<string, string> lowercased whole-key => override, checked before word-by-word LABEL_OVERRIDES. */
    const FULL_LABEL_OVERRIDES = [
        'attempt_idx' => 'Attempt Number',
        'completed_dt' => 'Completed On',
    ];

    /**
     * PHP port of sections-renderer.js's humanizeLabel() — see that
     * function's own comment for why this exists.
     */
    public static function humanize_label(string $key): string {
        if ($key === '') {
            return $key;
        }
        $fulloverride = self::FULL_LABEL_OVERRIDES[strtolower($key)] ?? null;
        if ($fulloverride !== null) {
            return $fulloverride;
        }
        $spaced = trim(preg_replace('/[_\-]+/', ' ', $key));
        $words = preg_split('/\s+/', $spaced);
        $out = array_map(function ($word) {
            $override = self::LABEL_OVERRIDES[strtolower($word)] ?? null;
            if ($override !== null) {
                return $override;
            }
            return mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1));
        }, $words);
        return implode(' ', $out);
    }

    /**
     * Categorical chart palette: $default normally, or the colorblind-safe Safe palette.
     */
    public static function qualitative_colors(bool $colorblindmode, array $default): array {
        return $colorblindmode ? self::PALETTE_SAFE : $default;
    }

    /**
     * Diverging red/yellow/green pass-rate scale, or its colorblind-safe equivalent.
     */
    public static function pass_fail_scale(bool $colorblindmode): array {
        return $colorblindmode ? self::PASS_FAIL_SCALE_COLORBLIND : self::PASS_FAIL_SCALE_DEFAULT;
    }

    /**
     * One bar per category (e.g. one bar per question, each its own color) —
     * matches px.bar(df, x=category_col, y=value_col, color=category_col).
     *
     * @param string[] $categories
     * @param float[] $values
     * @param string[] $palette
     */
    public static function build_bar_figure(
        array $categories,
        array $values,
        string $title,
        string $xtitle,
        string $ytitle,
        array $palette,
        bool $showlegend = false
    ): array {
        $data = [];
        foreach ($categories as $i => $cat) {
            $data[] = [
                'type' => 'bar',
                'x' => [$cat],
                'y' => [$values[$i]],
                'name' => (string) $cat,
                'marker' => ['color' => $palette[$i % count($palette)]],
            ];
        }
        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'showlegend' => $showlegend,
                'template' => 'plotly',
                'xaxis' => ['title' => ['text' => $xtitle]],
                'yaxis' => ['title' => ['text' => $ytitle]],
            ],
        ];
    }

    /**
     * One box trace per category, from raw (not pre-aggregated) values —
     * Plotly.js's own "box" trace type computes quartiles/whiskers
     * client-side from the raw y values, matching what px.box does.
     *
     * @param string[] $categories in display order
     * @param array<string, float[]> $valuesbycategory
     */
    public static function build_box_figure(
        array $categories,
        array $valuesbycategory,
        string $title,
        string $xtitle,
        string $ytitle,
        array $palette
    ): array {
        $data = [];
        foreach ($categories as $i => $cat) {
            $data[] = [
                'type' => 'box',
                'y' => array_values($valuesbycategory[$cat] ?? []),
                'x0' => $cat,
                'name' => (string) $cat,
                'marker' => ['color' => $palette[$i % count($palette)]],
            ];
        }
        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'showlegend' => false,
                'template' => 'plotly',
                'xaxis' => ['title' => ['text' => $xtitle]],
                'yaxis' => ['title' => ['text' => $ytitle]],
            ],
        ];
    }

    /**
     * One trace per named series, each spanning every category — matches
     * px.bar(df, x=category_col, y=[series1_col, series2_col, ...], barmode="group").
     *
     * @param string[] $categories
     * @param array<string, float[]> $series name => values (same order/length as $categories)
     * @param string[] $palette
     */
    public static function build_grouped_bar_figure(
        array $categories,
        array $series,
        string $title,
        string $xtitle,
        string $ytitle,
        array $palette
    ): array {
        $data = [];
        $i = 0;
        foreach ($series as $name => $values) {
            $data[] = [
                'type' => 'bar',
                'x' => $categories,
                'y' => array_values($values),
                'name' => self::humanize_label((string) $name),
                'marker' => ['color' => $palette[$i % count($palette)]],
            ];
            $i++;
        }
        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'barmode' => 'group',
                'template' => 'plotly',
                'xaxis' => ['title' => ['text' => $xtitle]],
                'yaxis' => ['title' => ['text' => $ytitle]],
                // Plotly's own default legend position (top-right, just
                // outside the plot area) sits right where a hover tooltip
                // for a bar near the top of the y-axis also renders,
                // especially with many categories on the x-axis (e.g. one
                // bar per question in a large quiz) — the two visibly
                // collide. A horizontal legend anchored below the plot has
                // nothing else rendering there to collide with.
                'legend' => [
                    'orientation' => 'h',
                    'x' => 0.5,
                    'xanchor' => 'center',
                    'y' => -0.2,
                ],
            ],
        ];
    }

    /**
     * Generic heatmap figure. $colorscale is either a Plotly.js built-in
     * named colorscale ("Viridis") or a list of hex colors to spread evenly
     * across [0,1] (e.g. the 3-color pass/fail scale).
     *
     * @param array<int, array<int, float|null>> $z row-major grid (null -> transparent cell)
     * @param string[] $xlabels
     * @param string[] $ylabels
     * @param string|string[] $colorscale
     */
    public static function build_heatmap_figure(
        array $z,
        array $xlabels,
        array $ylabels,
        string $title,
        string|array $colorscale,
        ?float $zmin = null,
        ?float $zmax = null,
        ?int $height = null,
        ?string $plotbgcolor = null
    ): array {
        $trace = [
            'type' => 'heatmap',
            'z' => $z,
            'x' => $xlabels,
            'y' => $ylabels,
            'colorscale' => is_array($colorscale) ? self::spread_colorscale($colorscale) : $colorscale,
        ];
        if ($zmin !== null) {
            $trace['zmin'] = $zmin;
        }
        if ($zmax !== null) {
            $trace['zmax'] = $zmax;
        }

        // A long y-label (e.g. a full student email address) needs a wide
        // left margin to avoid being clipped — automargin lets Plotly grow
        // the margin to fit whatever the tick labels actually need, and the
        // explicit margin.l (a rough char-width estimate) gives it a sane
        // starting point rather than relying on automargin alone, which on
        // a width-constrained page (Bootstrap container, not the full
        // viewport) doesn't always reserve enough room on the first layout
        // pass.
        $longestylabel = 0;
        foreach ($ylabels as $label) {
            $longestylabel = max($longestylabel, strlen((string) $label));
        }
        $leftmargin = (int) min(320, max(80, 6.5 * $longestylabel + 20));

        $layout = [
            'title' => ['text' => $title],
            'template' => 'plotly',
            'margin' => ['l' => $leftmargin, 'r' => 40, 't' => 60, 'b' => 60],
            'xaxis' => [
                'tickmode' => 'array',
                'tickvals' => count($xlabels) > 0 ? range(0, count($xlabels) - 1) : [],
                'ticktext' => array_values($xlabels),
                'range' => [-0.5, count($xlabels) - 0.5],
                'automargin' => true,
            ],
            'yaxis' => [
                'tickmode' => 'array',
                'tickvals' => count($ylabels) > 0 ? range(0, count($ylabels) - 1) : [],
                'ticktext' => array_values($ylabels),
                'range' => [-0.5, count($ylabels) - 0.5],
                'automargin' => true,
            ],
        ];
        if ($height !== null) {
            $layout['height'] = $height;
        }
        if ($plotbgcolor !== null) {
            $layout['plot_bgcolor'] = $plotbgcolor;
        }

        return ['data' => [$trace], 'layout' => $layout];
    }

    /**
     * px.colors.sample_colorscale(colors, [t])[0] for a plain list of
     * evenly-spaced color anchors: linear RGB interpolation between the two
     * anchors bracketing $t (0.0-1.0). Accepts '#rrggbb' or 'rgb(r,g,b)'
     * anchor strings, returns an 'rgb(r,g,b)' string (Plotly.js accepts
     * either format, so this doesn't need to preserve the input format).
     *
     * @param string[] $colors
     */
    public static function sample_colorscale_color(array $colors, float $t): string {
        $n = count($colors);
        if ($n === 1) {
            return $colors[0];
        }
        $t = max(0.0, min(1.0, $t));
        $scaled = $t * ($n - 1);
        $i = (int) floor($scaled);
        if ($i >= $n - 1) {
            return $colors[$n - 1];
        }
        $frac = $scaled - $i;
        [$r1, $g1, $b1] = self::parse_color($colors[$i]);
        [$r2, $g2, $b2] = self::parse_color($colors[$i + 1]);
        $r = (int) round($r1 + ($r2 - $r1) * $frac);
        $g = (int) round($g1 + ($g2 - $g1) * $frac);
        $b = (int) round($b1 + ($b2 - $b1) * $frac);
        return "rgb({$r}, {$g}, {$b})";
    }

    /**
     * Parses a "#rrggbb" or "rgb(r, g, b)" color string into its [r, g, b] components.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function parse_color(string $color): array {
        $color = trim($color);
        if ($color[0] === '#') {
            $hex = substr($color, 1);
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        }
        preg_match('/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $color, $m);
        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    /**
     * Spreads a list of colors evenly across the [0,1] colorscale domain,
     * e.g. 3 colors -> [[0,c1],[0.5,c2],[1,c3]] — the array form of a
     * Plotly.js colorscale.
     *
     * @param string[] $colors
     * @return array<int, array{0: float, 1: string}>
     */
    protected static function spread_colorscale(array $colors): array {
        $n = count($colors);
        if ($n === 1) {
            return [[0.0, $colors[0]], [1.0, $colors[0]]];
        }
        $stops = [];
        foreach ($colors as $i => $color) {
            $stops[] = [$i / ($n - 1), $color];
        }
        return $stops;
    }
}
