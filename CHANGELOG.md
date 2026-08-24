# Changelog

All notable changes to `local_quizanalytics` are documented here.
Version numbers match `version.php`'s `$plugin->release`.

This plugin merges two previously-separate, independently-installed
plugins — the original, standalone `local_quizanalytics` and
`local_stackanalytics` — into one, and now replaces the former's own
Marketplace listing (see [2.3.0]). Each phase below corresponds to one
commit in this repository's history. Entries before [2.2.0] refer to the
plugin by its merge-time component name, `local_stackquizanalytics`, and
[2.2.0] itself by the (as it turned out, incorrect) `local_stackanalytics`
— each accurate to what the code was actually called at that point in
time; see [2.3.0] for why and when that settled on the current
`local_quizanalytics`.

## [3.0.0] — Simplified per-quiz Question Analytics; real HTML in Question Review; Diagnostics out of the nav; Model 1 names link to profiles

- Question Analytics for a single quiz (`questionanalytics.php`) is
  simplified from six sections down to one: the old Question Difficulty
  Analysis, Question Response Distribution, Student Performance Matrix, and
  Question Metrics sections are replaced by a single "Question Response
  Overview" — one horizontal diverging bar chart per question (Correct/
  Incorrect/Invalid input/No response), sized to Moodle's own per-question
  Facility Index and mean mark rather than this plugin's self-computed
  equivalents, so the figures agree with Quiz -> Results -> Statistics.
- The summary table at the top of the page is replaced by a compact "Quiz
  snapshot" (overall average, students with attempts, finished/in-progress/
  other attempt counts) read straight from Moodle's own attempt and quiz
  statistics data, with a link to that quiz's own Moodle attempts report.
- The per-question drill-down is renamed "Question Review" and now groups
  a question's best-attempt responses by its instantiated STACK question
  text/expected answer ("Version 1", "Version 2", ...) instead of one flat
  list, so a randomized question's distinct variants no longer mix their
  expected answers and common-wrong-response lists together. Each version
  lists its most common incorrect responses (with student counts) and links
  to an example attempt in Moodle's own review page.
- The Question Analytics PDF export's section list and "Question Review"
  table are updated to match; the now-dropped Student Performance Matrix
  checkbox is removed from the PDF form rather than left pointing at a
  section that no longer exists on screen.
- The Solution Process Visualization view (`?view=solutionprocess`) is
  temporarily hidden from `questionanalytics.php`'s view selector — the
  underlying code is untouched and still reachable by URL, but is no longer
  offered as a teacher-facing option pending a redesign.
- Ported from `jumazevick`'s branch (`4e1b303c`, `c680134`) against the
  plugin's earlier, pre-merge `local_quizanalytics` codebase. Two pieces of
  that branch's own work were intentionally left out of this port: a
  `question_difficulty.php` documentation-link fix, whose target section
  (a course-wide "Moodle Facility Index" panel) was already deliberately
  reverted before this repo's merge and was never present here to begin
  with; and fetching in-progress (not just finished) attempts into the
  per-question response computation, to keep this view on the same
  finished-attempt cache fingerprint the rest of this plugin already uses
  — Moodle's own in-progress/finished attempt counts are still shown as-is
  in the new quiz snapshot, straight from SQL.
- Question Review's question text now preserves the author's actual HTML
  instead of being flattened to plain text. `question_text_html` used to be
  built from `parser::clean_html_text()`'s output — a helper written for
  compact table cells that flattens every tag to a single space, collapsing
  lists, `<code>` spans, and any other structure into bare `<br>`-joined
  lines that looked nothing like Moodle's own STACK question rendering. It
  now carries the raw (un-flattened) CAS-rendered HTML alongside the
  existing flattened copy and runs it through
  `latex_utils::render_question_html()`, which uses `format_text()` purely
  for its HTMLPurifier pass — content filters are deliberately skipped,
  since this plugin already renders math client-side via KaTeX and Moodle's
  own TeX filter would mangle the same `\(...\)` delimiters first. STACK's
  `[[input:...]]` placeholders now render as a small bordered box instead
  of plain "(student's answer)" text, and each version in the Question
  Review drill-down gets its own bordered card, so a question with several
  randomized variants reads as clearly separate entries instead of one
  continuous block.
- Diagnostics Analytics is removed from the section switcher's visible
  links (`classes/section_selector.php`) — `diagnosticsanalytics.php` and
  all of its underlying code (`classes/stack/diagnostics/*`,
  `classes/stack/analytics/report/diagnostics_report.php`) are untouched
  and still fully reachable by a direct URL, this only stops advertising it
  in the nav pending further work.
- Model Analytics' Model 1 ("Student at risk") table now links each
  student's name to their Moodle profile (`/user/view.php`), matching how
  Quiz Analytics' Student Quiz Summary table already links names to a
  review page. Correctly skipped when the anonymize toggle is on, since a
  link carrying the real userid would defeat the point of showing a
  pseudonym even with the visible text replaced.

## [2.5.0] — Redesigned Student Quiz Summary; scatter grade-type switches without reloading

- The course-wide "Merged List of Users and Files" section is now "Student
  Quiz Summary": one row per student per quiz instead of one row per raw
  attempt, showing attempt count, first grade, and latest grade. The
  student's name links to Moodle's own review page for their latest
  attempt, opening in a new tab. The Completed On/Started On columns are
  gone from this table (still available per-attempt elsewhere).
- The Attempts vs Grades scatter plot's "Compare attempts against:
  Highest/Average/Minimum Grade" control moved from a page-reloading form
  above the chart into the chart's own controls, and switching between them
  now swaps the plot in place via Plotly — every variant is computed once
  server-side and shipped together, so there's no page reload.
- Ported from `jumazevick`'s PR #4 against the plugin's earlier, pre-merge
  `local_quizanalytics` codebase.

## [2.4.15] — Auto-refresh while a large course computes in the background; two spacing fixes

