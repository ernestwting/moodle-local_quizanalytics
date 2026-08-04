<?php
/**
 * Admin settings for quiz_quizanalytics.
 *
 * Because this file exists, Moodle automatically adds a settings page under
 * Site administration > Plugins > Quiz reports > Interactive Analytics.
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_configtext(
        'quiz_quizanalytics/apiurl',
        get_string('apiurl', 'quiz_quizanalytics'),
        get_string('apiurl_desc', 'quiz_quizanalytics'),
        'http://127.0.0.1:8600/analyze',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'quiz_quizanalytics/apitimeout',
        get_string('apitimeout', 'quiz_quizanalytics'),
        get_string('apitimeout_desc', 'quiz_quizanalytics'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'quiz_quizanalytics/apipdftimeout',
        get_string('apipdftimeout', 'quiz_quizanalytics'),
        get_string('apipdftimeout_desc', 'quiz_quizanalytics'),
        90,
        PARAM_INT
    ));

}
