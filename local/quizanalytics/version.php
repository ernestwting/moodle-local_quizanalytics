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

// This plugin depends on mod_quiz (obviously) and on the quiz_quizanalytics
// report subplugin, because its data_fetcher deliberately calls into
// quiz_quizanalytics_data_fetcher::get_response_records() rather than
// reimplementing the attempt/response extraction logic a second time. If
// quiz_quizanalytics isn't installed, Moodle will refuse to enable this
// plugin rather than fail confusingly at runtime.
$plugin->dependencies = [
    'mod_quiz'           => 2022041900,
    'quiz_quizanalytics' => 2026080300,
];
