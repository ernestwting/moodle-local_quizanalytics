<?php
/**
 * Admin settings for quiz_solutionprocess.
 *
 * Because this file exists, Moodle automatically adds a settings page under
 * Site administration > Plugins > Quiz reports > Solution Process
 * Visualization. Unlike quiz_quizanalytics (a single fixed /analyze URL),
 * this plugin calls two different endpoint paths on the same analytics
 * service (/solution-process/meta and /solution-process), so the setting
 * here is the service's *base* URL, matching local_quizanalytics's pattern.
 *
 * @package quiz_solutionprocess
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_configtext(
        'quiz_solutionprocess/apibaseurl',
        get_string('apibaseurl', 'quiz_solutionprocess'),
        get_string('apibaseurl_desc', 'quiz_solutionprocess'),
        'http://127.0.0.1:8600',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'quiz_solutionprocess/apitimeout',
        get_string('apitimeout', 'quiz_solutionprocess'),
        get_string('apitimeout_desc', 'quiz_solutionprocess'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'quiz_solutionprocess/apipdftimeout',
        get_string('apipdftimeout', 'quiz_solutionprocess'),
        get_string('apipdftimeout_desc', 'quiz_solutionprocess'),
        90,
        PARAM_INT
    ));

}
