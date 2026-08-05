<?php
/**
 * The "Analytics" page: course-wide cross-quiz comparison by default, or a
 * single STACK quiz's Question Analytics or Solution Process Visualization
 * when ?quizid=... is supplied (?view=question|solutionprocess picks which,
 * defaulting to Question Analytics).
 *
 * Reached from the course's secondary navigation "Analytics" entry (see
 * lib.php's local_quizanalytics_extend_navigation_course()), from the
 * "Analytics" link this plugin adds to each STACK quiz's own settings menu
 * (lib.php's local_quizanalytics_extend_settings_navigation(), which links
 * straight here with &quizid= already set), or directly via
 * /local/quizanalytics/index.php?id=<courseid>.
 *
 * @package local_quizanalytics
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/data_fetcher.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/api_client.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/cache_helper.php');

use local_quizanalytics\output\sections_renderer;

$courseid = required_param('id', PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quizanalytics:view', $context);

$PAGE->set_url('/local/quizanalytics/index.php', ['id' => $courseid, 'quizid' => $quizid]);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);
$PAGE->set_title($course->shortname . ': ' . get_string('pagetitle', 'local_quizanalytics'));
$PAGE->set_heading($course->fullname);

$stackquizzes = local_quizanalytics_data_fetcher::get_course_stack_quizzes($course->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_quizanalytics'));

if (empty($stackquizzes)) {
    echo $OUTPUT->notification(get_string('nostackquizzes', 'local_quizanalytics'), 'notifymessage');
    echo $OUTPUT->footer();
    exit;
}

// --- The quiz selector: a plain GET form, so picking a quiz is just a page ---
// --- reload with &quizid=... — no JS or AJAX needed for the switch itself. ---
$selectoptions = [0 => get_string('quizselectoption', 'local_quizanalytics')];
foreach ($stackquizzes as $quiz) {
    $selectoptions[$quiz->id] = $quiz->name;
}

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::label(get_string('quizselectlabel', 'local_quizanalytics'), 'qa-quizid-select');
echo html_writer::select($selectoptions, 'quizid', $quizid, false, ['id' => 'qa-quizid-select']);
echo ' ';
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('gobutton', 'local_quizanalytics'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

$colorblind = sections_renderer::resolve_colorblind_mode();
echo sections_renderer::render_colorblind_toggle($colorblind);

$client = new local_quizanalytics_api_client();

if ($quizid) {
    // --- Drill-down: one quiz's Question Analytics or Solution Process ---
    // --- Visualization, picked by the "view" selector below.           ---
    $selectedquiz = null;
    foreach ($stackquizzes as $quiz) {
        if ((int) $quiz->id === $quizid) {
            $selectedquiz = $quiz;
            break;
        }
    }
    if (!$selectedquiz) {
        // Not a STACK quiz in this course (bad/stale quizid param) — fail
        // closed rather than silently falling back to course-wide.
        echo $OUTPUT->notification(get_string('nostackquizzes', 'local_quizanalytics'), 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->heading($selectedquiz->name, 3);

    // Cheap fingerprint first — a cache hit below skips the expensive
    // per-attempt DB fetch entirely. $records is fetched lazily (at most
    // once) since either view below might independently need it on its
    // own cache miss.
    $stats = local_quizanalytics_cache_helper::stats_for_quiz($selectedquiz);
    if ($stats->count === 0) {
        echo $OUTPUT->notification(get_string('noattempts', 'local_quizanalytics'), 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $records = null;
    $fetchrecords = function () use (&$records, $selectedquiz, $course): array {
        return $records ??= local_quizanalytics_data_fetcher::get_response_records_for_quiz($selectedquiz, $course);
    };

    // --- "View:" sub-selector — only the selected view's data is ever   ---
    // --- fetched/computed (a plain GET reload, not a client-side tab    ---
    // --- swap that would need both already computed).                  ---
    $view = optional_param('view', 'question', PARAM_ALPHA);
    if (!in_array($view, ['question', 'solutionprocess'], true)) {
        $view = 'question';
    }
    $PAGE->url->param('view', $view);
    echo sections_renderer::render_view_selector_form($view);

    if ($view === 'question') {
        $qacache = cache::make('local_quizanalytics', 'questionanalysis');
        $qakey = local_quizanalytics_cache_helper::build_key($selectedquiz->id, $stats->fingerprint, $colorblind);
        $result = $qacache->get($qakey);
        if ($result === false) {
            $result = $client->analyze($selectedquiz->name, $fetchrecords(), $colorblind);
            if ($result !== null) {
                $qacache->set($qakey, $result);
            }
        }

        if ($result === null) {
            echo $OUTPUT->notification(get_string('servererror', 'local_quizanalytics'), 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        }

        echo sections_renderer::render_containers('qa');
        echo sections_renderer::render_vendor_and_payload('qa', $result);

        echo $OUTPUT->heading(get_string('generatepdfheading', 'local_quizanalytics'), 3);
        echo sections_renderer::render_pdf_form(
            new moodle_url('/local/quizanalytics/pdf.php'),
            ['id' => $courseid, 'kind' => 'question', 'quizid' => $quizid, 'colorblind' => $colorblind ? 1 : 0],
            $client->report_sections('question'),
            get_string('downloadpdfbutton', 'local_quizanalytics'),
            'qa-pdf',
            'qa'
        );
    } else {
        // --- Solution Process Visualization ---
        $metacache = cache::make('local_quizanalytics', 'solutionprocessmeta');
        $metakey = local_quizanalytics_cache_helper::build_key($selectedquiz->id, $stats->fingerprint);
        $meta = $metacache->get($metakey);
        if ($meta === false) {
            $meta = $client->solution_process_meta($selectedquiz->name, $fetchrecords());
            if ($meta !== null) {
                $metacache->set($metakey, $meta);
            }
        }

        if ($meta === null) {
            echo $OUTPUT->notification(get_string('servererror', 'local_quizanalytics'), 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        }
        if (empty($meta['questions'])) {
            echo $OUTPUT->notification(get_string('nostackquestions', 'local_quizanalytics'), 'notifymessage');
            echo $OUTPUT->footer();
            exit;
        }

        // Resolve the current question/part/student selection, validating
        // every GET param against what meta() actually returned rather than
        // trusting the request.
        $questionnames = array_column($meta['questions'], 'name');
        $spvquestion = optional_param('spvquestion', $questionnames[0], PARAM_RAW);
        if (!in_array($spvquestion, $questionnames, true)) {
            $spvquestion = $questionnames[0];
        }

        $spvpartsforquestion = 1;
        foreach ($meta['questions'] as $q) {
            if ($q['name'] === $spvquestion) {
                $spvpartsforquestion = max(1, (int) $q['parts']);
                break;
            }
        }
        $spvpart = optional_param('spvpart', 1, PARAM_INT);
        if ($spvpart < 1 || $spvpart > $spvpartsforquestion) {
            $spvpart = 1;
        }

        $spvstudentid = optional_param('spvstudent', '', PARAM_RAW);
        if ($spvstudentid !== '') {
            $spvvalidstudent = false;
            foreach ($meta['students'] as $s) {
                if ($s['id'] === $spvstudentid) {
                    $spvvalidstudent = true;
                    break;
                }
            }
            if (!$spvvalidstudent) {
                $spvstudentid = '';
            }
        }

        $PAGE->url->params([
            'spvquestion' => $spvquestion,
            'spvpart'     => $spvpart,
            'spvstudent'  => $spvstudentid,
        ]);

        echo sections_renderer::render_solutionprocess_selector_form(
            $PAGE->url, $meta, $spvquestion, $spvpartsforquestion, $spvpart, $spvstudentid
        );

        $resultcache = cache::make('local_quizanalytics', 'solutionprocess');
        $resultkey = local_quizanalytics_cache_helper::build_key(
            $selectedquiz->id, $stats->fingerprint, $spvquestion, $spvpart, $spvstudentid, $colorblind
        );
        $result = $resultcache->get($resultkey);
        if ($result === false) {
            $result = $client->solution_process_analyze(
                $selectedquiz->name, $fetchrecords(), $spvquestion, $spvpart,
                $spvstudentid !== '' ? $spvstudentid : null, $colorblind
            );
            if ($result !== null) {
                $resultcache->set($resultkey, $result);
            }
        }

        if ($result === null) {
            echo $OUTPUT->notification(get_string('servererror', 'local_quizanalytics'), 'notifyproblem');
            echo $OUTPUT->footer();
            exit;
        }

        echo sections_renderer::render_containers('spv');
        echo sections_renderer::render_vendor_and_payload('spv', $result);

        echo $OUTPUT->heading(get_string('generatepdfheading', 'local_quizanalytics'), 3);
        echo sections_renderer::render_pdf_form(
            new moodle_url('/local/quizanalytics/pdf.php'),
            [
                'id'          => $courseid,
                'kind'        => 'solutionprocess',
                'quizid'      => $quizid,
                'spvquestion' => $spvquestion,
                'spvpart'     => $spvpart,
                'colorblind'  => $colorblind ? 1 : 0,
            ],
            $client->report_sections('solutionprocess'),
            get_string('downloadpdfbutton', 'local_quizanalytics'),
            'spv-pdf',
            'spv'
        );
    }
} else {
    // --- Course-wide: cross-quiz comparison across every STACK quiz. ---
    echo $OUTPUT->heading(get_string('coursewideheading', 'local_quizanalytics'), 3);

    // Combines every STACK quiz's attempts into one computation — the one
    // path in this plugin whose cost genuinely scales with the whole
    // course rather than a single quiz, so it's the one raising PHP's own
    // execution time limit (see settings.php).
    core_php_time_limit::raise((int) get_config('local_quizanalytics', 'computetimelimit'));

    // Cheap fingerprint across every STACK quiz in the course at once (the
    // course-wide result depends on all of their attempts together) — the
    // empty-course check below uses this instead of the expensive per-quiz
    // fetch, and $byquiz itself is fetched lazily only on a cache miss.
    $coursestats = local_quizanalytics_cache_helper::stats_for_quizzes($stackquizzes);
    if ($coursestats->count === 0) {
        echo $OUTPUT->notification(get_string('nocourseattempts', 'local_quizanalytics'), 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $fetchbyquiz = function () use ($course, $stackquizzes): array {
        $byquiz = local_quizanalytics_data_fetcher::get_course_response_records($course, $stackquizzes);
        return array_filter($byquiz, fn($records) => !empty($records));
    };

    // --- Grade-type selector for the Attempts-vs-Grades scatter plot — a  ---
    // --- plain GET-reload radio group, same convention as everywhere     ---
    // --- else in this plugin.                                           ---
    $gradetypeoptions = [
        'Highest Grade' => get_string('gradetypehighest', 'local_quizanalytics'),
        'Average Grade' => get_string('gradetypeaverage', 'local_quizanalytics'),
        'Minimum Grade' => get_string('gradetypeminimum', 'local_quizanalytics'),
    ];
    $gradetype = optional_param('gradetype', 'Average Grade', PARAM_RAW);
    if (!array_key_exists($gradetype, $gradetypeoptions)) {
        $gradetype = 'Average Grade';
    }
    $PAGE->url->param('gradetype', $gradetype);

    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(), 'class' => 'mb-3']);
    foreach ($PAGE->url->params() as $name => $value) {
        if ($name === 'gradetype') {
            continue;
        }
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    echo html_writer::tag('span', get_string('gradetypelabel', 'local_quizanalytics') . ' ');
    foreach ($gradetypeoptions as $value => $label) {
        $radioid = 'qw-gradetype-' . preg_replace('/[^a-z]/', '', strtolower($value));
        echo html_writer::empty_tag('input', array_merge([
            'type' => 'radio', 'name' => 'gradetype', 'value' => $value, 'id' => $radioid,
        ], $gradetype === $value ? ['checked' => 'checked'] : []));
        echo html_writer::label($label, $radioid, true, ['class' => 'mr-2 ml-1']);
    }
    echo ' ' . html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('gobutton', 'local_quizanalytics'), 'class' => 'btn btn-secondary btn-sm',
    ]);
    echo html_writer::end_tag('form');

    $qwcache = cache::make('local_quizanalytics', 'quizanalysiscoursewide');
    $qwkey = local_quizanalytics_cache_helper::build_key($courseid, $coursestats->fingerprint, $gradetype, $colorblind);
    $result = $qwcache->get($qwkey);
    if ($result === false) {
        $result = $client->analyze_course($course->fullname, $fetchbyquiz(), $colorblind, $gradetype);
        if ($result !== null) {
            $qwcache->set($qwkey, $result);
        }
    }

    if ($result === null) {
        echo $OUTPUT->notification(get_string('servererror', 'local_quizanalytics'), 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    echo sections_renderer::render_containers('qw');
    echo sections_renderer::render_vendor_and_payload('qw', $result);

    echo $OUTPUT->heading(get_string('generatepdfheading', 'local_quizanalytics'), 3);
    echo sections_renderer::render_pdf_form(
        new moodle_url('/local/quizanalytics/pdf.php'),
        ['id' => $courseid, 'kind' => 'quiz', 'colorblind' => $colorblind ? 1 : 0],
        $client->report_sections('quiz'),
        get_string('downloadpdfbutton', 'local_quizanalytics'),
        'qw-pdf',
        'qw'
    );
}

echo $OUTPUT->footer();
