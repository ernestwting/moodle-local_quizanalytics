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
 * Streams a PDF report from the Question Analytics section: a single STACK
 * quiz's Question Analytics or Solution Process Visualization PDF
 * (?kind=question|solutionprocess&quizid=...).
 *
 * A separate entry point from questionanalytics.php, gated by the same
 * local/quizanalytics:view capability that governs everything else
 * shown on that page. Re-derives every quiz/course/record from trusted
 * server-side data (course id, quiz id, selection, and section choices) —
 * the one piece of client-posted content this route does use,
 * chart_images, is never fed into any computation, only embedded as-is
 * into the requesting user's own PDF (each image was itself captured,
 * client-side, from a chart this same route's own re-derived data
 * rendered a moment earlier — see sections-renderer.js's setupPdfForm()).
 *
 * Renamed from pdf.php when Quiz Analytics itself split away from this
 * per-quiz drill-down — the course-wide ?kind=quiz report this file used
 * to also handle moved to its own quizanalyticspdf.php.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php'); // Send_file() lives here, not autoloaded for a lean entry point like this.
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/data_fetcher.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/api_client.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/cache_helper.php');

$courseid = required_param('id', PARAM_INT);
$kind     = required_param('kind', PARAM_ALPHA); // Expected: question or solutionprocess.
$quizid   = required_param('quizid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quizanalytics:view', $context);

// PDF generation redoes the full analytics computation (re-derived
// server-side, never trusting the client beyond the chart images — see
// below) on top of drawing the document itself, so it can run longer than
// a normal page load — see settings.php.
core_php_time_limit::raise((int) get_config('local_quizanalytics', 'computetimelimit'));

$colorblind = (bool) optional_param('colorblind', 0, PARAM_INT);
$anonymize = (bool) optional_param('anonymize', 0, PARAM_INT);
$sections = optional_param_array('sections', [], PARAM_RAW);
$selectedsections = !empty($sections) ? $sections : null;

// Client-captured chart PNGs (Plotly.toImage() data: URLs), keyed by chart
// id — see sections-renderer.js's setupPdfForm()/collectChartImages().
// Never trusted as anything more than opaque image bytes to embed: any
// entry that isn't a plausible "chartid => data:image/..." pair is dropped
// rather than passed through.
$chartimages = [];
$rawchartimages = optional_param('chart_images', '', PARAM_RAW);
if ($rawchartimages !== '') {
    $decoded = json_decode($rawchartimages, true);
    if (is_array($decoded)) {
        foreach ($decoded as $chartid => $datauri) {
            if (is_string($chartid) && is_string($datauri) && strpos($datauri, 'data:image/') === 0) {
                $chartimages[$chartid] = $datauri;
            }
        }
    }
}

$client = new local_quizanalytics_quiz_api_client();

if ($kind !== 'question' && $kind !== 'solutionprocess') {
    throw new \moodle_exception('invalidparameter', 'debug');
}

$stackquizzes = local_quizanalytics_quiz_data_fetcher::get_course_stack_quizzes($course->id);
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

// Reuses whatever questionanalytics.php already fetched (and cached) for
// this exact quiz, moments earlier, to render the page this PDF button
// appears on — see get_response_records_for_quiz_cached()'s own comment
// for why this route previously always re-fetched from scratch.
$stats = local_quizanalytics_quiz_cache_helper::stats_for_quiz($selectedquiz);
$records = local_quizanalytics_quiz_data_fetcher::get_response_records_for_quiz_cached($selectedquiz, $course, $stats->fingerprint);
if (empty($records)) {
    throw new \moodle_exception('noattempts', 'local_quizanalytics');
}

if ($kind === 'question') {
    $pdf = $client->download_pdf_question(
        $selectedquiz->name,
        $records,
        $selectedsections,
        $colorblind,
        $chartimages,
        $anonymize
    );
    $filename = clean_filename(
        $course->shortname . ' - ' . $selectedquiz->name . ' - Question Analytics - ' . date('Y-m-d') . '.pdf'
    );
} else {
    $meta = $client->solution_process_meta($selectedquiz->name, $records, $anonymize);
    if ($meta === null || empty($meta['questions'])) {
        throw new \moodle_exception('servererror', 'local_quizanalytics');
    }

    $questionnames = array_column($meta['questions'], 'name');
    $spvquestion = required_param('spvquestion', PARAM_RAW);
    if (!in_array($spvquestion, $questionnames, true)) {
        throw new \moodle_exception('servererror', 'local_quizanalytics'); // Stale/tampered selection — fail closed.
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

    $pdf = $client->download_pdf_solutionprocess(
        $selectedquiz->name,
        $records,
        $spvquestion,
        $spvpart,
        $selectedsections,
        $colorblind,
        $chartimages,
        $anonymize
    );
    $filename = clean_filename(
        $course->shortname . ' - ' . $selectedquiz->name . ' - Solution Process - ' . date('Y-m-d') . '.pdf'
    );
}

if ($pdf === null) {
    throw new \moodle_exception('pdferror', 'local_quizanalytics');
}

send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
