<?php
/**
 * Capabilities for quiz_quizanalytics.
 *
 * Moodle's quiz report dispatcher (mod/quiz/report.php) checks a capability
 * named quiz/<reportname>:view before it will render your report at all, so
 * this file is mandatory even though the report has only one thing to guard.
 *
 * @package    quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'quiz/quizanalytics:view' => [
        'riskbitmask'  => RISK_PERSONAL, // Shows individual students' response data.
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            // Deliberately NOT granted to 'student' — this exposes other
            // students' response text and grades.
        ],
    ],

];
