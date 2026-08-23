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
 * Privacy provider for local_quizanalytics, covering both merged
 * sections — this plugin stores no personal data of its own.
 *
 * Quiz Analytics (classes/quiz/) reads every quiz attempt, question
 * response, and user record it uses (classes/quiz/data_fetcher.php) live
 * from mod_quiz, the question engine, and core_user — all already covered
 * by their own privacy providers. Model & Diagnostics Analytics
 * (classes/stack/) does the same for grades and log events
 * (classes/stack/local/stack_attempt_reader.php, stack_course_helper.php,
 * stack_prt_graph.php), reading live from mod_quiz, the question engine,
 * grade_grades, and logstore_standard_log. Neither section writes any of
 * that back into a table this plugin owns (there is no db/install.xml
 * here).
 *
 * The only local storage either section uses is Moodle's own MUC cache API
 * (db/caches.php, Quiz Analytics only) — each entry a purely derived,
 * disposable recomputation of the same underlying data, invalidated
 * automatically the moment a relevant attempt changes and cleared by the
 * site's normal "Purge caches" action, not a separate store of personal
 * data with its own retention period. The *predictions and per-sample
 * indicator calculations* the Analytics API produces from Model &
 * Diagnostics Analytics's registered models (db/analytics.php) are stored
 * by core_analytics in its own analytics_* tables, keyed generically by
 * model id rather than by the declaring component, and are already handled
 * by \core_analytics\privacy\provider regardless of which plugin
 * registered the model — confirmed by reading that provider's source,
 * which queries the analytics_* tables directly with no per-component
 * branching.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\privacy;

/**
 * This plugin stores no personal data of its own — see get_reason() and the
 * privacy:metadata language string for the full explanation.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Gets the language string identifier explaining why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
