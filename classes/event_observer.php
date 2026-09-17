<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** Marks affected reusable quiz preparation data stale after quiz activity. */
class local_quizanalytics_event_observer {
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$attempt || empty($attempt->quiz)) {
            return;
        }
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], 'id,course');
        if (!$quiz) {
            return;
        }
        // warm_single_view_adhoc_task is namespaced (PSR-4-autoloaded) —
        // unlike prepared_store below, it must never be require_once()'d by
        // path. Its own file starts with several top-level
        // require_once($CFG->dirroot . ...) lines that rely on $CFG being
        // a real global; require_once()'d from inside this method (as it
        // used to be here), those lines run in *this method's* local scope,
        // where $CFG was never declared global, throwing an "Undefined
        // variable $CFG" warning partway through the file — which, under
        // PHPUnit's --fail-on-warning, aborts the require before the class
        // itself is ever defined, and PHP's own require_once bookkeeping
        // still marks that path "already included", so the class then
        // silently never loads even on this event's next occurrence
        // (confirmed directly: a later, unrelated test failed with "Class
        // ...warm_single_view_adhoc_task not found" from this exact code
        // path). Moodle's autoloader loads the file correctly on its own
        // the moment the namespaced call below actually runs, with $CFG
        // already available the way its own top-level requires expect.
        require_once(__DIR__ . '/quiz/prepared_store.php');
        local_quizanalytics_prepared_store::mark_stale((int) $quiz->course, (int) $quiz->id, 'course');
        local_quizanalytics_prepared_store::mark_stale((int) $quiz->course, (int) $quiz->id, 'question');
        \local_quizanalytics\task\warm_single_view_adhoc_task::dispatch_next_question_quiz_for_course(
            (int) $quiz->course
        );
    }
}
