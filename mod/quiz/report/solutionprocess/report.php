<?php
/**
 * The "Solution Process Visualization" tab on mod_quiz's results page.
 *
 * Moodle's mod/quiz/report.php dispatcher finds this automatically because of
 * the folder name (report/solutionprocess) and the class name
 * (quiz_solutionprocess_report) — that naming convention is how quiz report
 * subplugins register themselves, there's no separate "register this report"
 * step needed.
 *
 * @package quiz_solutionprocess
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/quizanalytics/classes/data_fetcher.php');
require_once(__DIR__ . '/classes/api_client.php');

use quiz_quizanalytics\output\sections_renderer;

class quiz_solutionprocess_report extends quiz_default_report {

    /**
     * @param stdClass $quiz
     * @param stdClass $cm
     * @param stdClass $course
     * @return bool
     */
    public function display($quiz, $cm, $course) {
        global $OUTPUT, $PAGE;

        $context = context_module::instance($cm->id);
        require_capability('quiz/solutionprocess:view', $context);

        // Draws the quiz name header plus the Grades/Responses/Statistics/
        // Interactive Analytics/Solution Process Visualization tab strip,
        // with "solutionprocess" highlighted.
        $this->print_header_and_tabs($cm, $course, $quiz, 'solutionprocess');

        // --- 1. Pull this quiz's finished attempts. Deliberately reuses    ---
        // --- quiz_quizanalytics_data_fetcher rather than re-implementing   ---
        // --- attempt/response extraction — see version.php's dependency.  ---
        $records = quiz_quizanalytics_data_fetcher::get_response_records($quiz, $cm, $course);
        if (empty($records)) {
            echo $OUTPUT->notification(get_string('noattempts', 'quiz_solutionprocess'), 'notifymessage');
            return true;
        }

        // --- 2. Cheap metadata call to populate the selectors. ---
        $client = new quiz_solutionprocess_api_client();
        $meta = $client->meta($quiz->name, $records);
        if ($meta === null) {
            echo $OUTPUT->notification(get_string('servererror', 'quiz_solutionprocess'), 'notifyproblem');
            return true;
        }
        if (empty($meta['questions'])) {
            echo $OUTPUT->notification(get_string('nostackquestions', 'quiz_solutionprocess'), 'notifymessage');
            return true;
        }

        // --- 3. Resolve the current question/part/student selection,     ---
        // --- validating every GET param against what meta() actually     ---
        // --- returned rather than trusting the request.                  ---
        $questionnames = array_column($meta['questions'], 'name');
        $question = optional_param('spvquestion', $questionnames[0], PARAM_RAW);
        if (!in_array($question, $questionnames, true)) {
            $question = $questionnames[0];
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

        $studentid = optional_param('spvstudent', '', PARAM_RAW);
        if ($studentid !== '') {
            $validstudent = false;
            foreach ($meta['students'] as $s) {
                if ($s['id'] === $studentid) {
                    $validstudent = true;
                    break;
                }
            }
            if (!$validstudent) {
                $studentid = '';
            }
        }

        // Register the resolved selection onto $PAGE->url so every form on
        // this page (this selector, and the shared colorblind toggle) can
        // preserve it as hidden fields without duplicating this logic.
        $PAGE->url->params([
            'spvquestion' => $question,
            'spvpart'     => $part,
            'spvstudent'  => $studentid,
        ]);

        $colorblind = sections_renderer::resolve_colorblind_mode();

        echo self::render_selector_form($PAGE->url, $meta, $question, $partsforquestion, $part, $studentid);
        echo sections_renderer::render_colorblind_toggle($colorblind);

        // --- 4. The actual visualization for the current selection. ---
        $result = $client->analyze(
            $quiz->name, $records, $question, $part,
            $studentid !== '' ? $studentid : null, $colorblind
        );
        if ($result === null) {
            echo $OUTPUT->notification(get_string('servererror', 'quiz_solutionprocess'), 'notifyproblem');
            return true;
        }

        echo sections_renderer::render_containers('spv');
        echo sections_renderer::render_vendor_and_payload('spv', $result);

        return true;
    }

    /**
     * The question/part/student selector — a plain GET-reload form, matching
     * the established convention elsewhere in this plugin family (no JS/AJAX
     * needed, and every distinct selection stays its own cacheable URL for
     * the caching layer added later). Public+static so local_quizanalytics
     * can reuse it unchanged when embedding Solution Process Visualization
     * on the course-level per-quiz view, rather than duplicating this form.
     *
     * @param moodle_url $url
     * @param array $meta
     * @param string $question
     * @param int $partsforquestion
     * @param int $part
     * @param string $studentid
     * @return string
     */
    public static function render_selector_form(
        \moodle_url $url,
        array $meta,
        string $question,
        int $partsforquestion,
        int $part,
        string $studentid
    ): string {
        $ownparams = ['spvquestion', 'spvpart', 'spvstudent'];

        $html = html_writer::start_tag('form', [
            'method' => 'get', 'action' => $url->out_omit_querystring(), 'class' => 'mb-3',
        ]);
        foreach ($url->params() as $name => $value) {
            if (in_array($name, $ownparams, true)) {
                continue;
            }
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }

        $questionoptions = [];
        foreach ($meta['questions'] as $q) {
            $questionoptions[$q['name']] = $q['name'];
        }
        $html .= html_writer::label(get_string('selectquestion', 'quiz_solutionprocess'), 'spv-question-select');
        $html .= ' ' . html_writer::select($questionoptions, 'spvquestion', $question, false, ['id' => 'spv-question-select']);
        $html .= ' ';

        $partoptions = [];
        for ($i = 1; $i <= $partsforquestion; $i++) {
            $partoptions[$i] = $i;
        }
        $html .= html_writer::label(get_string('selectpart', 'quiz_solutionprocess'), 'spv-part-select');
        $html .= ' ' . html_writer::select($partoptions, 'spvpart', $part, false, ['id' => 'spv-part-select']);
        $html .= ' ';

        $studentoptions = ['' => get_string('selectstudentnone', 'quiz_solutionprocess')];
        foreach ($meta['students'] as $s) {
            $studentoptions[$s['id']] = $s['name'];
        }
        $html .= html_writer::label(get_string('selectstudent', 'quiz_solutionprocess'), 'spv-student-select');
        $html .= ' ' . html_writer::select($studentoptions, 'spvstudent', $studentid, false, ['id' => 'spv-student-select']);
        $html .= ' ';

        $html .= html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => get_string('gobutton', 'quiz_solutionprocess'), 'class' => 'btn btn-secondary',
        ]);
        $html .= html_writer::end_tag('form');
        return $html;
    }
}
