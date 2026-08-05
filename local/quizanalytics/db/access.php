<?php
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
