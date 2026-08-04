<?php
/**
 * Admin settings for local_quizanalytics.
 *
 * Unlike a quiz-report subplugin's settings.php (where core pre-creates a
 * $settings admin_settingpage before including the file), core\plugininfo\
 * local::load_settings() just include()s this file directly with $ADMIN
 * available and nothing else — so a local_ plugin must create its own
 * admin_settingpage and add it to the tree itself. Verified against
 * public/lib/classes/plugininfo/local.php in the installed Moodle core.
 *
 * This points at the SAME analytics microservice quiz_quizanalytics already
 * uses — there is only ever one instance of that service running. The two
 * plugins store the setting slightly differently (this one stores just the
 * base URL, since it needs to call both /analyze and /analyze-course) but
 * both should point at the same host:port.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_quizanalytics',
        get_string('pluginname', 'local_quizanalytics')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/apibaseurl',
        get_string('apibaseurl', 'local_quizanalytics'),
        get_string('apibaseurl_desc', 'local_quizanalytics'),
        'http://127.0.0.1:8600',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/apitimeout',
        get_string('apitimeout', 'local_quizanalytics'),
        get_string('apitimeout_desc', 'local_quizanalytics'),
        30,
        PARAM_INT
    ));

}
