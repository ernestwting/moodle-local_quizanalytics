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
 * Declares this plugin's default Analytics API prediction models (the
 * Model & Diagnostics Analytics section's Model 1 and Model 2).
 *
 * Consumed automatically by \core_analytics\manager::update_default_models_for_component(),
 * which core's own upgrade_component_updated() calls for every plugin on
 * install/upgrade (lib/upgradelib.php) — confirmed directly against a real
 * Moodle 4.5 core checkout, along with this array's exact shape
 * (analytics/tests/fixtures/db_analytics_php/*.php). Models are created
 * once per declared target class; changing an already-registered model's
 * indicators/timesplitting here has no further effect — that has to be
 * done through Site Administration > Analytics > Models.
 *
 * `enabled` is deliberately false for both models: this plugin is alpha-stage
 * software and a newly-installed site should not start generating
 * predictions — and the insight notifications those trigger — on live data
 * before an administrator has reviewed the indicator thresholds and had a
 * chance to train/evaluate the model. Site Administration > Analytics >
 * Models is where it gets enabled once that review is done.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$models = [
    [
        'target' => '\local_quizanalytics\stack\analytics\target\student_at_risk',
        'indicators' => [
            '\local_quizanalytics\stack\analytics\indicator\grade_trajectory',
            '\local_quizanalytics\stack\analytics\indicator\response_latency_anomaly',
            '\local_quizanalytics\stack\analytics\indicator\disengagement_entropy',
            '\local_quizanalytics\stack\analytics\indicator\help_seeking_gap',
            '\local_quizanalytics\stack\analytics\indicator\feedback_revision_distance',
        ],
        'timesplitting' => '\core\analytics\time_splitting\quarters_accum',
        'enabled' => false,
    ],
    [
        'target' => '\local_quizanalytics\stack\analytics\target\question_needs_review',
        'indicators' => [
            '\local_quizanalytics\stack\analytics\indicator\question_difficulty_irt',
            '\local_quizanalytics\stack\analytics\indicator\syntax_error_rate',
            '\local_quizanalytics\stack\analytics\indicator\unreached_node_ratio',
            '\local_quizanalytics\stack\analytics\indicator\feedback_ineffectiveness',
        ],
        'timesplitting' => '\core\analytics\time_splitting\single_range',
        'enabled' => false,
    ],
];
