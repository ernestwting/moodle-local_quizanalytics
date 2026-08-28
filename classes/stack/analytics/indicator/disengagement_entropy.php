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
 * Model 1 indicator (c): disengagement / "rage-quit" entropy.
 *
 * Architecture doc §2.2(c): low Shannon entropy of the attempt-gap sequence
 * (a regular, mechanical submission rhythm) combined with a burst of
 * abandoned questions (state 'gaveup' before the question closed) signals
 * mechanical guessing / disengagement rather than genuine problem-solving.
 * H = -Σ pᵢ log₂ pᵢ over binned inter-attempt intervals; combined with the
 * abandonment rate into a weighted composite bounded to [-1, 1].
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
 * Composite of attempt-gap entropy and question-abandonment rate.
 */
class disengagement_entropy extends \core_analytics\local\indicator\linear {
    /** State recorded on a question_attempt_steps row when a student gives up before closing the question. */
    const ABANDONED_STATE = 'gaveup';

    /**
     * Gets this indicator's human-readable name.
     *
     * @return \lang_string
     */
    public static function get_name(): \lang_string {
        return new \lang_string('indicator:disengagemententropy', 'local_quizanalytics');
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
     * Shannon entropy (base 2) of a discrete probability distribution.
     * Zero-probability bins are skipped (0 * log2(0) is defined as 0).
     *
     * @param float[] $probabilities must sum to ~1.0
     * @return float
     */
    public static function shannon_entropy(array $probabilities): float {
        $entropy = 0.0;
        foreach ($probabilities as $p) {
            if ($p > 0.0) {
                $entropy -= $p * log($p, 2);
            }
        }
        return $entropy;
    }

    /**
     * Bins raw inter-attempt gaps (seconds) into $numbins equal-width bins
     * spanning the observed range, and returns the Shannon entropy of that
     * binned distribution normalized to [0, 1] by dividing by the maximum
     * possible entropy for $numbins bins (log2($numbins)) — 0 means every
     * gap fell in one bin (perfectly regular, mechanical rhythm), 1 means
     * gaps were spread evenly across all bins (varied, human-like pacing).
     *
     * @param float[] $gapseconds
     * @param int $numbins
     * @return float|null null if fewer than 2 gaps (nothing to bin)
     */
    public static function normalized_gap_entropy(array $gapseconds, int $numbins = 5): ?float {
        $n = count($gapseconds);
        if ($n < 2 || $numbins < 2) {
            return null;
        }

        $min = min($gapseconds);
        $max = max($gapseconds);
        $range = $max - $min;

        $counts = array_fill(0, $numbins, 0);
        foreach ($gapseconds as $gap) {
            if ($range <= 0.0) {
                $bin = 0; // All gaps identical — everything falls in bin 0 (entropy 0, maximally regular).
            } else {
                $bin = (int) floor((($gap - $min) / $range) * $numbins);
                $bin = min($bin, $numbins - 1);
            }
            $counts[$bin]++;
        }

        $probabilities = array_map(fn($c) => $c / $n, $counts);
        $entropy = self::shannon_entropy($probabilities);
        $maxentropy = log($numbins, 2);

        return $maxentropy > 0.0 ? $entropy / $maxentropy : 0.0;
    }

    /**
     * Weighted composite: low entropy (mechanical) and high abandonment both
     * push toward +1 (disengaged); high entropy and low abandonment push
     * toward -1 (engaged). Equal 0.5/0.5 weighting per the architecture
     * doc's "combine ... into a weighted composite" — no basis given in the
     * doc for an unequal split, so the simplest defensible choice is equal.
     *
     * @param float $entropyratio in [0, 1]
     * @param float $abandonmentrate in [0, 1]
     * @return float
     */
    public static function composite_to_indicator(float $entropyratio, float $abandonmentrate): float {
        $disengagement = 0.5 * (1.0 - $entropyratio) + 0.5 * $abandonmentrate;
        return max(-1.0, min(1.0, 2.0 * $disengagement - 1.0));
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
     * for the shared contract.
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

        $gaps = [];
        $abandoned = 0;
        foreach ($attempts as $steps) {
            for ($i = 1; $i < count($steps); $i++) {
                $gaps[] = (float) ($steps[$i]->timecreated - $steps[$i - 1]->timecreated);
            }
            $laststep = end($steps);
            if ($laststep->state === self::ABANDONED_STATE) {
                $abandoned++;
            }
        }

        $entropyratio = self::normalized_gap_entropy($gaps);
        if ($entropyratio === null) {
            $entropyratio = 1.0; // Too few gaps to judge rhythm — assume "engaged" rather than penalise sparse data.
        }
        $attemptcount = count($attempts);
        $abandonmentrate = $attemptcount > 0 ? $abandoned / $attemptcount : 0.0;

        $indicator = self::composite_to_indicator($entropyratio, $abandonmentrate);

        return (object) [
            'indicator' => $indicator,
            'band' => $indicator >= 0.33 ? 'watch' : ($indicator <= -0.33 ? 'good' : 'neutral'),
            'summary' => [
                'abandonedcount' => $abandoned,
                'attempts' => $attemptcount,
            ],
        ];
    }
}
