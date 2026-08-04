<?php
/**
 * The "Interactive Analytics" tab on mod_quiz's results page.
 *
 * Moodle's mod/quiz/report.php dispatcher finds this automatically because of
 * the folder name (report/quizanalytics) and the class name
 * (quiz_quizanalytics_report) — that naming convention is how quiz report
 * subplugins register themselves, there's no separate "register this report"
 * step needed.
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once(__DIR__ . '/classes/data_fetcher.php');
require_once(__DIR__ . '/classes/api_client.php');

use quiz_quizanalytics\output\sections_renderer;

class quiz_quizanalytics_report extends quiz_default_report {

    /**
     * @param stdClass $quiz
     * @param stdClass $cm
     * @param stdClass $course
     * @return bool
     */
    public function display($quiz, $cm, $course) {
        global $OUTPUT, $PAGE;

        $context = context_module::instance($cm->id);
        require_capability('quiz/quizanalytics:view', $context);

        // Draws the quiz name header plus the Grades/Responses/Statistics/
        // Interactive Analytics tab strip, with "quizanalytics" highlighted.
        $this->print_header_and_tabs($cm, $course, $quiz, 'quizanalytics');

        // --- 1. Pull this quiz's finished attempts straight out of the DB. ---
        $records = quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);

        if (empty($records)) {
            echo $OUTPUT->notification(get_string('noattempts', 'quiz_quizanalytics'), 'notifymessage');
            return true;
        }

        // --- 2. Hand off to the local analytics engine. Nothing here leaves ---
        // --- this server: api_client only ever calls the configured        ---
        // --- localhost/private-network endpoint.                          ---
        $client = new quiz_quizanalytics_api_client();
        $result = $client->analyze([
            'quiz_name' => $quiz->name,
            'records'   => $records,
        ]);

        if ($result === null) {
            echo $OUTPUT->notification(get_string('servererror', 'quiz_quizanalytics'), 'notifyproblem');
            return true;
        }

        // --- 3. Render via the shared sections_renderer (see its docblock ---
        // --- for why the vendored JS/CSS is echoed as raw tags rather    ---
        // --- than through $PAGE->requires()).                            ---
        echo sections_renderer::render_containers('qa');
        echo sections_renderer::render_vendor_and_payload('qa', $result);

        return true;
    }
}