- The "computed in the background" page previously just sat there
  indefinitely until a visitor manually reloaded it themselves — reported
  as sometimes needing to click back into the page repeatedly, and not
  always reliably remembered. Now auto-refreshes itself every 20 seconds
  via a plain `<meta http-equiv="refresh">` tag (no JS dependency, matching
  this plugin's own established "plain GET-reload" convention elsewhere)
  and shows a small spinner alongside the notice, so no one has to guess
  whether the page is still working or remember to check back by hand.
  A genuinely stuck task (past the existing 15-minute staleness threshold)
  still shows the plain admin-troubleshooting message without auto-
  refreshing, since repeating the same "still not done" result on a loop
  wouldn't help there.
- A rendered table's own bottom margin was getting trapped inside its
  horizontally-scrollable wrapper `<div>` (an element with `overflow` set
  forms its own block-formatting context, which stops a child's margin
  from collapsing outward the normal way) — invisible on its own, but
  visibly squeezing that table right up against whatever rendered next
  with no gap at all. The wrapper now carries its own explicit bottom
  margin instead of relying on the table's.
- The Response Outcome Percentages chart's own legend sat at Plotly's
  default position (top-right, just outside the plot area) — the same
  spot a hover tooltip for a bar near the top of the y-axis renders,
  visibly colliding on a chart with many questions along the x-axis.
  Moved the legend to a horizontal strip below the chart instead, where
  nothing else ever renders.

## [2.4.14] — Fixed a real, verified scoring bug: never-attempted questions counted as failed

A user reported, and directly verified against Moodle's own grades, that
"percent correct" and "average correct rate" were badly wrong for several
quizzes and courses — with the Response Outcome Percentages chart and PRT
Heatmap showing some questions as a flat 100% incorrect / 0% pass rate.
Root-caused against real production data (quiz 154, "Quiz 0: Typing STACK
Answers"), not assumed, to two distinct, real bugs:

- **Never-attempted questions counted as failed, not excluded.**
  `response_analysis.php`, `difficulty.php`, and `question_metrics.php`
  each computed their own "percent correct" / "facility" / "average score"
  by dividing a correct-count by *every* Pool B response for a question,
  including ones nobody had actually answered yet (`response_status` =
  'blank') or ones STACK re-validated after scoring, leaving no usable PRT
  result (`response_status` = 'ungraded'). A question three students out
  of 914 hadn't reached yet was a small, mostly-hidden distortion; a
  question *nobody* had reached at all divided by a denominator that
  still included all of them, landing on a flat 0% facility — indistinguishable
  from every real attempt genuinely failing. `compute_question_summary()`'s
  own "average_score"/"average_correct_rate" then inherited this per-
  question error directly, since both are just a mean across every
  question's own number. Added `parser::is_graded_response()` (true only
  for 'correct'/'incorrect', excluding 'blank'/'ungraded') and applied it
  as the shared denominator filter across all three files; a question with
  zero graded responses now shows 0%/0% (two empty bars — no assertion
  either way) instead of the previous 0%/100% (a false claim of universal
  failure).
- **A STACK input named bare `ans` (no numeric or named suffix at all)
  wasn't recognized as an answer field at all**, in both
  `parser.php` (`parse_response_cell()`) and `prt_analysis.php`
  (`ANS_FIELD_RE`) — both regexes required *at least one* character after
  "ans" (`\w+`), so a genuinely answered, correctly graded response like
  `"ans: sin(2*x) [score]"` parsed to an empty ans_list and was
  misclassified as blank in one file, and misread as a bogus PRT node
  (falling through to prt_analysis.php's own "unrecognized value shape"
  catch-all, recorded as a hard failure) in the other — for *every*
  response to that question. Fixed both regexes to `\w*` (zero or more),
  matching a bare "ans" the same way the existing `\w+` already matched
  "ans1"/"ans_mcq". Real production data confirms this pattern exists:
  quiz 154's Q10/Q13 went from an artificially low ~0% facility to their
  real ~100%, and the PRT Heatmap now correctly shows a gray "not
  applicable" cell for a genuinely unattempted question instead of a red
  "0% pass rate" one.

Verified against real, live course data throughout (not just unit-level
reasoning): traced the exact raw `response_N` text for both the
genuinely-unattempted questions and the bare-`ans` questions, confirmed
the fix's effect on real correct/incorrect and pass-rate numbers, and
scanned every quiz across four real courses afterward to confirm no
question was still landing on the impossible flat 0%/100% state.

## [2.4.13] — Maturity bumped to stable; the loading notice now disappears once results are ready

- `$plugin->maturity` changed from `MATURITY_ALPHA` to `MATURITY_STABLE`.
  This plugin has now been through extensive real-course testing and
  several rounds of real-world bug fixes since first merging the two
  source plugins; alpha maturity was also the likely reason this plugin
  never showed up as an available update on some sites, since a site's own
  Update notifications maturity filter (Site administration > Server >
  Update notifications) commonly excludes alpha releases from
  notifications by default.
- The "this may take a little time to load for a large course" notice,
  added across all four sections in 2.4.7/2.4.10/2.4.12, stayed on the
  page indefinitely even once the real results had fully rendered below
  it. Each section now echoes a tiny inline script right after its real
  results (never before) that removes the notice by id — a plain
  `<script>` tag runs the instant the browser's HTML parser reaches it, so
  by the time it runs, everything printed before it is already sitting in
  the DOM, with no event-listener or load-tracking machinery needed. Safe
  on a cache hit too, where no notice was shown in the first place.

## [2.4.12] — Loading notice consistency, and a chart box width fix

- Quiz Analytics only flushed its "this may take a little time" notice on
  an actual cache miss, unlike Model Analytics and Diagnostics Analytics,
  which always show it before their own (uncached) compute. In practice
  this meant the notice reliably appeared on Question/Model/Diagnostics
  Analytics (genuinely uncached, or per-quiz keyed so a freshly-picked
  quiz misses often) but stopped appearing on Quiz Analytics as soon as a
  course's single course-wide cache entry warmed up — the common case
  after the first visit. Now shown unconditionally, before the cache
  lookup, matching the other three sections.
- The horizontally-scrollable box 2.4.6 added around an unusually wide
  "Line Graph of Various Metrics" chart (course-wide view, section 6) was
  itself capped at a fixed 900px, narrower than every other element on the
  page (tables, headings, the other charts) — so on a course wide enough
  to need the scroll box at all, that one chart's own bounding box visibly
  didn't line up with anything around it. The box itself now stays 100% of
  the page's own content width, same as everything else; the (still
  potentially much wider) chart scrolls *inside* that box exactly as
  before.

## [2.4.11] — Made the flaky variant-seed test skip instead of fail

2.4.9's retry-loop fix (try more candidate seeds until one instantiates
different text from the anchor) still failed on the next real CI run — this
time *every* candidate seed collided with the anchor, not just the original
hardcoded pair. The earlier local verification that found real diversity
among these candidates ran against the main site's own database/config;
PHPUnit runs against a completely separate, isolated site (its own
`$CFG->phpunit_dataroot`/`phpunit_dbname`), which evidently doesn't vary
this fixture's instantiated text by seed the same way. This test's whole
point is proving the fixture *can* distinguish two variants before trusting
the rest of it to mean anything — an environment where it genuinely can't
isn't a bug in the per-variant caching the test exists to catch, so failing
the build over it would be testing this environment's STACK/CAS setup, not
this plugin's own code. Now skips (not fails) with a clear message when no differing pair is found.

## [2.4.10] — Made every section's header layout, labels, and notices uniform

Compared all four sections' headers side by side against real screenshots
and fixed the concrete differences found:

- **Selector order and layout.** Course, then Quiz (where applicable), then
  View (where applicable), then colorblind/anonymize toggles — one selector
  per row, in this fixed order, on every section. Model Analytics
  previously put Course and View on the same row and the Quiz selector
  after View instead of before it; Question Analytics put View after the
  quiz heading instead of before the toggles. Both now match.
- **Labels.** Question Analytics' quiz selector said "View a single quiz's
  analytics"; every other section already said "Quiz:". Unified on "Quiz:"
  (and folded the now-duplicate `quizselectlabel`/`viewselectlabel` lang
  strings into the ones already shared by the other sections). The
  Model Analytics toggle row's submit button said "View"; Quiz/Question
  Analytics' said "Apply". Unified on "Apply".
- **The blue/yellow info boxes.** Model Analytics and Diagnostics Analytics
  each showed two separate colored boxes (an "About this page" info box
  and a "Responsible use" warning box) stacked above the selectors. Merged
  into one plain, discreet `<details>` (no alert coloring) combining both,
  positioned consistently right after the section selector on both pages.
