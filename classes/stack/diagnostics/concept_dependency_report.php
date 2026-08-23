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
 * Diagnostics dashboard: concept-dependency mapping (architecture doc §3.4g).
 *
 * The doc itself frames this as "good material for the report's 'future
 * work' or a secondary offline Python analysis (pandas/networkx) outside the
 * live Moodle ML pipeline" (§3.4g) and as a Phase 6 stretch goal (§8) — a
 * first-order Markov chain over question/concept failure sequences is
 * unsupervised sequence mining with no natural place in either a per-sample
 * PHP indicator or a single dashboard query, and deserves its own offline
 * tooling rather than a half-built in-process approximation here.
 *
 * This class exists so the dashboard has one place to point at ("not yet
 * implemented — see the architecture doc's future work") instead of the
 * feature silently not appearing anywhere.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\diagnostics;

defined('MOODLE_INTERNAL') || die();

/**
 * Placeholder for the concept-dependency Markov-chain analysis.
 */
class concept_dependency_report {
    /**
     * Reports whether concept-dependency mapping is implemented yet.
     *
     * @return bool always false — see class docblock.
     */
    public static function is_available(): bool {
        return false;
    }
}
