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
 * Renders a pdf_content.php payload to PDF bytes via TCPDF (vendored at
 * classes/quiz/vendor/tcpdf/) — the PHP port's equivalent of
 * analytics-service/analytics/pdf_export.py's generate_pdf_report().
 *
 * Deliberate v1 simplifications from the Python original, matching the
 * approved port plan:
 * - Charts are embedded from client-captured PNGs (Plotly.toImage(), taken
 *   from the chart already rendered on screen) rather than rasterized
 *   server-side — PHP has no headless-browser/kaleido equivalent, and this
 *   plugin's whole point is needing no such external dependency.
 * - Math ($...$ delimited expressions in table cells) is rendered as
 *   plain, readable text/Unicode (see latex_utils::latex_to_plain_text())
 *   rather than a typeset image — no server-side math-rendering
 *   dependency needed, at the cost of not showing true typeset symbols in
 *   the PDF specifically (the on-screen page still renders the real
 *   $...$ string via KaTeX).
 * - No auto-generated table of contents / PDF outline bookmarks — a
 *   navigation nicety, not core report content.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Renders a pdf_content.php payload to PDF bytes via TCPDF (see quizanalytics_tcpdf.php
 * for the TCPDF subclass this uses).
 */
class pdf_builder {
    /** @var float Chart images are scaled to fit within this max height, matching pdf_export.py's layout. */
    const MAX_CHART_HEIGHT_MM = 90.0;

    /**
     * A captured chart PNG wider than this (in its own source pixels) gets
     * a landscape page instead of portrait. "Line Graph of Various Metrics"
     * (see quiz_metrics::build_line_graph_figure()) grows ~220px per quiz
     * with no upper bound, so a large course's chart, force-shrunk to fit
     * one portrait page's ~178mm usable width the way every other chart is,
     * would print with its per-quiz tick labels shrunk well past legible.
     * 1400px is comfortably above what a normal-size course's charts
     * capture at (typically 800-900px, the on-screen container width).
     */
    const WIDE_CHART_PIXEL_WIDTH = 1400;

    /** @var array<string, float> Column-width weighting, matching pdf_export.py's _compute_column_widths. */
    const NARROW_COLUMN_WEIGHTS = [
        'question' => 0.55, 'score' => 0.45, 'status' => 0.6,
        'student name' => 0.85, 'frequency' => 0.5,
    ];

    /** @var string[] Column-name keywords that get wide-column treatment instead of NARROW_COLUMN_WEIGHTS. */
    const WIDE_COLUMN_KEYWORDS = ['response', 'answer', 'text', 'email'];

    /**
     * Renders one PDF kind's content payload to PDF bytes.
     *
     * @param array{title: string, subtitle: string, sections: array[]} $content
     * @param array<string, string> $chartimages chart id => data: URL (PNG)
     */
    public static function build(array $content, array $chartimages): string {
        $pdf = new quizanalytics_tcpdf('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('local_quizanalytics');
        $pdf->SetAuthor('Moodle STACK q-type Analytics Hub');
        $pdf->SetTitle($content['title']);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(19, 22, 19);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(15);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();

        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->SetTextColor(0x1e, 0x3c, 0x72);
        $pdf->MultiCell(0, 8, $content['title'], 0, 'L');

        if (!empty($content['subtitle'])) {
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(0x47, 0x55, 0x69);
            $pdf->MultiCell(0, 5, $content['subtitle'], 0, 'L');
        }
        $pdf->Ln(4);

        if (empty($content['sections'])) {
            $pdf->SetFont('dejavusans', 'I', 10);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 6, get_string('pdfnosections', 'local_quizanalytics'), 0, 'L');
        }

        foreach ($content['sections'] as $i => $section) {
            self::render_section($pdf, $section, $chartimages, $i === 0);
        }

        return $pdf->Output($content['title'] . '.pdf', 'S');
    }

