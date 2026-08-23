# Session notes — 2026-08-19 → 2026-08-20

A working log of everything done and discovered across this multi-day
session, across plugin naming, a parsing bug, the Cloudflare 524 fix,
local dev environment setup, the large-course performance investigation,
and three further follow-up rounds (cron reliability/host-adaptive sizing/
50-quiz stress testing; a scoping audit that surfaced and fixed a severe
Model Analytics performance bug plus two real CI failures; and a UI/UX and
bug-fix pass across readability, copy, chart ordering, and PDF export).
Written after the fact as a reference — see the actual commits (`git log`) for the
authoritative, tested detail on each change; this is the narrative/context
around them, including things that were tried, discovered, or ruled out
that don't show up in a diff.

## Contents

1. [Display name renamed twice](#1-display-name-renamed-twice)
2. [Testing the Analytics API models (Model 1/2)](#2-testing-the-analytics-api-models-model-12)
3. [Named STACK input parsing bug (`ans_mcq`, `ans_1fx`, ...)](#3-named-stack-input-parsing-bug-ans_mcq-ans_1fx-)
4. [Cloudflare 524 fix for large courses (first pass)](#4-cloudflare-524-fix-for-large-courses-first-pass)
5. [Git history rewrite for sole authorship](#5-git-history-rewrite-for-sole-authorship)
6. [Local dev environment: live-mounting, php.ini, Docker rebuild](#6-local-dev-environment-live-mounting-phpini-docker-rebuild)
7. [Restoring real course data: the async-restore one-shot trap](#7-restoring-real-course-data-the-async-restore-one-shot-trap)
8. [The large-course performance investigation](#8-the-large-course-performance-investigation)
9. [Final state (first round)](#9-final-state-first-round)
10. [Follow-up round 1: cron reliability, host-adaptive sizing, 50-quiz stress test](#10-follow-up-round-1-cron-reliability-host-adaptive-sizing-50-quiz-stress-test)
11. [Follow-up round 2: scoping audit, Model Analytics performance bug, CI fixes](#11-follow-up-round-2-scoping-audit-model-analytics-performance-bug-ci-fixes)
12. [Follow-up round 3: readability, copy, chart ordering, PDF/UI polish](#12-follow-up-round-3-readability-copy-chart-ordering-pdfui-polish)

---

## 1. Display name renamed twice

- `pluginname` (drives the Site administration nav entry, page headings,
  PDF titles) was originally **"STACK Quiz & Model Analytics"**.
- First renamed to **"STACK Analytics"** (matching the plugin's actual
  Moodle Marketplace listing title, confirmed against
  `marketplace.moodle.com/plugins/3995` — a mismatch discovered mid-session
  that also explained why an earlier component rename to
  `local_stackanalytics` had been rejected by the Marketplace uploader).
- Later renamed again to **"STACK q-type Analytics"** per explicit
  instruction. Both passes updated every live echo of the name — lang
  strings, PDF report headings/authors, the privacy metadata string, the
  scheduled task's own name, and the current copy in `README.md`/
  `INSTALL.md`/`MARKETPLACE_LISTING.md` — while deliberately leaving
  historical CHANGELOG/marketplace-notes narrative describing what the
  name *was* at an earlier point in time untouched, matching this
  project's own established convention for prior renames.
- Along the way, found and fixed a couple of spots still stuck on the
  *original* pre-any-rename name that had never been updated in either
  earlier pass (`README.md`'s title, `version.php`'s docblock, the privacy
  string).

## 2. Testing the Analytics API models (Model 1/2)

Explained (no code changes) how to actually exercise this plugin's two
Analytics API models:

- Both ship **disabled by default** (`db/analytics.php`) — a newly
  installed site shouldn't start generating predictions/notifications
  before an admin has reviewed them.
- Model 1 (`student_at_risk`) samples **course enrolments**, extends core's
  `course_gradetopass`, needs a *finished* course with a grade-to-pass set
  and STACK activity.
- Model 2 (`question_needs_review`) samples **quiz_slots** (one STACK
  question-in-a-quiz), doesn't need the course to be finished.
- **`Evaluate`** runs cross-validation against the site's own real
  historical data — no upload needed. The `onlycli` analytics setting
  (`Site administration → Analytics → Analytics settings`) restricts
  `Evaluate`/`Get predictions`/`Log` to CLI/cron only; when it's on, those
  actions are simply missing from a model's Actions menu, with a banner
  explaining why.
- **Correction made during this session**: originally suggested
  `php admin/tool/task/cli/schedule_task.php` for forcing a task run from
  CLI — that path doesn't exist in real Moodle core. The correct path,
  confirmed by grepping a real Moodle 4.5 checkout, is
  `php admin/cli/scheduled_task.php --execute=...` (and
  `admin/cli/adhoc_task.php` for adhoc tasks). `INSTALL.md` was corrected
  to match.
- Real errors seen while testing on live data, explained:
  - Model 1 "There is not enough data to evaluate this model using the
    provided analysis interval" — traced to `mlbackend_php`'s own
    `processor.php`: fires when a target class (0 or 1) has fewer than 2
    samples, i.e. a class-balance problem, not literally "not enough data."
  - Model 2 "Analysable ... is not valid for this target: The course is
    too long" — traced to core's `course_enrolments` target base class
    (`course/classes/analytics/target/course_enrolments.php`), which
    rejects a course whose end date is more than a year + 4 weeks after
    its start — this check only applies to Model 1's ancestry
    (`course_gradetopass` extends `course_enrolments`), not Model 2's
    target class at all, so the two error reports were about two different
    models despite how the user described them.

## 3. Named STACK input parsing bug (`ans_mcq`, `ans_1fx`, ...)

**Symptom** (from a live screenshot): a question's error drill-down showed
every response as **blank** despite a real, correctly-computed score
(0.6), and the question's raw response text clearly wasn't empty.

**Root cause**: `classes/quiz/analytics/parser.php`,
`prt_analysis.php`, `latex_utils.php`, and `solution_distance.php` all
matched STACK's `ansK: <expr> [tag]` response fields with a regex hardcoded
to a **numeric-only** suffix (`ans\d+`). STACK questions can rename their
inputs to anything (`ans_mcq`, `ans_1fx`, ...) — this plugin already
accounted for the equivalent case on the PRT-name side, just not for
inputs. A named input:

- Never matched the `parser.php` regex, so the response was treated as
  having zero ans fields — indistinguishable from genuinely blank.
- Also never matched `prt_analysis.php`'s `ANS_FIELD_RE` (used to exclude
  ans fields from its by-elimination PRT detection), so it fell through
  and got counted as a **fake PRT**, always scored 0.0/incorrect —
  polluting the per-PRT pass-rate table too.

**Fix**: broadened `ans\d+` → `ans\w+` in all four locations.
`parser.php`'s `ans_list` entries now carry `'index' => null` for a
non-numeric input name rather than an incorrect `(int)` cast colliding
everything on 0 (the one consumer keying off a numeric index —
`solution_distance.php`'s TED lookup — already treats a missing index as
"no match"). Verified against the exact response text from the reporting
screenshot: correctly parses all 7 named ans fields, identifies the 5 real
PRTs, and reproduces the observed 0.6 score with status `incorrect`
instead of `blank`. Added `tests/parser_named_ans_test.php`.

## 4. Cloudflare 524 fix for large courses (first pass)

**Symptom**: Quiz/Question Analytics timing out (Cloudflare 524) on a
course with several hundred finished attempts.

**Root cause found by profiling, not assumed**: `data_fetcher.php`'s
`get_response_records_for_quiz()` called
`question_engine::load_questions_usage_by_activity()` (singular) once per
finished attempt — each call its own set of DB queries. At 500-1000
attempts that's 500-1000x the round trips. `local_quizanalytics/
computetimelimit` (PHP's own execution-time limit) couldn't have fixed
this even at any value, since it only raises PHP's own limit, not an
independent reverse-proxy timeout sitting in front of it.

**Fix**: switched to
`question_engine_data_mapper::load_questions_usages_by_activity()`
(plural) — one batched query across every attempt's usage at once, the
same fix Moodle core's own mod_quiz "Responses" report uses for this exact
problem (`quiz_first_or_all_responses_table::load_extra_data()`, confirmed
against a real Moodle 4.5 checkout). Verified byte-identical output
against the old per-attempt code on real data before shipping it.

Two complementary fixes shipped alongside:

- **`ignore_user_abort(true)`** around each cache-miss compute in
  `index.php`/`questionanalytics.php`: without it, a request that gets
  524'd mid-compute has its PHP process killed by the client disconnect
  before ever reaching the line that populates the cache — meaning every
  subsequent visitor re-triggers the same expensive path forever, even
  after the fix above. With it, the work finishes server-side regardless
  of whether the original visitor is still there to see it.
- **`classes/task/warm_analytics_cache.php`** (first version): a new
  scheduled task that proactively warms the course-wide and per-quiz
  caches for every STACK course, so a real visitor essentially never has
  to eat a cold compute at all. Scoped to the default view only
  (colorblind/anonymize off) — a visitor with a personal preference
  toggled still computes cold on their own next visit, an accepted gap.

This was version [2.3.2] (originally numbered 2.4.0, renumbered down since
it's a patch on the 2.3.x line, not new user-facing functionality).

## 5. Git history rewrite for sole authorship

Three already-pushed commits from this session had carried a
`Co-Authored-By: Claude` trailer. Per explicit instruction ("ALWAYS make
me the sole author"), rewrote them:

- Used `git commit-tree` (not `filter-branch`/`rebase -i`) to reconstruct
  each commit with its exact original tree/author/dates, just with the
  trailer stripped from the message — verified via `git diff <old> <new>`
  showing **zero** file-content difference for each pair before updating
  the branch pointer.
- Force-pushed only after explicit confirmation (per the git safety
  protocol: never force-push without asking first).
- Saved a standing memory (`feedback_sole_git_author.md`) so this applies
  automatically to every future commit in this repo without being asked
  again.

## 6. Local dev environment: live-mounting, php.ini, Docker rebuild

The local Docker Moodle stack (`~/Desktop/moodle`, separate from this
plugin's own git repo) needed several changes to support real iterative
development and, later, the parallel-fetch work:

- **Live-mounted the plugin repo directly** over `local/quizanalytics` in
  `docker-compose.yml`, replacing what had been a disconnected, manually
  `docker cp`'d copy. Edits to the real repo now show up in the running
  site on refresh with zero manual sync step.
- **`php.ini` limits raised** (`Dockerfile.moodle`'s baked-in
  `moodle.ini`): `upload_max_filesize=200M`, `post_max_size=220M`,
  `max_execution_time=300`, `max_input_time=300`, `memory_limit=512M` —
  needed for uploading real, large (~100MB) course backup files through
  the browser.
- **Added `pcntl`** to the PHP build (`docker-php-ext-install ... pcntl`)
  — required for the forked-worker-pool parallel fetch (§8); not present
  in the base `php:8.2-apache` image at all.
- **Fixed JIT/opcache being effectively inert**: `opcache.jit_buffer_size`
  was `0` despite `opcache.jit=tracing` looking "on" — JIT needs a
  non-zero buffer to actually compile anything. Also `opcache.enable_cli`
  was `Off`, meaning CLI-run scripts (including the scheduled task this
  whole session ended up optimizing) got zero opcode caching, paying full
  recompilation cost on every invocation. Both fixed.
- Reset the local admin password via `admin/cli/reset_password.php`
  (didn't know it) to get a real authenticated browser session for
  testing, rather than fighting curl-based login/session cookies.

## 7. Restoring real course data: the async-restore one-shot trap

Getting real, large (~50k-attempt) course data onto the local instance
from `.mbz` backups surfaced a genuinely confusing failure mode worth
recording:

- The local container had **no cron running at all** (no crontab, no cron
  daemon) — queued `asynchronous_restore_task` adhoc tasks just sat there
  indefinitely with no error, looking "stuck" when they were actually just
  never being processed. Fixed by manually running
  `admin/cli/adhoc_task.php --execute`.
- **Killed several restores prematurely** early on, not yet knowing each
  one genuinely needs ~20 minutes uninterrupted for a course this size —
  learned this the hard way, several times, before adopting a strict
  "always wait in the background with no artificial timeout, never
  interrupt" discipline for anything long-running for the rest of the
  session.
- **The costly discovery**: `\core\task\asynchronous_restore_task::
  retry_until_success()` returns `false` — confirmed directly in Moodle
  core source
  (`lib/classes/task/asynchronous_restore_task.php`). This means the task
  gets **exactly one attempt, ever** (`attemptsavailable` forced to `1` in
  `manager.php`); any interruption — including everything above —
  permanently and silently kills that specific queued restore. There is no
  way to resume it; only a fresh restore (new upload, new backupid) works.
  One restore that *was* left to run uninterrupted (task 15, 1235
  seconds/~20.5 min) succeeded cleanly, confirming the mechanism itself
  was never broken — only repeated interruption was.
- Net result: of an original 5 queued restore attempts (across 3 uploaded
  backup files), most were permanently lost to this before the pattern was
  understood; the affected courses were successfully re-restored from
  scratch afterward. Final real courses used for the rest of the session:
  course 9 (MT171 2023/2024, the one profiled most heavily — **38 STACK
  quizzes, 48,445 finished attempts combined**), plus 10/11/12 (smaller).

## 8. The large-course performance investigation

By far the largest single piece of work this session. Full detail and
exact code is in the commits (`c0ca160` through `5ed80ca`); this is the
narrative of how the investigation actually unfolded, including dead ends.

### 8.1 First symptom, and the wrong initial theory

Reported symptom: Quiz/Question Analytics on the real 38-quiz course took
close to a minute to "load," then rendered **nothing** — no charts, no
error message, just the page shell. Model Analytics silently bounced back
to Quiz Analytics; Diagnostics Analytics landed on Course reuse.

Initial theory (wrong, but reasonable given the code at the time):
`get_response_summary()`/`get_right_answer_summary()` — assumed these
re-ran live CAS/PRT grading on every call, based on reading
`qtype_stack_question::summarise_response()` → `get_prt_result()`, which
genuinely does call into CAS. This was **disproven** by tracing one level
deeper: `question_attempt::get_response_summary()` is just
`return $this->responsesummary;` — a plain property read, populated once
from a stored DB column (`question_attempts.responsesummary`) when the
attempt is hydrated, not recomputed on access. The real cost was
elsewhere (§8.3).

### 8.2 Profiling method

Rather than keep guessing, added direct `microtime()`/
`memory_get_usage()` instrumentation around each stage, run against the
real course via CLI (the local Docker container has a live DB with the
real 48,445-attempt data — see §7). Key numbers, all measured directly,
not estimated:

- **Fetch stage** (`get_response_records_for_quiz()`) for one 2,764-attempt
  quiz: **46.05s**.
- **Entire analysis pipeline** (`question_analysis::build_analysis()` —
  parsing, PRT frame, difficulty, response outcomes, ranking, everything)
  for the *same* quiz: **~2-4s total**.

This one comparison redirected the whole investigation: the fetch stage
was the story, by 10-20x, and `tree_edit_distance.php`/PRT-comparison
code (which the user had specifically flagged as suspect) turned out to
be unreachable from this code path at all — confirmed by grep — only ever
called from Solution Process Visualization.

### 8.3 Finding the real cost inside the fetch stage

Instrumented each of the four per-slot calls in
`get_response_records_for_quiz()` separately:

| Call | Time (300 attempts) | Per-call |
|---|---|---|
| `$quba->get_question($slot)` | 1.269s | **4.23ms** |
| `get_response_summary()` | 0.002s | 0.0067ms |
| `get_right_answer_summary()` | 0.002s | 0.0058ms |
| marks (x2) | 0.004s | 0.0127ms |

`get_question($slot)` — instantiating the STACK question object for that
attempt's specific random seed, real CAS work — was **~99.5%** of the
whole per-attempt-slot cost. Re-reading the plugin's own code at this
point found the actual fix: `$question` (the return value of
`get_question()`) is used for exactly one thing in the whole method —
`render_stack_question_text($question)` — which was *already* memoized
per slot from an earlier pass this same session. The expensive call was
being made unconditionally, every attempt, even when its only consumer
was about to be thrown away. Moving the `get_question()` call inside the
`if (empty($questiontextbyslot[$qnum]))` guard removed the redundant
instantiation entirely. Verified byte-identical output before/after;
measured a 1,455-attempt quiz go from 8.04s → 4.23s from this alone.

### 8.4 The garbage-collector cliff

Separately, running the *course-wide* fetch (looping over all 38 quizzes
in one PHP process) showed something stranger: quiz timings that had
nothing to do with quiz size. One 705-attempt quiz took **461.85 seconds**
— dramatically slower than a 924-attempt quiz processed earlier in the
same run (22.27s). Isolating that same 705-attempt quiz in its own,
fresh process: **0.02s** (essentially instant, confirming it wasn't the
quiz's own data at fault).

Root cause: PHP's automatic cyclic garbage collector — needed because
`question_usage_by_activity`/`question_attempt`/`question_attempt_step`
objects hold internal reference cycles refcounting alone can't free — was
falling further behind the more quizzes got processed sequentially in one
run, and each of its automatic sweeps got more expensive to walk as more
cyclic garbage piled up uncollected. Empirically confirmed by testing two
variants on the same quiz sequence:

- `gc_disable()`: eliminated the slowdown, but memory then grew
  unboundedly (1850MB after only 7 of 11 quizzes) until the script
  crashed — trading the CPU problem for a worse memory problem.
- Explicit `gc_collect_cycles()` after each quiz: the previously-461.85s
  quiz dropped to **16.66s** (~28x), a previously-229.3s quiz dropped to
  **9.82s** (~23x), and memory stayed bounded (~844MB peak across the
  whole 11-quiz run) rather than climbing.

Shipped the explicit `gc_collect_cycles()` version.

### 8.5 Deciding on parallelization (option B)

At this point the fetch stage was much faster per-quiz, but a
*course-wide* view combining all 38 quizzes was still bound by every
attempt's CAS grading happening strictly sequentially in one process —
despite the local Docker stack's Maxima backend (`goemaxima`,
`GOEMAXIMA_QUEUE_LEN: 32`) sitting almost entirely idle the whole time,
since nothing had ever asked it to do more than one thing at once. Given
explicit direction to focus on parallelizing the CAS-bound work (rather
than, say, a fully async/background-task UI rewrite, or trying to
memoize the CAS grading itself, which turned out to already be a
non-issue per §8.1's correction), built `classes/task/
parallel_course_fetcher.php`: forks up to N worker processes (new
`parallelworkers` setting), each running the existing, completely
unmodified `get_response_records_for_quiz()` for its assigned quizzes.

Deliberately **not** applied to the synchronous on-demand pages
(`index.php`/`questionanalytics.php`) — `pcntl_fork()` only works in
CLI/cron context, so this only ever runs inside the `warm_analytics_cache`
scheduled task. The on-demand path still fetches serially on a cold
cache, same as before, an accepted/documented gap.

### 8.6 Getting the fork mechanism actually correct at scale

The first working version passed correctness testing at small scale
(4 quizzes, byte-identical serial-vs-parallel output) but failed
repeatedly once run against the real, full 38-quiz course — three
separate, genuinely different failure modes, each found only by running
the real task end to end, not by any smaller benchmark:

1. **"MySQL server has gone away"** on the *parent's* connection, well
   inside MariaDB's 8-hour `wait_timeout` (checked directly — not a
   timeout at all). Diagnosed by tracing `moodle_database::__destruct()`
   → `dispose()`: a forked child's *inherited* connection object — even
   after the child creates and switches to its own fresh one — still gets
   destructed at some point in the child's own lifetime, and that
   destructor closes the connection on the socket the parent duplicated
   via `fork()` and is still actively using. Fixed by having the **parent**
   dispose its own connection *before* forking (so nobody has it open at
   the instant `fork()` actually runs), then reconnecting fresh in every
   child and the parent afterward.
2. **Memory exhaustion**: a worker drawing several of the course's largest
   quizzes (some 2,000-2,700+ attempts each) held all of their combined,
   *finished* records in memory simultaneously before writing anything
   out. Fixed by rewriting the write path to stream each quiz's records to
   its temp file (a simple length-prefixed binary format — plain
   newline-delimited text wasn't safe, since response text can contain
   arbitrary bytes including newlines) and free them immediately after,
   bounding peak memory to roughly one quiz's worth rather than a whole
   worker's chunk.
3. **Still-unbounded memory growth**, even after (2) — traced via direct
   `memory_get_usage()` instrumentation per quiz within a worker to
   Moodle's own `core_questiondata` MUC cache: it accumulates every
   distinct question a process ever loads for the rest of that process's
   life. A reasonable assumption for a short web request; not for a CLI
   worker that can touch hundreds of distinct STACK questions across many
   quizzes. Fixed by calling `cache_helper::purge_by_definition('core',
   'questiondata')` after each quiz — confirmed directly that memory then
   plateaus instead of climbing for the rest of the run.
4. Along the way, an initial "just make it unlimited" fix
   (`ini_set('memory_limit', -1)`) was tried and then explicitly reverted:
   confirmed directly that with no PHP-level ceiling, multiple concurrent
   workers exceeded the *host's* real available memory before PHP would
   ever object, at which point the Linux kernel's OOM killer started
   choosing what to sacrifice — observed taking out this task's own worker
   processes with no catchable error (`SIGKILL`, no PHP fatal message at
   all), and on a busier/shared host it could just as easily have picked
   MariaDB or Maxima instead. Replaced with a bounded, admin-configurable
   `parallelworkermemory` setting (default 2048M) — a real PHP "Allowed
   memory size exhausted" fatal is self-contained and reported; an
   OOM-killed process is neither.

### 8.7 Final verified result

Running the actual `warm_analytics_cache` scheduled task, unmodified, via
CLI, against the real course:

- Fully cold cache warm of all 38 quizzes plus the course-wide view:
  **526.8 seconds** (workers=2, worker memory=3072M — the configuration
  that worked reliably on this specific, memory-constrained local Docker
  host; see the note in §9 about sizing for a real production host).
- Compare: the *unfixed* serial fetch, given 28 minutes, had processed 13
  of 38 quizzes — extrapolated, well over an hour for the full course.
- Confirmed via direct cache inspection afterward: all 38 per-quiz caches
  and the course-wide cache genuinely warm (not just "the task didn't
  throw").
- Byte-identical serial-vs-parallel output reverified after every change
  to the fork mechanism in this section, each time, on a small real
  sample.

### 8.8 What wasn't resolved

- A second `warm_analytics_cache` run immediately after a confirmed-fully-warm
  cache took ~180s rather than being near-instant, despite direct
  inspection showing every relevant cache entry already warm. Likely the
  per-quiz/per-course cache-fingerprint check itself (a `COUNT`/`MAX`/`SUM`
  query per quiz, run for every course on the site) rather than a genuine
  recompute, but not root-caused.
- No isolated JIT-only before/after benchmark was produced, despite
  fixing the JIT config (§6) — the investigation kept surfacing real
  correctness bugs that needed fixing before any number was trustworthy,
  and time ran out before circling back.
- `tree_edit_distance.php`'s string-key-to-integer-key optimization
  (confirmed not on this course's critical path, but fixed anyway since
  explicitly requested) has no dedicated automated test — the change
  itself is a mechanical, behavior-preserving storage-layout refactor,
  reviewed carefully but not covered by a new test.
- Sub-30-second cold-start for a course this size was the original
  target and is **not achievable** — CAS grading is fundamentally
  per-attempt work; parallelism buys roughly linear speedup with worker
  count against a host's real available resources, not a constant-time
  answer regardless of course size.

## 9. Final state (first round)

- 7 commits this session (`c0ca160` → `5ed80ca`), all sole-authored, all
  pushed to `origin/main`.
- Plugin version bumped to **2.4.0** (`version.php`, `CHANGELOG.md`).
- New admin settings: `local_quizanalytics/parallelworkers` (default 4),
  `local_quizanalytics/parallelworkermemory` (default 2048, in MB) — both
  need sizing against whatever host actually runs the scheduled task; the
  defaults are a reasonable starting point for typical dedicated hosting,
  not tuned to this session's specific 7.75GB-shared local Docker VM
  (which needed workers=2/memory=3072M to succeed reliably at the full
  48k-attempt scale — left configured that way on the local test site
  intentionally, since it's what's actually proven to work there).
- New file: `classes/task/parallel_course_fetcher.php`.
- New test: `tests/parallel_course_fetcher_test.php`.
- Local Docker Moodle environment: `pcntl` extension added, JIT/opcache
  fixed, plugin repo live-mounted, admin password reset to a known value
  for testing, debug logging left off (production-like default) after
  being toggled on/off repeatedly for diagnostics during the session.

## 10. Follow-up round 1: cron reliability, host-adaptive sizing, 50-quiz stress test

A second, much larger round of work, working through a prioritized list of
eight items (0-7). Full detail is in the commits (`45a0c4c` through
`edecefb`); summarized here.

### 10.0 Verify and push

Discovered the previous round's 4 commits had never actually reached
`origin/main` — local only. Pushed immediately; confirmed clean afterward.

### 10.1 The stuck "generating in the background" page

Confirmed the user's own theory directly: this local Docker environment had
*no cron daemon at all* — a real `task_adhoc` row sat queued forever. But
running it by hand surfaced two further, independent real bugs that cron
alone would never have fixed:

- `warm_single_view_adhoc_task`'s course-wide path called the plain serial
  fetch with no memory_limit raise and no parallelization — a real 38-quiz
  course died silently at PHP's default 512M CLI limit (exit 255, no
  visible error at that specific margin).
- Running under *real* Moodle cron (not just standalone CLI scripts)
  surfaced a shutdown-function inheritance bug across `pcntl_fork()`: a
  forked child's plain `exit()` re-runs PHP's whole shutdown sequence,
  including a cron lock's auto-release handler the child inherited but
  never itself acquired, using a `$DB` reference the child had already
  reconnected out from under — cascading into the scheduled task itself
  being marked failed. Fixed by having a child SIGKILL itself instead,
  skipping the inherited shutdown sequence entirely.

Added a `moodle-cron` service to the local Docker `docker-compose.yml`
(this environment had none), a cron-health banner on the plugin's own
settings page (reusing core's `tool_task\check\cronrunning`), and honest
staleness detection for the "generating" notice (15-minute flat cap, past
which it says the task looks stuck rather than repeating the calm message
forever). Dedup logic audited directly (dispatch the same view 5x, hit two
other views, inspect `task_adhoc`) and confirmed already correct.

### 10.2 Confirmed already-correct scoping

Question Analytics and Model 2 were already fetching/computing only the
selected quiz, not the whole course — no code change needed. (Revisited,
and found a real problem in a *different* place, in round 2 below.)

### 10.3-10.4 Fixed-number thresholds replaced with host-measured ones

The on-demand background-compute threshold was a fixed attempt count.
Replaced with `data_fetcher.php`'s `estimate_seconds_per_attempt()` — times
a real ~100-attempt sample on the actual quiz/host and extrapolates,
verified directly to correctly reverse the old threshold's blind spot (a
slow complex quiz with fewer attempts now defers; a fast simple quiz with
more attempts doesn't). `resource_detector.php` detects CPU/RAM (cgroup
first, `/proc` fallback) and recommends `parallelworkers`, wired into
`db/install.php`/`db/upgrade.php` (without overwriting an admin's own
already-tuned value) with a live "Detected resources" readout + "Re-detect"
button on the settings page.

### 10.5 Stress test: 50 quizzes × 1,000 students (50,000 attempts)

Generated by reusing a small, fixed pool of 10 STACK variants per attempt
(measured ~10-13x faster than fresh variants each time — appropriate here
since this test is about fetch/cache/task-layer scaling, not grading
diversity, already verified separately) — 19.4 minutes for the full
50,000-attempt dataset.

Surfaced the single most important bug of this round: an earlier attempt
(within *this same* round, in 10.1's cron-lock fix) to remove
`parallel_course_fetcher::fetch()`'s pre-fork `$DB->dispose()` step broke
down specifically at this larger scale — "MySQL server has gone away" on
the *parent's* own connection, invisible at real 38-quiz scale but
reliably reproducible at 50. Reproduced down to a fast 6-quiz/3-worker
case; confirmed specifically fork-related via an A/B check against
`workers=1` (no forking — stays healthy). Tried a Reflection-based fix
first (mark a child's inherited connection as already-disposed so its
eventual destructor is a no-op) — verified the Reflection call itself
worked, but the corruption persisted anyway, meaning that theory of the
mechanism was wrong (likely an OS/TCP-level file-descriptor-sharing effect
from forking with a live connection at all, not specifically a PHP
destructor/close() call — not fully pinned down). Reverted to disposing
the parent's connection before forking, proven reliable across this
project's entire testing history including this stress test after the
revert; documented the real remaining tradeoff (the cron lock's own
reference can still go stale, showing this task as "failed" in Moodle's
task log despite the work having actually succeeded) rather than pretend
it doesn't exist.

Final numbers at this scale: cold cron warm 293.7s (peak 2.3GB), on-demand
single quiz 0.6s, on-demand course-wide 20.3s — all comfortably under a
100s reverse-proxy timeout. One calibration gap found and documented, not
hidden: the sampled course-wide time estimate (13.8s) underestimated the
real course-wide time (20.3s) — the per-attempt-rate model doesn't account
for fixed per-quiz overhead, which becomes material at 50 quizzes. Both
numbers stay well clear of the actual 100s timeout, so this is a minor
internal-threshold note, not a safety gap.

### 10.6 PDF export redundant re-fetch

Investigated before building either of the two originally-proposed UX
options (grey out the button, or a background-task+ready-link flow) —
traced that the "Download PDF" button is only ever reachable *after* the
on-screen view has already computed successfully (its own chart images are
captured from the already-rendered page), so gating the button on the
threshold would rarely trigger. The real, still-live problem was different:
`questionanalyticspdf.php` unconditionally re-fetched every raw record from
scratch on every click, even moments after the same records had just been
fetched for the page the button appears on. Fixed with a new, short-lived
(300s) raw-record cache shared between the two routes — verified
byte-identical output, cache hit dropped from several seconds to 0.00s.

### 10.7 Documentation

README.md/INSTALL.md updated with deployment/sizing guidance, a "how do I
know it's working" checklist, and the tested-scale ceiling stated
explicitly. Released as **2.4.1**.

## 11. Follow-up round 2: scoping audit, Model Analytics performance bug, CI fixes

Prompted by two things: a screenshot of Moodle's own "Reactive instances"
developer-debug footer appearing on a real page (traced to a debug flag
this session had left on from 10.1's cron-check diagnostics and forgotten
to reset — turned off, and this alone likely explains why Question
Analytics had seemed to stop loading, since debug-level SQL trace output on
a page issuing many queries can make a browser choke), and a request to
verify every one of the four sections is actually scoped to only the
course/quiz selected, not silently computing for everything.

### 11.1 Scoping verification, live

- **Quiz Analytics** (`index.php`) — already course-scoped only. ✓.
- **Question Analytics** — already quiz-scoped only (re-confirmed in code,
  then live: loaded in ~1s for a real course once the stray debug flag was
  off). ✓.
- **Model 2** — already quiz-scoped when a quiz is selected, with its own
  row cap. ✓.
- **Model 1** — correctly course-scoped *by design* (its indicators are
  per-student; there's no quiz to scope it to) — but found something much
  more serious while checking it (11.2 below).
- **Diagnostics Analytics** — course/quiz-scoped correctly; 11.6s for a
  real 38-quiz course is the natural cost of ~100 genuine per-slot
  computations (each row's own queries are scoped to a *different*
  quiz/question, unlike Model 1's pattern below), not a bug — no fix
  needed.

### 11.2 Model 1 taking 5+ minutes on a real course

`modelanalytics.php?id=9` timed out an entire 3-minute test batch on its
own. Direct CLI timing: `model1_report::build(9)` took **299.4 seconds** on
the real 38-quiz/1,147-student course — effectively unusable, and a
genuinely different problem from scoping (the code already caps rows at
100 students).

Root cause: `model1_report::build()` calls 5 indicators once per student
row (up to 100). Two of `stack_attempt_reader`'s own query methods several
of those indicators go through are *course-wide* —
`get_course_step_deltas()` (no userid filter at all), and
`get_resource_access_timestamps()`/`get_stack_failure_events()` when
`help_seeking_gap` calls them with `$userid` left null (the course-wide
baseline population) — meaning the exact same expensive multi-table join,
returning an identical result every time within one report, was being
re-run in full once per student, up to 100 times over. A second, smaller
redundancy: `disengagement_entropy` and `feedback_revision_distance` both
independently call `get_attempt_step_sequences()` for the same student.

Fixed with a per-process memoization layer inside `stack_attempt_reader`
itself, keyed on each method's own call parameters — deliberately not
touching the 5 indicator classes' `compute_for_sample()` signatures, since
that's the Analytics API's own contract (real predictions call these too,
not just this plugin's dashboard). Verified: **299.4s → 16.8s (~18x)** on
the same real course, live-confirmed via an actual authenticated page load
(17.5s, status 200, correct content), and byte-identical output against the
pre-fix version across 19 checks including two consecutive calls to the
same course-wide method to directly confirm the memo returns a consistent
result on a cache hit, not just matching old behaviour once.

### 11.3 Two real GitHub Actions CI failures fixed

Confirmed directly from the actual failed-run output the user provided (a
screenshot of the GitHub Actions log), not guessed:

- `db/upgrade.php`'s version-guarded block never called
  `upgrade_plugin_savepoint()` — `moodle-plugin-ci`'s own "Check upgrade
  savepoints" step flags this by design. Added the missing call.
- `tests/data_fetcher_variant_test.php` used `$CFG->dirroot` at the top of
  the file without `global $CFG;` first — works when a file runs as the
  true top-level script, but PHPUnit's own test loader `require_once()`s
  test files from inside one of its own methods, so the included file's
  top-level code executes in *that* method's local scope, not the true
  global scope. `$CFG` was undefined, crashing every PHPUnit job in the CI
  matrix (3 PHP versions × 2 databases = the "six failing tests") before a
  single test could run. Fixed with an explicit `global $CFG;`, verified by
  simulating PHPUnit's own inclusion pattern locally.
- Also removed a stray `#[\Override]` attribute (PHP 8.3+ only) found via a
  defensive scan of this round's changed files — the CI matrix tests PHP
  8.1 too, and this repo doesn't use that attribute anywhere else.

Not independently re-verified against a live GitHub Actions run as of this
writing (no `gh` authentication available in this environment) — the fixes
are verified as correct against the exact reported error text and via local
simulation, but the next real CI run is what confirms all 6 matrix jobs
actually go green.

## 12. Follow-up round 3: readability, copy, chart ordering, PDF/UI polish

Prompted by two screenshots: a multi-part STACK question's text rendering
as one dense unbroken paragraph with stray literal `[[validation:ansN]]`
markup visible, and the Right Answer/error drill-down sections cramming
every `ansN` value onto one semicolon-joined line. Turned into a broader
pass across readability, copy, a real chart-ordering bug, PDF export, and
cross-section UI consistency, done and pushed in six phases (2.4.3-2.4.7,
plus one 2.4.4 copy-only phase).

### 12.1 Multi-part question text and answer readability (2.4.3)

Traced the full pipeline from raw STACK question HTML through to the
browser: the shared `sections-renderer.js` assigns question/answer text via
`innerHTML`, so a real `<br>` tag — not a bare `\n`, which a browser just
collapses as whitespace — is what actually produces a visible line break.
Found and fixed three real bugs in `classes/quiz/analytics/`, all verified
against a real 14-part definite-integral question matching the reported
screenshots:

- `parser::clean_html_text()` stripped every HTML tag down to a single
  space, throwing away a multi-part question's own `<br>`/`<p>`/`<table>`
  structure and collapsing the whole question into one unbroken run of
  text. Rewrote to preserve those boundaries as real `<br>` breaks, using a
  `\x02` placeholder (not whitespace, so it survives the later
  whitespace-collapsing pass) converted to `<br>` right before returning.
- `latex_utils::strip_stack_input_placeholders()` only stripped a
  `[[validation:ansN]]` tag when it sat immediately next to its own
  `[[input:ansN]]` — real STACK authoring often puts other content between
  the two (closing math delimiters, a unit label), so those orphaned tags
  survived as literal text in the rendered question, exactly what the
  screenshot showed. Fixed with a second, unconditional strip pass — which
  in turn left dangling runs of now-empty `<br>` tags where a
  `[[validation:...]]`/`[[feedback:...]]` tag had been sitting between two
  HTML boundaries and blocking `clean_html_text()`'s own collapse logic
  from combining them; added a follow-up collapse pass here too, and a
  `&nbsp;`-to-space fold in `clean_html_text()` (PHP's default `\s` never
  matches `U+00A0`, so a WYSIWYG editor's "empty" trailing `<p>&nbsp;</p>`
  survived every collapse untouched).
- `latex_utils::extract_stack_answer_latex()` joined a question's multiple
  `ansN` values with `"; "` on one line — hard to scan for a dozen-plus
  parts. Changed to one `ansN` per line, fixing both the Right Answer
  section and every row of the error drill-down table (both route through
  this one function).

### 12.2 Copy cleanup and anonymize default (2.4.4)

Rewrote the exact `computingnotice` wording per explicit instruction (an
em-dash/semicolon run-on → four plain sentences), then swept every other
user-facing string in the lang file for the same pattern — an em-dash or
semicolon bolting a second clause onto the first — and rewrote each as
separate sentences, or a colon for short label/value pairs (status badges,
PDF titles) where splitting didn't read naturally. Left genuine hyphenated
compound words (drill-down, cache-warming, on-demand) and breadcrumb "→"
separators alone; this was about run-on clause-joining, not hyphens as
such. Separately checked whether "anonymize student data" defaults to on
anywhere, per the request to turn it off across the board — it already
defaulted to `false` in every code path (`resolve_anonymize_mode()`, Model
Analytics, Diagnostics Analytics, all three PDF endpoints), so nothing
needed changing there; likely a stale per-user preference on the live
install rather than a code default.

### 12.3 Course-wide charts plotting quizzes out of order (2.4.5)

Real bug, confirmed against real data before and after: the course-wide
view's charts 3-6 (box plot, engagement, scatter, line graph) plotted
quizzes alphabetically instead of chronologically, so "Quiz 10" sorted
before "Quiz 2". Two layers:

- `data_fetcher::get_course_stack_quizzes()`'s SQL used `ORDER BY
  quiz.name`. Changed to `COALESCE(NULLIF(quiz.timeopen, 0), cm.added)`
  (falling back to the course module's own creation time when no open date
  is set), with `quiz.name` as a tie-break for genuinely same-timestamp
  quizzes.
- Even with the query fixed, `quiz_metrics.php`'s own chart-building
  functions independently re-sorted their quiz name lists alphabetically
  before plotting — a deliberate leftover from porting the original
  Python's pandas `groupby(sort=True)` default. Removed those internal
  `sort()` calls so they keep the chronological order the data already
  arrives in (first-appearance order in `$attemptframe`, which follows
  `course_analysis.php`'s own iteration order).

Verified end-to-end: fetched a real course's quiz list and ran it through
the actual `course_analysis::build_analysis()` pipeline, confirming the
boxplot/scatter chart trace order and the trend table's row order matched
the corrected chronological input, not alphabetical.

Also checked the "doesn't have to recompute each time" half of the same
request: Question Analytics' existing `questionanalysis` MUC cache was
already working as designed — purged it, then measured a cold compute at
3.67s and a repeat cache hit at ~1ms on the same real quiz. No fix needed.

### 12.4 PDF download notice and large-course chart rendering (2.4.6)

Added a short "this may take a while for a large course" notice above the
download button on all four sections' PDF forms (both `render_pdf_form()`
implementations — `sections_output_helper`'s and `dashboard_renderer`'s).

Investigating "does the PDF render these charts correctly for large
courses" surfaced a genuine, unbounded scaling problem: the course-wide
"Line Graph of Various Metrics" chart's width grows ~220px per quiz with no
cap (`quiz_metrics::build_line_graph_figure()`), which on a large course:

- **On screen**, stretched the whole page horizontally instead of just the
  chart. Wrapped in the same horizontally-scrollable box already used for
  unusually tall charts (the Student Performance Matrix heatmap), triggered
  once a chart's declared width crosses 900px — confirmed this doesn't
  affect the PDF capture math, since `collectChartImages()` reads the inner
  chart div's own `offsetWidth`, unaffected by an ancestor's
  `overflow`/`max-width`.
- **In the PDF**, `pdf_builder.php` force-shrinks every chart to fit one
  portrait page's ~178mm usable width, which for a wide many-quiz chart
  shrank its per-quiz tick labels well past legible. A chart captured wider
  than 1400px now gets a dedicated landscape page instead (~36% more
  usable width on LETTER), with a fresh portrait page immediately after to
  restore orientation for whatever content follows — self-contained to
  `render_chart()`, not requiring every other `AddPage()` call site in the
  file to track orientation. The chart's title had to move to *after* the
  landscape switch too, or it would print alone at the bottom of the
  previous (portrait) page, disconnected from its own chart.

Verified against the real PDF builder, not just code reading: built a PDF
with one normal-width and one artificially wide (1800px) chart, then
regex-matched the raw PDF bytes' own `/MediaBox` entries — confirmed page 1
portrait (612×792pt), page 2 (the wide chart) landscape (792×612pt), page 3
back to portrait.

### 12.5 Cross-section UI consistency (2.4.7)

Compared all four sections' headers (course/quiz selectors, colorblind/
anonymize toggles, warnings) side by side. Quiz Analytics and Question
Analytics already agreed on order: selectors, then toggles, then the
section's own heading. Two real inconsistencies:

- Model Analytics' anonymize toggle was rendered *after* the Model 1
  heading and intro text, inside the Model 1-only content block — moving
  position (and disappearing entirely) depending which view (Model 1 vs
  Model 2) was selected. Moved to right after the View selector, before any
  section-specific content, still conditional on Model 1 (Model 2's table
  has no student names, so correctly shows no toggle at all).
- Model Analytics and Diagnostics Analytics never had the "this may take a
  while" flush-before-compute notice Quiz/Question Analytics already show
  on a cold-cache view — despite Model 1 alone measuring up to ~17s on a
  real 38-quiz course (see §11.2). Added the same notice (new
  `dashboard_renderer::flush_computing_notice()`, mirroring
  `sections_output_helper`'s own) before both `model1_report::build()` /
  `model2_report::build()` and `diagnostics_report::build()`.

Verified all three affected pages (Model 1, Model 2, Diagnostics) via a
real authenticated request against real course data — bootstrapped a
Moodle session as admin from a CLI script (`\core\session\manager::
set_user(get_admin())`, then `include()`d the page file directly with
`$_GET` populated) rather than just a syntax check, confirming no PHP
warnings/notices leaked into the output and the new elements render in the
intended position.

### 12.6 GitHub Actions PHPUnit fixes (2.4.8-2.4.9), header uniformity (2.4.10), and a real local-environment incident

Two further real GitHub Actions PHPUnit failures reported after 2.4.7, both
confirmed from the actual CI output:

- `qtype_stack`'s own `version.php` depends on four more plugins
  (`qbehaviour_adaptivemultipart`, `qbehaviour_dfexplicitvaildate`,
  `qbehaviour_dfcbmexplicitvaildate`, `qbank_importasversion`) never
  checked out by this repo's own workflow — confirmed against qtype_stack's
  own installation docs, all four repos' existence verified before adding
  them. Missing them surfaced as an unrelated-looking missing-file error.
- A second run then hit `data_fetcher_variant_test`'s own fixture sanity
  check: its two hardcoded seeds (2, 137) happened to instantiate identical
  text in that environment. Root cause confirmed directly (not assumed): a
  standalone script run against this project's own real question engine
  found only 8 distinct texts among 12 candidate seeds for `test1`'s
  `n : rand(5)+3; a : rand(5)+3` fixture — a real, if occasional, collision
  risk with only 25 possible combinations. Fixed by probing a candidate
  list for the second seed and keeping the first one that actually differs,
  rather than trusting one hardcoded pair.

Then a further round of UI polish (2.4.10, prompted by four fresh
screenshots comparing all four sections' headers side by side): unified
selector order (Course → Quiz → View → toggles, one per row), the quiz
selector's label ("Quiz:" everywhere, was "View a single quiz's analytics"
on Question Analytics), the toggle row's submit button ("Apply"
everywhere, was "View" on Model Analytics), merged Model/Diagnostics'
separate blue/yellow info boxes into one discreet uncolored `<details>`,
and unified the three different "may take a while" notice wordings around
one shared lead sentence.

**A real incident, worth recording so it doesn't happen again:** verifying
this round's changes involved running `admin/cli/purge_caches.php` in the
local dev container to confirm an updated lang string was actually
reflected. Every page load afterward silently crashed (empty 500, no PHP
error text at all — a genuine process-level failure, not a normal PHP
error) for the *entire site*, not just this plugin's own pages, including
plain `/login/index.php`. Root cause, found by bisecting Moodle's own
`lib/setup.php` line by line with `fwrite(STDERR, ...)` markers until the
crash point was isolated to `core_component`'s scan of the `local/`
plugin type: a stray `local/quizanalytics_bak/` directory — a full copy of
this plugin, made several hours earlier in this same session as a
before-I-sync-changes safety backup during PHPUnit verification work, and
never cleaned up — was still sitting inside Moodle's own `local/` plugins
directory. Since it carried the exact same `$plugin->component =
'local_quizanalytics'` as the real one, Moodle's plugin scanner was
discovering it as a colliding duplicate component on every single
bootstrap, corrupting that scan badly enough to crash the whole site, not
just something specific to this plugin's own pages. Fixed by deleting the
stray directory; the site recovered immediately with no data loss (the
database itself, in a separate container, was never touched). This dev
container turned out to be the same one shown in the user's own live
screenshots, not an isolated sandbox — a reminder that ad hoc backup
copies made inside a real plugin directory (`cp -r plugin plugin_bak`,
here) need to go somewhere Moodle's own component scanner will never see
them (e.g. outside `local/` entirely), or be deleted the moment they're no
longer needed, not left for "later."

### 12.7 Two follow-on incidents from the same root cause, and a chart-box width fix (2.4.12)

The version.php bumps across 2.4.3-2.4.11 were synced to the container's
files on every round, but the database's own stored plugin version was
never brought forward to match (no `admin/cli/upgrade.php` run in
between) — Moodle detected the mismatch and silently suspended cron
site-wide ("Moodle upgrade pending, cron execution suspended"), which
manifested as two separate-looking user reports:

- A background course-wide compute stuck "queued for 1 hour" with nothing
  ever processing it — cron wasn't running at all, so nothing was left to
  work through the adhoc task queue, regardless of which course dispatched
  to it.
- Smaller courses' Quiz Analytics page also failing to load — the same
  cause: any course whose analytics happened to get dispatched to the
  background queue (even briefly, under momentary host load) sat stuck
  right alongside the large ones.

Fixed by running `admin/cli/upgrade.php --non-interactive`, which brought
the database back in sync and let cron resume immediately; the existing
backlog (3 courses) cleared within minutes without further intervention.
One of those courses (54,894 attempts, the largest in the system) took
635s to compute and its task then logged as "failed" — traced this to an
*already-documented*, deliberate trade-off inside
`parallel_course_fetcher.php`'s own comments (a lock-release shutdown
function racing against the fork-safety reconnect, after the real
fetch/cache work has already completed) rather than a new bug; confirmed
directly that the result had, in fact, been cached correctly and the page
rendered the full result on the next load.

Two smaller, unrelated fixes landed in the same version:

- Quiz Analytics only showed its "may take a while" notice on an actual
  cache miss, unlike Model/Diagnostics Analytics (which always show it,
  having no cache at all) — moved to fire unconditionally, before the
  cache lookup, so it's reliably visible regardless of cache state like
  the other three sections.
- The horizontally-scrollable box added around an unusually wide chart
  (2.4.6, the course-wide "Line Graph of Various Metrics") was itself
  capped at a fixed 900px — narrower than every other element on the page,
  so its own bounding box visibly didn't line up with anything around it
  on a course wide enough to need the scroll box at all. Changed the
  wrapper's own width to 100% of its container (matching everything else
  on the page), with the wider chart still scrolling *inside* that box
  exactly as before.

### 12.8 Maturity bumped to stable, and the loading notice now self-removes (2.4.13)

After publishing to the Moodle Plugins directory, the user asked why the
new version wasn't showing up as an installable "available update" on
their production site's own Plugins overview page — not something
reproducible in this project's own dev environment, so answered from
Moodle's own documentation instead (confirmed via
https://docs.moodle.org/501/en/Available_update_notifications rather than
assumed): a site's own Update notifications maturity filter (Site
administration > Server > Update notifications) commonly excludes alpha
releases, and `version.php` had declared `MATURITY_ALPHA` this entire
project. Bumped to `MATURITY_STABLE`, a real statement of confidence
given how much real-course testing and how many rounds of real-world bug
fixes this plugin has been through since the original merge.

Also asked for the "may take a while" loading notice (shown across all
four sections since 2.4.7/2.4.10/2.4.12) to disappear once the real
results have actually rendered, rather than sitting on the page
indefinitely. Solved with a shared DOM id on the notice
(`sections_output_helper::LOADING_NOTICE_ID`, mirrored in
`dashboard_renderer.php` since Model/Diagnostics Analytics don't
otherwise depend on that class) and a tiny inline `<script>` echoed right
after each section's real results, never before — a `<script>` tag runs
synchronously the instant the browser's own HTML parser reaches it, so by
construction everything printed earlier in the response, notice included,
is already in the DOM by the time it runs. No event listeners, load-state
tracking, or changes to the existing JS renderer's own entry point needed.
Verified for all four sections (including both Model 1/Model 2 and both
Question Analytics/Solution Process Visualization) via a real
authenticated request, checking the actual byte offsets of the notice and
the hide-script in the raw HTML response to confirm the ordering, and
confirming the hide-script is a safe no-op on a cache hit where no notice
was ever shown in the first place.

### 12.9 A real scoring bug, and background-page/spacing follow-ups (2.4.14-2.4.15)

The user reported, and directly verified against Moodle's own grades,
that "percent correct" and "average correct rate" were badly wrong on
several quizzes/courses — some questions showing a flat 100% incorrect on
the Response Outcome Percentages chart and 0% pass rate on the PRT
Heatmap. This was the single most serious finding of the whole session:
a real, live correctness bug in analytics teachers would reasonably trust
as ground truth.

Root-caused against real production data (quiz 154, "Quiz 0: Typing STACK
Answers", course 9) rather than assumed — a general-purpose subagent's
initial investigation correctly flagged the right FILES and the right
general SHAPE of bug (silent fallback-to-wrong parsing) but got the
specific mechanism wrong when checked against live data; that agent's
report is preserved in this transcript as a reminder that "plausible and
well-argued" isn't the same as "verified," even from a careful read of
the code alone. The real, confirmed causes:

1. **Never-attempted questions counted as failed, not excluded.**
   `response_analysis.php`/`difficulty.php`/`question_metrics.php` each
   divided a correct-count by *every* Pool B response for a question,
   including 'blank' (nobody had attempted it) and 'ungraded' (STACK
   re-validated after scoring, no PRT result survives). A question nobody
   had reached landed on a flat 0% facility — indistinguishable from
   everyone genuinely failing. Added `parser::is_graded_response()`
   (true only for 'correct'/'incorrect') as the shared denominator filter
   across all three files; zero graded responses now shows 0%/0% (two
   empty bars, asserting nothing) instead of the previous, false 0%/100%.
   `compute_question_summary()`'s own average_score/average_correct_rate
   inherited this fix automatically, since both are just a mean of every
   question's own (now-correct) number.
2. **A STACK input named bare `ans` (no suffix at all) wasn't recognized
   as an answer field**, in both `parser.php` and `prt_analysis.php` — both
   regexes required *at least one* character after "ans" (`\w+`). Real data
   confirmed this exists (`"ans: sin(2*x) [score]"`): the response
   misclassified as blank in one file, and misread as a bogus failed PRT
   node (falling through to prt_analysis.php's own "unrecognized value"
   catch-all) in the other, for every response to that question. Both
   regexes changed to `\w*`.

Verified end to end: traced raw `response_N` text for both bug patterns
directly from the database, confirmed the fix's effect on real per-
question numbers (quiz 154's Q10/Q13 went from an artificial ~0% facility
to their real ~99-100%, Q1/Q3/Q12 — genuinely, completely unattempted —
correctly went from a false 100% incorrect to an honest 0%/0%), and
scanned every quiz across four real courses afterward confirming no
question still landed on the impossible flat 0%/100% state.

Three smaller items from the same report:

- **Large-course pages sometimes appearing to never load.** Traced to the
  existing "computed in the background" page having no auto-refresh at
  all — a visitor had to remember to manually reload it themselves, which
  the user reported not reliably doing ("sometimes... nothing loads" until
  clicking back in). Added a plain `<meta http-equiv="refresh"
  content="20">` (matching this plugin's own no-JS-needed convention) plus
  a small CSS spinner, only for a task still working normally — a
  genuinely stuck task (past the existing 15-minute staleness threshold)
  keeps its plain, non-refreshing admin-troubleshooting message instead,
  since auto-refreshing a loop of "still not done" wouldn't help there.
- **A table squeezed with no gap against whatever rendered right after
  it.** `wrapScrollable()`'s wrapper `<div>` sets `overflow`, which forms
  its own block-formatting context — this silently traps the wrapped
  table's own bottom margin instead of letting it collapse outward the
  normal way, leaving genuinely zero visible space afterward. Given the
  wrapper its own explicit margin instead of relying on the table's.
- **A chart's legend colliding with its own hover tooltip.** Plotly's
  default legend position (top-right, just outside the plot) is the same
  spot a hover tooltip for a bar near the top of the y-axis renders —
  visible on a chart with many questions along the x-axis. Moved
  `build_grouped_bar_figure()`'s legend to a horizontal strip below the
  chart instead.
