<?php
/**
 * PHP port of analytics-service/analytics/spv_charts.php.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

class spv_charts {

    const CENTRALITY_METRICS = [
        ['in_degree_centrality', 'In-Degree Centrality'],
        ['out_degree_centrality', 'Out-Degree Centrality'],
        ['degree_centrality', 'Degree Centrality'],
    ];

    /**
     * The three in/out/degree-centrality bar charts, one per node, in
     * prt_transitions::compute_network_features()'s node order.
     *
     * @param array[] $network_features
     * @return array{metric: string, label: string, plotly_json: array}[]
     */
    public static function build_centrality_bar_figures(array $network_features): array {
        $node_order = array_map(fn($r) => $r['node'], $network_features);
        $charts = [];
        foreach (self::CENTRALITY_METRICS as [$metric, $label]) {
            $data = [[
                'type' => 'bar',
                'x' => $node_order,
                'y' => array_map(fn($r) => $r[$metric], $network_features),
                'marker' => ['color' => '#3b82f6'],
            ]];
            $charts[] = [
                'metric' => $metric,
                'label' => $label,
                'plotly_json' => [
                    'data' => $data,
                    'layout' => [
                        'title' => ['text' => $label],
                        'showlegend' => false,
                        'xaxis' => ['type' => 'category', 'title' => ['text' => 'Node']],
                        'yaxis' => ['title' => ['text' => $label]],
                    ],
                ],
            ];
        }
        return $charts;
    }
}
