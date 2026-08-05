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
 * All the STACK/Maxima response analytics run in-process (see classes/
 * analytics/) rather than calling a separate service, so the only setting
 * left is how long PHP itself is allowed to spend on the heaviest of those
 * computations.
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
        'local_quizanalytics/computetimelimit',
        get_string('computetimelimit', 'local_quizanalytics'),
        get_string('computetimelimit_desc', 'local_quizanalytics'),
        120,
        PARAM_INT
    ));

}
