<?php
// This file is part of Moodle - http://moodle.org/
//
// local_quizanalytics is free software distributed under the GPL, same as Moodle core.

/**
 * Version details for the course-level Interactive Quiz Analytics plugin.
 *
 * @package    local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_quizanalytics';  // Must match the folder: local/quizanalytics.
$plugin->version   = 2026080300;             // YYYYMMDDXX — bump this every time you push an update.
$plugin->requires  = 2022041900;             // Moodle 4.0.0 — lower this if you're on an older Moodle,
                                              // raise it if you use APIs from a newer one. Check your
                                              // target Moodle's own version.php for the right number.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// This plugin depends on mod_quiz (obviously), on the quiz_quizanalytics
// report subplugin (its data_fetcher deliberately calls into
// quiz_quizanalytics_data_fetcher::get_response_records() rather than
// reimplementing attempt/response extraction a second time), and on
// quiz_solutionprocess (index.php embeds its Solution Process Visualization
// selector form and api_client for the per-quiz view). Moodle refuses to
// enable this plugin if any of these is missing or too old, rather than
// failing confusingly at runtime.
$plugin->dependencies = [
    'mod_quiz'             => 2022041900,
    'quiz_quizanalytics'   => 2026080300,
    'quiz_solutionprocess' => 2026080300,
];
