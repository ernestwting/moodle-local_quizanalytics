<?php
/**
 * Capabilities for local_quizanalytics.
 *
 * Unlike the per-quiz quiz_quizanalytics report (a CONTEXT_MODULE capability
 * checked on one quiz), this capability is checked at CONTEXT_COURSE, since
 * the "Analytics" tab lives on the course's secondary navigation and can show
 * data from every STACK quiz in the course at once.
 *
 * @package    local_quizanalytics
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
