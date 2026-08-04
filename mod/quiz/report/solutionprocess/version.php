<?php
// This file is part of Moodle - http://moodle.org/
//
// quiz_solutionprocess is free software distributed under the GPL, same as Moodle core.

/**
 * Version details for the Solution Process Visualization report subplugin.
 *
 * @package    quiz_solutionprocess
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quiz_solutionprocess';   // Must match the folder: mod/quiz/report/solutionprocess.
$plugin->version   = 2026080300;               // YYYYMMDDXX — bump this every time you push an update.
$plugin->requires  = 2022041900;               // Moodle 4.0.0 — lower this if you're on an older Moodle,
                                                // raise it if you use APIs from a newer one. Check your
                                                // target Moodle's own version.php for the right number.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// Depends on mod_quiz (obviously) and on quiz_quizanalytics, because
// report.php below deliberately reuses quiz_quizanalytics_data_fetcher
// rather than re-implementing attempt/response extraction a second time —
// Moodle refuses to enable this plugin if that dependency is missing or too
// old, rather than failing confusingly at runtime.
$plugin->dependencies = [
    'mod_quiz'           => 2022041900,
    'quiz_quizanalytics' => 2026080300,
];
