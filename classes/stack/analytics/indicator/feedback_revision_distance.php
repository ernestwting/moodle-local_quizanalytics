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
 * Model 1 indicator (e): feedback-revision distance.
 *
 * Architecture doc §2.2(e): similarity between consecutive attempt inputs on
 * the same PRT node after specific feedback was shown, via normalized
 * Levenshtein/token edit distance. Normalize: 1 - 2 * (edit_distance /
 * max_len) — near 1 means no change was made despite feedback.
 *
 * "Same PRT node" is read here as: the same question-type response variable
 * name (e.g. STACK's 'ans1') appearing in two consecutive steps of the same
 * question_attempt — for STACK's interactive-behavior question engine, each
 * new step *is* a new try at that PRT's inputs, so consecutive steps holding
 * the same input name are exactly the "attempt n, attempt n+1 on the same
 * node" pair the architecture doc describes. Keys starting with '-' or '_'
 * are excluded, matching question_attempt_step's own convention that those
 * prefixes mark behavior-internal/private data rather than the student's
 * actual response.
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
 * Average normalized edit distance between a student's consecutive tries at
 * the same STACK input, after feedback was shown.
 */
class feedback_revision_distance extends \core_analytics\local\indicator\linear {
    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:feedbackrevisiondistance', 'local_quizanalytics');
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
     * Normalized Levenshtein distance in [0, 1]. Two empty strings are
     * treated as identical (distance 0), not undefined.
     *
     * @param string $a
     * @param string $b
     * @return float
     */
    public static function normalized_edit_distance(string $a, string $b): float {
        $maxlen = max(strlen($a), strlen($b));
        if ($maxlen === 0) {
            return 0.0;
        }
        return levenshtein($a, $b) / $maxlen;
    }

    /**
     * Normalizes an average normalized edit distance to the indicator's [-1, 1] range.
     *
     * @param float $avgdistance in [0, 1]
     * @return float
     */
    public static function distance_to_indicator(float $avgdistance): float {
        return max(-1.0, min(1.0, 1.0 - 2.0 * $avgdistance));
    }

    /**
     * A response-data key counts as a student input (rather than
     * behavior-internal bookkeeping) unless it starts with '-' or '_'.
     *
     * @param string $key
     * @return bool
     */
    public static function is_response_key(string $key): bool {
        return $key !== '' && $key[0] !== '-' && $key[0] !== '_';
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
     * Dashboard-facing computation — see grade_trajectory::compute_for_sample()
     * for the shared contract. A high indicator here means the student's
     * revisions barely changed after seeing feedback (concerning); a low one
     * means they revised substantially (good).
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

        $attempts = stack_attempt_reader::get_attempt_step_sequences(
            (int) $enrolment->userid,
            (int) $enrolment->courseid,
            $starttime,
            $endtime
        );
        if (empty($attempts)) {
            return null;
        }

        $distances = [];
        foreach ($attempts as $steps) {
            for ($i = 1; $i < count($steps); $i++) {
                $before = stack_attempt_reader::get_step_response_data((int) $steps[$i - 1]->stepid);
                $after = stack_attempt_reader::get_step_response_data((int) $steps[$i]->stepid);

                foreach ($after as $key => $aftervalue) {
                    if (!self::is_response_key($key) || !array_key_exists($key, $before)) {
                        continue;
                    }
                    $distances[] = self::normalized_edit_distance((string) $before[$key], (string) $aftervalue);
                }
            }
        }

        if (empty($distances)) {
            return null; // Only single-try attempts in this window — nothing to compare revisions against.
        }

        $avgdistance = array_sum($distances) / count($distances);
        $indicator = self::distance_to_indicator($avgdistance);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : ($indicator <= -0.33 ? 'good' : 'neutral'),
            'summary' => [
                'changepercent' => round(100.0 * $avgdistance, 0),
                'revisions' => count($distances),
            ],
        ];
    }
}
