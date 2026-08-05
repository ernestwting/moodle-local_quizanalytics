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
 * Part of the PHP port of analytics-service/analytics/expression_tree.py.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * One node of a parsed CAS expression tree: label is the operator/function/
 * atom name, children are its operands in left-to-right order (empty for a
 * leaf). A real class (not an array) so spl_object_id() gives each node a
 * stable identity, matching Python's id(node) usage in tree_edit_distance.py.
 */
class expr_node {
    /** @var string The operator/function/atom name at this node. */
    public string $label;

    /** @var expr_node[] */
    public array $children;

    /**
     * Builds a node with the given label and (possibly empty) child operands.
     */
    public function __construct(string $label, array $children = []) {
        $this->label = $label;
        $this->children = $children;
    }
}
