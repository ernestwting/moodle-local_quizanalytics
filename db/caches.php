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
 * MUC cache area definitions for local_quizanalytics.
 *
 * Every area is keyed on a cheap SQL fingerprint of the underlying attempts
 * (see classes/cache_helper.php), not a fixed TTL alone: a cache entry is
 * only ever served when the fingerprint still matches the current DB state,
 * so new/regraded attempts are reflected immediately rather than waiting out
 * the TTL. The TTL here is only a backstop against unbounded growth for
 * quizzes that are no longer being actively looked at.
 *
 * simplekeys: every key this plugin builds is an md5() hex string (see
 * cache_helper.php), which satisfies MUC's simple-key charset.
 * simpledata: false, since every area stores a decoded JSON array (the
 * {summary, sections, ...} API response), not a scalar.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // The Question Analytics /analyze result for one quiz.
    'questionanalysis' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The cheap /solution-process/meta result for one quiz (question/part/
    // student lists for the selector form).
    'solutionprocessmeta' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The /solution-process result for one (quiz, question, part, student,
    // colorblind) selection — by far the most expensive of the four (tree
    // edit distance, 3D figures, network graphs), so the one caching
    // benefits the most.
    'solutionprocess' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The /analyze-course result for one course.
    'quizanalysiscoursewide' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

];
