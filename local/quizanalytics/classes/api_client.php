<?php
/**
 * Talks to the local analytics microservice (a small FastAPI app wrapping
 * the analytics/ Python package — see analytics-service/ at the repo root).
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
     * @param string $endpointpath e.g. '/analyze', '/analyze-course', '/solution-process/meta'
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
     * Question Analytics for a single quiz, for the per-quiz drill-down view.
     *
     * Computed directly in PHP (see classes/analytics/) rather than calling
     * out to the analytics-service — the whole point of this plugin being
     * the only thing an institution has to install.
     *
     * @param string $quizname
     * @param array  $records
     * @param bool   $colorblindmode
     * @return array|null
     */
    public function analyze(string $quizname, array $records, bool $colorblindmode = false): ?array {
        try {
            return \local_quizanalytics\analytics\question_analysis::build_analysis(
                $records, $quizname, $colorblindmode
            );
        } catch (\Throwable $e) {
            debugging('local_quizanalytics: error building question analytics: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Cross-quiz analysis, for the course-wide view.
     *
     * @param string      $coursename
     * @param array       $quizzes   [quiz_name => records[]]
     * @param bool        $colorblindmode
     * @param string|null $gradetype One of "Highest Grade"/"Average Grade"/
     *                    "Minimum Grade" — null lets the service apply its
     *                    own default (Average Grade).
     * @return array|null
     */
    public function analyze_course(
        string $coursename,
        array $quizzes,
        bool $colorblindmode = false,
        ?string $gradetype = null
    ): ?array {
        $payload = [
            'course_name'     => $coursename,
            'quizzes'         => $quizzes,
            'colorblind_mode' => $colorblindmode,
        ];
        if ($gradetype !== null) {
            $payload['grade_type'] = $gradetype;
        }
        return $this->post('/analyze-course', $payload);
    }

    /**
     * Cheap Solution Process Visualization metadata (question/part/student
     * lists) for populating the selector form.
     *
     * @param string $quizname
     * @param array  $records
     * @return array|null
     */
    public function solution_process_meta(string $quizname, array $records): ?array {
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
    public function solution_process_analyze(
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
     * GET /report-sections/{kind} — the section names available for one PDF
     * kind, driving the "Generate PDF Report" checkbox list so it can never
     * drift from what the PDF route actually includes. Returns [] on any
     * failure (the form still works, it just renders with no checkboxes).
     *
     * @param string $kind 'question', 'solutionprocess', or 'quiz'
     * @return string[]
     */
    public function report_sections(string $kind): array {
        $config = get_config('local_quizanalytics');
        $base = !empty($config->apibaseurl) ? rtrim($config->apibaseurl, '/') : 'http://127.0.0.1:8600';

        $curl = curl_init($base . '/report-sections/' . $kind);
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
     * Downloads raw PDF bytes from any /pdf/* endpoint. Shared by the three
     * download_pdf_*() methods below — the request shape differs per kind,
     * but the transport (POST JSON, expect application/pdf back) is identical.
     *
     * @param string $endpointpath e.g. '/pdf/question', '/pdf/solution-process', '/pdf/quiz'
     * @param array  $payload
     * @return string|null Raw PDF bytes, or null on any failure.
     */
    protected function post_pdf(string $endpointpath, array $payload): ?string {
        $config = get_config('local_quizanalytics');
        $base = !empty($config->apibaseurl) ? rtrim($config->apibaseurl, '/') : 'http://127.0.0.1:8600';
        $timeout = !empty($config->apipdftimeout) ? (int) $config->apipdftimeout : 90;

        $curl = curl_init($base . $endpointpath);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
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
            debugging('local_quizanalytics: PDF request failed (' . $endpointpath . '): HTTP ' . $httpcode . ' ' .
                $curlerror, DEBUG_DEVELOPER);
            return null;
        }
        return $response;
    }

    /**
     * Question Analytics PDF for one quiz.
     *
     * @param string $quizname
     * @param array  $records
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @return string|null
     */
    public function download_pdf_question(
        string $quizname,
        array $records,
        ?array $selectedsections,
        bool $colorblindmode = false
    ): ?string {
        return $this->post_pdf('/pdf/question', [
            'quiz_name'         => $quizname,
            'records'           => $records,
            'selected_sections' => $selectedsections,
            'colorblind_mode'   => $colorblindmode,
        ]);
    }

    /**
     * Solution Process Visualization PDF for one (quiz, question, part).
     *
     * @param string $quizname
     * @param array  $records
     * @param string $question
     * @param int    $partindex
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @return string|null
     */
    public function download_pdf_solutionprocess(
        string $quizname,
        array $records,
        string $question,
        int $partindex,
        ?array $selectedsections,
        bool $colorblindmode = false
    ): ?string {
        return $this->post_pdf('/pdf/solution-process', [
            'quiz_name'         => $quizname,
            'records'           => $records,
            'question'          => $question,
            'part_index'        => $partindex,
            'selected_sections' => $selectedsections,
            'colorblind_mode'   => $colorblindmode,
        ]);
    }

    /**
     * Cross-quiz Quiz Analysis PDF for the course-wide view.
     *
     * @param string $coursename
     * @param array  $quizzes [quiz_name => records[]]
     * @param array|null $selectedsections
     * @param bool   $colorblindmode
     * @return string|null
     */
    public function download_pdf_quiz(
        string $coursename,
        array $quizzes,
        ?array $selectedsections,
        bool $colorblindmode = false
    ): ?string {
        return $this->post_pdf('/pdf/quiz', [
            'course_name'       => $coursename,
            'quizzes'           => $quizzes,
            'selected_sections' => $selectedsections,
            'colorblind_mode'   => $colorblindmode,
        ]);
    }
}
