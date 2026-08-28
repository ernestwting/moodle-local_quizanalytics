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
 * Builds plain-text {columns, rows} table payloads for the PDF export, one
 * per dashboard section — re-derived directly from the same report-builder
 * classes the on-screen dashboard uses (model1_report/model2_report/
 * diagnostics_report), not scraped from rendered HTML. Cell text reuses the
 * exact same get_string() lookups dashboard_renderer.php's badges/sentences
 * do, just composed as plain "Label — sentence" text instead of HTML,
 * since a PDF table cell has no badge styling to render into.
 *
 * The Diagnostics section is deliberately summary-only here (question, quiz,
 * one line each for seed-bias/branch-coverage) rather than the full
 * per-branch breakdown table dashboard_renderer.php's collapsed detail view
 * shows — the same "condensed for readability" reasoning that drove that
 * on-screen redesign applies at least as much to a printed report.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics;

use local_quizanalytics\stack\analytics\report\model1_report;
use local_quizanalytics\stack\analytics\report\model2_report;
use local_quizanalytics\stack\analytics\report\diagnostics_report;
use local_quizanalytics\stack\output\dashboard_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * Re-derives each dashboard section's content, in the plain-text table shape pdf_builder.php draws.
 */
class pdf_content {
    /** @var array<string, string> indicator key => lang string suffix, matching dashboard_renderer::MODEL1_INDICATORS. */
    const MODEL1_INDICATORS = [
        'gradetrajectory' => 'gradetrajectory',
        'responselatencyanomaly' => 'responselatencyanomaly',
        'disengagemententropy' => 'disengagemententropy',
        'helpseekinggap' => 'helpseekinggap',
        'feedbackrevisiondistance' => 'feedbackrevisiondistance',
    ];

    /** @var array<string, string> indicator key => lang string suffix, matching dashboard_renderer::MODEL2_INDICATORS. */
    const MODEL2_INDICATORS = [
        'questiondifficultyirt' => 'questiondifficultyirt',
        'syntaxerrorrate' => 'syntaxerrorrate',
        'unreachednoderatio' => 'unreachednoderatio',
        'feedbackineffectiveness' => 'feedbackineffectiveness',
    ];

    /**
     * The Model 1 section payload for the PDF export.
     *
     * @param int $courseid
     * @param bool $anonymize replace each student's name with the same "Student N" pseudonym the on-screen table uses
     * @return array{title: string, columns: string[], rows: array[], shown: int, total: int, truncated: bool}
     */
    public static function build_model1_section(int $courseid, bool $anonymize = false): array {
        $report = model1_report::build($courseid);

        $columns = array_merge(
            [
                get_string('columnstudent', 'local_quizanalytics'),
                get_string('columncurrentstatus', 'local_quizanalytics'),
            ],
            array_map(
                fn($stringsuffix) => get_string('indicator:' . $stringsuffix, 'local_quizanalytics'),
                array_values(self::MODEL1_INDICATORS)
            )
        );

        $rows = [];
        foreach ($report->rows as $i => $row) {
            $name = $anonymize ? dashboard_renderer::pseudonym($i) : $row->fullname;
            $cells = [$name, self::gradestatus_cell($row->gradestatus)];
            foreach (self::MODEL1_INDICATORS as $indicatorkey => $stringsuffix) {
                $cells[] = self::indicator_cell($row->indicators[$indicatorkey], 'model1sentence_' . $stringsuffix);
            }
            $rows[] = $cells;
        }

        return [
            'title' => get_string('model1heading', 'local_quizanalytics'),
            'columns' => $columns,
            'rows' => $rows,
            'shown' => count($rows),
            'total' => $report->total,
            'truncated' => $report->truncated,
        ];
    }

    /**
     * The Model 2 section payload for the PDF export.
     *
     * @param int $courseid
     * @param int|null $quizid
     * @return array{title: string, columns: string[], rows: array[], shown: int, total: int, truncated: bool}
     */
    public static function build_model2_section(int $courseid, ?int $quizid): array {
        $report = model2_report::build($courseid, $quizid);

        $columns = array_merge(
            [
                get_string('columnquestion', 'local_quizanalytics'),
                get_string('columncurrentstatus', 'local_quizanalytics'),
            ],
            array_map(
                fn($stringsuffix) => get_string('indicator:' . $stringsuffix, 'local_quizanalytics'),
                array_values(self::MODEL2_INDICATORS)
            )
        );

        $rows = [];
        foreach ($report->rows as $row) {
            $cells = [
                $row->questionname . ' (' . $row->quizname . ')',
                self::needsreview_cell($row->needsreview, $row->attemptcount),
            ];
            foreach (self::MODEL2_INDICATORS as $indicatorkey => $stringsuffix) {
                $cells[] = self::indicator_cell(
                    $row->indicators[$indicatorkey],
                    'model2sentence_' . $stringsuffix,
                    $row->attemptcount
                );
            }
            $rows[] = $cells;
        }

        return [
            'title' => get_string('model2heading', 'local_quizanalytics'),
            'columns' => $columns,
            'rows' => $rows,
            'shown' => count($rows),
            'total' => $report->total,
            'truncated' => $report->truncated,
        ];
    }

