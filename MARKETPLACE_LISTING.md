# Moodle Marketplace / Plugins directory listing text

Copy-paste source for the plugin registration form. Not part of the
plugin itself — delete or ignore this file when packaging a release ZIP.

## Plugin name

STACK q-type Analytics

## Component / Frankenstyle name

`local_quizanalytics`

(Matches plugin id 3995 on the Marketplace — https://marketplace.moodle.com/plugins/3995
— the listing this replaces. That listing's own display title read
"STACK Analytics" as of the last time it was checked, not the current
"STACK q-type Analytics" above; worth reconciling manually when
submitting, since that's a listing-level field this doc can't confirm
gets overwritten by a version upload alone.)

## Short description (1–2 sentences)

Question-level, course-wide, and Analytics-API-backed analytics for STACK
(Maxima CAS) quizzes — statistics, solution-process visualizations, and
risk/review prediction models, computed directly from Moodle's own
database with no separate service to install.

## Long description

STACK q-type Analytics brings four sections of insight to STACK
(Maxima CAS) quizzes under one installable plugin, without any CSV
export/upload step or separate server to configure — everything reads
straight out of Moodle's own database and runs in-process, in plain PHP.

**Quiz Analytics** — course-wide view: compare every STACK quiz in a
course side by side (grade distributions, engagement over time, an
attempts-vs-grade scatter plot, trend lines).

**Question Analytics** — per quiz: a compact quiz snapshot (attempt
counts, overall average), a Question Response Overview chart per question
sized to Moodle's own Facility Index and mean mark, and a Question Review
drill-down grouping each question's responses by instantiated variant,
showing exactly what students submitted next to the correct answer, with
the question text rendered as real HTML (lists, code, tables) rather than
flattened plain text. Also includes **Solution Process Visualization**:
class-wide answer transition graphs showing how students moved through a
question's Potential Response Tree, per-node network centrality, 3D charts
plotting each student's distance from the correct answer across attempts,
and a cross-attempt comparison highlighting who improved, stayed flat, or
regressed — currently implemented but temporarily hidden from the view
selector pending a redesign, still reachable by direct URL. PDF export on
every view in both of these sections, with section checkboxes, a
colorblind mode, and an anonymize-student-data toggle.

**Model Analytics** — built on Moodle's own Analytics API.
**Model 1 (Student risk)**: a target predicting whether a student is at
risk of not achieving course success, fed by five behavioral indicators
(grade trajectory, response-latency anomaly, disengagement entropy,
help-seeking gap, feedback-revision distance). **Model 2 (Question/PRT
review)**: a target predicting whether a STACK question's Potential
Response Tree needs instructor review, fed by four indicators (IRT-inspired
difficulty, syntax-error rate, unreached-node ratio, feedback-
ineffectiveness). Both models ship **disabled** by default — what this
section shows is each model's live indicator reading, not a trained
prediction, until an administrator reviews the thresholds and
enables/trains a model under Site Administration → Analytics → Models.

**Diagnostics Analytics** — seed-bias (one-way ANOVA) and PRT
branch-coverage reports, kept outside the ML pipeline since they have no
natural ground-truth label: direct calculations, not model predictions.
Currently not advertised in the section switcher pending a redesign, but
the page and all of its code remain intact and reachable by direct URL.
PDF export re-derives whichever sections are ticked, in both Model
Analytics and Diagnostics Analytics, as a landscape report.

One "Analytics" nav entry (reachable from a course's own navigation, and
from an "Analytics" link this plugin adds directly to each STACK quiz's
own settings menu) with a "Section:" switcher between the three
currently-advertised sections at the top of every page — previously two
separate plugins, now one install.

No external services, subscriptions, or API keys of any kind — every
computation runs in-process in plain PHP, and nothing ever leaves the
Moodle server.

Requires `qtype_stack` (the STACK question type) to have anything to show.

## Release notes (v2.4.13)

Copy-paste source for the "Plugin versions" tab (Edit plugin page →
Versions) when uploading this release.

