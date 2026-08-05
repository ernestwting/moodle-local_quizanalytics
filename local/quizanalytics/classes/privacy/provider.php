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
 * Privacy provider for local_quizanalytics.
 *
 * @package    local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\privacy;

/**
 * This plugin stores no personal data of its own — see get_reason() and
 * the privacy:metadata language string for the full explanation.
 *
 * Every quiz attempt, question response, and user record this plugin reads
 * (classes/data_fetcher.php) is fetched live from mod_quiz, the question
 * engine, and core_user — all already covered by their own privacy
 * providers. This plugin never writes any of that back into a table of
 * its own; the only local storage it uses is Moodle's own MUC cache API
 * (db/caches.php), and each cache entry is a purely derived, disposable
 * recomputation of the same underlying data, invalidated automatically
 * the moment a relevant attempt changes and cleared by the site's normal
 * "Purge caches" action — not a separate, independent store of personal
 * data with its own retention period.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
