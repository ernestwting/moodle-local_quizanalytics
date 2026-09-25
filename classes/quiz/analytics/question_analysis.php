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
 * Assembles the simplified Question Analytics {summary, snapshot, sections,
 * questions, audit} payload — a single Question Response Overview chart plus
 * a versioned per-question drill-down, backed by a compact Moodle-native
 * "quiz snapshot" (see local_quizanalytics_quiz_data_fetcher::
 * get_quiz_snapshot()) instead of this plugin's own self-computed difficulty/
 * metrics tables.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizanalytics\quiz\analytics;

/**
 * Assembles the simplified Question Analytics {summary, snapshot, sections, questions, audit} payload for one quiz.
 */
class question_analysis {
    /**
     * Current shape version for prepared Question Review payloads. Also
     * bumped for same-shape correctness fixes — a cached payload's values,
     * not just its shape, need to be treated as stale here too:
     * - v4: parser.php's PRT-count (M) computation changed from a global
     *   max across every response to a per-question mode, fixing responses
     *   misclassified 'incorrect' when a single malformed response
     *   elsewhere inflated the PRT count for every other student on that
     *   question.
     * - v5: parser.php's score computation now prefers Moodle's own
     *   authoritative per-question mark (question_{n}_mark/_maxmark) over
     *   re-deriving it from the PRT fraction embedded in the
     *   response-summary text, fixing responses misclassified 'incorrect'
     *   when an older STACK release's summarise_response() omitted the
     *   "# = <score> |" prefix that fraction parsing depends on — confirmed
     *   directly against a real course's own 2022 attempts.
     */
    public const QUESTION_REVIEW_PAYLOAD_VERSION = 5;

    /**
     * Return whether a prepared payload contains the current navigation
     * metadata. Older durable payloads are rebuilt through the normal path.
     *
     * @param array|null $payload
     * @return bool
     */
    public static function has_current_question_review_metadata(?array $payload): bool {
        return is_array($payload)
            && ($payload['question_review_payload_version'] ?? null) === self::QUESTION_REVIEW_PAYLOAD_VERSION;
    }

    /**
     * Remove the expensive per-variant detail from the lecturer-facing
     * payload. The complete result remains in the questionanalysis MUC cache
     * and is retrieved by questionreview.php only for the selected variant.
     *
     * @param array $result
     * @param string $reviewurl
     * @return array
     */
    public static function to_lightweight_review(array $result, string $reviewurl): array {
        foreach ($result['questions'] ?? [] as &$detail) {
            foreach ($detail['versions'] ?? [] as &$version) {
                unset($version['question_text_html'], $version['question_text_raw'], $version['question_text']);
                unset($version['common_responses']);
            }
            unset($version);
        }
        unset($detail);
        $result['question_review_url'] = $reviewurl;
        return $result;
    }

