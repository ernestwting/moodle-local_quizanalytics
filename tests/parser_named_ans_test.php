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

namespace local_quizanalytics;

use local_quizanalytics\quiz\analytics\parser;
use local_quizanalytics\quiz\analytics\prt_analysis;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression coverage for STACK questions whose inputs are renamed away from
 * the default "ans1"/"ans2" convention (e.g. "ans_mcq", "ans_1fx") — a
 * multi-part ODE question observed live misclassified every such response
 * as "blank" (parser.php's ans-field regex required a numeric suffix) and
 * leaked the "ans_*" fields into the PRT breakdown as fake zero-scoring
 * PRTs (prt_analysis.php's exclusion regex had the same numeric-only gap).
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parser_named_ans_test extends \advanced_testcase {
    /** A real response cell from a "separation of variables" ODE question with author-renamed inputs. */
    const NAMED_ANS_RESPONSE = 'Seed: 69321680; ans_mcq: "Separation of Variables" [score]; '
        . 'ans_fx: -4*x^3 [score]; ans_gy: y [score]; ans_1gy: 1/y [score]; ans_1fx: -4*x^3 [score]; '
        . 'ans_lnyeqn: ln(abs(x)) [score]; ans_ysol: Ke^(-x^4) [score]; '
        . 'prt_mcq: # = 1 | ATAlgEquiv_String | prt_mcq-1-T; '
        . 'prt_fxgy: # = 1 | ATLogic_True. | prt_fxgy-1-T | ATLogic_True. | prt_fxgy-2-T | ATLogic_True. | prt_fxgy-3-T; '
        . 'prt_1fxgy: # = 1 | ATLogic_True. | prt_1fxgy-1-T | ATLogic_True. | prt_1fxgy-2-T | ATLogic_True. | prt_1fxgy-3-T; '
        . 'prt_lnyeqn: # = 0 | prt_lnyeqn-1-F; '
        . 'prt_ysol: # = 0 | prt_ysol-1-F | prt_ysol-2-F';

    public function test_named_ans_fields_are_parsed_not_treated_as_blank(): void {
        $parsed = parser::parse_response_cell(self::NAMED_ANS_RESPONSE);

        $this->assertNotEmpty($parsed['ans_list'], 'Named ans_* fields must still populate ans_list.');
        $this->assertCount(7, $parsed['ans_list']);

        $bymcq = array_values(array_filter($parsed['ans_list'], fn($a) => $a['expression'] === '"Separation of Variables"'));
        $this->assertNotEmpty($bymcq);
        $this->assertNull($bymcq[0]['index'], 'A non-numeric input name has no meaningful numeric index.');
    }

    public function test_numeric_ans_suffix_still_gets_an_int_index(): void {
        $parsed = parser::parse_response_cell('ans1: x^2 [score]');
        $this->assertSame(1, $parsed['ans_list'][0]['index']);
    }

    public function test_named_ans_fields_do_not_leak_into_prt_breakdown(): void {
        $rows = prt_analysis::build_prt_frame([[
            'question' => 'Q1',
            'response_text' => self::NAMED_ANS_RESPONSE,
            'response_status' => 'incorrect',
        ]]);

        $prtnames = array_map(fn($r) => $r['prt_name'], $rows);
        foreach ($prtnames as $name) {
            $this->assertStringStartsNotWith('ans', $name, 'An ans_* field was misidentified as a PRT.');
        }
        // The five real PRTs in the sample (prt_mcq, prt_fxgy, prt_1fxgy, prt_lnyeqn, prt_ysol).
        $this->assertCount(5, $rows);
    }
}