Readability and consistency pass, prompted by real user reports: a
multi-part STACK question's text and its Right Answer/error drill-down
values now render with a visible line break between parts/answers instead
of running together on one line, and orphaned `[[validation:...]]` markup
no longer leaks into the displayed question text. Course-wide charts now
plot quizzes left-to-right in the order they were actually taught (by quiz
open date, falling back to creation order) instead of alphabetically. Every
user-facing message was rewritten as plain sentences instead of
em-dash/semicolon run-ons. All four sections' PDF downloads now show a
"this may take a while for a large course" notice, and a course-wide line
chart wide enough to need it now prints on a landscape page instead of
shrinking its labels past legible. Every section's header now follows the
same selector order (Course, then Quiz, then View, then colorblind/
anonymize toggles), the same "Quiz:"/"Apply" labeling, and the same "may
take a while" loading notice — shown reliably regardless of cache state,
not just on a cold view, and now disappearing on its own once the real
results have finished rendering below it. Maturity is now Stable.

## Release notes (v2.3.0)

Copy-paste source for the "Plugin versions" tab (Edit plugin page →
Versions) when uploading this release.

Internal housekeeping release: no functional or UI changes. Aligns the
plugin's internal component identifier with this Marketplace listing so
future versions install as an update rather than a separate plugin.

## Release notes (v2.1.0)

Copy-paste source for the "Plugin versions" tab (Edit plugin page →
Versions) when uploading this release.

Split the plugin's two combined pages into four independently-reachable
sections: **Quiz Analytics** (course-wide comparison only) and **Question
Analytics** (the per-quiz drill-down, split out so picking a quiz doesn't
silently swap you into a different report), and **Model Analytics** (Model
1 + Model 2) and **Diagnostics Analytics** (the Diagnostics Dashboard,
split out since it's direct calculations, not a model). Each section gets
its own PDF export entry point. Also: side-by-side Course/quiz selectors,
a consistent "STACK Analytics" page heading across every section, and
visual quiz-grouping dividers in Model 2's table and the Diagnostics list.

## Release notes (v2.0.0)

Copy-paste source for the "Plugin versions" tab (Edit plugin page →
Versions) when uploading this release.

Post-merge polish, ahead of submission: wording fixes throughout (title
case on the view/quiz selectors, plain-sentence intro text with no dashes
or semicolons, American spelling), the Model & Diagnostics PDF export now
colors each cell to match the on-screen good/watch bands, descriptive
per-download PDF filenames (course, scope, date) across all four PDF
export entry points, and a new "Anonymize student data" toggle on the
Model 1 view (shared with Quiz Analytics's existing one) that replaces
student names with stable pseudonyms on-screen and in the PDF alike.

## Release notes (v1.0.0)

Copy-paste source for the "Plugin versions" tab (Edit plugin page →
Versions) when uploading this release.

Initial release, merging the previously-separate `local_quizanalytics` and
`local_stackanalytics` plugins into one. Everything runs as plain PHP
inside the plugin itself — no separate service to deploy, no external
dependency beyond PHP.

- **Quiz Analytics**: course-wide cross-quiz comparison, per-quiz Question
  Analytics (difficulty/discrimination, response distribution, error
  drill-down, student performance matrix, question metrics), and per-quiz
  Solution Process Visualization (PRT transition graphs, network
  centrality, 3D distance charts, cross-attempt comparison). PDF export
  with colorblind-mode and anonymize-student-data toggles.
- **Model & Diagnostics Analytics**: two Moodle Analytics API prediction
  models (Student risk, Question/PRT review) plus a non-ML Diagnostics
  Dashboard (seed-bias ANOVA, PRT branch coverage), ships with both models
  disabled pending administrator review. PDF export of whichever sections
  are selected.
- One course-level "Analytics" nav entry and per-quiz settings-menu link,
  with a "Section:" switcher between the two halves at the top of every
  page.
- Colorblind-safe chart palettes throughout Quiz Analytics.

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

https://github.com/ernestwting/moodle-local_quizanalytics#readme —
full instructor/administrator documentation:
https://github.com/ernestwting/moodle-local_quizanalytics/tree/main/docs/guide

## License

GNU GPL v3 or later (see `LICENSE`). TCPDF is vendored (for Quiz
Analytics/Question Analytics's PDF exports only) under LGPLv3
(GPL-compatible) — see `classes/quiz/vendor/tcpdf/LICENSE.TXT`. Declared
per-library in `thirdpartylibs.xml`.

## Privacy

Implements `\core_privacy\local\metadata\null_provider` — this plugin
stores no personal data of its own; every section only reads data already
governed by mod_quiz/the question engine/gradelib/logstore_standard_log's
own privacy providers, and Quiz Analytics/Question Analytics's own MUC
caches are derived/disposable, not independent storage. See
`classes/privacy/provider.php`.
