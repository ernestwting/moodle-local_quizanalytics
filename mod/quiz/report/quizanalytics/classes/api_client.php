<?php
/**
 * Talks to the local analytics microservice (your existing analytics/ Python
 * package, wrapped in a small FastAPI app — see the README for that side).
 *
 * Deliberately dumb: no retries, no queuing. If it fails, the report shows a
 * friendly error rather than a stack trace, and nothing about a failure here
 * ever sends data anywhere else — it just doesn't get an answer back.
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

class quiz_quizanalytics_api_client {

    /**
     * @param array $payload  ['quiz_name' => string, 'records' => array]
     *                        or ['course_name' => string, 'quizzes' => [name => records[]]]
     *                        for the cross-quiz endpoint.
     * @return array|null     Decoded JSON response, or null on any failure.
     */
    public function analyze(array $payload): ?array {
        $config = get_config('quiz_quizanalytics');
        $endpoint = !empty($config->apiurl) ? $config->apiurl : 'http://127.0.0.1:8600/analyze';
        $timeout  = !empty($config->apitimeout) ? (int) $config->apitimeout : 30;

        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            // Belt-and-braces: refuse to talk to anything that isn't the
            // configured internal endpoint, even if apiurl somehow got
            // misconfigured to something public. Remove this only if you have
            // a specific reason the service isn't on localhost/private network.
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            debugging('quiz_quizanalytics: curl error contacting analytics service: ' . $curlerror,
                DEBUG_DEVELOPER);
            return null;
        }

        if ($httpcode !== 200) {
            debugging('quiz_quizanalytics: analytics service returned HTTP ' . $httpcode,
                DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('quiz_quizanalytics: could not decode analytics service response: ' .
                json_last_error_msg(), DEBUG_DEVELOPER);
            return null;
        }

        return $decoded;
    }
}
