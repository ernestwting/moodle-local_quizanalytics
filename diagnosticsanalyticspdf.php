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
 * Streams a PDF export of the Diagnostics Analytics dashboard, re-derived
 * server-side from the same report-builder class diagnosticsanalytics.php
 * uses, respecting the same quiz filter. A separate entry point from
 * diagnosticsanalytics.php, gated by the same local/quizanalytics:view
 * capability.
 *
 * Split out of modelspdf.php (now modelanalyticspdf.php) when Diagnostics
 * split into its own section — the "Download PDF" form here only ever
 * shows one checkbox (diagnostics), but still goes through
 * dashboard_renderer::render_pdf_form()'s same $sectionheadings mechanism
 * as Model Analytics, rather than a bespoke non-form download link.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_quizanalytics\stack\analytics\pdf_content;
use local_quizanalytics\stack\analytics\pdf_builder;

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/quizanalytics:view', $context);

// Each diagnostic indicator is its own set of DB queries per sample, with
// no batching (see diagnostics_report.php's own docblock) — can run long on
// a large course.
core_php_time_limit::raise(120);

$quizid = optional_param('quizid', 0, PARAM_INT);
$sections = optional_param_array('sections', ['diagnostics'], PARAM_ALPHANUM);

$payload = [];
if (in_array('diagnostics', $sections, true)) {
    $payload[] = pdf_content::build_diagnostics_section($courseid, $quizid !== 0 ? $quizid : null);
}

if (empty($payload)) {
    throw new \moodle_exception('pdfnosections', 'local_quizanalytics');
}

$pdfbytes = pdf_builder::build($course->fullname, $payload);

// Short and to the point: course, report type, download date — which quiz
// the report was scoped to is already in the PDF's own content, not worth
// spelling out in the filename too.
$filename = clean_filename($course->shortname . ' - Diagnostics Analytics - ' . date('Y-m-d') . '.pdf');

send_file($pdfbytes, $filename, 0, 0, true, true, 'application/pdf');
