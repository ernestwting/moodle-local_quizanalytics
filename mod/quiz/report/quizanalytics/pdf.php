<?php
/**
 * Streams the Question Analysis PDF report for one quiz.
 *
 * A separate entry point from report.php (not a mode of it) so it has its
 * own require_capability() call — reachable directly, not just via a link
 * on the report page. Deliberately re-derives $quiz/$cm/$course and re-fetches
 * response records from the database itself rather than trusting anything
 * posted by the client: the "Generate PDF Report" form only ever submits the
 * quiz's course-module id and the user's section/colorblind choices, never a
 * copy of the response data.
 *
 * @package quiz_quizanalytics
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/filelib.php'); // send_file() lives here, not autoloaded for a lean entry point like this.
require_once(__DIR__ . '/classes/data_fetcher.php');
require_once(__DIR__ . '/classes/api_client.php');

$id = required_param('id', PARAM_INT); // Course-module id, same as report.php.

$cm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quiz/quizanalytics:view', $context);

$colorblind = (bool) optional_param('colorblind', 0, PARAM_INT);
$sections = optional_param_array('sections', [], PARAM_RAW);

$records = quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);
if (empty($records)) {
    throw new \moodle_exception('noattempts', 'quiz_quizanalytics');
}

$client = new quiz_quizanalytics_api_client();
$pdf = $client->download_pdf([
    'quiz_name'         => $quiz->name,
    'records'           => $records,
    'selected_sections' => !empty($sections) ? $sections : null,
    'colorblind_mode'   => $colorblind,
]);

if ($pdf === null) {
    throw new \moodle_exception('pdferror', 'quiz_quizanalytics');
}

$filename = clean_filename($quiz->name . '-question-analysis.pdf');
send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
