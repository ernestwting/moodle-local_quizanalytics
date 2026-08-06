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
 * TCPDF subclass adding the page header/footer band pdf_export.py's
 * NumberedCanvas drew directly on the reportlab canvas.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\analytics;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../vendor/tcpdf/tcpdf.php');

/**
 * TCPDF subclass adding the page header/footer band pdf_export.py's
 * NumberedCanvas drew directly on the reportlab canvas.
 *
 * Header()/Footer() keep TCPDF's own PascalCase method names deliberately -
 * TCPDF calls them back by that exact name, so renaming to lower-case (as
 * Moodle's naming sniff would otherwise want) would silently break the
 * override.
 */
class quizanalytics_tcpdf extends \TCPDF {
    /** @var string Heading text drawn in the page header band. */
    public string $reportheading = 'Moodle STACK Analytics Hub — Performance Report';

    /**
     * Builds the PDF document and suppresses TCPDF's own branding link.
     */
    public function __construct(
        $orientation = 'P',
        $unit = 'mm',
        $format = 'A4',
        $unicode = true,
        $encoding = 'UTF-8',
        $diskcache = false,
        $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        // Suppress TCPDF's own "Powered by TCPDF" link, appended to the last
        // page by Close() by default — a clean report, not a TCPDF ad.
        $this->tcpdflink = false;
    }

    /**
     * Draws the page header band (called back by TCPDF itself on every page).
     */
    public function Header(): void { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetFont('dejavusans', '', 9);
        $this->SetTextColor(0x64, 0x74, 0x8b);
        $this->SetDrawColor(0xcb, 0xd5, 0xe1);
        $this->SetLineWidth(0.2);
        $y = $this->GetHeaderMargin() > 0 ? 10 : 10;
        $this->Line($this->getMargins()['left'], $y, $this->getPageWidth() - $this->getMargins()['right'], $y);
        $this->SetXY($this->getMargins()['left'], $y - 5);
        $this->Cell(0, 5, $this->reportheading, 0, 0, 'L');
    }

    /**
     * Draws the page footer band (called back by TCPDF itself on every page).
     */
    public function Footer(): void { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetFont('dejavusans', '', 9);
        $this->SetTextColor(0x64, 0x74, 0x8b);
        $this->SetDrawColor(0xcb, 0xd5, 0xe1);
        $this->SetLineWidth(0.2);
        $y = -15;
        $this->SetY($y);
        $this->Line(
            $this->getMargins()['left'],
            $this->GetY(),
            $this->getPageWidth() - $this->getMargins()['right'],
            $this->GetY()
        );
        $this->SetY($y + 2);
        $this->Cell(0, 8, 'Fully client-side chart export • No data transmitted externally', 0, 0, 'L');
        $this->Cell(0, 8, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
