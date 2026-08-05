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
 * Runs all the STACK/Maxima response analytics (see classes/analytics/) and
 * returns them in the {summary, sections} shape classes/output/
 * sections_output_helper.php and js/vendor-shared/sections-renderer.js expect.
 *
 * Kept as its own class — rather than having index.php/pdf.php call
 * classes/analytics/*.php directly — so those two entry points don't need
 * to know which analytics class computes which view; this is the one
 * place that mapping lives.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Routes each on-screen/PDF view to the right classes/analytics/ entry point.
 */
class local_quizanalytics_api_client {
    /**
     * Question Analytics for a single quiz, for the per-quiz drill-down view.
     *
     * Computed directly in PHP (see classes/analytics/) rather than calling
     * out to the analytics-service — the whole point of this plugin being
     * the only thing an institution has to install.
     *
     * @param string $quizname
     * @param array  $records
     * @param bool   $colorblindmode
     * @return array|null
     */
    public function analyze(string $quizname, array $records, bool $colorblindmode = false): ?array {
        try {
            return \local_quizanalytics\analytics\question_analysis::build_analysis(
                $records,
                $quizname,
                $colorblindmode
            );
        } catch (\Throwable $e) {
            debugging(
                'local_quizanalytics: error building question analytics: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Cross-quiz analysis, for the course-wide view.
     *
     * @param string      $coursename
     * @param array       $quizzes   [quiz_name => records[]]
     * @param bool        $colorblindmode
     * @param string|null $gradetype One of "Highest Grade"/"Average Grade"/
     *                    "Minimum Grade" — null lets the service apply its
     *                    own default (Average Grade).
     * @return array|null
     */
    public function analyze_course(
        string $coursename,
        array $quizzes,
        bool $colorblindmode = false,
        ?string $gradetype = null
    ): ?array {
        try {
            return \local_quizanalytics\analytics\course_analysis::build_analysis(
                $coursename,
                $quizzes,
                $colorblindmode,
                null,
                null,
                $gradetype ?? \local_quizanalytics\analytics\course_analysis::DEFAULT_GRADE_TYPE
            );
        } catch (\Throwable $e) {
            debugging(
                'local_quizanalytics: error building course analysis: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Cheap Solution Process Visualization metadata (question/part/student
     * lists) for populating the selector form.
     *
     * @param string $quizname
     * @param array  $records
     * @return array|null
     */
    public function solution_process_meta(string $quizname, array $records): ?array {
        try {
            return \local_quizanalytics\analytics\solution_process_analysis::build_meta($records, $quizname);
        } catch (\Throwable $e) {
            debugging(
                'local_quizanalytics: error building solution process meta: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * The full Solution Process Visualization for one (question, part),
     * optionally scoped further to one student's own drill-down.
     *
     * @param string      $quizname
     * @param array       $records
     * @param string      $question
     * @param int         $partindex
     * @param string|null $studentid
     * @param bool        $colorblindmode
     * @return array|null
     */
    public function solution_process_analyze(
        string $quizname,
        array $records,
        string $question,
        int $partindex = 1,
        ?string $studentid = null,
        bool $colorblindmode = false
    ): ?array {
        try {
            return \local_quizanalytics\analytics\solution_process_analysis::build_analysis(
                $records,
                $quizname,
                $question,
                $partindex,
                $studentid,
                $colorblindmode
            );
        } catch (\Throwable $e) {
            debugging(
                'local_quizanalytics: error building solution process analysis: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * GET /report-sections/{kind} — the section names available for one PDF
     * kind, driving the "Generate PDF Report" checkbox list so it can never
     * drift from what the PDF route actually includes.
     *
     * @param string $kind 'question', 'solutionprocess', or 'quiz'
     * @return string[]
     */
    public function report_sections(string $kind): array {
        return \local_quizanalytics\analytics\pdf_sections::labels($kind);
    }

    /**
     * Question Analytics PDF for one quiz.
     *
     * @param string $quizname
     * @param array  $records
     * @param array|null $selectedsections Checkbox labels ticked in the PDF
     *        form — null (no selection posted) means every section.
     * @param bool   $colorblindmode
     * @param array  $chartimages Chart id => data: URL (PNG), captured
     *        client-side from the already-rendered on-screen charts.
     * @return string|null Raw PDF bytes, or null on any failure.
     */
    public function download_pdf_question(
        string $quizname,
        array $records,
        ?array $selectedsections,
        bool $colorblindmode = false,
        array $chartimages = []
    ): ?string {
        try {
            $labels = $selectedsections ?? \local_quizanalytics\analytics\pdf_sections::labels('question');
            $content = \local_quizanalytics\analytics\pdf_content::build_question_content(
                $records,
                $quizname,
                $labels,
                $colorblindmode
            );
            return \local_quizanalytics\analytics\pdf_builder::build($content, $chartimages);
        } catch (\Throwable $e) {
            debugging('local_quizanalytics: error building question PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Solution Process Visualization PDF for one (quiz, question, part).
     *
     * @param string $quizname
     * @param array  $records
     * @param string $question
     * @param int    $partindex
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @param array  $chartimages
     * @return string|null
     */
    public function download_pdf_solutionprocess(
        string $quizname,
        array $records,
        string $question,
        int $partindex,
        ?array $selectedsections,
        bool $colorblindmode = false,
        array $chartimages = []
    ): ?string {
        try {
            $labels = $selectedsections ?? \local_quizanalytics\analytics\pdf_sections::labels('solutionprocess');
            $content = \local_quizanalytics\analytics\pdf_content::build_solutionprocess_content(
                $records,
                $quizname,
                $question,
                $partindex,
                $labels,
                $colorblindmode
            );
            return \local_quizanalytics\analytics\pdf_builder::build($content, $chartimages);
        } catch (\Throwable $e) {
            debugging(
                'local_quizanalytics: error building solution process PDF: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Cross-quiz Quiz Analysis PDF for the course-wide view.
     *
     * @param string $coursename
     * @param array  $quizzes [quiz_name => records[]]
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @param array  $chartimages
     * @return string|null
     */
    public function download_pdf_quiz(
        string $coursename,
        array $quizzes,
        ?array $selectedsections,
        bool $colorblindmode = false,
        array $chartimages = []
    ): ?string {
        try {
            $labels = $selectedsections ?? \local_quizanalytics\analytics\pdf_sections::labels('quiz');
            $content = \local_quizanalytics\analytics\pdf_content::build_quiz_content(
                $coursename,
                $quizzes,
                $labels,
                $colorblindmode,
                'Average Grade'
            );
            return \local_quizanalytics\analytics\pdf_builder::build($content, $chartimages);
        } catch (\Throwable $e) {
            debugging('local_quizanalytics: error building quiz PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
