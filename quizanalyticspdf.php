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
 * Streams the Quiz Analytics section's course-wide cross-quiz comparison
 * PDF — the report index.php itself shows, re-derived server-side.
 *
 * A separate entry point from index.php, gated by the same
 * local/quizanalytics:view capability that governs everything else
 * shown on that page. Re-derives every quiz/course/record from trusted
 * server-side data (course id, selection, and section choices) — the one
 * piece of client-posted content this route does use, chart_images, is
 * never fed into any computation, only embedded as-is into the requesting
 * user's own PDF (each image was itself captured, client-side, from a
 * chart this same route's own re-derived data rendered a moment earlier —
 * see sections-renderer.js's setupPdfForm()).
 *
 * Split out of what used to be pdf.php's ?kind=quiz branch when Quiz
 * Analytics itself split away from the per-quiz drill-down (now Question
 * Analytics, see questionanalyticspdf.php) — one file, one report, no
 * ?kind= param needed any more.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php'); // Send_file() lives here, not autoloaded for a lean entry point like this.
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/data_fetcher.php');
require_once($CFG->dirroot . '/local/quizanalytics/classes/quiz/api_client.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quizanalytics:view', $context);

// PDF generation redoes the full analytics computation (re-derived
// server-side, never trusting the client beyond the chart images — see
// below) on top of drawing the document itself, so it can run longer than
// a normal page load — this is the one report whose cost scales with the
// whole course rather than a single quiz — see settings.php.
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

$stackquizzes = local_quizanalytics_quiz_data_fetcher::get_course_stack_quizzes($course->id);
$byquiz = local_quizanalytics_quiz_data_fetcher::get_course_response_records($course, $stackquizzes);
$byquiz = array_filter($byquiz, fn($records) => !empty($records));
if (empty($byquiz)) {
    throw new \moodle_exception('nocourseattempts', 'local_quizanalytics');
}

$pdf = $client->download_pdf_quiz($course->fullname, $byquiz, $selectedsections, $colorblind, $chartimages, $anonymize);
if ($pdf === null) {
    throw new \moodle_exception('pdferror', 'local_quizanalytics');
}

$filename = clean_filename($course->shortname . ' - Quiz Analytics - ' . date('Y-m-d') . '.pdf');

send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