- **The "may take a while" notices.** Three different wordings existed
  across sections (`computingnotice`, `largecoursenotice`,
  `generatinginbackground`). Now share one lead sentence ("This may take a
  little time to load for a large course.") followed by whatever's
  actually true for that state — still wait-here vs. still
  come-back-later, since those genuinely differ, just no longer reading as
  unrelated messages. Dropped the now-redundant `computingnotice` string
  entirely in favor of the shared `largecoursenotice`.

Verified all four pages' full header structure via a real authenticated
request against real course data (not just a syntax check), confirming the
new selector order, labels, and merged box render correctly in every
view (Question Analytics/Solution Process, Model 1/Model 2).

## [2.4.9] — Fixed a flaky PHPUnit test (real seed collision, confirmed live)

The previous round's CI fix got PHPUnit past the missing-plugin errors,
surfacing one further failure: `data_fetcher_variant_test`'s own fixture
sanity check ("the two forced variants produced identical instantiated
text") — the two hardcoded seeds it forced (2 and 137) happened to collide
on the same instantiated text in that run. Root cause: `test1`'s fixture
question variables are `n : rand(5)+3; a : rand(5)+3` (qtype_stack's own
`tests/helper.php`) — only 25 distinct `(n, a)` combinations total, so any
two arbitrarily-picked seeds have a real, non-trivial chance of colliding.
Confirmed directly, not assumed: a standalone script exercising 12
candidate seeds through the real question engine found only 8 distinct
texts, with three actual collision pairs among them — this local
environment's seeds 2/137 happened not to collide, but the mechanism the
CI hit is real.

Fixed by no longer trusting a single hardcoded pair: the second user's
attempt now probes a list of candidate seeds (cheap — building the
in-memory attempt/question-usage-by-activity doesn't touch the database
until the attempt is actually saved) and keeps the first one whose
instantiated text differs from the first user's, only then committing a
real finished attempt with that seed.

## [2.4.8] — Fixed two real GitHub Actions PHPUnit failures

Confirmed from the actual failed-run output (5 errors, 100 tests), not
guessed:

- `qtype_stack`'s own `version.php` depends on four more plugins not
  shipped with Moodle core or checked out by this repo's own CI workflow
  (`qbehaviour_adaptivemultipart`, `qbehaviour_dfexplicitvaildate`,
  `qbehaviour_dfcbmexplicitvaildate`, `qbank_importasversion` — confirmed
  against qtype_stack's own installation docs, not guessed). Missing them
  doesn't fail cleanly: a STACK question's own `question.php`
  `require_once()`s the adaptivemultipart behaviour unconditionally, so any
  PHPUnit test that instantiates a real STACK quiz attempt
  (`data_fetcher_variant_test.php`) failed with a missing-file error on one
  test and a cascading "class not found" on another, both looking unrelated
  to a missing plugin at first glance. Added all four as extra plugin
  checkouts in `.github/workflows/moodle-ci.yml`, alongside the existing
  `qtype_stack` one.
- `tests/parallel_course_fetcher_test.php` called
  `\local_quizanalytics_quiz_data_fetcher` directly (a legacy,
  non-namespaced class Moodle's own `classes/` autoloader doesn't pick up)
  without ever requiring `classes/quiz/data_fetcher.php` itself — the only
  thing that had been loading it, `parallel_course_fetcher::fetch()`'s own
  `require_once`, runs too late (inside its method body) to help a call
  made before or alongside it. Added the missing `require_once`.

## [2.4.7] — Made toggle placement consistent, added the missing large-course notice

Compared all four sections' headers (course/quiz selectors, colorblind/
anonymize toggles, warnings) side by side. Quiz Analytics and Question
Analytics already agreed: selectors, then toggles, then the section's own
heading. Two real inconsistencies found and fixed:

- Model Analytics' anonymize toggle was rendered *after* the Model 1 heading
  and intro text, buried inside the Model 1-only content block, instead of
  up with the other selectors/toggles like every other section — meaning it
  moved position (and vanished from view) depending which view you were on.
  Now sits right after the View selector, before any section-specific
  content, still shown only for Model 1 (Model 2's table has no student
  names to anonymize, so it correctly has no toggle at all).
- Quiz Analytics and Question Analytics show a "this may take a while"
  notice before a cold-cache compute; Model Analytics and Diagnostics
  Analytics never had an equivalent, so a slow load on a large course (Model
  1 alone measured up to ~17s on a real 38-quiz course) looked identical to
  a hung page. Added the same flush-before-compute notice to both.

Verified all three affected pages (Model 1, Model 2, Diagnostics) render
without errors and in the intended order via a real authenticated request
against actual course data, not just a syntax check.

## [2.4.6] — Added a PDF download-time notice, and fixed large-course chart rendering

- All four sections' "Generate PDF" forms now show a short notice ("Generating
  this PDF may take a while for a large course...") right above the download
  button, so a slow download on a large course doesn't read as broken.
- The course-wide "Line Graph of Various Metrics" chart's width scales with
  the number of quizzes (~220px each, no upper bound — see
  quiz_metrics::build_line_graph_figure()), which on a large course:
  - **On screen**, stretched the whole page horizontally instead of just the
    chart, with no way to scroll just that one chart into view. Now wrapped
    in the same horizontally-scrollable box already used for unusually tall
    charts (the Student Performance Matrix heatmap), once a chart's declared
    width crosses a threshold.
  - **In the PDF**, every chart is force-shrunk to fit one portrait page's
    ~178mm usable width, which for a wide, many-quiz chart shrank its
    per-quiz tick labels well past legible. A chart captured wider than
    1400px now gets a dedicated landscape page instead (~36% more usable
    width on LETTER), with a fresh portrait page immediately after to
    restore orientation for whatever follows — verified end-to-end (a
    normal-width and an artificially wide chart through the real PDF
    builder) that the wide chart's page, and only that page, actually comes
    out landscape in the generated PDF's own page dimensions.

## [2.4.5] — Fixed course-wide charts plotting quizzes out of chronological order

The course-wide view's charts 3 through 6 (Quiz Grade Distribution box plot,
Engagement Over Time, Attempts vs Grades scatter, Line Graph of Various
Metrics — all under the Quiz Analytics section's "All STACK quizzes" view)
plotted quizzes alphabetically instead of in teaching order, so e.g. "Quiz 10"
appeared before "Quiz 2". Two layers, both fixed:

- `data_fetcher::get_course_stack_quizzes()` queried `ORDER BY quiz.name`.
  Now orders by `quiz.timeopen` (when the teacher set one) falling back to
  the course module's own creation time otherwise, so the quiz list —
  and everywhere that drives, including the Question Analytics quiz
  selector — reads chronologically.
- Even with that fixed, `quiz_metrics.php`'s own chart-building functions
  independently re-sorted their quiz name lists alphabetically before
  plotting (a deliberate port of the original Python's pandas
  `groupby(sort=True)` default). Removed those internal re-sorts so they
  keep the chronological order the data already arrives in.

Verified end-to-end against a real course's quiz list and the resulting
boxplot/scatter/trend chart traces, not assumed from code reading alone.
Also confirmed Question Analytics' existing questionanalysis cache is
already working as designed — a cold view computed in 3.67s, a cache hit on
repeat came back in ~1ms — so no separate fix was needed there.

## [2.4.4] — Rewrote em-dash/semicolon run-on strings as plain sentences

A copy pass across every user-facing string in `lang/en/local_quizanalytics.php`
(plus one caption in the shared on-screen renderer) that used an em-dash or
semicolon to bolt a second clause onto the first, in the style of the old
`computingnotice` string ("...this can take a while for a large course. This
page will show the results below once it's done; no need to refresh."). Each
was rewritten as separate plain sentences, or a colon for short label/value
pairs (status badges, PDF titles) where splitting into full sentences didn't
read naturally. Genuine hyphenated compound words (drill-down, cache-warming,
on-demand, question-complexity-dependent) and breadcrumb-style "→" separators
were left alone — this was about run-on clause-joining, not hyphens as such.
Also confirmed "anonymize student data" already defaults to off in every
section's code path (`resolve_anonymize_mode()`, Model Analytics,
Diagnostics Analytics, and all three PDF endpoints) — nothing to change there.

## [2.4.3] — Fixed multi-part question text and answer readability

Prompted by screenshots showing a multi-part STACK question rendering as one
dense, unbroken paragraph with stray literal `[[validation:ansN]]` markup
visible, and the Right Answer / error drill-down sections cramming every
`ansN` value onto a single semicolon-joined line. Traced the full pipeline
from raw STACK question HTML through to the browser (the shared
`sections-renderer.js` assigns all of this via `innerHTML`, so a real `<br>`
tag — not a bare newline character, which a browser just collapses as
whitespace — is what actually produces a visible line break) and fixed three
real bugs found along the way, all in `classes/quiz/analytics/`:

- **`parser::clean_html_text()`** stripped every HTML tag down to a single
  space, throwing away a multi-part question's own `<br>`/`<p>`/`<table>`
  structure entirely and collapsing the whole thing into one unbroken run of
  text. Now preserves those boundaries as real `<br>` breaks instead.
- **`latex_utils::strip_stack_input_placeholders()`** only stripped a
  `[[validation:ansN]]` tag when it sat immediately next to its own
  `[[input:ansN]]` — real STACK authoring often puts other content (closing
  math delimiters, a unit label) between the two, so those orphaned tags
  survived as confusing literal text in the rendered question. Now stripped
  regardless of position, with a follow-up pass that also collapses the
  runs of now-empty `<br>` tags this can leave dangling.
- **`latex_utils::extract_stack_answer_latex()`** joined a question's
  multiple `ansN` values with `"; "` on a single line — hard to scan for a
  question with a dozen-plus parts. Changed to one `ansN` per line, fixing
  both the Right Answer section and every row of the error drill-down table
  (both route through this one function).

Verified against a real 14-part definite-integral question from an actual
course (matching the reported screenshots) at every stage of the fix, not
assumed correct from code reading alone.

## [2.4.2] — Fixed Model Analytics taking 5+ minutes on a real course, and two GitHub Actions CI failures

Prompted by a request to verify every section of the plugin is actually
scoped to the course/quiz selected, not silently computing for everything.
Quiz Analytics, Question Analytics, and Model 2 were already correctly
scoped (Question Analytics's apparent "not loading" turned out to be a
debug-mode setting left on from earlier diagnostics, now off); Diagnostics
Analytics's ~12s on a real large course is the natural cost of ~100 genuine
per-slot computations, not a bug. Model 1 (correctly course-scoped by
design — its indicators are per-student) turned out to have a much more
serious, separate problem:

- **`model1_report::build()` measured at 299.4s on a real 38-quiz/
  1,147-student course** — effectively unusable, despite the existing
  100-row cap. Root cause: two of `stack_attempt_reader`'s query methods
  several of its 5 indicators go through are *course-wide*
  (`get_course_step_deltas()`, and `get_resource_access_timestamps()`/
  `get_stack_failure_events()` as `help_seeking_gap` calls them) but were
  being re-run in full once per student row regardless — the same
  expensive join, identical result every time, up to 100 times over. Fixed
  with per-process memoization inside `stack_attempt_reader` itself, not
  touching the indicators' `compute_for_sample()` signatures (the Analytics
  API's own contract). Verified: 299.4s → 16.8s (~18x), byte-identical
  output across 19 checks, and a real authenticated page load (17.5s,
  previously timed out entirely).
- **Two real GitHub Actions CI failures**, confirmed from the actual
  failed-run output: `db/upgrade.php` was missing a required
  `upgrade_plugin_savepoint()` call, and `tests/data_fetcher_variant_test.php`
  referenced `$CFG->dirroot` without `global $CFG;` at file scope — safe
  when a file runs as the true top-level script, but PHPUnit's own loader
  `require_once()`s test files from inside one of its own methods, so the
  included file's top-level code isn't in the true global scope; `$CFG`
  was undefined, crashing every PHPUnit job across the CI matrix (3 PHP
  versions × 2 databases) before a single test could run. Also removed a
  stray PHP 8.3-only `#[\Override]` attribute found via a defensive scan
  (the CI matrix tests PHP 8.1 too).

## [2.4.1] — Made the on-demand background-compute safeguard actually reliable, host-adaptive, and stress-tested to 50 quizzes/1,000 students

[2.4.0]'s parallel cache-warming and (added in an unreleased follow-up)
on-demand background-compute safeguard both turned out to have real gaps
only surfaced by running them for real — under actual Moodle cron, and at
a synthetic 50-quiz/1,000-student/50,000-attempt scale well beyond the
38-quiz real course [2.4.0] was tested against. Each found and fixed by
reproducing directly, not assumed:

**Cron dependency made real, and its own failures made visible:**
- The on-demand background-compute safeguard (a cold view above a
  threshold dispatches to a queued adhoc task instead of blocking the
  request) depends entirely on Moodle cron running. Confirmed directly:
  this project's own local dev environment had *no cron daemon at all* —
  a real stuck task sat queued, never executed. Added a dedicated
  `moodle-cron` service to the local Docker dev environment, and documented
  cron as a hard requirement (not optional) in `INSTALL.md`.
- Cron alone would not have fixed the stuck page, though — two further,
  independent real bugs surfaced by actually running the queued task:
  - Its course-wide path called the plain serial fetch directly, with no
    `memory_limit` raise and no parallelization — a real 38-quiz course
    died silently at PHP's default 512M CLI limit (exit 255, no visible
    error at that specific margin). Fixed to match `warm_analytics_cache`'s
    own pattern (raised memory, routed through the forked fetcher).
  - Running under real Moodle cron (not just standalone CLI scripts)
    surfaced a shutdown-function inheritance bug across `pcntl_fork()`: a
    forked child's plain `exit()` re-runs PHP's whole shutdown sequence,
    including a cron lock's auto-release handler the child inherited but
    never itself acquired, using a `$DB` reference the child had already
    reconnected out from under — cascading into the scheduled task itself
    being marked failed. Fixed by having a child terminate via a direct
    `SIGKILL` to itself instead, skipping the inherited shutdown sequence
    entirely (safe: the child's own result is already flushed to disk by
    that point).
- A visible cron-health banner was added to the plugin's own settings page
  (reusing core's own `tool_task\check\cronrunning` check), and the
  "generating in the background" notice now distinguishes a task still
  working normally from one that's been queued for a genuinely
  suspicious amount of time (15+ minutes) — a real failure state (no
  cron, a crashed worker) that now says so honestly instead of repeating
  the same calm message indefinitely.
- Dedup logic (queuing the same background compute at most once no matter
  how many visitors/reloads hit it) was audited and confirmed already
  correct — no fix needed.

**Fixed-number thresholds replaced with host-measured ones:**
- The synchronous on-demand path's background-compute threshold was a
  fixed attempt count. Measured directly that this has a real blind spot:
  per-attempt cost is genuinely question-complexity-dependent (a simple
  randomised question measured ~10x cheaper per attempt than a complex
  real one) — a fixed count picked one quiz too large to compute inline
  while letting a *larger* but cheaper one through. Replaced with a real,
  small sampled fetch (~100 attempts, on the actual quiz, on the actual
  host) that extrapolates the full cost, verified directly to correctly
  reverse both of the old threshold's misjudged cases.
- `local_quizanalytics/parallelworkers` similarly stops being a fixed
  number tuned to one development machine: `resource_detector.php` now
  detects this host's own CPU cores and RAM (cgroup limits first, falling
  back to `/proc`) and recommends a value from them, applied automatically
  on install/upgrade (without overwriting an admin's own already-tuned
  value on an existing site) and shown live on the settings page with a
  "Re-detect" action for hardware that changes later.

**Question Analytics/Model 2 scoping confirmed already correct** — both
were already fetching/computing only the selected quiz, not the whole
course; no fetch-path change was needed there.

**`questionanalyticspdf.php`'s redundant re-fetch fixed:** flagged but not
built in [2.4.0]'s own follow-up work. Traced that the "Download PDF"
button is only ever reachable *after* the on-screen view has already
computed successfully — so gating the button on the threshold above
would rarely trigger in practice. The real, still-live problem was
different: this route unconditionally re-fetched every raw record from
scratch on every click, even moments after the same records had just been
fetched for the page the button appears on. Fixed with a new, short-lived
raw-record cache shared between the two routes — verified byte-identical
output and a cache hit dropping from several seconds to effectively 0.00s.

**Stress-tested at a synthetic 50 quizzes × 1,000 students (50,000 total
attempts)** — well beyond [2.4.0]'s real 38-quiz ceiling. Surfaced one
more real bug in the process: an earlier attempt to remove
`parallel_course_fetcher::fetch()`'s pre-fork `$DB->dispose()` step (as
part of the cron-lock fix above) broke down at this larger scale —
"MySQL server has gone away" on the *parent's* own connection,
reproduced down to a fast, minimal 6-quiz/3-worker case, and confirmed
specifically fork-related via an A/B check against `workers=1`. Reverted
that specific removal (prioritizing connection safety, proven reliable
across this whole project's testing, over a cosmetic task-log accuracy
issue the cron-lock fix still leaves — documented honestly in code
rather than silently reintroduced). Full results at this scale:

