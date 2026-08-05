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
 * PHP port of analytics-service/analytics/prt_transitions.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class prt_transitions {
    // Green (few students traversed this edge) -> yellow -> red (many
    // students traversed it); the colorblind variant swaps in the Okabe-Ito
    // blue/yellow/vermillion triple used elsewhere in this app for
    // red-green-unsafe scales.
    const DEFAULT_TRAFFIC_SCALE = ['#22c55e', '#fde68a', '#ef4444'];
    const COLORBLIND_TRAFFIC_SCALE = ['#0072B2', '#F0E442', '#D55E00'];

    const NODE_RE = '/-(\d+)-([TF])$/';

    /**
     * The PRT dict for one part of a multi-part question (prt1 -> part_index
     * 1, prt2 -> 2, ...), or null if this response has no such part.
     */
    public static function get_part(array $row, int $partindex = 1): ?array {
        foreach (($row['prt_list'] ?? []) as $prt) {
            if (($prt['index'] ?? null) === $partindex) {
                return $prt;
            }
        }
        return null;
    }

    /**
     * How many PRT parts (prt1, prt2, ...) this question has, taken as the
     * largest part index seen across every response to it — a blank/invalid
     * response can omit trailing parts, so the maximum rather than the first
     * row's count is what's authoritative.
     */
    public static function count_question_parts(array $responserows, string $question): int {
        $maxindex = 0;
        foreach ($responserows as $row) {
            if ($row['question'] !== $question) {
                continue;
            }
            foreach (($row['prt_list'] ?? []) as $prt) {
                $maxindex = max($maxindex, (int) ($prt['index'] ?? 0));
            }
        }
        return max($maxindex, 1);
    }

    /**
     * (node_number, "T"|"F") for the node a PRT traversal ended at, or null
     * if this part carries no usable trace (blank/invalid input).
     *
     * @return array{0: int, 1: string}|null
     */
    private static function terminal_node(array $part): ?array {
        $notes = $part['answer_notes'] ?? [];
        if (empty($notes)) {
            $single = (string) ($part['answer_note'] ?? '');
            $notes = $single !== '' ? [$single] : [];
        }
        for ($i = count($notes) - 1; $i >= 0; $i--) {
            if (preg_match(self::NODE_RE, trim((string) $notes[$i]), $m)) {
                return [(int) $m[1], $m[2]];
            }
        }
        return null;
    }

    /**
     * Classify one response to one part of a question into a transition-
     * graph node label:
     * - full marks on this part -> "c"
     * - traversal ended True at node k>1 -> str(k - 1), so node 2 -> "1"
     * - traversal ended False, or the part is missing/blank/invalid -> "0"
     *
     * Correctness is judged per part (fraction == 1.0) rather than from the
     * row's overall response_status, which on a multi-part question only
     * says "every part was right".
     */
    public static function classify_node(array $row, int $partindex = 1): string {
        $part = self::get_part($row, $partindex);
        if ($part === null) {
            return '0';
        }

        if (($part['fraction'] ?? null) === 1.0) {
            return 'c';
        }

        $terminal = self::terminal_node($part);
        if ($terminal === null) {
            return '0';
        }

        [$nodenumber, $outcome] = $terminal;
        if ($outcome !== 'T') {
            return '0';
        }
        return $nodenumber === 1 ? 'c' : (string) ($nodenumber - 1);
    }

    /**
     * Deterministic node ordering: "0" first, ascending numeric nodes next,
     * "c" last — used both for layout and for any node-ordered display.
     *
     * @return array{0: int, 1: int, 2: string}
     */
    public static function node_sort_key(string $node): array {
        if ($node === '0') {
            return [0, 0, $node];
        }
        if ($node === 'c') {
            return [2, 0, $node];
        }
        if (ctype_digit($node)) {
            return [1, (int) $node, $node];
        }
        return [1, 1000000, $node];
    }

    private static function node_cmp(string $a, string $b): int {
        return self::node_sort_key($a) <=> self::node_sort_key($b);
    }

    /**
     * Ordered (by completed_dt, then attempt_idx) node-classification
     * sequence for one student's attempts at one part of one question.
     * $responserows should be Pool A (all attempts, not just each student's
     * best) so a retry trajectory is visible.
     *
     * @param array[] $responserows
     * @return array[] each {attempt_order, node, grade, completed_dt, attempt_idx}
     */
    public static function build_student_node_sequence(
        array $responserows,
        string $question,
        string $studentid,
        int $partindex = 1
    ): array {
        $rows = array_values(array_filter(
            $responserows,
            fn($r) => $r['question'] === $question && $r['student_id'] === $studentid
        ));
        if (empty($rows)) {
            return [];
        }

        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) $a['completed_dt'], (string) $b['completed_dt']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['attempt_idx'] <=> $b['attempt_idx'];
        });

        $out = [];
        foreach ($rows as $order => $row) {
            $out[] = [
                'attempt_order' => $order,
                'node' => self::classify_node($row, $partindex),
                'grade' => $row['grade'],
                'completed_dt' => $row['completed_dt'],
                'attempt_idx' => $row['attempt_idx'],
            ];
        }
        return $out;
    }

    /**
     * Consecutive transition pairs from an ordered node sequence, including
     * self-transitions (two consecutive attempts landing on the same node).
     *
     * @param string[] $nodes
     * @return array{0: string, 1: string}[]
     */
    public static function build_transition_pairs(array $nodes): array {
        $pairs = [];
        for ($i = 0; $i < count($nodes) - 1; $i++) {
            $pairs[] = [$nodes[$i], $nodes[$i + 1]];
        }
        return $pairs;
    }

    /**
     * Union of node labels (always including "0"/"c") and summed
     * transition-pair counts across every student's sequence for one part of
     * $question, aggregated over the whole class.
     *
     * @param array[] $responserows
     * @return array{0: string[], 1: array<string, int>} nodes, edge_counts
     *         keyed on "src|dst"
     */
    public static function build_aggregate_graph(array $responserows, string $question, int $partindex = 1): array {
        $questionrows = array_values(array_filter($responserows, fn($r) => $r['question'] === $question));
        // A plain list, not an associative "set" keyed on the label itself:
        // PHP silently casts numeric-string array keys ("0") to int keys, and
        // node labels are exactly that ("0", "1", "2", ... or "c") — using
        // them as dict keys would turn "0" into the integer 0 as soon as it
        // round-trips through array_keys(), corrupting every downstream
        // consumer of the node list (JSON output, lookups, sorting).
        // array_unique()/array_values() below dedupe by value, not key, so
        // they don't have this problem.
        $nodes = ['0', 'c'];
        $edgecounts = [];

        $seenstudents = [];
        foreach ($questionrows as $row) {
            $seenstudents[$row['student_id']] = true;
        }

        foreach (array_keys($seenstudents) as $studentid) {
            $seq = self::build_student_node_sequence($responserows, $question, $studentid, $partindex);
            $seqnodes = array_map(fn($r) => $r['node'], $seq);
            foreach ($seqnodes as $n) {
                $nodes[] = $n;
            }
            foreach (self::build_transition_pairs($seqnodes) as [$src, $dst]) {
                $key = "{$src}|{$dst}";
                $edgecounts[$key] = ($edgecounts[$key] ?? 0) + 1;
            }
        }

        $nodelist = array_values(array_unique($nodes));
        usort($nodelist, fn($a, $b) => self::node_cmp($a, $b));

        return [$nodelist, $edgecounts];
    }

    /**
     * Per-node in-degree / out-degree / degree and their centrality
     * (degree / (n-1)), computed directly from the edge-count dict.
     *
     * @param string[] $nodes
     * @param array<string, int> $edgecounts keyed on "src|dst"
     * @return array[] one row per node
     */
    public static function compute_network_features(array $nodes, array $edgecounts): array {
        $n = count($nodes);
        $denom = max($n - 1, 1);

        $indegree = array_fill_keys($nodes, 0);
        $outdegree = array_fill_keys($nodes, 0);
        foreach ($edgecounts as $key => $weight) {
            [$src, $dst] = explode('|', $key);
            $outdegree[$src] = ($outdegree[$src] ?? 0) + $weight;
            $indegree[$dst] = ($indegree[$dst] ?? 0) + $weight;
        }

        $rows = [];
        foreach ($nodes as $node) {
            $indeg = $indegree[$node] ?? 0;
            $outdeg = $outdegree[$node] ?? 0;
            $rows[] = [
                'node' => $node,
                'in_degree' => $indeg,
                'out_degree' => $outdeg,
                'degree' => $indeg + $outdeg,
                'in_degree_centrality' => py_compat::round($indeg / $denom, 4),
                'out_degree_centrality' => py_compat::round($outdeg / $denom, 4),
                'degree_centrality' => py_compat::round(($indeg + $outdeg) / $denom, 4),
            ];
        }
        return $rows;
    }

    /**
     * Deterministic node positions for drawing a small transition graph:
     * evenly spaced on a unit circle in node_sort_key order ("0" first,
     * ascending numeric nodes, "c" last), independent of the input list's
     * own order.
     *
     * @param string[] $nodes
     * @return array<string, array{0: float, 1: float}>
     */
    public static function circular_layout(array $nodes): array {
        $ordered = array_values(array_unique($nodes));
        usort($ordered, fn($a, $b) => self::node_cmp($a, $b));
        $n = count($ordered);
        if ($n === 0) {
            return [];
        }
        if ($n === 1) {
            return [$ordered[0] => [0.0, 0.0]];
        }

        $positions = [];
        foreach ($ordered as $index => $node) {
            $angle = 2 * M_PI * $index / $n;
            $positions[$node] = [cos($angle), sin($angle)];
        }
        return $positions;
    }

    /**
     * Directed transition graph: nodes placed via circular_layout, edges
     * drawn with width and a green (low traffic) -> red (high traffic) color
     * scaled from transition count.
     *
     * @param string[] $nodes
     * @param array<string, int> $edgecounts keyed on "src|dst"
     */
    public static function build_transition_graph_figure(
        array $nodes,
        array $edgecounts,
        bool $colorblindmode = false,
        string $title = ''
    ): array {
        $positions = self::circular_layout($nodes);
        $scale = $colorblindmode ? self::COLORBLIND_TRAFFIC_SCALE : self::DEFAULT_TRAFFIC_SCALE;

        $weights = array_values(array_filter($edgecounts, fn($w) => $w > 0));
        $minw = !empty($weights) ? min($weights) : 0;
        $maxw = !empty($weights) ? max($weights) : 0;

        $style = function (float $weight) use ($minw, $maxw, $scale): array {
            $norm = ($maxw == $minw) ? 0.5 : ($weight - $minw) / ($maxw - $minw);
            $width = 1.5 + $norm * 9.0;
            $color = chart_helpers::sample_colorscale_color($scale, $norm);
            return [$width, $color];
        };

        $data = [];
        $annotations = [];

        foreach ($edgecounts as $key => $weight) {
            [$src, $dst] = explode('|', $key);
            if (!isset($positions[$src]) || !isset($positions[$dst])) {
                continue;
            }
            [$width, $color] = $style((float) $weight);
            [$x0, $y0] = $positions[$src];
            [$x1, $y1] = $positions[$dst];

            if ($src === $dst) {
                $normlen = hypot($x0, $y0) ?: 1.0;
                $ox = $x0 / $normlen;
                $oy = $y0 / $normlen;
                $cx = $x0 + $ox * 0.28;
                $cy = $y0 + $oy * 0.28;
                $loopx = [];
                $loopy = [];
                for ($i = 0; $i <= 40; $i++) {
                    $t = $i / 40 * 2 * M_PI;
                    $loopx[] = $cx + 0.14 * cos($t);
                    $loopy[] = $cy + 0.14 * sin($t);
                }
                $data[] = [
                    'type' => 'scatter', 'x' => $loopx, 'y' => $loopy, 'mode' => 'lines',
                    'line' => ['width' => $width, 'color' => $color],
                    'hoverinfo' => 'text', 'text' => "{$src} \u{2192} {$dst}: " . self::format_g($weight),
                    'showlegend' => false,
                ];
                continue;
            }

            $dx = $x1 - $x0;
            $dy = $y1 - $y0;
            $dist = hypot($dx, $dy) ?: 1.0;
            $shrink = 0.16;
            $sx0 = $x0 + $dx / $dist * $shrink;
            $sy0 = $y0 + $dy / $dist * $shrink;
            $sx1 = $x1 - $dx / $dist * $shrink;
            $sy1 = $y1 - $dy / $dist * $shrink;

            $data[] = [
                'type' => 'scatter', 'x' => [$sx0, $sx1], 'y' => [$sy0, $sy1], 'mode' => 'lines',
                'line' => ['width' => $width, 'color' => $color],
                'hoverinfo' => 'text', 'text' => "{$src} \u{2192} {$dst}: " . self::format_g($weight),
                'showlegend' => false,
            ];
            $annotations[] = [
                'x' => $sx1, 'y' => $sy1, 'ax' => $sx0, 'ay' => $sy0,
                'xref' => 'x', 'yref' => 'y', 'axref' => 'x', 'ayref' => 'y',
                'showarrow' => true, 'arrowhead' => 3, 'arrowsize' => 1.2,
                'arrowwidth' => min($width, 4), 'arrowcolor' => $color, 'standoff' => 6,
            ];
        }

        $orderednodes = array_values(array_unique($nodes));
        usort($orderednodes, fn($a, $b) => self::node_cmp($a, $b));
        $nodecolors = [];
        foreach ($orderednodes as $node) {
            if ($node === '0') {
                $nodecolors[] = '#9ca3af';
            } else if ($node === 'c') {
                $nodecolors[] = $colorblindmode ? '#0072B2' : '#16a34a';
            } else {
                $nodecolors[] = $colorblindmode ? '#F0E442' : '#3b82f6';
            }
        }

        $data[] = [
            'type' => 'scatter',
            'x' => array_map(fn($n) => $positions[$n][0], $orderednodes),
            'y' => array_map(fn($n) => $positions[$n][1], $orderednodes),
            'mode' => 'markers+text',
            'text' => $orderednodes,
            'textposition' => 'middle center',
            'textfont' => ['color' => $colorblindmode ? 'black' : 'white', 'size' => 13],
            'marker' => ['size' => 34, 'color' => $nodecolors, 'line' => ['width' => 2, 'color' => 'white']],
            'hoverinfo' => 'text',
            'showlegend' => false,
        ];

        return [
            'data' => $data,
            'layout' => [
                'title' => ['text' => $title],
                'annotations' => $annotations,
                'xaxis' => ['visible' => false, 'range' => [-1.6, 1.6]],
                'yaxis' => ['visible' => false, 'range' => [-1.6, 1.6], 'scaleanchor' => 'x', 'scaleratio' => 1],
                'margin' => ['l' => 20, 'r' => 20, 't' => 50, 'b' => 20],
                'height' => 420,
            ],
        ];
    }

    /**
     * Python's f"{weight:g}" — shortest round-trip decimal representation,
     * integers rendered without a trailing ".0".
     */
    private static function format_g(float $weight): string {
        if ($weight == (int) $weight) {
            return (string) (int) $weight;
        }
        return rtrim(rtrim(sprintf('%.6g', $weight), '0'), '.');
    }
}
