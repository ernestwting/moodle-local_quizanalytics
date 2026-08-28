<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings for local_quizanalytics, merging both source
 * plugins' settings onto one page.
 *
 * Unlike a quiz-report subplugin's settings.php (where core pre-creates an
 * admin_settingpage before including the file), core\plugininfo\
 * local::load_settings() just include()s this file directly with $ADMIN
 * available and nothing else — so a local_ plugin must create its own
 * admin_settingpage and add it to the tree itself. Verified against
 * public/lib/classes/plugininfo/local.php in the installed Moodle core.
 *
 * computetimelimit (from the original local_quizanalytics) governs how long
 * PHP itself is allowed to spend on the Quiz Analytics course-wide
 * computation, the one path whose cost scales with the whole course. The
 * other three (from the original local_stackanalytics) are hardcoded
 * constants worth putting in an administrator's hands rather than leaving
 * as code-only defaults: the
 * Model 2 proxy-label threshold (a real methodological choice, not just a
 * tuning knob), the bloated-tree "low traffic" floor, and the help-seeking
 * lookback window. Each is read via get_config() with the original class
 * constant kept as fallback default, so an unconfigured site behaves
 * exactly as either source plugin did before this merge.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // "Re-detect" action for the resources readout below — a plain GET
    // link (sesskey-protected, same as any other admin settings action)
    // rather than a separate controller script, since settings.php is
    // already include()'d directly with the full admin environment
    // available (see this file's own docblock on why a local_ plugin's
    // settings.php works this way). Re-runs the exact same detection
    // db/install.php/db/upgrade.php ran once automatically — for an admin
    // who's moved this site to different hardware (laptop to a real
    // server, or the reverse) since then.
    if (optional_param('quizanalyticsredetect', 0, PARAM_BOOL)) {
        require_sesskey();
        require_once($CFG->dirroot . '/local/quizanalytics/classes/task/resource_detector.php');
        $workermemorymb = (int) (get_config('local_quizanalytics', 'parallelworkermemory') ?: 2048);
        $recommendation = \local_quizanalytics\task\resource_detector::recommend_parallel_workers($workermemorymb);
        set_config('parallelworkers', $recommendation['workers'], 'local_quizanalytics');
        redirect(new moodle_url('/admin/settings.php', ['section' => 'local_quizanalytics']));
    }

    $settings = new admin_settingpage(
        'local_quizanalytics',
        get_string('pluginname', 'local_quizanalytics')
    );
    $ADMIN->add('localplugins', $settings);

    // Detected-resources readout: makes item 4's install/upgrade-time
    // auto-detection visible rather than a value an admin would only
    // discover by reading parallelworkers' own current number and
    // wondering where it came from. Re-detects fresh on every page load
    // (cheap — see resource_detector.php's own detection cost) rather than
    // showing a stale, possibly-outdated figure from whenever install/
    // upgrade last ran; the "Re-detect" link updates the actual
    // parallelworkers setting to match, for a host that's changed since.
    require_once($CFG->dirroot . '/local/quizanalytics/classes/task/resource_detector.php');
    $currentworkermemorymb = (int) (get_config('local_quizanalytics', 'parallelworkermemory') ?: 2048);
    $liverecommendation = \local_quizanalytics\task\resource_detector::recommend_parallel_workers($currentworkermemorymb);
    $redetecturl = new moodle_url('/admin/settings.php', [
        'section' => 'local_quizanalytics',
        'quizanalyticsredetect' => 1,
        'sesskey' => sesskey(),
    ]);
    if ($liverecommendation['source'] === 'detected') {
        $resourcessummary = get_string('detectedresources', 'local_quizanalytics', (object) [
            'cores' => $liverecommendation['cores'],
            'memorygb' => round($liverecommendation['memorymb'] / 1024, 1),
            'workers' => $liverecommendation['workers'],
        ]);
    } else {
        $resourcessummary = get_string('detectedresourcesfailed', 'local_quizanalytics');
    }
    $settings->add(new admin_setting_heading(
        'local_quizanalytics/detectedresources',
        get_string('detectedresourcesheading', 'local_quizanalytics'),
        html_writer::div($resourcessummary, 'alert alert-info')
            . html_writer::link($redetecturl, get_string('redetectbutton', 'local_quizanalytics'), ['class' => 'btn btn-secondary btn-sm'])
    ));

    // Cron-status banner: this plugin's cache-warming scheduled task and
    // its on-demand background-compute safeguard (warm_single_view_adhoc_task
    // — see that class's own docblock) both depend entirely on Moodle's
    // cron actually running; a site with cron broken or missing doesn't
    // get a slow version of either, it gets a Quiz/Question Analytics page
    // that shows "generating in the background" and never resolves. Reuses
    // core's own tool_task cron-health check (the same one Site
    // administration's Server status page already surfaces) rather than
    // re-deriving lastcronstart/expectedcronfrequency logic here — this is
    // just a more prominent, plugin-specific place to see it, not a
    // separate source of truth.
    require_once($CFG->dirroot . '/admin/tool/task/classes/check/cronrunning.php');
    $cronresult = (new \tool_task\check\cronrunning())->get_result();
    if ($cronresult->get_status() !== \core\check\result::OK) {
        $alertclass = $cronresult->get_status() === \core\check\result::CRITICAL ? 'alert-danger' : 'alert-warning';
        $settings->add(new admin_setting_heading(
            'local_quizanalytics/cronstatus',
            get_string('cronstatusheading', 'local_quizanalytics'),
            html_writer::div(
                html_writer::tag('strong', get_string('cronstatuswarning', 'local_quizanalytics')) . ' '
                    . s($cronresult->get_summary()),
                'alert ' . $alertclass
            )
        ));
    }

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/computetimelimit',
        get_string('computetimelimit', 'local_quizanalytics'),
        get_string('computetimelimit_desc', 'local_quizanalytics'),
        120,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/questionneedsreviewthreshold',
        get_string('questionneedsreviewthreshold', 'local_quizanalytics'),
        get_string('questionneedsreviewthreshold_desc', 'local_quizanalytics'),
        \local_quizanalytics\stack\analytics\target\question_needs_review::DEFAULT_PASSRATE_THRESHOLD,
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/lowtrafficfloor',
        get_string('lowtrafficfloor', 'local_quizanalytics'),
        get_string('lowtrafficfloor_desc', 'local_quizanalytics'),
        \local_quizanalytics\stack\diagnostics\bloated_tree_report::LOW_TRAFFIC_FLOOR,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/helpseekinglookback',
        get_string('helpseekinglookback', 'local_quizanalytics'),
        get_string('helpseekinglookback_desc', 'local_quizanalytics'),
        \local_quizanalytics\stack\analytics\indicator\help_seeking_gap::LOOKBACK_SECONDS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/parallelworkers',
        get_string('parallelworkers', 'local_quizanalytics'),
        get_string('parallelworkers_desc', 'local_quizanalytics'),
        $liverecommendation['workers'],
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/parallelworkermemory',
        get_string('parallelworkermemory', 'local_quizanalytics'),
        get_string('parallelworkermemory_desc', 'local_quizanalytics'),
        2048,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizanalytics/backgroundtimebudget',
        get_string('backgroundtimebudget', 'local_quizanalytics'),
        get_string('backgroundtimebudget_desc', 'local_quizanalytics'),
        \local_quizanalytics\quiz\output\sections_output_helper::DEFAULT_BACKGROUND_TIME_BUDGET_SECONDS,
        PARAM_INT
    ));
}
