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
require_once($CFG->dirroot . '/mod/quiz/report/quizanalytics/classes/cache_helper.php');
require_once($CFG->dirroot . '/mod/quiz/report/solutionprocess/classes/api_client.php');
require_once($CFG->dirroot . '/mod/quiz/report/solutionprocess/report.php');

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

$colorblind = sections_renderer::resolve_colorblind_mode();
echo sections_renderer::render_colorblind_toggle($colorblind);

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

    // Cheap fingerprint first — a cache hit below skips the expensive
    // per-attempt DB fetch entirely. $records is fetched lazily (at most
    // once) since the Solution Process Visualization embed further down
    // also needs it on its own cache miss.
    $stats = quiz_quizanalytics_cache_helper::stats_for_quiz($selectedquiz);
    if ($stats->count === 0) {
        echo $OUTPUT->notification(get_string('noattempts', 'local_quizanalytics'), 'notifymessage');
        echo $OUTPUT->footer();
        exit;
    }

    $records = null;
    $fetchrecords = function () use (&$records, $selectedquiz, $course): array {
        return $records ??= local_quizanalytics_data_fetcher::get_response_records_for_quiz($selectedquiz, $course);
    };

    // Same cache area (and same key format) quiz_quizanalytics's own
    // report.php uses for this exact request shape — visiting a quiz's
    // Interactive Analytics tab and this course-level drill-down for the
    // same quiz shares one cache entry rather than computing it twice.
    $qacache = cache::make('quiz_quizanalytics', 'questionanalysis');
    $qakey = quiz_quizanalytics_cache_helper::build_key($selectedquiz->id, $stats->fingerprint, $colorblind);
    $result = $qacache->get($qakey);
    if ($result === false) {
        $result = $client->analyze($selectedquiz->name, $fetchrecords(), $colorblind);
        if ($result !== null) {
            $qacache->set($qakey, $result);
        }
    }
} else {
    // --- Course-wide: cross-quiz comparison across every STACK quiz. ---
    echo $OUTPUT->heading(get_string('coursewideheading', 'local_quizanalytics'), 3);

    // Cheap fingerprint across every STACK quiz in the course at once (the
    // course-wide result depends on all of their attempts together) — the
    // empty-course check below uses this instead of the expensive per-quiz
    // fetch, and $byquiz itself is fetched lazily only on a cache miss.
    $coursestats = quiz_quizanalytics_cache_helper::stats_for_quizzes($stackquizzes);
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
    // --- else in this plugin family.                                    ---
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

    $qwcache = cache::make('quiz_quizanalytics', 'quizanalysiscoursewide');
    $qwkey = quiz_quizanalytics_cache_helper::build_key($courseid, $coursestats->fingerprint, $gradetype, $colorblind);
    $result = $qwcache->get($qwkey);
    if ($result === false) {
        $result = $client->analyze_course($course->fullname, $fetchbyquiz(), $colorblind, $gradetype);
        if ($result !== null) {
            $qwcache->set($qwkey, $result);
        }
    }
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
// --- and was confirmed to corrupt them. "qa" prefix for the per-quiz       ---
// --- drill-down, "qw" (course-Wide) for the cross-quiz comparison — kept   ---
// --- distinct so neither view's DOM ids can ever collide with the other.  ---
$mainprefix = $quizid ? 'qa' : 'qw';
echo sections_renderer::render_containers($mainprefix);
echo sections_renderer::render_vendor_and_payload($mainprefix, $result);

// --- "Generate PDF Report" for whichever view is showing — Question       ---
// --- Analysis for the per-quiz drill-down, Quiz Analysis for the         ---
// --- course-wide view. pdf.php re-derives everything server-side rather  ---
// --- than trusting a client-posted copy of $records/$byquiz.             ---
echo $OUTPUT->heading(get_string('generatepdfheading', 'local_quizanalytics'), 3);
if ($quizid) {
    echo sections_renderer::render_pdf_form(
        new moodle_url('/local/quizanalytics/pdf.php'),
        ['id' => $courseid, 'kind' => 'question', 'quizid' => $quizid, 'colorblind' => $colorblind ? 1 : 0],
        $client->report_sections('question'),
        get_string('downloadpdfbutton', 'local_quizanalytics'),
        'qa-pdf'
    );
} else {
    echo sections_renderer::render_pdf_form(
        new moodle_url('/local/quizanalytics/pdf.php'),
        ['id' => $courseid, 'kind' => 'quiz', 'colorblind' => $colorblind ? 1 : 0],
        $client->report_sections('quiz'),
        get_string('downloadpdfbutton', 'local_quizanalytics'),
        'qw-pdf'
    );
}

// --- Also embed Solution Process Visualization for the selected quiz, ---
// --- reusing the exact same records already fetched above for the QA  ---
// --- call and the same selector-form/rendering code the standalone    ---
// --- quiz_solutionprocess report tab uses — see that plugin's         ---
// --- report.php for what each piece does; nothing here re-implements  ---
// --- it, just calls it a second time with a "spv" prefix so its DOM   ---
// --- ids never collide with the "qa" containers above.                ---
if ($quizid) {
    echo $OUTPUT->heading(get_string('pluginname', 'quiz_solutionprocess'), 3);

    $spvclient = new quiz_solutionprocess_api_client();

    // Same cache area (and key format) quiz_solutionprocess's own report.php
    // uses for meta() — $fetchrecords() reuses the QA branch's lazy fetch
    // above, so if the QA cache already hit, this can still avoid ever
    // touching the database for this request.
    $spvmetacache = cache::make('quiz_quizanalytics', 'solutionprocessmeta');
    $spvmetakey = quiz_quizanalytics_cache_helper::build_key($selectedquiz->id, $stats->fingerprint);
    $spvmeta = $spvmetacache->get($spvmetakey);
    if ($spvmeta === false) {
        $spvmeta = $spvclient->meta($selectedquiz->name, $fetchrecords());
        if ($spvmeta !== null) {
            $spvmetacache->set($spvmetakey, $spvmeta);
        }
    }

    if ($spvmeta === null) {
        echo $OUTPUT->notification(get_string('servererror', 'quiz_solutionprocess'), 'notifyproblem');
    } else if (empty($spvmeta['questions'])) {
        echo $OUTPUT->notification(get_string('nostackquestions', 'quiz_solutionprocess'), 'notifymessage');
    } else {
        $spvquestionnames = array_column($spvmeta['questions'], 'name');
        $spvquestion = optional_param('spvquestion', $spvquestionnames[0], PARAM_RAW);
        if (!in_array($spvquestion, $spvquestionnames, true)) {
            $spvquestion = $spvquestionnames[0];
        }

        $spvpartsforquestion = 1;
        foreach ($spvmeta['questions'] as $q) {
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
            foreach ($spvmeta['students'] as $s) {
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

        echo quiz_solutionprocess_report::render_selector_form(
            $PAGE->url, $spvmeta, $spvquestion, $spvpartsforquestion, $spvpart, $spvstudentid
        );

        // Same cache area/key format as quiz_solutionprocess's own
        // report.php for this (quiz, question, part, student, colorblind)
        // selection.
        $spvresultcache = cache::make('quiz_quizanalytics', 'solutionprocess');
        $spvresultkey = quiz_quizanalytics_cache_helper::build_key(
            $selectedquiz->id, $stats->fingerprint, $spvquestion, $spvpart, $spvstudentid, $colorblind
        );
        $spvresult = $spvresultcache->get($spvresultkey);
        if ($spvresult === false) {
            $spvresult = $spvclient->analyze(
                $selectedquiz->name, $fetchrecords(), $spvquestion, $spvpart,
                $spvstudentid !== '' ? $spvstudentid : null, $colorblind
            );
            if ($spvresult !== null) {
                $spvresultcache->set($spvresultkey, $spvresult);
            }
        }

        if ($spvresult === null) {
            echo $OUTPUT->notification(get_string('servererror', 'quiz_solutionprocess'), 'notifyproblem');
        } else {
            echo sections_renderer::render_containers('spv');
            // include_vendor=false: the 'qa' render above already emitted
            // the Plotly/KaTeX <script> tags once; re-emitting them would
            // just duplicate identical <script src> tags harmlessly, but
            // there's no reason to.
            echo sections_renderer::render_vendor_and_payload('spv', $spvresult, false);

            echo $OUTPUT->heading(get_string('generatepdfheading', 'quiz_solutionprocess'), 3);
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
                $spvclient->report_sections(),
                get_string('downloadpdfbutton', 'quiz_solutionprocess'),
                'spv-pdf'
            );
        }
    }
}

echo $OUTPUT->footer();
