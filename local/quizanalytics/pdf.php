<?php
/**
 * Streams a PDF report from the course-level "Analytics" page: the
 * per-quiz Question Analysis or Solution Process Visualization PDF
 * (?kind=question|solutionprocess&quizid=...), or the course-wide
 * cross-quiz Quiz Analysis PDF (?kind=quiz).
 *
 * A separate entry point from index.php, gated by the same
 * local/quizanalytics:view capability that governs everything else shown on
 * that page. Re-derives every quiz/course/record from trusted URL params —
 * never trusts anything posted by the client beyond the course id, quiz id,
 * selection, and section choices.
 *
 * @package local_quizanalytics
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php'); // send_file() lives here, not autoloaded for a lean entry point like this.
require_once($CFG->dirroot . '/local/quizanalytics/classes/data_fetcher.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/api_client.php');
require_once($CFG->dirroot . '/mod/quiz/report/solutionprocess/classes/api_client.php');

$courseid = required_param('id', PARAM_INT);
$kind     = required_param('kind', PARAM_ALPHA); // 'question' | 'solutionprocess' | 'quiz'

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quizanalytics:view', $context);

$colorblind = (bool) optional_param('colorblind', 0, PARAM_INT);
$sections = optional_param_array('sections', [], PARAM_RAW);
$selectedsections = !empty($sections) ? $sections : null;

$client = new local_quizanalytics_api_client();

if ($kind === 'quiz') {
    $stackquizzes = local_quizanalytics_data_fetcher::get_course_stack_quizzes($course->id);
    $byquiz = local_quizanalytics_data_fetcher::get_course_response_records($course, $stackquizzes);
    $byquiz = array_filter($byquiz, fn($records) => !empty($records));
    if (empty($byquiz)) {
        throw new \moodle_exception('nocourseattempts', 'local_quizanalytics');
    }

    $pdf = $client->download_pdf_quiz($course->fullname, $byquiz, $selectedsections, $colorblind);
    $filename = clean_filename($course->shortname . '-quiz-analysis.pdf');

} else if ($kind === 'question' || $kind === 'solutionprocess') {
    $quizid = required_param('quizid', PARAM_INT);

    $stackquizzes = local_quizanalytics_data_fetcher::get_course_stack_quizzes($course->id);
    $selectedquiz = null;
    foreach ($stackquizzes as $quiz) {
        if ((int) $quiz->id === $quizid) {
            $selectedquiz = $quiz;
            break;
        }
    }
    if (!$selectedquiz) {
        throw new \moodle_exception('nostackquizzes', 'local_quizanalytics');
    }

    $records = local_quizanalytics_data_fetcher::get_response_records_for_quiz($selectedquiz, $course);
    if (empty($records)) {
        throw new \moodle_exception('noattempts', 'local_quizanalytics');
    }

    if ($kind === 'question') {
        $pdf = $client->download_pdf_question($selectedquiz->name, $records, $selectedsections, $colorblind);
        $filename = clean_filename($selectedquiz->name . '-question-analysis.pdf');
    } else {
        $spvclient = new quiz_solutionprocess_api_client();

        $meta = $spvclient->meta($selectedquiz->name, $records);
        if ($meta === null || empty($meta['questions'])) {
            throw new \moodle_exception('servererror', 'quiz_solutionprocess');
        }

        $questionnames = array_column($meta['questions'], 'name');
        $spvquestion = required_param('spvquestion', PARAM_RAW);
        if (!in_array($spvquestion, $questionnames, true)) {
            throw new \moodle_exception('servererror', 'quiz_solutionprocess'); // Stale/tampered selection — fail closed.
        }

        $partsforquestion = 1;
        foreach ($meta['questions'] as $q) {
            if ($q['name'] === $spvquestion) {
                $partsforquestion = max(1, (int) $q['parts']);
                break;
            }
        }
        $spvpart = optional_param('spvpart', 1, PARAM_INT);
        if ($spvpart < 1 || $spvpart > $partsforquestion) {
            $spvpart = 1;
        }

        $pdf = $spvclient->download_pdf(
            $selectedquiz->name, $records, $spvquestion, $spvpart, $selectedsections, $colorblind
        );
        $filename = clean_filename(
            $selectedquiz->name . '-' . $spvquestion . '-part' . $spvpart . '-solution-process.pdf'
        );
    }
} else {
    throw new \moodle_exception('invalidparameter', 'debug');
}

if ($pdf === null) {
    throw new \moodle_exception('pdferror', 'local_quizanalytics');
}

send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
