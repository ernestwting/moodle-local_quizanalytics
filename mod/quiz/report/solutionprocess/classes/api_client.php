<?php
/**
 * Talks to the same local analytics microservice quiz_quizanalytics uses
 * (see mod/quiz/report/quizanalytics/classes/api_client.php) — there is only
 * ever one analytics-service process; this plugin just calls two of its
 * endpoints (/solution-process/meta, /solution-process) instead of one.
 *
 * Deliberately dumb: no retries, no queuing. If it fails, the report shows a
 * friendly error rather than a stack trace, and nothing about a failure here
 * ever sends data anywhere else — it just doesn't get an answer back.
 *
 * @package quiz_solutionprocess
 */

defined('MOODLE_INTERNAL') || die();

class quiz_solutionprocess_api_client {

    /**
     * @param string $endpointpath e.g. '/solution-process/meta'
     * @param array  $payload      request body matching that endpoint's schema
     * @return array|null          Decoded JSON response, or null on any failure.
     */
    protected function post(string $endpointpath, array $payload): ?array {
        $config = get_config('quiz_solutionprocess');
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
            debugging('quiz_solutionprocess: curl error contacting analytics service (' . $endpointpath . '): ' .
                $curlerror, DEBUG_DEVELOPER);
            return null;
        }

        if ($httpcode !== 200) {
            debugging('quiz_solutionprocess: analytics service returned HTTP ' . $httpcode . ' for ' . $endpointpath,
                DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('quiz_solutionprocess: could not decode analytics service response: ' .
                json_last_error_msg(), DEBUG_DEVELOPER);
            return null;
        }

        return $decoded;
    }

    /**
     * Cheap metadata (question/part/student lists) for populating selectors.
     *
     * @param string $quizname
     * @param array  $records
     * @return array|null
     */
    public function meta(string $quizname, array $records): ?array {
        return $this->post('/solution-process/meta', [
            'quiz_name' => $quizname,
            'records'   => $records,
        ]);
    }

    /**
     * The full Solution Process Visualization for one (question, part),
     * optionally scoped further to one student's own drill-down.
     *
     * @param string      $quizname
     * @param array       $records
     * @param string      $question
     * @param int         $partindex
     * @param string|null $studentid
     * @param bool        $colorblindmode
     * @return array|null
     */
    public function analyze(
        string $quizname,
        array $records,
        string $question,
        int $partindex = 1,
        ?string $studentid = null,
        bool $colorblindmode = false
    ): ?array {
        return $this->post('/solution-process', [
            'quiz_name'       => $quizname,
            'records'         => $records,
            'question'        => $question,
            'part_index'      => $partindex,
            'student_id'      => $studentid,
            'colorblind_mode' => $colorblindmode,
        ]);
    }

    /**
     * The section names available for the Solution Process Visualization
     * PDF, from GET /report-sections/solutionprocess — drives the "Generate
     * PDF Report" checkbox list. Returns [] on any failure (the form still
     * works, it just renders with no checkboxes).
     *
     * @return string[]
     */
    public function report_sections(): array {
        $config = get_config('quiz_solutionprocess');
        $base = !empty($config->apibaseurl) ? rtrim($config->apibaseurl, '/') : 'http://127.0.0.1:8600';

        $curl = curl_init($base . '/report-sections/solutionprocess');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $httpcode !== 200) {
            return [];
        }
        $decoded = json_decode($response, true);
        return $decoded['sections'] ?? [];
    }

    /**
     * Requests the Solution Process Visualization PDF for one (question,
     * part) and returns its raw bytes.
     *
     * @param string $quizname
     * @param array  $records
     * @param string $question
     * @param int    $partindex
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @return string|null Raw PDF bytes, or null on any failure.
     */
    public function download_pdf(
        string $quizname,
        array $records,
        string $question,
        int $partindex,
        ?array $selectedsections,
        bool $colorblindmode = false
    ): ?string {
        $config = get_config('quiz_solutionprocess');
        $base = !empty($config->apibaseurl) ? rtrim($config->apibaseurl, '/') : 'http://127.0.0.1:8600';
        $timeout = !empty($config->apipdftimeout) ? (int) $config->apipdftimeout : 90;

        $curl = curl_init($base . '/pdf/solution-process');
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'quiz_name'         => $quizname,
                'records'           => $records,
                'question'          => $question,
                'part_index'        => $partindex,
                'selected_sections' => $selectedsections,
                'colorblind_mode'   => $colorblindmode,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contenttype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        if ($response === false || $httpcode !== 200 || strpos((string) $contenttype, 'application/pdf') !== 0) {
            debugging('quiz_solutionprocess: PDF request failed: HTTP ' . $httpcode . ' ' . $curlerror,
                DEBUG_DEVELOPER);
            return null;
        }
        return $response;
    }
}
