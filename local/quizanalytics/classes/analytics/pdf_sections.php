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
 * The canonical PDF section checkbox list for each of the three report
 * kinds, mapped to the section id each one corresponds to in the matching
 * on-screen orchestrator's output (question_analysis::build_analysis(),
 * solution_process_analysis::build_analysis(), course_analysis::build_analysis()).
 *
 * PHP port of analytics-service/analytics/report_sections.py's
 * QUESTION_MODULES/SPV_MODULES/QUIZ_MODULES lists — used both for the PDF
 * form's checkboxes (via api_client::report_sections()) and to select which
 * of the on-screen sections/synthetic sections go into the PDF, so the two
 * can never drift apart.
 *
 * 'summary' and 'questiondetails' are synthetic ids: they don't come from
 * $result['sections'] (which is only sections 2/4/5/6 for Question
 * Analytics) but are built directly from $result['summary']-equivalent
 * data and $result['questions'] respectively — see pdf_content.php.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

class pdf_sections {
    const QUESTION = [
        '1. Question Summary' => 'summary',
        '2. Question Difficulty Analysis' => 'difficulty',
        '3. Question Item Details & Error Drill-Down' => 'questiondetails',
        '4. Question Response Distribution' => 'response-distribution',
        '5. Student Performance Matrix' => 'student-matrix',
        '6. Question Metrics' => 'metrics',
    ];

    const SOLUTIONPROCESS = [
        'Class-Wide Transition Graph' => 'transition-graph',
        'Network Features per Node' => 'network-features',
        'PRT-Distance 3D Chart' => 'prt-distance-3d',
        'Tree Edit Distance 3D Chart' => 'ted-distance-3d',
        'Cross-Attempt Comparison' => 'cross-attempt',
    ];

    const QUIZ = [
        '1. Merged List of Users and Files' => 'attempt-list',
        '2. Summary of Quiz Stats' => 'quiz-stats',
        '3. Quiz Grade Distribution (Box Plot)' => 'boxplot',
        '4. Engagement Over Time' => 'engagement',
        '5. Scatter Plot: Attempts vs Grades' => 'scatter',
        '6. Line Graph of Various Metrics' => 'trend',
    ];

    /** @return string[] the checkbox label list for one PDF kind. */
    public static function labels(string $kind): array {
        switch ($kind) {
            case 'question':
                return array_keys(self::QUESTION);
            case 'solutionprocess':
                return array_keys(self::SOLUTIONPROCESS);
            case 'quiz':
                return array_keys(self::QUIZ);
            default:
                return [];
        }
    }

    /**
     * @param string[] $selectedlabels checkbox labels the user ticked
     * @return string[] the corresponding section ids, in canonical order
     *         (not necessarily the order $selectedlabels was posted in)
     */
    public static function selected_ids(string $kind, array $selectedlabels): array {
        $map = match ($kind) {
            'question' => self::QUESTION,
            'solutionprocess' => self::SOLUTIONPROCESS,
            'quiz' => self::QUIZ,
            default => [],
        };
        $ids = [];
        foreach ($map as $label => $id) {
            if (in_array($label, $selectedlabels, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