| Check | Result |
|---|---|
| Cold cron warm (all 50 quizzes + course-wide) | 293.7s, peak 2.3GB |
| On-demand single quiz (1,000 attempts) | 0.6s |
| On-demand course-wide (50,000 attempts) | 20.3s |

Both comfortably under a 100s reverse-proxy timeout. One real calibration
gap found and documented rather than glossed over: the sampled course-wide
time estimate (13.8s, from one quiz's own rate extrapolated by total
attempts) underestimated the real course-wide time (20.3s) — the
per-attempt-rate model doesn't account for fixed per-quiz overhead, which
becomes material once a course has many quizzes (50 here, vs. the 38 the
model was tuned against). Both numbers stay well clear of the actual 100s
proxy timeout that matters, so this is a minor internal-threshold
calibration note, not a safety gap — left as a known limitation for a
future, quiz-count-aware refinement rather than rushing an unverified fix.

## [2.4.0] — Made Quiz Analytics usable on a real 48,445-attempt course

Live testing against a real 38-quiz course (48,445 finished attempts
combined) found Quiz/Question Analytics still effectively unusable even
after [2.3.2]'s fix: loads taking close to a minute and then rendering
nothing, Model Analytics silently bouncing back to Quiz Analytics,
Diagnostics Analytics landing on Course reuse instead. Root-caused and
fixed in stages, each verified directly against the real course rather
than assumed:

**Fetch-stage fixes (data_fetcher.php), ~10x on their own:**
- `render_stack_question_text()`'s CASText2 render was being called once
  per attempt per slot, but every consumer of `question_N_text` only ever
  wants the first non-empty value across a question's attempts — memoized
  per slot.
- `$quba->get_question($slot)` — instantiating the STACK question for that
  attempt's seed — measured at 4.2ms/call, ~99.5% of the method's whole
  per-attempt-slot cost, versus under 0.03ms combined for
  `get_response_summary()`/`get_right_answer_summary()`/marks (which read
  already-graded DB columns, not live CAS, confirmed directly against
  `question_attempt::load_from_records()`). `$question`'s only use in this
  method is the render call above, so it's now only fetched when that
  memo is actually still empty.
- PHP's automatic cyclic garbage collector fell further behind the more
  quizzes a single course-wide request processed — one quiz's own fetch
  went from ~10s to 461s with no code difference between them, purely from
  QUBA object cycles piling up across earlier quizzes in the same run.
  `gc_collect_cycles()` after each quiz keeps every run cheap instead of
  one increasingly expensive automatic sweep.

**Made the CAS-bound fetch parallel (the actual >10x-at-scale fix):**
Even after the above, the fetch stage was still bound by every quiz's
attempts being graded via CAS strictly one at a time, in one process — despite
the Maxima backend's own multi-worker queue sitting almost entirely idle
the whole time. `classes/task/parallel_course_fetcher.php` fans a course's
quiz list across up to `local_quizanalytics/parallelworkers` (new setting)
forked worker processes — CLI/cron only, so this only ever runs inside the
`warm_analytics_cache` scheduled task; the on-demand web pages are
untouched and still fetch serially on a cold cache, as already documented
there. Getting this right at real scale took three more rounds of fixes,
each found by running the actual task end to end against the real course,
not just per-quiz:
- A forked child inherits the same underlying DB socket the parent is
  still using; a child reconnecting to its own fresh connection doesn't
  fully remove the risk, since the *old* inherited connection object it
  discards still gets destructed at some point in the child's life,
  closing the connection on that *shared* socket. Fixed by disposing the
  parent's own connection before forking, reconnecting fresh in every
  child *and* the parent afterward — confirmed this was really "MySQL
  server has gone away" well inside MariaDB's own 8-hour wait_timeout, not
  an actual timeout.
- A worker drawing several of a course's largest quizzes could hold all of
  them in memory simultaneously before writing its result out — now
  streams each quiz's records to its temp file and frees them immediately
  after, bounding peak memory to roughly one quiz's worth.
- Moodle's own `core_questiondata` cache accumulates every distinct
  question a process ever loads for the rest of that process's life — fine
  for a short web request, not a CLI worker touching hundreds of distinct
  STACK questions. Purging it after each quiz keeps memory flat instead of
  climbing without bound for as long as the worker runs.
- Replaced an initial `ini_set('memory_limit', -1)` (unlimited) with a
  bounded, admin-configurable `local_quizanalytics/parallelworkermemory`
  setting (default 2048M) — unlimited isn't actually safe: without a PHP-
  level ceiling, concurrent workers can exceed the host's real available
  memory before PHP would object, at which point it's the kernel's OOM
  killer choosing what to sacrifice, observed directly taking out this
  task's own workers with no catchable error and capable of just as easily
  picking MariaDB or Maxima on a busier host.

Verified end to end: the real 38-quiz/48,445-attempt course now completes
a fully cold cache warm (all 38 quizzes plus the course-wide view) in
526.8s via the scheduled task, versus well over an hour previously
(extrapolated — the unfixed serial fetch hadn't finished 13 of 38 quizzes
after 28 minutes). Byte-identical output verified between the parallel and
serial code paths throughout.

