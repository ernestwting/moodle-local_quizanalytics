<?php
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
 */

namespace local_quizanalytics\analytics;

class chart_helpers {

    // Named Plotly.express qualitative palettes actually used by the ported
    // chart functions — exact RGB values pulled directly from plotly.express
    // itself (px.colors.qualitative.*), not approximated.
    const PALETTE_SET2 = [
        'rgb(102,194,165)', 'rgb(252,141,98)', 'rgb(141,160,203)', 'rgb(231,138,195)',
        'rgb(166,216,84)', 'rgb(255,217,47)', 'rgb(229,196,148)', 'rgb(179,179,179)',
    ];
    const PALETTE_VIVID = [
        'rgb(229, 134, 6)', 'rgb(93, 105, 177)', 'rgb(82, 188, 163)', 'rgb(153, 201, 69)',
        'rgb(204, 97, 176)', 'rgb(36, 121, 108)', 'rgb(218, 165, 27)', 'rgb(47, 138, 196)',
        'rgb(118, 78, 159)', 'rgb(237, 100, 90)', 'rgb(165, 170, 153)',
    ];
    const PALETTE_SAFE = [
        'rgb(136, 204, 238)', 'rgb(204, 102, 119)', 'rgb(221, 204, 119)', 'rgb(17, 119, 51)',
        'rgb(51, 34, 136)', 'rgb(170, 68, 153)', 'rgb(68, 170, 153)', 'rgb(153, 153, 51)',
        'rgb(136, 34, 85)', 'rgb(102, 17, 0)', 'rgb(136, 136, 136)',
    ];

    const PASS_FAIL_SCALE_DEFAULT = ['#ef4444', '#fde68a', '#22c55e'];
    // Blue/yellow/vermillion (Okabe-Ito) — red vs. green (the default scale's
    // pass/fail encoding) is the one combination red-green colorblind users
    // can't reliably tell apart.
    const PASS_FAIL_SCALE_COLORBLIND = ['#0072B2', '#F0E442', '#D55E00'];

    /** Categorical chart palette: $default normally, or the colorblind-safe Safe palette. */
    public static function qualitative_colors(bool $colorblind_mode, array $default): array {
        return $colorblind_mode ? self::PALETTE_SAFE : $default;
    }

    /** Diverging red/yellow/green pass-rate scale, or its colorblind-safe equivalent. */
    public static function pass_fail_scale(bool $colorblind_mode): array {
        return $colorblind_mode ? self::PASS_FAIL_SCALE_COLORBLIND : self::PASS_FAIL_SCALE_DEFAULT;
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
     * @param array<string, float[]> $values_by_category
     */
    public static function build_box_figure(
        array $categories,
        array $values_by_category,
        string $title,
        string $xtitle,
        string $ytitle,
        array $palette
    ): array {
        $data = [];
        foreach ($categories as $i => $cat) {
            $data[] = [
                'type' => 'box',
                'y' => array_values($values_by_category[$cat] ?? []),
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
                'name' => (string) $name,
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
        ?string $plot_bgcolor = null
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

        $layout = [
            'title' => ['text' => $title],
            'template' => 'plotly',
            'xaxis' => [
                'tickmode' => 'array',
                'tickvals' => count($xlabels) > 0 ? range(0, count($xlabels) - 1) : [],
                'ticktext' => array_values($xlabels),
                'range' => [-0.5, count($xlabels) - 0.5],
            ],
            'yaxis' => [
                'tickmode' => 'array',
                'tickvals' => count($ylabels) > 0 ? range(0, count($ylabels) - 1) : [],
                'ticktext' => array_values($ylabels),
                'range' => [-0.5, count($ylabels) - 0.5],
            ],
        ];
        if ($height !== null) {
            $layout['height'] = $height;
        }
        if ($plot_bgcolor !== null) {
            $layout['plot_bgcolor'] = $plot_bgcolor;
        }

        return ['data' => [$trace], 'layout' => $layout];
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
