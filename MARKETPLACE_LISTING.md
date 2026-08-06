# Moodle Marketplace / Plugins directory listing text

Copy-paste source for the plugin registration form. Not part of the
plugin itself — delete or ignore this file when packaging a release ZIP.

## Plugin name

Quiz Analytics for STACK

## Component / Frankenstyle name

`local_quizanalytics`

## Short description (1–2 sentences)

Question-level and course-wide analytics for STACK (Maxima CAS) quizzes —
difficulty, response, and solution-process visualizations, computed
directly from Moodle's own database with no separate service to install.

## Long description

Quiz Analytics brings detailed, question-by-question insight to STACK
(Maxima CAS) quizzes, without any CSV export/upload step or separate
server to configure — everything reads straight out of Moodle's own
database and runs in-process, in plain PHP.

**Course-wide view**: compare every STACK quiz in a course side by side —
grade distributions, engagement over time, an attempts-vs-grade scatter
plot, and a trend line across whichever stats matter most.

**Question Analytics** (per quiz): difficulty and discrimination indices,
response outcome distribution, a student performance matrix, consolidated
question metrics, and a per-question error drill-down showing exactly
what each student submitted next to the correct answer.

**Solution Process Visualization** (per quiz, per question): class-wide
answer transition graphs showing how students moved through a question's
Potential Response Tree, per-node network centrality, 3D charts plotting
each student's distance from the correct answer across attempts, and a
cross-attempt comparison highlighting who improved, stayed flat, or
regressed.

**PDF export** on every view, with section checkboxes, a colorblind mode,
and an anonymize-student-data toggle (replaces real names/emails with
stable per-student pseudonyms, consistent across every table, chart, and
PDF) — charts are captured from the already-rendered page and assembled
into a downloadable PDF entirely server-side, no headless browser or
external rasterization service involved.

Reachable from a course's own navigation, and from an "Analytics" link
this plugin adds directly to each STACK quiz's own settings menu.

No external services, subscriptions, or API keys of any kind — every
computation runs in-process in plain PHP, and nothing ever leaves the
Moodle server.

Requires `qtype_stack` (the STACK question type) to have anything to show.

## Suggested category

Analytics (or Reports, if "Analytics" isn't offered as a category on the
current Marketplace form)

## Maintainer

Ernest Ting — eting@caltech.edu

## Supported Moodle versions

`$plugin->requires` in `version.php` currently states Moodle 4.0.0 as the
floor; CI (`.github/workflows/moodle-ci.yml`) tests against MOODLE_405_STABLE
specifically across PHP 8.1/8.2/8.3 and both PostgreSQL and MariaDB — verify
that branch is still on Moodle's currently-maintained list at submission
time (see moodledev.io/general/releases) and adjust `requires`/this listing
if not.

## Dependencies

- `mod_quiz` (core) — this plugin reads finished quiz attempts through
  mod_quiz's own question engine.
- `qtype_stack` — required; this plugin exists specifically to analyze
  STACK/Maxima question responses and has nothing to show without it.
  Declared in `version.php`'s `$plugin->dependencies` (`ANY_VERSION`, no
  qtype_stack API added in a specific release is depended on).

## Repository

https://github.com/ernestwting/moodle-local_quizanalytics

## Issue tracker

https://github.com/ernestwting/moodle-local_quizanalytics/issues

(Required field — confirm GitHub Issues is enabled for the repo:
Settings → General → Features → Issues, on github.com.)

## Documentation

https://github.com/ernestwting/moodle-local_quizanalytics#readme

## License

GNU GPL v3 or later (see `LICENSE`). TCPDF is vendored under LGPLv3
(GPL-compatible) — see `local/quizanalytics/classes/vendor/tcpdf/LICENSE.TXT`.
Declared per-library in `local/quizanalytics/thirdpartylibs.xml`.

## Privacy

Implements `\core_privacy\local\metadata\null_provider` — this plugin
stores no personal data of its own; it only reads data already governed
by mod_quiz/the question engine/core_user's own privacy providers, and
its own MUC caches are derived/disposable, not independent storage. See
`local/quizanalytics/classes/privacy/provider.php`.
