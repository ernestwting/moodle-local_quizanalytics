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

namespace local_quizanalytics\quiz\analytics;

/**
 * Checkbox id <-> lang-string-key maps for the "which sections to include" PDF export forms.
 *
 * The array keys are the stable section ids posted back as each checkbox's
 * `value` (see sections_output_helper::render_pdf_form()) — never shown to
 * the user and never translated. The array values are lang string keys in
 * lang/en/local_quizanalytics.php, resolved through get_string() to build
 * the localized checkbox label the user actually sees.
 */
class pdf_sections {
    /** @var array<string, string> section id => lang string key, for the Question Analytics PDF. */
    const QUESTION = [
        'summary' => 'pdfsectionsummary',
        'difficulty' => 'pdfsectiondifficulty',
        'questiondetails' => 'pdfsectionquestiondetails',
        'response-distribution' => 'pdfsectionresponsedistribution',
        'student-matrix' => 'pdfsectionstudentmatrix',
        'metrics' => 'pdfsectionmetrics',
    ];

    /** @var array<string, string> section id => lang string key, for the Solution Process Visualization PDF. */
    const SOLUTIONPROCESS = [
        'transition-graph' => 'pdfsectiontransitiongraph',
        'network-features' => 'pdfsectionnetworkfeatures',
        'prt-distance-3d' => 'pdfsectionprtdistance3d',
        'ted-distance-3d' => 'pdfsectiontreeeditdistance3d',
        'cross-attempt' => 'pdfsectioncrossattempt',
    ];

    /** @var array<string, string> section id => lang string key, for the Quiz Analysis (course-wide) PDF. */
    const QUIZ = [
        'attempt-list' => 'pdfsectionattemptlist',
        'quiz-stats' => 'pdfsectionquizstats',
        'boxplot' => 'pdfsectionboxplot',
        'engagement' => 'pdfsectionengagement',
        'scatter' => 'pdfsectionscatter',
        'trend' => 'pdfsectiontrend',
    ];

    /**
     * The id => lang string key map for one PDF kind.
     *
     * @return array<string, string>
     */
    private static function map_for(string $kind): array {
        return match ($kind) {
            'question' => self::QUESTION,
            'solutionprocess' => self::SOLUTIONPROCESS,
            'quiz' => self::QUIZ,
            default => [],
        };
    }

    /**
     * The section ids for one PDF kind, in canonical order.
     *
     * @return string[]
     */
    public static function ids(string $kind): array {
        return array_keys(self::map_for($kind));
    }

    /**
     * The checkbox id => localized label list for one PDF kind.
     *
     * @return array<string, string>
     */
    public static function labels(string $kind): array {
        $labels = [];
        foreach (self::map_for($kind) as $id => $stringkey) {
            $labels[$id] = get_string($stringkey, 'local_quizanalytics');
        }
        return $labels;
    }

    /**
     * Filters the section ids the user posted back down to the ones that
     * are actually valid for this PDF kind, in canonical order (not
     * necessarily the order they were posted in).
     *
     * @param string[] $postedids section ids ticked in the PDF form
     * @return string[]
     */
    public static function selected_ids(string $kind, array $postedids): array {
        $valid = self::ids($kind);
        return array_values(array_intersect($valid, $postedids));
    }
}
