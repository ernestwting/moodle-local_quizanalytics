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
 * Version details for the course-level Interactive Quiz Analytics plugin.
 *
 * @package    local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_quizanalytics';  // Must match the folder: local/quizanalytics.
$plugin->version   = 2026080500;             // YYYYMMDDXX — bump this every time you push an update.
$plugin->requires  = 2022041900;             // Moodle 4.0.0 — lower this if you're on an older Moodle,
                                              // raise it if you use APIs from a newer one. Check your
                                              // target Moodle's own version.php for the right number.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.0.0';

// This plugin depends on mod_quiz (obviously) and nothing else — as of
// version 2026080500 this is the only plugin in the STACK Quiz Analytics
// suite. Question Analytics and Solution Process Visualization used to be
// separate quiz-report subplugins (quiz_quizanalytics, quiz_solutionprocess);
// their code now lives in this plugin's own classes/, reached via the
// per-quiz drill-down (index.php's "view" selector) and a link this plugin
// adds to each STACK quiz's settings menu (see lib.php), rather than as
// separate tabs on the quiz results page.
$plugin->dependencies = [
    'mod_quiz' => 2022041900,
];
