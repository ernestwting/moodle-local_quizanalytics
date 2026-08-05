<?php
/**
 * MUC cache area definitions for local_quizanalytics.
 *
 * Every area is keyed on a cheap SQL fingerprint of the underlying attempts
 * (see classes/cache_helper.php), not a fixed TTL alone: a cache entry is
 * only ever served when the fingerprint still matches the current DB state,
 * so new/regraded attempts are reflected immediately rather than waiting out
 * the TTL. The TTL here is only a backstop against unbounded growth for
 * quizzes that are no longer being actively looked at.
 *
 * simplekeys: every key this plugin builds is an md5() hex string (see
 * cache_helper.php), which satisfies MUC's simple-key charset.
 * simpledata: false, since every area stores a decoded JSON array (the
 * {summary, sections, ...} API response), not a scalar.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // The Question Analytics /analyze result for one quiz.
    'questionanalysis' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The cheap /solution-process/meta result for one quiz (question/part/
    // student lists for the selector form).
    'solutionprocessmeta' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The /solution-process result for one (quiz, question, part, student,
    // colorblind) selection — by far the most expensive of the four (tree
    // edit distance, 3D figures, network graphs), so the one caching
    // benefits the most.
    'solutionprocess' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

    // The /analyze-course result for one course.
    'quizanalysiscoursewide' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'simplekeys'  => true,
        'simpledata'  => false,
        'staticacceleration' => true,
        'ttl'         => 3600,
    ],

];
