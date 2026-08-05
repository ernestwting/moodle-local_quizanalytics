<?php
/**
 * Renders a pdf_content.php payload to PDF bytes via TCPDF (vendored at
 * classes/vendor/tcpdf/) — the PHP port's equivalent of
 * analytics-service/analytics/pdf_export.py's generate_pdf_report().
 *
 * Deliberate v1 simplifications from the Python original, matching the
 * approved port plan:
 * - Charts are embedded from client-captured PNGs (Plotly.toImage(), taken
 *   from the chart already rendered on screen) rather than rasterized
 *   server-side — PHP has no headless-browser/kaleido equivalent, and this
 *   plugin's whole point is needing no such external dependency.
 * - Math (STACK/Maxima LaTeX and \(...\)/$...$ delimited expressions in
 *   table cells) prints as its raw source text rather than being
 *   rasterized to an inline image — no server-side math-rendering
 *   dependency needed, at the cost of not showing typeset symbols in the
 *   PDF specifically (the on-screen page still renders it via KaTeX).
 * - No auto-generated table of contents / PDF outline bookmarks — a
 *   navigation nicety, not core report content.
 *
 * @package local_quizanalytics
 */

namespace local_quizanalytics\analytics;

require_once(__DIR__ . '/../vendor/tcpdf/tcpdf.php');

/**
 * TCPDF subclass adding the page header/footer band pdf_export.py's
 * NumberedCanvas drew directly on the reportlab canvas.
 */
class quizanalytics_tcpdf extends \TCPDF {

    public string $reportheading = 'Moodle STACK Analytics Hub — Performance Report';

    public function __construct(
        $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true,
        $encoding = 'UTF-8', $diskcache = false, $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        // Suppress TCPDF's own "Powered by TCPDF" link, appended to the last
        // page by Close() by default — a clean report, not a TCPDF ad.
        $this->tcpdflink = false;
    }

