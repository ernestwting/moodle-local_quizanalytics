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
 * Assembles the content payload for one PDF report — re-derives the exact
 * same section data the matching on-screen view shows (via
 * question_analysis/solution_process_analysis/course_analysis), filtered
 * down to whichever sections the reader ticked in the PDF form, in the
 * shape pdf_builder.php actually draws:
 *
 *   {
 *     "title": str, "subtitle": str,
 *     "sections": [
 *       {"title": str, "caption": str|null, "table": {"columns", "rows"}|null,
 *        "charts": [{"id": str, "title": str|null}]},
 *       ...
 *     ]
 *   }
 *
 * Charts carry only their id/title here — pdf_builder.php looks up the
 * actual PNG image the caller supplied (captured client-side from the
 * already-rendered on-screen chart via Plotly.toImage(), never
 * re-rasterized server-side) by that id.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

/**
 * Re-derives each PDF kind's section content server-side from the teacher's checkbox selections.
 */
class pdf_content {
    /** Matches pdf_export.py's df.head(60) row cap per table. */
    const MAX_TABLE_ROWS = 60;

    /**
     * Question Analytics PDF section content for the checkbox labels the teacher selected.
     *
     * @param array[] $records
     * @param string[] $selectedlabels checkbox labels ticked in the PDF form
     */
    public static function build_question_content(
        array $records,
        string $quizname,
        array $selectedlabels,
        bool $colorblindmode
    ): array {
        $selectedids = pdf_sections::selected_ids('question', $selectedlabels);
        if (empty($selectedids)) {
            return ['title' => "{$quizname} — Question Analytics", 'subtitle' => '', 'sections' => []];
        }

        $result = question_analysis::build_analysis($records, $quizname, $colorblindmode);
        $sectionsbyid = [];
        foreach ($result['sections'] as $s) {
            $sectionsbyid[$s['id']] = $s;
        }

        $sections = [];
        foreach ($selectedids as $id) {
            if ($id === 'summary') {
                $responserows = parser::build_response_rows($records, $quizname);
                $qmetrics = question_metrics::compute_question_metrics($responserows);
                $rows = array_map(fn($r) => [
                    'question' => $r['question'], 'attempts' => $r['attempts'], 'students' => $r['students'],
                    'percent_valid' => $r['percent_valid'], 'percent_invalid' => $r['percent_invalid'],
                    'syntax_error_count' => $r['syntax_error_count'],
                ], $qmetrics);
                $sections[] = [
                    'title' => '1. Question Summary',
                    'caption' => 'Participation and summary statistics',
                    'table' => table_helpers::to_table($rows),
                    'charts' => [],
                ];
            } else if ($id === 'questiondetails') {
                $rows = [];
                foreach ($result['questions'] as $qname => $detail) {
                    foreach ($detail['error_drilldown']['rows'] as $row) {
                        $rows[] = array_merge([$qname], $row);
                    }
                }
                if (!empty($rows)) {
                    $firstquestion = $result['questions'][array_key_first($result['questions'])];
                    $table = [
                        'columns' => array_merge(['Question'], $firstquestion['error_drilldown']['columns']),
                        'rows' => $rows,
                    ];
                } else {
                    $table = ['columns' => [], 'rows' => []];
                }
                $sections[] = [
                    'title' => '3. Question Item Details & Error Drill-Down',
                    'caption' => 'Question text, right answer, and wrong-response drill-down (Best Attempt)',
                    'table' => $table,
                    'charts' => [],
                ];
            } else if (isset($sectionsbyid[$id])) {
                $sections[] = self::from_onscreen_section($sectionsbyid[$id]);
            }
        }

        return [
            'title' => "{$quizname} — Question Analytics",
            'subtitle' => self::format_summary_subtitle($result['summary']),
            'sections' => $sections,
        ];
    }

    /**
     * Solution Process Visualization PDF section content for the checkbox labels the teacher selected.
     *
     * @param array[] $records
     * @param string[] $selectedlabels
     */
    public static function build_solutionprocess_content(
        array $records,
        string $quizname,
        string $question,
        int $partindex,
        array $selectedlabels,
        bool $colorblindmode
    ): array {
        $selectedids = pdf_sections::selected_ids('solutionprocess', $selectedlabels);
        if (empty($selectedids)) {
            return ['title' => "{$quizname} — Solution Process Visualization", 'subtitle' => '', 'sections' => []];
        }

        $result = solution_process_analysis::build_analysis(
            $records,
            $quizname,
            $question,
            $partindex,
            null,
            $colorblindmode
        );
        $sectionsbyid = [];
        foreach ($result['sections'] as $s) {
            $sectionsbyid[$s['id']] = $s;
        }

        $sections = [];
        foreach ($selectedids as $id) {
            if (isset($sectionsbyid[$id])) {
                $sections[] = self::from_onscreen_section($sectionsbyid[$id]);
            }
        }

        return [
            'title' => "{$quizname} — Solution Process Visualization",
            'subtitle' => "{$question}, part {$result['part_index']}",
            'sections' => $sections,
        ];
    }

    /**
     * Quiz Analysis (course-wide) PDF section content for the checkbox labels the teacher selected.
     *
     * @param array<string, array[]> $quizzes quiz_name => records[]
     * @param string[] $selectedlabels
     */
    public static function build_quiz_content(
        string $coursename,
        array $quizzes,
        array $selectedlabels,
        bool $colorblindmode,
        string $gradetype
    ): array {
        $selectedids = pdf_sections::selected_ids('quiz', $selectedlabels);
        if (empty($selectedids)) {
            return ['title' => "{$coursename} — Quiz Analysis", 'subtitle' => '', 'sections' => []];
        }

        $result = course_analysis::build_analysis($coursename, $quizzes, $colorblindmode, null, null, $gradetype);
        $sectionsbyid = [];
        foreach ($result['sections'] as $s) {
            $sectionsbyid[$s['id']] = $s;
        }

        $sections = [];
        foreach ($selectedids as $id) {
            if (isset($sectionsbyid[$id])) {
                $sections[] = self::from_onscreen_section($sectionsbyid[$id]);
            }
        }

        return [
            'title' => "{$coursename} — Quiz Analysis",
            'subtitle' => 'Combined across every STACK quiz in the course',
            'sections' => $sections,
        ];
    }

    /**
     * One row-per-key display of the on-screen summary scalars.
     */
    private static function format_summary_subtitle(array $summary): string {
        $parts = [];
        foreach ($summary as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = ucwords(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }
        return implode('  •  ', $parts);
    }

    /**
     * Converts one {id, title, caption, table, charts} on-screen section
     * into pdf_builder's {title, caption, table, charts} shape, capping the
     * table to MAX_TABLE_ROWS rows (matching pdf_export.py's df.head(60)).
     */
    private static function from_onscreen_section(array $section): array {
        $table = $section['table'] ?? null;
        if ($table !== null && count($table['rows']) > self::MAX_TABLE_ROWS) {
            $table = [
                'columns' => $table['columns'],
                'rows' => array_slice($table['rows'], 0, self::MAX_TABLE_ROWS),
                'truncated_from' => count($table['rows']),
            ];
        }
        return [
            'title' => $section['title'] ?? '',
            'caption' => $section['caption'] ?? null,
            'table' => $table,
            'charts' => array_map(fn($c) => ['id' => $c['id'], 'title' => $c['title'] ?? null], $section['charts'] ?? []),
        ];
    }
}
