<?php
/**
 * Streams the Solution Process Visualization PDF report for one
 * (quiz, question, part).
 *
 * A separate entry point from report.php (not a mode of it) so it has its
 * own require_capability() call. Re-derives $quiz/$cm/$course and re-fetches
 * response records itself, and re-validates the question/part selection
 * against a fresh meta() call — never trusts anything posted by the client
 * beyond the course-module id and the selection/section choices.
 *
 * @package quiz_solutionprocess
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/filelib.php'); // send_file() lives here, not autoloaded for a lean entry point like this.
require_once($CFG->dirroot . '/mod/quiz/report/quizanalytics/classes/data_fetcher.php');
require_once(__DIR__ . '/classes/api_client.php');

$id = required_param('id', PARAM_INT); // Course-module id, same as report.php.

$cm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quiz/solutionprocess:view', $context);

$colorblind = (bool) optional_param('colorblind', 0, PARAM_INT);
$sections = optional_param_array('sections', [], PARAM_RAW);

$records = quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);
if (empty($records)) {
    throw new \moodle_exception('noattempts', 'quiz_solutionprocess');
}

$client = new quiz_solutionprocess_api_client();

$meta = $client->meta($quiz->name, $records);
if ($meta === null || empty($meta['questions'])) {
    throw new \moodle_exception('servererror', 'quiz_solutionprocess');
}

$questionnames = array_column($meta['questions'], 'name');
$question = required_param('spvquestion', PARAM_RAW);
if (!in_array($question, $questionnames, true)) {
    throw new \moodle_exception('servererror', 'quiz_solutionprocess'); // Stale/tampered selection — fail closed.
}

$partsforquestion = 1;
foreach ($meta['questions'] as $q) {
    if ($q['name'] === $question) {
        $partsforquestion = max(1, (int) $q['parts']);
        break;
    }
}
$part = optional_param('spvpart', 1, PARAM_INT);
if ($part < 1 || $part > $partsforquestion) {
    $part = 1;
}

$pdf = $client->download_pdf(
    $quiz->name, $records, $question, $part,
    !empty($sections) ? $sections : null, $colorblind
);

if ($pdf === null) {
    throw new \moodle_exception('pdferror', 'quiz_solutionprocess');
}

$filename = clean_filename($quiz->name . '-' . $question . '-part' . $part . '-solution-process.pdf');
send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
