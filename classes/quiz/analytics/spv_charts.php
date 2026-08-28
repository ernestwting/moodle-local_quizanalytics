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
 * PHP port of analytics-service/analytics/spv_charts.php.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Solution Process Visualization's network-feature bar charts (in/out/degree centrality per node).
 */
class spv_charts {
    /** @var array{0: string, 1: string}[] [network-feature key, display label] pairs for the centrality bar charts. */
    const CENTRALITY_METRICS = [
        ['in_degree_centrality', 'In-Degree Centrality'],
        ['out_degree_centrality', 'Out-Degree Centrality'],
        ['degree_centrality', 'Degree Centrality'],
    ];

    /**
     * The three in/out/degree-centrality bar charts, one per node, in
     * prt_transitions::compute_network_features()'s node order.
     *
     * @param array[] $networkfeatures
     * @return array{metric: string, label: string, plotly_json: array}[]
     */
    public static function build_centrality_bar_figures(array $networkfeatures): array {
        $nodeorder = array_map(fn($r) => $r['node'], $networkfeatures);
        $charts = [];
        foreach (self::CENTRALITY_METRICS as [$metric, $label]) {
            $data = [[
                'type' => 'bar',
                'x' => $nodeorder,
                'y' => array_map(fn($r) => $r[$metric], $networkfeatures),
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