    public function Header(): void {
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0x64, 0x74, 0x8b);
        $this->SetDrawColor(0xcb, 0xd5, 0xe1);
        $this->SetLineWidth(0.2);
        $y = $this->GetHeaderMargin() > 0 ? 10 : 10;
        $this->Line($this->getMargins()['left'], $y, $this->getPageWidth() - $this->getMargins()['right'], $y);
        $this->SetXY($this->getMargins()['left'], $y - 5);
        $this->Cell(0, 5, $this->reportheading, 0, 0, 'L');
    }

    public function Footer(): void {
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(0x64, 0x74, 0x8b);
        $this->SetDrawColor(0xcb, 0xd5, 0xe1);
        $this->SetLineWidth(0.2);
        $y = -15;
        $this->SetY($y);
        $this->Line($this->getMargins()['left'], $this->GetY(), $this->getPageWidth() - $this->getMargins()['right'], $this->GetY());
        $this->SetY($y + 2);
        $this->Cell(0, 8, 'Fully client-side chart export • No data transmitted externally', 0, 0, 'L');
        $this->Cell(0, 8, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

class pdf_builder {

    const MAX_CHART_HEIGHT_MM = 90.0;

    // Column-width weighting, matching pdf_export.py's _compute_column_widths.
    const NARROW_COLUMN_WEIGHTS = [
        'question' => 0.55, 'score' => 0.45, 'status' => 0.6,
        'student name' => 0.85, 'frequency' => 0.5,
    ];
    const WIDE_COLUMN_KEYWORDS = ['response', 'answer', 'text', 'email'];

    /**
     * @param array{title: string, subtitle: string, sections: array[]} $content
     * @param array<string, string> $chart_images chart id => data: URL (PNG)
     */
    public static function build(array $content, array $chart_images): string {
        $pdf = new quizanalytics_tcpdf('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('local_quizanalytics');
        $pdf->SetAuthor('Moodle STACK Analytics Hub');
        $pdf->SetTitle($content['title']);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(19, 22, 19);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(15);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0x1e, 0x3c, 0x72);
        $pdf->MultiCell(0, 8, $content['title'], 0, 'L');

        if (!empty($content['subtitle'])) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(0x47, 0x55, 0x69);
            $pdf->MultiCell(0, 5, $content['subtitle'], 0, 'L');
        }
        $pdf->Ln(4);

        if (empty($content['sections'])) {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 6, 'No sections were selected for this report.', 0, 'L');
        }

        foreach ($content['sections'] as $section) {
            self::render_section($pdf, $section, $chart_images);
        }

        return $pdf->Output($content['title'] . '.pdf', 'S');
    }

    private static function render_section(quizanalytics_tcpdf $pdf, array $section, array $chart_images): void {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0x1e, 0x29, 0x3b);
        $pdf->MultiCell(0, 6, $section['title'], 0, 'L');

        if (!empty($section['caption'])) {
            $pdf->SetFont('helvetica', 'I', 8.5);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 4, $section['caption'], 0, 'L');
        }
        $pdf->Ln(1);

        if (!empty($section['table']) && !empty($section['table']['rows'])) {
            self::render_table($pdf, $section['table']);
            $pdf->Ln(2);
        }

        foreach ($section['charts'] as $chart) {
            self::render_chart($pdf, $chart, $chart_images);
        }

        $pdf->Ln(4);
    }

    /** @param array{columns: string[], rows: array[], truncated_from?: int} $table */
    private static function render_table(quizanalytics_tcpdf $pdf, array $table): void {
        $usablewidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $widths = self::compute_column_widths($table['columns'], $usablewidth);

        $html = '<table cellspacing="0" cellpadding="3" border="0.1">';
        $html .= '<thead><tr style="background-color:#1e3c72;color:#ffffff;font-weight:bold;">';
        foreach ($table['columns'] as $i => $col) {
            $html .= '<td width="' . $widths[$i] . 'mm"><span style="font-size:7.5pt;">'
                . htmlspecialchars((string) $col, ENT_QUOTES) . '</span></td>';
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
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $shown = count($table['rows']);
            $pdf->MultiCell(0, 5, "Showing the first {$shown} of {$table['truncated_from']} rows.", 0, 'L');
        }
    }

    private static function format_cell($value): string {
        if ($value === null) {
            return '';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.2f', $value), '0'), '.') ?: '0';
        }
        return (string) $value;
    }

    /** @return float[] column widths in mm, summing to $usablewidth. */
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

    /** @param array{id: string, title: ?string} $chart */
    private static function render_chart(quizanalytics_tcpdf $pdf, array $chart, array $chart_images): void {
        $datauri = $chart_images[$chart['id']] ?? null;

        if ($chart['title']) {
            $pdf->SetFont('helvetica', 'I', 8.5);
            $pdf->SetTextColor(0x64, 0x74, 0x8b);
            $pdf->MultiCell(0, 4, $chart['title'], 0, 'L');
        }

        if ($datauri === null || !preg_match('#^data:image/(png|jpe?g);base64,(.+)$#s', $datauri, $m)) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(0xb4, 0x54, 0x54);
            $label = $chart['title'] ?: $chart['id'];
            $pdf->MultiCell(0, 5, "{$label} — chart image unavailable (not captured from the page).", 0, 'L');
            return;
        }

        $imagedata = base64_decode($m[2]);
        if ($imagedata === false || strlen($imagedata) === 0) {
            return;
        }

        $usablewidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $imageinfo = @getimagesizefromstring($imagedata);
        if ($imageinfo === false) {
            return;
        }
        [$pxwidth, $pxheight] = $imageinfo;
        $drawwidth = $usablewidth;
        $drawheight = $pxheight * ($drawwidth / $pxwidth);
        if ($drawheight > self::MAX_CHART_HEIGHT_MM) {
            $shrink = self::MAX_CHART_HEIGHT_MM / $drawheight;
            $drawwidth *= $shrink;
            $drawheight *= $shrink;
        }

        if ($pdf->GetY() + $drawheight > $pdf->getPageHeight() - $pdf->getMargins()['bottom']) {
            $pdf->AddPage();
        }
        $pdf->Image('@' . $imagedata, '', '', $drawwidth, $drawheight, '', '', '', true, 300);
        $pdf->Ln(3);
    }
}
