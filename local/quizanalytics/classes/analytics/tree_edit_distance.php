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
 * PHP port of analytics-service/analytics/tree_edit_distance.py — Zhang &
 * Shasha (1989) edit distance between two ordered labeled trees, where
 * insert/delete/rename each cost 1.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Zhang-Shasha tree edit distance between two CAS expression trees.
 */
class tree_edit_distance {
    /**
     * Postorder traversal (children before parent).
     *
     * @return expr_node[] 0-indexed list where index $i corresponds to
     *         1-indexed postorder position $i + 1 in the classic Zhang-Shasha
     *         formulation used below.
     */
    private static function postorder(expr_node $root): array {
        $order = [];
        $walk = function (expr_node $node) use (&$walk, &$order) {
            foreach ($node->children as $child) {
                $walk($child);
            }
            $order[] = $node;
        };
        $walk($root);
        return $order;
    }

    /**
     * 1-indexed l(i): the postorder position of node i's leftmost leaf
     * descendant (itself, if i is a leaf). l[0] is an unused placeholder so
     * l[i] lines up with 1-indexed postorder position i.
     *
     * @param expr_node[] $nodes
     * @return int[]
     */
    private static function leftmost_leaf_positions(array $nodes): array {
        $n = count($nodes);
        $positionofid = [];
        foreach ($nodes as $index => $node) {
            $positionofid[spl_object_id($node)] = $index + 1;
        }
        $l = array_fill(0, $n + 1, 0);
        for ($i = 1; $i <= $n; $i++) {
            $node = $nodes[$i - 1];
            if (empty($node->children)) {
                $l[$i] = $i;
            } else {
                $firstchildpos = $positionofid[spl_object_id($node->children[0])];
                $l[$i] = $l[$firstchildpos];
            }
        }
        return $l;
    }

    /**
     * Keyroots: for each distinct l(i) value, the largest postorder index
     * sharing it — equivalent to (and simpler to compute than) "root, or
     * l(i) != l(parent(i))".
     *
     * @param int[] $l
     * @return int[]
     */
    private static function keyroots(array $l, int $n): array {
        $lastindexforl = [];
        for ($i = 1; $i <= $n; $i++) {
            $lastindexforl[$l[$i]] = $i;
        }
        $values = array_values($lastindexforl);
        sort($values);
        return $values;
    }

    /**
     * Zhang-Shasha edit distance between two ordered labeled trees — the
     * edit-operation model used to measure how far a student's submitted CAS
     * expression tree is from the correct answer's tree.
     */
    public static function compute(expr_node $treea, expr_node $treeb): int {
        $nodesa = self::postorder($treea);
        $nodesb = self::postorder($treeb);
        $n = count($nodesa);
        $m = count($nodesb);

        $la = self::leftmost_leaf_positions($nodesa);
        $lb = self::leftmost_leaf_positions($nodesb);
        $keyrootsa = self::keyroots($la, $n);
        $keyrootsb = self::keyroots($lb, $m);

        $labela = [];
        for ($i = 1; $i <= $n; $i++) {
            $labela[$i] = $nodesa[$i - 1]->label;
        }
        $labelb = [];
        for ($j = 1; $j <= $m; $j++) {
            $labelb[$j] = $nodesb[$j - 1]->label;
        }

        $treedist = [];

        foreach ($keyrootsa as $i) {
            foreach ($keyrootsb as $j) {
                $key = fn($x, $y) => "{$x},{$y}";
                $forestdist = [$key($la[$i] - 1, $lb[$j] - 1) => 0];

                for ($i1 = $la[$i]; $i1 <= $i; $i1++) {
                    $forestdist[$key($i1, $lb[$j] - 1)] = $forestdist[$key($i1 - 1, $lb[$j] - 1)] + 1;
                }

                for ($j1 = $lb[$j]; $j1 <= $j; $j1++) {
                    $forestdist[$key($la[$i] - 1, $j1)] = $forestdist[$key($la[$i] - 1, $j1 - 1)] + 1;
                }

                for ($i1 = $la[$i]; $i1 <= $i; $i1++) {
                    for ($j1 = $lb[$j]; $j1 <= $j; $j1++) {
                        if ($la[$i1] === $la[$i] && $lb[$j1] === $lb[$j]) {
                            $renamecost = ($labela[$i1] === $labelb[$j1]) ? 0 : 1;
                            $dist = min(
                                $forestdist[$key($i1 - 1, $j1)] + 1,
                                $forestdist[$key($i1, $j1 - 1)] + 1,
                                $forestdist[$key($i1 - 1, $j1 - 1)] + $renamecost
                            );
                            $forestdist[$key($i1, $j1)] = $dist;
                            $treedist[$key($i1, $j1)] = $dist;
                        } else {
                            $dist = min(
                                $forestdist[$key($i1 - 1, $j1)] + 1,
                                $forestdist[$key($i1, $j1 - 1)] + 1,
                                $forestdist[$key($la[$i1] - 1, $lb[$j1] - 1)] + $treedist[$key($i1, $j1)]
                            );
                            $forestdist[$key($i1, $j1)] = $dist;
                        }
                    }
                }
            }
        }

        return $treedist["{$n},{$m}"];
    }
}