    /**
     * Renders one section: divider, title/caption, table, and charts.
     */
    private static function render_section(
        quizanalytics_tcpdf $pdf,
        array $section,
        array $chartimages,
        bool $isfirst
    ): void {
        // Don't strand a section's divider/heading alone at the very bottom
        // of a page with no room for anything under it.
        if ($pdf->GetY() > $pdf->getPageHeight() - $pdf->getMargins()['bottom'] - 20) {
            $pdf->AddPage();
        }

        // A light rule between sections (skipped before the very first one,
        // where the title/subtitle block already separates it from the
        // page header) — on-screen, each section is visually its own
        // <div>/heading; the PDF had nothing marking that boundary beyond
        // whatever whitespace Ln() happened to leave.
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

        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->SetTextColor(0x1e, 0x29, 0x3b);
        $pdf->MultiCell(0, 6, $section['title'], 0, 'L');

        if (!empty($section['caption'])) {
            $pdf->SetFont('dejavusans', 'I', 8.5);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 4, $section['caption'], 0, 'L');
        }
        $pdf->Ln(2);

        if (!empty($section['table']) && !empty($section['table']['rows'])) {
            self::render_table($pdf, $section['table']);
            $pdf->Ln(3);
        }

        foreach ($section['charts'] as $chart) {
            self::render_chart($pdf, $chart, $chartimages);
        }

