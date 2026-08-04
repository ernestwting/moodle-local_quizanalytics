<?php
/**
 * Cheap SQL fingerprints used to key the cache areas defined in db/caches.php,
 * so a cached result is only ever served while it still matches the current
 * DB state — new attempts, finished attempts, and regrades (which change
 * sumgrades) all naturally bust the cache; a manual per-question mark
 * override without a full regrade does not change sumgrades and so won't
 * bust the cache until the 1-hour TTL — an accepted gap for v1.
 *
 * Deliberately just COUNT/MAX(timefinish)/SUM(sumgrades) rather than hashing
 * every attempt's full response data: the whole point is for this check to
 * be dramatically cheaper than the thing it's guarding (get_response_records()
 * calls question_engine::load_questions_usage_by_activity() once per
 * attempt, which is the actual expensive step).
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

class quiz_quizanalytics_cache_helper {

    /**
     * @param int[] $quizids
     * @return stdClass {count: int, fingerprint: string}
     */
    protected static function stats_for_quiz_ids(array $quizids): stdClass {
        global $DB;

        if (empty($quizids)) {
            return (object) ['count' => 0, 'fingerprint' => md5('empty')];
        }

        [$insql, $params] = $DB->get_in_or_equal($quizids);
        $row = $DB->get_record_sql(
            "SELECT COUNT(*) AS cnt, MAX(timefinish) AS maxfinish, SUM(sumgrades) AS sumgrades
               FROM {quiz_attempts}
              WHERE quiz $insql AND state = 'finished'",
            $params
        );

        $fingerprint = md5(
            ($row->cnt ?? 0) . '_' . ($row->maxfinish ?? '0') . '_' . ($row->sumgrades ?? '0')
        );

        return (object) ['count' => (int) $row->cnt, 'fingerprint' => $fingerprint];
    }

    /**
     * Fingerprint for one quiz's finished attempts — used by
     * quiz_quizanalytics's questionanalysis cache area and
     * quiz_solutionprocess's solutionprocessmeta/solutionprocess areas.
     *
     * @param stdClass $quiz row from mdl_quiz
     * @return stdClass {count: int, fingerprint: string}
     */
    public static function stats_for_quiz(stdClass $quiz): stdClass {
        return self::stats_for_quiz_ids([$quiz->id]);
    }

    /**
     * Fingerprint across every quiz in a set — used by
     * local_quizanalytics's quizanalysiscoursewide cache area, where the
     * course-wide result depends on the attempts of every STACK quiz in the
     * course at once.
     *
     * @param stdClass[] $quizzes rows from mdl_quiz (or anything with ->id)
     * @return stdClass {count: int, fingerprint: string}
     */
    public static function stats_for_quizzes(array $quizzes): stdClass {
        return self::stats_for_quiz_ids(array_map(fn($q) => $q->id, $quizzes));
    }

    /**
     * Builds a cache key safe for a `simplekeys => true` MUC cache area
     * (alphanumeric-only) from an arbitrary list of key parts.
     *
     * @param mixed ...$parts
     * @return string
     */
    public static function build_key(...$parts): string {
        return md5(implode('|', array_map(
            fn($p) => $p === null ? '' : (string) $p,
            $parts
        )));
    }
}
