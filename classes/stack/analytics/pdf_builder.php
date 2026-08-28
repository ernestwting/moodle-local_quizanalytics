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
 * Renders pdf_content.php's {title, columns, rows} section payloads to PDF
 * bytes via stack_pdf (core's bundled TCPDF, see that class's
 * docblock). Landscape A4: Model 1/2 tables run to seven columns
 * (name + status + 4-5 indicators), which portrait width can't hold legibly.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders one or more section payloads to a single PDF document.
 */
class pdf_builder {
    /** @var float The name/question column (always first) gets this share of the table width; the rest split evenly. */
    const NAME_COLUMN_SHARE = 0.18;

    /**
     * Renders every given section, in order, to one PDF document.
     *
     * @param string $coursename
     * @param array[] $sections each {title, columns, rows, shown, total, truncated} from pdf_content.php
     * @return string raw PDF bytes
     */
    public static function build(string $coursename, array $sections): string {
        $pdf = new stack_pdf('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('local_quizanalytics');
        $pdf->SetAuthor('STACK q-type Analytics Dashboard');
        $pdf->SetTitle($coursename . ' - ' . get_string('dashboardtitle', 'local_quizanalytics'));
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(12, 20, 12);
        $pdf->SetHeaderMargin(8);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();

        $pdf->SetFont('freesans', 'B', 16);
        $pdf->SetTextColor(0x1e, 0x3c, 0x72);
        $pdf->MultiCell(0, 8, $coursename . ' — ' . get_string('dashboardtitle', 'local_quizanalytics'), 0, 'L');
        $pdf->Ln(4);

        foreach ($sections as $i => $section) {
            self::render_section($pdf, $section, $i === 0);
        }

        return $pdf->Output($coursename . '-stack-analytics.pdf', 'S');
    }

    /**
     * Renders one section: a divider (skipped for the first), title, table, and truncation notice.
     *
     * @param stack_pdf $pdf
     * @param array $section {title, columns, rows, shown, total, truncated}
     * @param bool $isfirst
     */
    private static function render_section(stack_pdf $pdf, array $section, bool $isfirst): void {
        if (!$isfirst) {
            $pdf->SetDrawColor(0xe2, 0xe8, 0xf0);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(
                $pdf->getMargins()['left'],
                $pdf->GetY(),
                $pdf->getPageWidth() - $pdf->getMargins()['right'],
                $pdf->GetY()
            );
            $pdf->Ln(5);
        }

        $pdf->SetFont('freesans', 'B', 13);
        $pdf->SetTextColor(0x1e, 0x29, 0x3b);
        $pdf->MultiCell(0, 6, $section['title'], 0, 'L');
        $pdf->Ln(2);

        if (empty($section['rows'])) {
            $pdf->SetFont('freesans', 'I', 9);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 5, get_string('pdfnorows', 'local_quizanalytics'), 0, 'L');
            $pdf->Ln(5);
            return;
        }

        self::render_table($pdf, $section['columns'], $section['rows']);

        if ($section['truncated']) {
            $pdf->SetFont('freesans', 'I', 8);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 5, get_string('truncatednotice', 'local_quizanalytics', (object) [
                'shown' => $section['shown'],
                'total' => $section['total'],
            ]), 0, 'L');
        }
        $pdf->Ln(6);
    }

    /**
     * Background/text colors for a cell's 'good'/'watch' band, matching the
     * on-screen dashboard's badge-success/badge-warning colors so a reader
     * sees the same green/yellow signal in the PDF as on the page. 'neutral'
     * and cells with no band (plain name/question columns, "not enough
     * data" text) get no special color, just the normal row background.
     */
    const BAND_COLORS = [
        'good' => ['bg' => '#d4edda', 'text' => '#155724'],
        'watch' => ['bg' => '#fff3cd', 'text' => '#856404'],
    ];

    /**
     * Renders one section's table as an HTML table via TCPDF's writeHTML().
     *
     * @param stack_pdf $pdf
     * @param string[] $columns
     * @param array[] $rows each cell either a plain string, or {text, band} from pdf_content.php
     */
    private static function render_table(stack_pdf $pdf, array $columns, array $rows): void {
        $pdf->SetFont('freesans', '', 7);
        $usablewidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $widths = self::compute_column_widths(count($columns), $usablewidth);

        $html = '<table cellspacing="0" cellpadding="2" border="0.1">';
        $html .= '<thead><tr style="background-color:#1e3c72;color:#ffffff;font-weight:bold;">';
        foreach ($columns as $i => $column) {
            $html .= '<td width="' . $widths[$i] . 'mm"><span style="font-size:7pt;">'
                . htmlspecialchars($column, ENT_QUOTES) . '</span></td>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $rowindex => $row) {
            $rowbg = ($rowindex % 2 === 1) ? '#f8fafc' : '#ffffff';
            $html .= '<tr style="background-color:' . $rowbg . ';">';
            foreach ($row as $i => $value) {
                if (is_array($value)) {
                    $text = $value['text'];
                    $colors = self::BAND_COLORS[$value['band']] ?? null;
                } else {
                    $text = (string) $value;
                    $colors = null;
                }
                $cellstyle = $colors ? 'background-color:' . $colors['bg'] . ';color:' . $colors['text'] . ';' : '';
                $html .= '<td width="' . ($widths[$i] ?? 20) . 'mm" style="' . $cellstyle . '">'
                    . '<span style="font-size:7pt;">' . nl2br(htmlspecialchars($text, ENT_QUOTES)) . '</span></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, false, false, '');
    }

    /**
     * Column widths in mm: the first column (student/question name) gets a
     * fixed share; every other column splits the rest evenly, whether
     * there's one of them (the Diagnostics table) or five (Model 1's).
     *
     * @param int $columncount
     * @param float $usablewidth
     * @return float[]
     */
    private static function compute_column_widths(int $columncount, float $usablewidth): array {
        if ($columncount <= 1) {
            return array_fill(0, $columncount, $usablewidth / max($columncount, 1));
        }

        $namewidth = $usablewidth * self::NAME_COLUMN_SHARE;
        $remainingcolumns = $columncount - 1;
        $remainingwidth = ($usablewidth - $namewidth) / $remainingcolumns;

        return array_merge([$namewidth], array_fill(0, $remainingcolumns, $remainingwidth));
    }
}