        $pdf->Ln(7);
    }

    /**
     * Renders one section's table as an HTML table via TCPDF's writeHTML().
     *
     * @param array{columns: string[], rows: array[], truncated_from?: int} $table
     */
    private static function render_table(quizanalytics_tcpdf $pdf, array $table): void {
        // WriteHTML() below has no explicit font-family in its inline CSS,
        // so it inherits whatever font was last set on the document — set
        // explicitly here (not just relying on the caption/heading font
        // that happened to run before this) since table cells are the one
        // place converted math symbols (√, π, ·, ...) can appear, and
        // 'helvetica' (a core PDF font, Latin-1 only) shows those as
        // missing-glyph boxes; 'dejavusans' has full Unicode coverage.
        $pdf->SetFont('dejavusans', '', 7.5);
        $usablewidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $widths = self::compute_column_widths($table['columns'], $usablewidth);

        $html = '<table cellspacing="0" cellpadding="3" border="0.1">';
        $html .= '<thead><tr style="background-color:#1e3c72;color:#ffffff;font-weight:bold;">';
        foreach ($table['columns'] as $i => $col) {
            $html .= '<td width="' . $widths[$i] . 'mm"><span style="font-size:7.5pt;">'
                . htmlspecialchars(chart_helpers::humanize_label((string) $col), ENT_QUOTES) . '</span></td>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($table['rows'] as $rowindex => $row) {
            $bg = ($rowindex % 2 === 1) ? '#f8fafc' : '#ffffff';
            $html .= '<tr style="background-color:' . $bg . ';">';
            foreach ($row as $i => $value) {
                $html .= '<td width="' . ($widths[$i] ?? 20) . 'mm"><span style="font-size:7.5pt;">'
                    . nl2br(htmlspecialchars(self::format_cell($value), ENT_QUOTES)) . '</span></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        if (!empty($table['truncated_from'])) {
            $pdf->SetFont('dejavusans', 'I', 8);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $shown = count($table['rows']);
            $pdf->MultiCell(0, 5, get_string('pdftruncatedrows', 'local_quizanalytics', (object) [
                'shown' => $shown,
                'total' => $table['truncated_from'],
            ]), 0, 'L');
        }
    }

    /**
     * Matches sections-renderer.js's ISO_DATETIME_RE — the same
     * "Y-m-d H:i:s" shape data_fetcher.php's userdate() calls produce.
     *
     * @var string
     */
    const ISO_DATETIME_RE = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?$/';

    /**
     * Formats one table cell value for display: dates get reformatted, math gets
     * rendered as plain text, floats get trimmed, everything else stringified.
     */
    private static function format_cell($value): string {
        if ($value === null) {
            return '';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.') ?: '0';
        }
        if (is_string($value)) {
            if (preg_match(self::ISO_DATETIME_RE, $value)) {
                $formatted = date('M j, Y, g:i A', strtotime(str_replace('T', ' ', $value)));
                if ($formatted !== false) {
                    return $formatted;
                }
            }
            if (str_contains($value, '$')) {
                return latex_utils::latex_to_plain_text($value);
            }
        }
        return (string) $value;
    }

    /**
     * Column widths in mm for a table, narrow/wide-weighted by column name, summing to $usablewidth.
     *
     * @return float[]
     */
    private static function compute_column_widths(array $columns, float $usablewidth): array {
        $weights = [];
        foreach ($columns as $col) {
            $key = strtolower(trim((string) $col));
            if (isset(self::NARROW_COLUMN_WEIGHTS[$key])) {
                $weights[] = self::NARROW_COLUMN_WEIGHTS[$key];
                continue;
            }
            $wide = false;
            foreach (self::WIDE_COLUMN_KEYWORDS as $keyword) {
                if (str_contains($key, $keyword)) {
                    $wide = true;
                    break;
                }
            }
            $weights[] = $wide ? 1.6 : 1.0;
        }
        $total = array_sum($weights) ?: 1.0;
        return array_map(fn($w) => $usablewidth * $w / $total, $weights);
    }

    /**
     * Embeds one chart's client-captured PNG image, sized to fit within MAX_CHART_HEIGHT_MM.
     *
     * @param array{id: string, title: ?string} $chart
     */
    private static function render_chart(quizanalytics_tcpdf $pdf, array $chart, array $chartimages): void {
        $datauri = $chartimages[$chart['id']] ?? null;

        // Decoded and size-checked up front, before the title below is
        // drawn: whether this chart needs a landscape page has to be known
        // before the title is placed, not just before the image, or writing
        // the title to the current (portrait) page and only then switching
        // pages for the image would orphan the title alone at the bottom of
        // the previous page, disconnected from its own chart.
        $imagedata = null;
        $imageinfo = false;
        if ($datauri !== null && preg_match('#^data:image/(png|jpe?g);base64,(.+)$#s', $datauri, $m)) {
            $decoded = base64_decode($m[2]);
            if ($decoded !== false && strlen($decoded) > 0) {
                $imagedata = $decoded;
                $imageinfo = @getimagesizefromstring($decoded);
            }
        }
        // A landscape page's own usable width (~36% more than portrait's on
        // LETTER) gives an unusually wide chart real extra room before it's
        // still shrunk to fit, same as every other chart. TCPDF reuses
        // whichever orientation the last AddPage() call set for every page
        // after it, so a fresh portrait page right after this chart (below)
        // restores it for whatever content follows — self-contained to this
        // one chart rather than requiring every other AddPage() call site
        // in this file to track/pass an explicit orientation.
        $waswidelandscape = $imageinfo !== false && $imageinfo[0] > self::WIDE_CHART_PIXEL_WIDTH;
        if ($waswidelandscape) {
            $pdf->AddPage('L');
        }

        if ($chart['title']) {
            $pdf->SetFont('dejavusans', 'I', 8.5);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 4, $chart['title'], 0, 'L');
        }

        if ($imagedata === null || $imageinfo === false) {
            $pdf->SetFont('dejavusans', 'I', 9);
            $pdf->SetTextColor(0xb4, 0x54, 0x54);
            $label = $chart['title'] ?: $chart['id'];
            $pdf->MultiCell(0, 5, get_string('pdfchartunavailable', 'local_quizanalytics', $label), 0, 'L');
            if ($waswidelandscape) {
                $pdf->AddPage('P');
            }
            return;
        }
        [$pxwidth, $pxheight] = $imageinfo;

        $usablewidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $drawwidth = $usablewidth;
        $drawheight = $pxheight * ($drawwidth / $pxwidth);
        if ($drawheight > self::MAX_CHART_HEIGHT_MM) {
            $shrink = self::MAX_CHART_HEIGHT_MM / $drawheight;
            $drawwidth *= $shrink;
            $drawheight *= $shrink;
        }

        if (!$waswidelandscape && $pdf->GetY() + $drawheight > $pdf->getPageHeight() - $pdf->getMargins()['bottom']) {
            $pdf->AddPage();
        }
        // TCPDF's Image() never advances the page cursor itself (unlike
        // Cell()/MultiCell()/writeHTML()), even with an explicit height —
        // without this, every chart after the first in a section drew
        // starting from the same Y as the one before it, stacking directly
        // on top of each other. $y is passed explicitly (not '') so this
        // draws from the position already established above, then the
        // cursor is moved past it by hand.
        $y = $pdf->GetY();
        $pdf->Image('@' . $imagedata, $pdf->GetX(), $y, $drawwidth, $drawheight, '', '', '', true, 300);
        $pdf->SetY($y + $drawheight + 4);
        if ($waswidelandscape) {
            $pdf->AddPage('P');
        }
    }
}
