<?php
// This file is part of Moodle - http://moodle.org/
//
// quiz_quizanalytics is free software distributed under the GPL, same as Moodle core.

/**
 * Version details for the Interactive Quiz Analytics report subplugin.
 *
 * @package    quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quiz_quizanalytics';   // Must match the folder: mod/quiz/report/quizanalytics.
$plugin->version   = 2026080300;             // YYYYMMDDXX — bump this every time you push an update.
$plugin->requires  = 2022041900;             // Moodle 4.0.0 — lower this if you're on an older Moodle,
                                              // raise it if you use APIs from a newer one. Check your
                                              // target Moodle's own version.php for the right number.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// This subplugin depends on mod_quiz itself (obviously), so Moodle refuses to
// enable it if mod_quiz is missing or too old.
$plugin->dependencies = [
    'mod_quiz' => 2022041900,
];
