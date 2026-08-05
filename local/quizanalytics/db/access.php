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
 * Capabilities for local_quizanalytics.
 *
 * Checked at CONTEXT_COURSE rather than CONTEXT_MODULE — the course-level
 * "Analytics" page can show data from every STACK quiz in the course at
 * once, and the per-quiz link this plugin adds to a quiz's own settings menu
 * (see lib.php) checks this same capability against that quiz's enclosing
 * course context.
 *
 * @package    local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/quizanalytics:view' => [
        'riskbitmask'  => RISK_PERSONAL, // Shows individual students' response data across the whole course.
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            // Deliberately NOT granted to 'student' — this exposes other
            // students' response text and grades across every quiz in the course.
        ],
    ],

];
