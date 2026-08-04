<?php
/**
 * Talks to the same local analytics microservice quiz_quizanalytics uses
 * (see mod/quiz/report/quizanalytics/classes/api_client.php for the per-quiz
 * version this is adapted from) — there is only ever one analytics-service
 * process; this plugin just calls two of its endpoints instead of one.
 *
 * Deliberately dumb: no retries, no queuing. If it fails, the page shows a
 * friendly error rather than a stack trace, and nothing about a failure here
 * ever sends data anywhere else — it just doesn't get an answer back.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

class local_quizanalytics_api_client {

    /**
     * @param string $endpointpath either '/analyze' or '/analyze-course'
     * @param array  $payload      request body matching that endpoint's schema
     * @return array|null          Decoded JSON response, or null on any failure.
     */
    protected function post(string $endpointpath, array $payload): ?array {
        $config = get_config('local_quizanalytics');
        $base = !empty($config->apibaseurl) ? rtrim($config->apibaseurl, '/') : 'http://127.0.0.1:8600';
        $timeout = !empty($config->apitimeout) ? (int) $config->apitimeout : 30;
        $endpoint = $base . $endpointpath;

        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            // Belt-and-braces: this only ever calls the configured internal
            // endpoint (default localhost). Remove this only if you have a
            // specific reason the service isn't on localhost/private network.
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            debugging('local_quizanalytics: curl error contacting analytics service (' . $endpointpath . '): ' .
                $curlerror, DEBUG_DEVELOPER);
            return null;
        }

        if ($httpcode !== 200) {
            debugging('local_quizanalytics: analytics service returned HTTP ' . $httpcode . ' for ' . $endpointpath,
                DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('local_quizanalytics: could not decode analytics service response: ' .
                json_last_error_msg(), DEBUG_DEVELOPER);
            return null;
        }

        return $decoded;
    }

    /**
     * Single-quiz analysis, for the drill-down view. Same request shape as
     * quiz_quizanalytics_api_client::analyze().
     *
     * @param string $quizname
     * @param array  $records
     * @param bool   $colorblindmode
     * @return array|null
     */
    public function analyze(string $quizname, array $records, bool $colorblindmode = false): ?array {
        return $this->post('/analyze', [
            'quiz_name'       => $quizname,
            'records'         => $records,
            'colorblind_mode' => $colorblindmode,
        ]);
    }

    /**
     * Cross-quiz analysis, for the course-wide view.
     *
     * @param string $coursename
     * @param array  $quizzes [quiz_name => records[]]
     * @param bool   $colorblindmode
     * @return array|null
     */
    public function analyze_course(string $coursename, array $quizzes, bool $colorblindmode = false): ?array {
        return $this->post('/analyze-course', [
            'course_name'     => $coursename,
            'quizzes'         => $quizzes,
            'colorblind_mode' => $colorblindmode,
        ]);
    }
}