    /**
     * Builds the individual Question Analytics payload: response overview
     * and per-question drill-down.
     *
     * @param array[] $records as returned by
     *        local_quizanalytics_quiz_data_fetcher::get_response_records_for_quiz()
     * @param string $quizname
     * @param bool $colorblindmode
     * @param bool $anonymize
     * @param array|null $snapshot Moodle-backed quiz snapshot from
     *        local_quizanalytics_quiz_data_fetcher::get_quiz_snapshot()
     * @return array {quiz_name, summary, snapshot, sections, questions, audit}
     */
    public static function build_analysis(
        array $records,
        string $quizname,
        bool $colorblindmode = false,
        bool $anonymize = false,
        ?array $snapshot = null,
        ?callable $progresscallback = null
    ): array {
        $responserows = parser::build_response_rows($records, $quizname, $anonymize);

        $pools = parser::get_attempt_pools($responserows);
        $poolb = $pools['pool_b'];

        $questionmetricsrows = question_metrics::compute_question_metrics($responserows);
        $snapshotquestions = array_column($snapshot['students_per_question'] ?? [], 'question');
        $questionstudents = [];
        foreach ($snapshot['students_per_question'] ?? [] as $questionstudent) {
            $questionstudents[$questionstudent['question']] = (int) $questionstudent['students'];
        }
        $responseoverviewrows = response_analysis::compute_response_statuses(
            $responserows,
            $snapshotquestions,
            $questionstudents,
            $snapshot['question_means'] ?? [],
            $snapshot['question_mean_counts'] ?? []
        );

        $sections = [];
        $questionorder = array_map(fn($r) => $r['question'], $questionmetricsrows);

        $facilityrows = array_values(array_filter(
            $snapshot['question_facilities'] ?? [],
            fn($row) => array_key_exists('facility_index', $row)
        ));
        if (!empty($facilityrows)) {
            $sections[] = [
                'id' => 'facility-index',
                'title' => 'Facility Index',
                'caption' => 'Moodle\'s official measure of how easy each question was in practice. Low values highlight questions that may need review; dashed lines mark 30% and 70%.',
                'documentation_link' => [
                    'url' => 'https://docs.moodle.org/404/en/mod/quiz/statistics',
                    'label' => 'Read Moodle\'s Facility Index documentation',
                ],
                'charts' => [[
                    'id' => 'facility-index-fig',
                    'title' => null,
                    'plotly_json' => question_charts::build_facility_index_figure($facilityrows, $colorblindmode),
                ]],
            ];
        }

        // Question Response Overview — replaces the old Question Difficulty
        // Analysis / Question Response Distribution / Student Performance
        // Matrix / Question Metrics sections with a single chart, matching
        // this simplified page's own reduced scope.
        $responsecharts = [];
        if (!empty($responseoverviewrows)) {
            $responsecharts[] = [
                'id' => 'response-status', 'title' => null,
                'plotly_json' => question_charts::build_response_status_figure($responseoverviewrows, $colorblindmode),
            ];
        }
        $sections[] = [
            'id' => 'question-response-overview',
            'title' => 'Question Response Overview',
            'caption' => 'Shows how students responded to each question. Select a question to inspect the responses ' .
                'in more detail.',
            'charts' => $responsecharts,
        ];

        $totalquestions = count($questionorder);
        if ($progresscallback !== null) {
            $progresscallback(0, $totalquestions, '');
        }

        // Per-question detail (drives the PHP question <select>), grouped by
        // instantiated STACK question "version" so randomized variants don't
        // mix their expected answers/wrong-response lists together.
        $questions = [];
        foreach ($questionorder as $questionindex => $q) {
            $versions = question_details::build_versioned_review($poolb, $q, $anonymize);
            $metadata = [
                'question_id' => (int) ($versions[0]['question_id'] ?? 0),
                'cmid' => (int) ($versions[0]['cmid'] ?? 0),
            ];
            $slotquestionid = question_details::get_question_id_for_quiz_label(
                $metadata['cmid'],
                $q
            );
            if ($slotquestionid > 0) {
                $metadata['question_id'] = $slotquestionid;
            }
            $stacklinks = question_details::build_stack_links(
                $metadata['question_id'],
                $metadata['cmid']
            );
            foreach ($versions as &$version) {
                // Debug-dump detection stays on the raw (pre-format_text) HTML,
                // matching where this check always ran — format_text()'s HTML
                // purification runs after, on whatever's left once a leaked dump
                // is split off.
                [$cleanraw, ] = latex_utils::split_stack_debug_dump(
                    $version['question_text_raw'] !== '' ? $version['question_text_raw'] : $version['question_text']
                );
                $version['question_text_html'] = latex_utils::render_question_html($cleanraw);
                $version['right_answer_html'] = latex_utils::extract_stack_answer_latex($version['right_answer_text']);
                foreach ($version['common_responses'] as &$commonresponse) {
                    $commonresponse['response'] = latex_utils::extract_stack_answer_latex(
                        (string) $commonresponse['response']
                    );
                }
                unset($commonresponse);
                unset($version['question_text'], $version['question_text_raw'], $version['right_answer_text']);
            }
            unset($version);
            $questions[$q] = [
                'question_id' => $metadata['question_id'],
                'cmid' => $metadata['cmid'],
                'question_dashboard_url' => $stacklinks['question_dashboard_url'],
                'versions' => $versions,
            ];
            if ($progresscallback !== null) {
                $progresscallback($questionindex + 1, $totalquestions, $q);
            }
        }

        return [
            'quiz_name' => $quizname,
            'summary' => [],
            'snapshot' => $snapshot,
            'sections' => $sections,
            'questions' => $questions,
            'question_review_links_allowed' => !$anonymize,
            'question_review_payload_version' => self::QUESTION_REVIEW_PAYLOAD_VERSION,
            'audit' => null,
        ];
    }
}