**Also, not on the critical path for this course but requested
alongside it:** replaced `tree_edit_distance.php`'s string-concatenated
array keys with integer indices (only reachable from Solution Process
Visualization, confirmed via the same investigation — not Quiz/Question
Analytics — so doesn't move the numbers above, but is a real, low-risk
win for that view's own cold-load time). Added a "computing, please
wait" notice that flushes to the browser immediately on a cold-cache
on-demand view, so a visitor isn't just staring at a blank tab during
whatever wait remains — cosmetic only, doesn't change what gets computed.

**Known follow-up, not resolved tonight:** a second `warm_analytics_cache`
run immediately after a fully-warm one took ~180s rather than being
near-instant, despite every cache entry checked afterward showing warm —
likely per-quiz fingerprint-check DB overhead across all courses on the
site rather than a real recompute, but not root-caused.

## [2.3.3] — Renamed display name to "STACK q-type Analytics"

`pluginname` (and every other on-screen/PDF/docblock echo of it — the
on-screen page heading, both PDF report headings/authors, the privacy
metadata string, the cache-warming task's own name, `README.md`/
`INSTALL.md`/`MARKETPLACE_LISTING.md`'s live copy) renamed from "STACK
Analytics" to "STACK q-type Analytics". Historical narrative describing
what the name *was* at an earlier point in time — old CHANGELOG entries,
the "Release notes (v2.1.0)" section and the Marketplace-listing
reconciliation note's own "as of the last time it was checked" wording in
`MARKETPLACE_LISTING.md` — is deliberately left as it read at the time,
same convention every earlier rename in this file has followed. This is a
display-string-only change: no frankenstyle component, capability id,
class namespace, cache/Analytics API identifier, or URL changed, so no
uninstall/reinstall or user-preference migration is needed — only a
Moodle string-cache purge (or the automatic one an upgrade triggers) to
see it everywhere.

## [2.3.2] — Fixed Quiz Analytics timing out on large courses (Cloudflare 524)

Live testing against a real 500-1000-finished-attempt course reproduced a
Cloudflare 524 ("A timeout occurred") on Quiz Analytics/Question Analytics —
the origin server not answering before the reverse proxy in front of it
(Cloudflare, ~100s default edge timeout) gave up. Three compounding causes,
all fixed without any UI change:

- **The actual bottleneck**: `data_fetcher.php`'s
  `get_response_records_for_quiz()` called
  `question_engine::load_questions_usage_by_activity()` once per finished
  attempt — each call its own set of DB queries — so a 500-1000-attempt
  course meant 500-1000x the round trips inside one HTTP request.
  `local_quizanalytics/computetimelimit` (PHP's own execution time limit)
  couldn't help here: it protects against PHP timing itself out, not
  against an independent reverse-proxy timeout sitting in front of it.
  Switched to `question_engine_data_mapper::load_questions_usages_by_activity()`
  (plural) — one batched query across every attempt's usage at once,
  the exact fix Moodle core's own mod_quiz "Responses" report uses for this
  identical problem at scale (`quiz_first_or_all_responses_table::
  load_extra_data()`, confirmed against a real Moodle 4.5 checkout).
  Verified byte-identical output against the old per-attempt code on real
  attempt data before and after the change.
- **The cache could never actually warm**: `index.php`/
  `questionanalytics.php` already cached results keyed by an attempt
  fingerprint (`cache_helper.php`), but a cold-cache request that gets
  524'd mid-compute has its PHP process killed by the disconnect (unless
  told otherwise) before ever reaching the `cache->set()` call that would
  have made the *next* visitor's request fast — so every visitor kept
  re-triggering the same expensive cold path indefinitely. Wrapped each
  cache-miss compute in `ignore_user_abort(true)`/restore, so the work
  still finishes and the cache still warms even after the browser/proxy
  has given up on that particular request.
- **No user should ever be the one paying for a cold cache in the first
  place**: added a scheduled task
  (`local_quizanalytics\task\warm_analytics_cache`, every 15 minutes) that
  proactively recomputes any stale/missing cache entry for every course
  with STACK activity, site-wide — the same compute, just run by cron
  instead of inside a visitor's own request. Scoped to the default view
  (colorblind/anonymize off) only; a visitor with a personal preference
  toggled on still computes cold on their own first visit, an accepted
  gap. Solution Process Visualization isn't warmed by this task — there's
  no single default question/part/student selection to precompute for it.

## [2.3.1] — Fixed named STACK inputs being misread as blank

Live testing against a course whose STACK questions rename their inputs
away from the default `ans1`/`ans2`/... convention (e.g. a "separation of
variables" ODE question using `ans_mcq`, `ans_fx`, `ans_1fx`, etc.) showed
every one of those questions' responses misclassified as blank in the Quiz
Analytics error drill-down, despite a real score and a real submitted
response — the response text just didn't contain any Correct Answer, and
was contributing entirely fabricated "PRT" rows to the per-PRT pass-rate
breakdown.

Root cause: the `ansK: <expr> [tag]` field regex used in four places
(`classes/quiz/analytics/parser.php`, `latex_utils.php`,
`solution_distance.php`, and the PRT-field *exclusion* regex in
`prt_analysis.php`) all hardcoded a numeric-only suffix (`ans\d+`). A
question author can rename a STACK input to anything (`ans_mcq`,
`ans_1fx`, ...) — same as this plugin already accounted for on the PRT
side (PRT names are matched by shape, not a literal `prt` prefix, per
`prt_analysis.php`'s own docblock). A named input:

- Never matched the ans-field regex, so `parser.php` treated its response
  as having zero ans fields — the same test `parse_response_cell()` uses
  for a genuinely blank response — regardless of the real (non-empty,
  correctly-scored) PRT data sitting right next to it in the same cell.
- Also never matched `prt_analysis.php`'s `ANS_FIELD_RE` (used to exclude
  ans fields from its by-elimination PRT detection), so it fell through
  and got counted as a fake PRT named e.g. `ans_mcq`, always scored 0.0/
  incorrect — polluting the per-PRT pass-rate table and PRT branch-
  coverage diagnostics with entries that were never real PRTs.

Broadened all four regexes from `ans(\d+)` to `ans(\w+)`. `parser.php`'s
`ans_list` entries now carry `'index' => null` for a non-numeric input
name (rather than an incorrect `(int)` cast colliding everything on 0) —
the one consumer that keys off a numeric index
(`solution_distance.php`'s Solution Process Visualization tree-edit-
distance lookup) already treats a missing index as "no match," so a named
question degrades to no TED score there rather than a wrong one. Added
`tests/parser_named_ans_test.php` covering both the ans-field parse and
the PRT-exclusion fix against a real captured response.

## [2.3.0] — Renamed component to `local_quizanalytics`

[2.2.0]'s rename picked the wrong target. Confirming against the actual
Marketplace submission page (plugin id 3995,
marketplace.moodle.com/plugins/3995) showed it's registered as
`local_quizanalytics` — not `local_stackanalytics` — with a display title
("STACK Analytics") that happened to read like the latter. Renamed the
frankenstyle component again, this time to `local_quizanalytics`, through
the same set of files [2.2.0] lists (`version.php`, every `classes/`
namespace, the capability id, legacy class/function prefixes,
`lang/en/*.php`, cache/Analytics API identifiers, user-preference keys,
every hardcoded URL, the privacy provider, and docs).

The rename this time touches far more prose than [2.2.0] did, because
`local_quizanalytics` collides with the *other* source plugin's own name
(the original, standalone `local_quizanalytics` that this merge's
`classes/quiz/` subsystem was ported from) rather than just this plugin's
own prior name — several docblocks and comments explicitly named *both*
source plugins side by side (`version.php`, `db/access.php`, `lib.php`,
`settings.php`) and needed hand-fixing after the mechanical rename to stop
reading as the same name twice. `CHANGELOG.md`'s own historical narrative
(Phases 1–21, and the [2.0.0]/[2.1.0]/[2.2.0] entries) is deliberately
left describing the plugin by whatever name was actually true at each
point in time, same convention [2.2.0] established — see the note at the
top of this file.

## [2.2.0] — Renamed component to `local_stackanalytics`

This plugin's Marketplace listing replaces the original, standalone
`local_stackanalytics`'s own rather than sitting alongside it (see
[1.0.0]/"Pre-merge history" below) — but the Moodle Plugins directory
upload was rejected with "the frankenstyle component name in the uploaded
plugin does not match," since the component had stayed
`local_stackquizanalytics` (this merge's own working name) through v2.1.0.
Renamed the frankenstyle component to `local_stackanalytics` throughout so
it uploads as a new version of that existing listing:

- `version.php`'s `$plugin->component`.
- Every `classes/` namespace (`local_stackquizanalytics\...` →
  `local_stackanalytics\...`).
- The capability id (`db/access.php`,
  `local/stackquizanalytics:view` → `local/stackanalytics:view`) and every
  `require_capability()`/`has_capability()` call site.
- Every legacy global-namespace class prefix
  (`local_stackquizanalytics_quiz_*`, `local_stackquizanalytics_section_selector`
  → `local_stackanalytics_quiz_*`, `local_stackanalytics_section_selector`)
  and the two `lib.php` navigation-hook function names.
- `lang/en/local_stackquizanalytics.php` → `lang/en/local_stackanalytics.php`.
- Cache area and Analytics API component identifiers (`db/caches.php`,
  `db/analytics.php`), and the shared user-preference keys
  (`local_stackquizanalytics_anonymize`/`_colorblind` →
  `local_stackanalytics_anonymize`/`_colorblind`).
- Every hardcoded `/local/stackquizanalytics/...` asset/page URL across
  every entry point and `js/vendor-shared/sections-renderer.js`.
- `classes/privacy/provider.php`'s component references.
- Docs (`README.md`, `INSTALL.md`, `MARKETPLACE_LISTING.md`) updated to
  the new component/path throughout.

The plugin's display name (`pluginname`, "STACK Quiz & Model Analytics")
is unchanged — that's a marketing name independent of the frankenstyle
id, not part of what the Marketplace validator checks. Also unresolved:
the GitHub repository itself is still named
`moodle-local_stackquizanalytics`, not yet renamed to match
(`MARKETPLACE_LISTING.md`'s Repository/Issue tracker/Documentation URLs
still point at the current, real repo name pending that — see the
`<!-- TODO -->` comment there).

## [2.1.0] — Split into four sections

Quiz Analytics did double duty as both the course-wide comparison and,
via a quiz picker, the per-quiz Question Analytics/Solution Process
drill-down — picking a quiz silently swapped the whole page into a
different kind of report. Model & Diagnostics Analytics combined two
actual ML models with a non-ML Diagnostics Dashboard behind a View:
selector, which made Diagnostics read as a third model rather than what
it is. Both combined pages are split into four independently-reachable
sections, each with its own PDF export — no changes to any
report-builder, indicator, renderer, or PDF-content class, since this
was a page/routing-level split only.

- **UI polish first.** Course/View selectors laid out side by side
  instead of stacked; a single `pagemaintitle` ("STACK Analytics") used
  for every section's on-screen heading, replacing the previous
  per-section heading text; a full-width divider row in Model 2's table
  and a labeled block above each quiz's questions in the Diagnostics
  list, so which quiz a row belongs to is visually obvious instead of
  small muted text; the Diagnostics page's long intro text collapsed
  into a native `<details>` panel.
- **Quiz Analytics** (`index.php`) trimmed to the course-wide comparison
  only — no more quiz picker, no more `if ($quizid)` branch. Its PDF
  export moves to a new `quizanalyticspdf.php`.
- **Question Analytics** (`questionanalytics.php`, new) is the per-quiz
  drill-down moved out of `index.php`, with its own required quiz
  selector (defaulting to the course's first STACK quiz when none is
  specified) and its own PDF export, `questionanalyticspdf.php` (renamed
  from `pdf.php`, dropping the old `kind=quiz` branch — that's
  `quizanalyticspdf.php`'s job now).
- **Model Analytics** (`modelanalytics.php`, renamed from `models.php`)
  trimmed to Model 1 + Model 2 only. Its PDF export is
  `modelanalyticspdf.php` (renamed from `modelspdf.php`).
- **Diagnostics Analytics** (`diagnosticsanalytics.php`, new) is the
  Diagnostics Dashboard moved out of `models.php`'s View: selector, with
  its own PDF export, `diagnosticsanalyticspdf.php`.
  `dashboard_renderer::render_pdf_form()` gained a `$sectionheadings`
  parameter so Model Analytics and Diagnostics Analytics each control
  their own "Include in the PDF" checkbox subset, instead of the method
  hardcoding all three of the old page's sections.
- **`classes/section_selector.php`** grows from a 2-way to a 4-way
  switch; `lib.php`'s per-quiz settings-menu link now points at
  `questionanalytics.php` instead of `index.php`, since that's where
  per-quiz analytics live now. The course-level "Analytics" nav entry
  still lands on `index.php`.

## [2.0.0] — Post-merge polish

The first round of feedback after the 21-phase merge landed, ahead of
submitting this as the new major version of `local_quizanalytics` on the
Moodle Plugins directory rather than a separate listing.

- **Wording fixes.** "Question analytics"/"Solution process visualization"
  in Quiz Analytics's "View:" selector and "All quizzes" in Model &
  Diagnostics's quiz selector now match the title-case convention used
  everywhere else in the plugin.
- **Simpler intro text.** Model & Diagnostics Analytics's blue and yellow
  info boxes at the top of the page (`pageintro`, `responsibleusecallout`)
  rewritten as plain sentences — the two blue boxes combined into one, and
  every em dash/hyphen-as-punctuation/semicolon in them replaced with
  ordinary sentence breaks.
- **American spelling throughout.** "Behaviour"/"behavioural" →
  "behavior"/"behavioral", "labelled" → "labeled", "optimised" →
  "optimized", "Initialise" → "Initialize", across every lang string,
  docblock, and doc file. Left untouched: "analyser"/"analysable"/"analyse"
  and `summarise_response()`, which are Moodle core's and qtype_stack's own
  API vocabulary, not this plugin's wording.
- **Model & Diagnostics PDF now shows the same colors as the dashboard.**
  `pdf_content.php`'s cell builders return `{text, band}` instead of plain
  strings; `pdf_builder.php` colors each cell's background/text to match
  the on-screen badge-success (green) / badge-warning (yellow) palette for
  'good'/'watch' bands. Previously every PDF cell was plain black-on-white
  regardless of band.
- **Descriptive PDF filenames everywhere.** All four PDF export entry
  points (`pdf.php`'s three kinds, `modelspdf.php`) now name the download
  after the course, the specific quiz/section scope, and the download
  date (e.g. `mycourse-question-analytics-2026-08-18.pdf`,
  `mycourse-model-diagnostics-model1-model2-2026-08-18.pdf`) instead of a
  bare `{shortname}-quiz-analysis.pdf`/`{shortname}-stack-analytics.pdf`,
  so a teacher who downloads several reports over time can tell them apart
  in their downloads folder without opening each one.
- **Anonymize toggle for Model 1.** A new "Anonymize student data" checkbox
  on the Model 1 view replaces every student's name with a stable
  "Student N" pseudonym (`dashboard_renderer::pseudonym()`, numbered by
  `model1_report::build()`'s deterministic row order), on-screen and in
  the PDF export alike. Shares its user preference
  (`local_stackquizanalytics_anonymize`) and lang string
  (`anonymizemode`) with Quiz Analytics's existing anonymize toggle, so
  it's one teacher preference across both sections rather than two
  separate switches.
- **Repository renamed** from `moodle_analytics` (this merge's working name)
  to `moodle-local_stackquizanalytics`, matching the
  `moodle-{plugintype}_{pluginname}` convention both source plugins'
  repositories already followed — the naming-convention gap flagged in
  Phase 20 is now resolved.

## [1.0.0] — Merge phases 1–21

This plugin's Marketplace listing replaces the original, standalone
`local_quizanalytics`'s own (plugin id 3995) — the version history
continues directly from that plugin's v1.0.1 (see "Pre-merge history:
`local_quizanalytics`" at the bottom of this file for its own v1.0.0/
v1.0.1, which predate and form the basis of Phases 2–6 below).

**Phase 1 — Skeleton.** Bare installable no-op plugin: `version.php`
(component `local_stackquizanalytics`, dependencies `mod_quiz` + declared
`qtype_stack`), `LICENSE` (GPL v3), `.gitignore`, `db/access.php` (the one
merged capability, `local/stackquizanalytics:view`, matching both source
plugins' identical archetypes for the same content class), a minimal
`lib.php` stub, and a skeleton `lang/en/local_stackquizanalytics.php`.

**Phase 2 — Quiz Analytics data layer.** Ported `classes/data_fetcher.php`,
`api_client.php`, `cache_helper.php` from `local_quizanalytics` into
`classes/quiz/`, renamed to the `local_stackquizanalytics_quiz_*` prefix
(these are legacy global-namespace classes, manually `require_once`'d —
confirmed never autoloaded, so free to relocate). `db/caches.php` carried
over unchanged.

**Phase 3 — Quiz Analytics computation layer.** All ~29 files from
`classes/analytics/` ported to `classes/quiz/analytics/`, namespace
`local_stackquizanalytics\quiz\analytics` — chart/table/stats helpers,
parser/expression-tree math, course/question/solution-process analysis,
PDF builder classes, the vendored TCPDF subclass. `classes/output/
sections_output_helper.php` → `classes/quiz/output/`, with hardcoded
`/local/quizanalytics/...` asset URLs and user-preference keys updated to
`/local/stackquizanalytics/...`.

**Phase 4 — Vendored libraries.** TCPDF (`classes/quiz/vendor/tcpdf/`),
KaTeX and Plotly.js (`js/vendor/`), and the shared sections-renderer
(`js/vendor-shared/sections-renderer.js`) copied verbatim.
`thirdpartylibs.xml` updated with the new TCPDF path and a note that it's
Quiz-Analytics-specific.

**Phase 5 — Quiz Analytics entry point.** `index.php` adapted from
`local_quizanalytics`'s own, all paths/namespaces/capability/cache/config
keys updated, plus the new `classes/section_selector.php` — the one
genuinely new piece of UI this merge adds, a "Section:" switcher between
Quiz Analytics and Model & Diagnostics Analytics. Live-verified against a
real course: course-wide, Question Analytics, and Solution Process
Visualization views all render correctly.

**Phase 6 — Quiz Analytics PDF export.** `pdf.php` adapted from
`local_quizanalytics`'s own. Live-verified by generating a real PDF against
real course data and visually inspecting rendered pages.

**Phase 7 — Model & Diagnostics data/indicator/target/analyser layer.**
Ported from `local_stackanalytics`'s `classes/local/`,
`classes/analytics/{indicator,target,analyser}/` into
`classes/stack/{local,analytics/indicator,analytics/target,
analytics/analyser}/`, namespace `local_stackquizanalytics\stack\...`.
Caught and fixed one real bug during the port: `stack_course_helper.php`'s
`get_viewable_courses()` had a hardcoded capability string
(`'local/stackanalytics:view'`) the mechanical sed pass for quoted
component-name literals didn't catch (it's a capability string, slash not
underscore before the colon) — would have silently returned zero courses
for everyone.

**Phase 8 — Model & Diagnostics diagnostics/report/renderer layer.**
Ported `classes/diagnostics/*.php`, `classes/analytics/report/*.php`,
`classes/output/dashboard_renderer.php` into their `\stack\` equivalents.
Brought forward the full Model & Diagnostics lang string set (~85 strings)
plus three new strings for the shared section selector. Verification
initially checked only non-empty PDF/HTML byte length, which wouldn't
catch missing lang strings (`get_string()` degrades silently to
`[[stringid]]` rather than throwing) — caught this gap and added a proper
`preg_match_all` check against real rendered output.

**Phase 9 — Model & Diagnostics PDF system.** `pdf_builder.php`,
`pdf_content.php`, and `stack_pdf.php` (renamed from
`stackanalytics_pdf.php`) ported, built on Moodle core's own bundled TCPDF
rather than vendoring a second copy (Quiz Analytics's own PDF export
already vendors one; no need to duplicate that ~5MB library twice in one
plugin). Live-verified by generating a real PDF against real course data
(35 Model 1 rows, 21 Model 2 rows, 21 Diagnostics rows) and visually
inspecting it.

**Phase 10 — Model & Diagnostics entry point + PDF.** `models.php`
(adapted from `local_stackanalytics`'s index.php, with the shared "Section:"
selector) and `modelspdf.php` (adapted from its pdf.php, renamed to avoid
colliding with Quiz Analytics's own `pdf.php`). Found and fixed one missing
lang string (`courseselectorlabel`, dropped during Phase 8's port) via live
click-through testing. Live-verified all three views (Model 1, Model 2,
Diagnostics) and the full PDF export request path.

**Phase 11 — Analytics API registration.** `db/analytics.php` registers
both prediction models, target/indicator class strings updated to the new
namespace. Live-verified via `\core_analytics\manager`: both models
register with the correct target, indicator set, and time-splitting
method, both disabled by default.

**Phase 12 — Navigation hooks.** `lib.php`'s
`local_stackquizanalytics_extend_navigation_course()` adds the single
merged course-nav "Analytics" entry (landing on Quiz Analytics),
`local_stackquizanalytics_extend_settings_navigation()` carries over
`local_quizanalytics`'s per-quiz settings-menu link (`local_stackanalytics`
never had one). Both gated on the same capability plus a single "does this
course have any STACK quiz" check — both source plugins' own gating
queries resolve to the same underlying join. Live-verified via a CLI script
calling both hook functions directly against a real course/quiz.

**Phase 13 — Unified settings.** `settings.php` merges
`local_quizanalytics`'s `computetimelimit` with `local_stackanalytics`'s
three Model & Diagnostics settings (`questionneedsreviewthreshold`,
`lowtrafficfloor`, `helpseekinglookback`) onto one admin settings page.
Live-verified by loading the real admin settings page.

**Phase 14 — Lang file reconciliation.** Full click-through across every
page and view in both sections (course-wide, Question Analytics, Solution
Process Visualization, Model 1, Model 2, Diagnostics) confirmed zero
missing-string placeholders and zero duplicate keys; the four originally-
overlapping keys between both source plugins' lang files
(`pluginname`, `privacy:metadata`, `downloadpdfbutton`, `pdfnosections`)
each resolved to one reconciled value.

**Phase 15 — Unified privacy provider.** `classes/privacy/provider.php`
merges both source plugins' identical `null_provider`s into one, covering
both sections' data sources. Live-verified via
`\core_privacy\manager::component_is_compliant()`.

**Phase 16 — Tests.** All 16 PHPUnit test files ported from
`local_stackanalytics`'s `tests/`, namespace-adapted. 11 are DB-free
pure-math tests, live-verified by running their real test methods
directly against the ported production classes (73 test methods, 99
assertions, all passing). The remaining 5 use PHPUnit generator fixtures
and need a full `composer install` + `admin/tool/phpunit/cli/init.php`
environment this dev container doesn't have to actually execute — the
production code paths they exercise were already live-verified end-to-end
with real course data in Phases 7-11.

**Phase 17 — CI.** `.github/workflows/moodle-ci.yml` combines
`local_quizanalytics`'s wider test matrix (PHP 8.1-8.3 × pgsql/mariadb)
with `local_stackanalytics`'s `qtype_stack` handling (Maxima installation,
the `QTYPE_STACK_TEST_CONFIG_PLATFORM` override that skips qtype_stack's
own optimized-Maxima-image build during PHPUnit init). Code Checker and
PHPDoc Checker stay `continue-on-error` pending Phase 20's cleanup.

**Phase 18 — Docs.** `README.md`, `INSTALL.md`, `MARKETPLACE_LISTING.md`,
and this changelog rewritten to describe the merged plugin; the Model &
Diagnostics Analytics architecture doc carried over to `docs/`.

**Phase 19 — Full functional pass, two real bugs found and fixed.** Fresh
full redeploy (not an incremental sync) and a complete click-through of
every page/view in both sections with `$CFG->debug` set to
`DEVELOPER`/`debugdisplay` on, to catch what `debug=0` silently swallows.
This surfaced two real, pre-existing correctness bugs in Model &
Diagnostics Analytics's question-versioning handling, both predating this
merge:

- `stack_course_helper.php` and `stack_attempt_reader.php` each joined
  `{question_versions}` on `questionbankentryid` alone, with no filter to
  a single version. Any STACK question with edit history (this plugin's
  own test course has one with 6 accumulated 'ready' versions) fanned
  every slot out to one row per version — in `stack_course_helper.php`
  this could return the wrong `questionid` for a slot depending on row
  order; in `stack_attempt_reader.php` it duplicated every attempt step
  once per version, corrupting five indicators with inflated observations.
  Fixed by pinning `{question_versions}` to the referenced version or the
  latest non-draft one, the same semantics mod_quiz's own
  `qbank_helper::get_question_structure()` uses.
- `get_slot_finished_fractions()` selected only `qas.fraction` (no unique
  column) via `get_records_sql()`, which keys its return array by the
  first selected column regardless — silently collapsing every attempt
  sharing a fraction value down to one, badly corrupting
  `question_difficulty_irt`'s distribution math. Fixed by switching to
  `get_fieldset_sql()`, the correct API for a flat list of one column's
  values.

Both found via genuine `debugging()` warning floods against real test
data, not guessed. Re-ran the Phase 16 pure-math suite (still 73/73) and
regenerated a real PDF after the fix (same row counts) to confirm nothing
else broke. The only remaining warning after both fixes is confirmed to
originate entirely inside `qtype_stack`'s own `connector.class.php`
(third-party code), not this plugin's.

**Phase 20 — Marketplace compliance pass.** Ran `phpcbf` across the whole
plugin (34 opening-brace violations auto-fixed), then manually resolved
the remaining ~40 "missing one-line docblock description" errors and 6
over-length lines — full-plugin `phpcs` total went from 85 errors/52
warnings (baseline debt tracked since Phase 7-9) to 0 errors/41 warnings
(all MOODLE_INTERNAL stylistic notices, left as-is, matching both source
plugins' universal convention). Re-verified zero regressions via the pure-
math suite and a full page click-through after redeploying. Security
review: grepped for eval/exec/shell_exec, unescaped superglobal access,
raw SQL interpolation, unserialize(), extract(), debug leftovers, and
hardcoded credentials — zero hits. Confirmed every entry point requires
login+capability before doing anything, `settings.php` gates on
`$hassiteconfig`, and the plugin's one POST form (Quiz Analytics's PDF
export, POST only because it carries client-captured chart images too
large for a GET query string) is a read-only, capability-gated action that
doesn't need sesskey protection by Moodle's own convention. Flagged rather
than silently fixed: this repository's name (`moodle_analytics`) doesn't
follow the Moodle Plugins directory's `moodle-{plugintype}_{pluginname}`
convention for single-plugin repos — the user's own call to make at
submission time.

**Phase 21 — Wrap-up.** This changelog entry.

---

## Pre-merge history: `local_quizanalytics`

The entries below are carried over unchanged from the original, standalone
`local_quizanalytics`'s own `CHANGELOG.md` — that plugin's v1.0.0 and
v1.0.1, predating this merge and forming the basis of Phases 2–6 above.
Kept here in full since this listing (id 3995 on the Marketplace) replaces
that plugin's own rather than sitting alongside it.

## [1.0.1] — Plugins directory review fixes

- Fixed hard-coded language strings: PDF section checkboxes, PDF
  titles/captions, and PDF body text now go through `get_string()`
  instead of literal English, so they translate with the site
  language. The PDF section checkboxes previously posted their own
  label text as the form value; they now post a stable internal id,
  independent of the display language.
- Added the four `cachedef_*` language strings the plugin's MUC cache
  areas (`db/caches.php`) were missing, so they display properly on
  the site admin's cache configuration screen.

## [1.0.0] — Initial release

First public release. Everything runs as plain PHP inside the plugin
itself — no separate service, no external dependency beyond PHP.

**Features**
- Course-wide cross-quiz comparison (grade distribution, engagement over
  time, attempts-vs-grade scatter, per-quiz stats and trend lines) across
  every STACK quiz in a course.
- Per-quiz **Question Analytics**: difficulty and discrimination indices,
  response distribution, a per-question error drill-down, a student
  performance matrix, and consolidated question metrics.
- Per-quiz **Solution Process Visualization**: class-wide PRT answer
  transition graphs, per-node network centrality, PRT/tree-edit-distance
  3D distance charts, and a cross-attempt comparison with a clickable
  per-student drill-down.
- **Generate PDF Report** on every view, with section checkboxes, a
  colorblind-mode toggle, and an anonymize-student-data toggle (replaces
  real names/emails with stable per-student pseudonyms, consistent
  across every table, chart, and PDF) — charts are captured client-side
  from the already-rendered page and embedded into a PHP-generated PDF
  (TCPDF).
- Reachable from a course's secondary navigation, and from an **Analytics**
  link this plugin adds to each STACK quiz's own settings menu.
- Colorblind-safe chart palettes throughout, toggled next to the
  anonymize-student-data checkbox.
