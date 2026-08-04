<?php
/**
 * The course-level "Analytics" page: course-wide cross-quiz comparison by
 * default, or a single STACK quiz's full question-level analytics when
 * ?quizid=... is supplied.
 *
 * Reached from the course's secondary navigation "Analytics" tab (see
 * lib.php for how that tab gets registered and gated), or directly via
 * /local/quizanalytics/index.php?id=<courseid>.
 *
 * @package local_quizanalytics
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/data_fetcher.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/api_client.php');

use quiz_quizanalytics\output\sections_renderer;

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

$client = new local_quizanalytics_api_client();
$result = null;

if ($quizid) {
    // --- Drill-down: one quiz's full question-level analytics. ---
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

    $records = local_quizanalytics_data_fetcher::get_response_records_for_quiz($selectedquiz, $course);
    if (empty($records)) {
        echo $OUTPUT->notification(get_string('noattempts', 'local_quizanalytics'), 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $result = $client->analyze($selectedquiz->name, $records);
} else {
    // --- Course-wide: cross-quiz comparison across every STACK quiz. ---
    echo $OUTPUT->heading(get_string('coursewideheading', 'local_quizanalytics'), 3);

    $byquiz = local_quizanalytics_data_fetcher::get_course_response_records($course, $stackquizzes);
    $byquiz = array_filter($byquiz, fn($records) => !empty($records));

    if (empty($byquiz)) {
        echo $OUTPUT->notification(get_string('nocourseattempts', 'local_quizanalytics'), 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $result = $client->analyze_course($course->fullname, $byquiz);
}

if ($result === null) {
    echo $OUTPUT->notification(get_string('servererror', 'local_quizanalytics'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// --- Render via the shared sections_renderer (mod_quiz_report_quizanalytics) ---
// --- — keep the {summary, sections} contract in sync with the FastAPI      ---
// --- service's /analyze and /analyze-course responses. Vendored JS/CSS is  ---
// --- echoed as raw tags rather than through $PAGE->requires(), same reason ---
// --- as report.php: that path re-minifies already-minified vendor bundles  ---
// --- and was confirmed to corrupt them.                                    ---
echo sections_renderer::render_containers('qa');
echo sections_renderer::render_vendor_and_payload('qa', $result);

echo $OUTPUT->footer();
