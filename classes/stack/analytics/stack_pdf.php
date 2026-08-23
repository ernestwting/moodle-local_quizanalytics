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
 * PDF export for the Model & Diagnostics Analytics section, built on
 * Moodle core's own bundled TCPDF wrapper (lib/pdflib.php's \pdf class)
 * rather than vendoring a separate copy — core has shipped TCPDF for its
 * own PDF-generating features (badges, certificates, mod_assign feedback)
 * for years, confirmed against a real Moodle 4.5 core checkout, so there's
 * no need to duplicate that ~5MB third-party library a second time here.
 * The Quiz Analytics section's own PDF export (classes/quiz/analytics/
 * pdf_builder.php) does vendor its own copy (classes/quiz/vendor/tcpdf/),
 * predating this check — the two PDF systems are independent, this one
 * simply doesn't need to repeat that choice.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

/**
 * Adds a simple page header/footer band to core's \pdf class.
 *
 * Header()/Footer() keep TCPDF's own PascalCase method names deliberately —
 * TCPDF calls them back by that exact name, so renaming to lower_case (as
 * Moodle's naming sniff would otherwise want) would silently break the
 * override, same reasoning local_quizanalytics's own TCPDF subclass documents.
 */
class stack_pdf extends \pdf {
    /** @var string Heading text drawn in the page header band. */
    public string $reportheading = 'STACK q-type Analytics Dashboard';

    /**
     * Draws the page header band (called back by TCPDF itself on every page).
     */
    public function Header(): void { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetFont('freesans', '', 9);
        $this->SetTextColor(0x64, 0x74, 0x8b);
        $this->SetDrawColor(0xcb, 0xd5, 0xe1);
        $this->SetLineWidth(0.2);
        $y = 10;
        $this->Line($this->getMargins()['left'], $y, $this->getPageWidth() - $this->getMargins()['right'], $y);
        $this->SetXY($this->getMargins()['left'], $y - 5);
        $this->Cell(0, 5, $this->reportheading, 0, 0, 'L');
    }

    /**
     * Draws the page footer band (called back by TCPDF itself on every page).
     */
    public function Footer(): void { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $this->SetFont('freesans', '', 8);
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
        $this->Cell(0, 8, get_string('pdffooternote', 'local_quizanalytics'), 0, 0, 'L');
        $this->Cell(0, 8, get_string('page') . ' ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
