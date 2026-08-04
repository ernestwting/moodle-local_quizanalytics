<?php
/**
 * The "Question Analytics" tab on mod_quiz's results page.
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
require_once(__DIR__ . '/classes/cache_helper.php');

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
        // Question Analytics tab strip, with "quizanalytics" highlighted.
        $this->print_header_and_tabs($cm, $course, $quiz, 'quizanalytics');

        // --- 1. Cheap fingerprint of this quiz's finished attempts (one small ---
        // --- COUNT/MAX/SUM query) — lets a cache hit below skip both the     ---
        // --- expensive per-attempt DB fetch and the analytics service call   ---
        // --- entirely, while still reflecting new/regraded attempts          ---
        // --- immediately rather than waiting out the cache's TTL.            ---
        $stats = quiz_quizanalytics_cache_helper::stats_for_quiz($quiz);
        if ($stats->count === 0) {
            echo $OUTPUT->notification(get_string('noattempts', 'quiz_quizanalytics'), 'notifymessage');
            return true;
        }

        $colorblind = sections_renderer::resolve_colorblind_mode();
        $client = new quiz_quizanalytics_api_client();

        $cache = cache::make('quiz_quizanalytics', 'questionanalysis');
        $cachekey = quiz_quizanalytics_cache_helper::build_key($quiz->id, $stats->fingerprint, $colorblind);
        $result = $cache->get($cachekey);

        if ($result === false) {
            // --- 2. Cache miss: pull this quiz's finished attempts, then hand ---
            // --- off to the local analytics engine. Nothing here leaves this ---
            // --- server: api_client only ever calls the configured           ---
            // --- localhost/private-network endpoint.                        ---
            $records = quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);

            if (empty($records)) {
                echo $OUTPUT->notification(get_string('noattempts', 'quiz_quizanalytics'), 'notifymessage');
                return true;
            }

            $result = $client->analyze([
                'quiz_name'       => $quiz->name,
                'records'         => $records,
                'colorblind_mode' => $colorblind,
            ]);

            if ($result === null) {
                echo $OUTPUT->notification(get_string('servererror', 'quiz_quizanalytics'), 'notifyproblem');
                return true;
            }

            $cache->set($cachekey, $result);
        }

        // --- 3. Render via the shared sections_renderer (see its docblock ---
        // --- for why the vendored JS/CSS is echoed as raw tags rather    ---
        // --- than through $PAGE->requires()).                            ---
        echo sections_renderer::render_colorblind_toggle($colorblind);
        echo sections_renderer::render_containers('qa');
        echo sections_renderer::render_vendor_and_payload('qa', $result);

        // --- 4. "Generate PDF Report" — a separate GET-reload form to      ---
        // --- pdf.php, which re-derives everything server-side rather than ---
        // --- trusting a client-posted copy of $records (see pdf.php).     ---
        echo $OUTPUT->heading(get_string('generatepdfheading', 'quiz_quizanalytics'), 3);
        echo sections_renderer::render_pdf_form(
            new moodle_url('/mod/quiz/report/quizanalytics/pdf.php'),
            ['id' => $cm->id, 'colorblind' => $colorblind ? 1 : 0],
            $client->report_sections(),
            get_string('downloadpdfbutton', 'quiz_quizanalytics'),
            'qa-pdf'
        );

        return true;
    }
}
