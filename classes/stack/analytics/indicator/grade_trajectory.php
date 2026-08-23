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
 * Model 1 indicator (a): grade trajectory.
 *
 * Architecture doc §2.2(a): rolling average of STACK question grades within
 * the time-split window, normalized via 2 * (mean_grade / max_grade) - 1.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\stack\analytics\indicator;

use local_quizanalytics\stack\local\stack_attempt_reader;
use local_quizanalytics\stack\local\stack_course_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Scaled rolling mean of a student's own STACK question grades.
 */
class grade_trajectory extends \core_analytics\local\indicator\linear {
    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:gradetrajectory', 'local_quizanalytics');
    }

    /**
     * Declares which sample-data types this indicator needs.
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return ['user_enrolments'];
    }

    /**
     * Pure normalization: 2 * (mean_grade / max_grade) - 1, clipped to
     * [-1, 1] as a defensive bound (the ratio is already in [0, 1] under
     * normal grading, but a course with negative/extra-credit grading could
     * push it outside that range).
     *
     * @param float $meangrade
     * @param float $maxgrade
     * @return float
     */
    public static function scale_to_indicator(float $meangrade, float $maxgrade): float {
        if ($maxgrade <= 0.0) {
            return 0.0;
        }
        $scaled = 2.0 * ($meangrade / $maxgrade) - 1.0;
        return max(-1.0, min(1.0, $scaled));
    }

    /**
     * Feeds this indicator's score to the Analytics API for one sample.
     *
     * @param int $sampleid a user_enrolments.id
     * @param string $sampleorigin
     * @param int|false $starttime
     * @param int|false $endtime
     * @return float|null
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime, $endtime) {
        return self::compute_for_sample((int) $sampleid, $starttime, $endtime)->indicator ?? null;
    }

    /**
     * Dashboard-facing computation: the same value calculate_sample() feeds
     * to the Analytics API, plus the real-world facts and a plain-language
     * band a non-technical reader can act on without knowing what a [-1, 1]
     * indicator scale means.
     *
     * @param int $sampleid a user_enrolments.id
     * @param int|false $starttime
     * @param int|false $endtime
     * @return \stdClass|null null if there isn't enough data yet
     */
    public static function compute_for_sample(int $sampleid, $starttime = false, $endtime = false): ?\stdClass {
        $enrolment = stack_course_helper::get_enrolment_user_and_course($sampleid);
        if (!$enrolment) {
            return null;
        }

        $grades = stack_attempt_reader::get_finished_stack_grades(
            (int) $enrolment->userid,
            (int) $enrolment->courseid,
            $starttime,
            $endtime
        );
        if (empty($grades)) {
            return null; // No finished STACK attempts in this window yet.
        }

        $totalgrade = 0.0;
        $totalmax = 0.0;
        foreach ($grades as $grade) {
            if ($grade->fraction === null) {
                continue;
            }
            $totalgrade += (float) $grade->fraction * (float) $grade->maxmark;
            $totalmax += (float) $grade->maxmark;
        }

        $count = count($grades);
        if ($count === 0 || $totalmax <= 0.0) {
            return null;
        }

        $meangrade = $totalgrade / $count;
        $meanmax = $totalmax / $count;
        $indicator = self::scale_to_indicator($meangrade, $meanmax);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'good' : ($indicator <= -0.33 ? 'watch' : 'neutral'),
            'summary' => [
                'meanpercent' => $meanmax > 0.0 ? round(100.0 * $meangrade / $meanmax, 1) : 0.0,
                'attempts' => $count,
            ],
        ];
    }
}