    /**
     * The Diagnostics section payload for the PDF export.
     *
     * @param int $courseid
     * @param int|null $quizid
     * @return array{title: string, columns: string[], rows: array[], shown: int, total: int, truncated: bool}
     */
    public static function build_diagnostics_section(int $courseid, ?int $quizid): array {
        $report = diagnostics_report::build($courseid, $quizid);

        $columns = [
            get_string('columnquestion', 'local_quizanalytics'),
            get_string('seedbiasheading', 'local_quizanalytics'),
            get_string('bloatedtreeheading', 'local_quizanalytics'),
        ];

        $rows = [];
        foreach ($report->rows as $row) {
            $rows[] = [
                $row->questionname . ' (' . $row->quizname . ')',
                self::seedbias_cell($row->seedbias),
                self::bloatedtree_cell($row->bloatedtree),
            ];
        }

        return [
            'title' => get_string('diagnosticsheading', 'local_quizanalytics'),
            'columns' => $columns,
            'rows' => $rows,
            'shown' => count($rows),
            'total' => $report->total,
            'truncated' => $report->truncated,
        ];
    }

    /**
     * "Label — sentence" plus its color band for one indicator cell.
     *
     * @param \stdClass|null $result an indicator's compute_for_sample() return value
     * @param string $sentencestringkey the lang string key for the facts sentence, taking $result->summary as $a
     * @param int|null $attemptcount see dashboard_renderer::not_enough_data_text()'s docblock
     * @return array{text: string, band: ?string}
     */
    private static function indicator_cell(?\stdClass $result, string $sentencestringkey, ?int $attemptcount = null): array {
        if ($result === null) {
            return ['text' => dashboard_renderer::not_enough_data_text($attemptcount), 'band' => null];
        }
        $label = get_string('band_' . $result->band, 'local_quizanalytics');
        $sentence = get_string($sentencestringkey, 'local_quizanalytics', (object) $result->summary);
        return ['text' => $label . ' — ' . $sentence, 'band' => $result->band];
    }

    /**
     * The "Current status" cell for one Model 1 row, plus its color band.
     *
     * @param \stdClass $gradestatus {gradepasspercent, gradepercent, atrisk} — see model1_report::get_grade_status()
     * @return array{text: string, band: ?string}
     */
    private static function gradestatus_cell(\stdClass $gradestatus): array {
        if ($gradestatus->gradepasspercent === null) {
            return ['text' => get_string('gradestatusnothreshold', 'local_quizanalytics'), 'band' => null];
        }
        if ($gradestatus->gradepercent === null) {
            return ['text' => get_string('gradestatusnogradeyet', 'local_quizanalytics'), 'band' => null];
        }
        $stringkey = $gradestatus->atrisk ? 'gradestatusatrisk' : 'gradestatuspassing';
        $text = get_string($stringkey, 'local_quizanalytics', (object) [
            'grade' => $gradestatus->gradepercent,
            'gradepass' => $gradestatus->gradepasspercent,
        ]);
        return ['text' => $text, 'band' => $gradestatus->atrisk ? 'watch' : 'good'];
    }

    /**
     * The "Current status" cell for one Model 2 row, plus its color band.
     *
     * @param \stdClass|null $needsreview question_needs_review::compute_for_sample()'s return value
     * @param int|null $attemptcount see dashboard_renderer::not_enough_data_text()'s docblock
     * @return array{text: string, band: ?string}
     */
    private static function needsreview_cell(?\stdClass $needsreview, ?int $attemptcount = null): array {
        if ($needsreview === null) {
            return ['text' => dashboard_renderer::not_enough_data_text($attemptcount), 'band' => null];
        }
        $stringkey = $needsreview->needsreview ? 'needsreviewyes' : 'needsreviewno';
        $text = get_string($stringkey, 'local_quizanalytics', (object) [
            'passpercent' => $needsreview->passpercent,
            'thresholdpercent' => $needsreview->thresholdpercent,
        ]);
        return ['text' => $text, 'band' => $needsreview->needsreview ? 'watch' : 'good'];
    }

    /**
     * "Label — sentence" plus its color band for one Diagnostics row's seed-bias cell.
     *
     * @param \stdClass|null $seedbias diagnostics_report's seed-bias summary, or null
     * @return array{text: string, band: ?string}
     */
    private static function seedbias_cell(?\stdClass $seedbias): array {
        if ($seedbias === null) {
            return ['text' => get_string('notenoughdata', 'local_quizanalytics'), 'band' => null];
        }
        $label = get_string('band_' . $seedbias->band, 'local_quizanalytics');
        $sentence = get_string('diagnosticsseedbiassentence', 'local_quizanalytics', (object) [
            'etasquared' => format_float($seedbias->anova->etasquared, 3),
            'magnitude' => get_string('etamagnitude_' . $seedbias->magnitude, 'local_quizanalytics'),
        ]);
        return ['text' => $label . ' — ' . $sentence, 'band' => $seedbias->band];
    }

    /**
     * "Label — sentence" plus its color band for one Diagnostics row's branch-coverage cell.
     *
     * @param \stdClass|null $bloatedtree diagnostics_report's branch-coverage summary, or null
     * @return array{text: string, band: ?string}
     */
    private static function bloatedtree_cell(?\stdClass $bloatedtree): array {
        if ($bloatedtree === null) {
            return ['text' => get_string('notenoughdata', 'local_quizanalytics'), 'band' => null];
        }
        $label = get_string('band_' . $bloatedtree->band, 'local_quizanalytics');
        $sentence = get_string('diagnosticsbloatedtreesentence', 'local_quizanalytics', (object) [
            'unreached' => $bloatedtree->unreachedcount,
            'total' => $bloatedtree->totalbranches,
        ]);
        return ['text' => $label . ' — ' . $sentence, 'band' => $bloatedtree->band];
    }
}
