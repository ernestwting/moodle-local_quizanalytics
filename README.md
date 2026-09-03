# STACK q-type Analytics for Moodle

[![Moodle Plugin CI](https://github.com/ernestwting/moodle-local_quizanalytics/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/ernestwting/moodle-local_quizanalytics/actions/workflows/moodle-ci.yml)

One installable Moodle plugin covering four sections of analytics for STACK
(Maxima CAS) quizzes, in two families: course-wide/per-quiz STACK response
statistics and visualizations (**Quiz Analytics**, **Question Analytics**),
and Analytics-API-backed risk/review prediction models plus a diagnostics
dashboard (**Model Analytics**, **Diagnostics Analytics**). A single
"Analytics" entry point, with a "Section:" switcher between the three
teacher-facing sections at the top of every page — where previously these
were two separate plugins a teacher had to install, find, and use
independently. (Diagnostics Analytics is currently omitted from that
switcher pending a redesign — see the table below.)

**This is a single, self-contained Moodle plugin.** Every computation (STACK/
Maxima response parsing, statistics, indicator math, PDF export) runs in
plain PHP, in-process — there is no separate service to deploy, configure, or
keep running, and nothing here ever sends data anywhere outside the Moodle
server itself. Installing `local_quizanalytics` is the only step.

## Requirements

**Requires the [STACK question type](https://marketplace.moodle.com/plugins/qtype_stack) (`qtype_stack`) to already be installed.** Every section of this plugin analyzes STACK/Maxima question responses specifically — install `qtype_stack` first, then this plugin.

## What's included

| Section | Reached via | What it does |
|---|---|---|
| **Quiz Analytics** (`index.php`) | The course's "Analytics" nav entry (lands here first) | Course-wide cross-quiz comparison: attempts-vs-grades scatter, difficulty/response distributions aggregated across every STACK quiz in the course. |
| **Question Analytics** (`questionanalytics.php`) | The "Section:" switcher, or an "Analytics" link this plugin adds to each STACK quiz's own settings menu | Drill into any one quiz: a compact "Quiz snapshot" (attempt counts, overall average), a "Question Response Overview" chart per question sized to Moodle's own Facility Index/mean mark, and a "Question Review" drill-down grouping each question's responses by instantiated variant, with the question text rendered as real HTML rather than flattened plain text. **Solution Process Visualization** (PRT transition graphs, network features, PRT/TED 3D distance charts, cross-attempt comparison) is still fully implemented but temporarily hidden from the view selector pending a redesign — the underlying code remains reachable by direct URL. |
| **Model Analytics** (`modelanalytics.php`) | The "Section:" switcher | **Model 1 — Student risk**: a Moodle Analytics API target on the course/enrolment analyser, fed by five behavioral indicators (grade trajectory, response-latency anomaly, disengagement entropy, help-seeking gap, feedback-revision distance); each student's name links to their Moodle profile (skipped when anonymized). **Model 2 — Question/PRT review**: a target on each STACK question-in-a-quiz, fed by four indicators (IRT-inspired difficulty, syntax-error rate, unreached-node ratio, feedback-ineffectiveness). |
| **Diagnostics Analytics** (`diagnosticsanalytics.php`) | Direct URL only — not currently offered in the "Section:" switcher, pending a redesign | **Diagnostics Dashboard**: seed-bias (one-way ANOVA) and PRT branch-coverage reports, deliberately kept outside the ML pipeline since they have no natural ground-truth label — direct calculations, not model predictions. |

Both models ship **disabled** by default (alpha stage) — what Model
Analytics shows is each model's *live indicator reading*, not a trained
prediction; those live in Site Administration → Analytics → Insights once
an administrator reviews the indicator thresholds and enables/trains a
model.

Every view in every section has a **Generate/Download PDF** button
(landscape-oriented, section checkboxes) that re-derives the same content
server-side into a downloadable report, from that section's own `*pdf.php`
entry point — Quiz Analytics/Question Analytics embed chart images captured
client-side via `Plotly.toImage()`; Model Analytics/Diagnostics Analytics
render plain-text tables re-derived directly from the same report-builder
classes the on-screen dashboard uses.

## Architecture

```
Moodle (PHP)
     |
     | reads quiz_attempts/grades/log events via the question engine,
     | gradelib, and logstore_standard_log directly
     v
  Moodle DB
     |
     +--> classes/quiz/analytics/*.php    (Quiz Analytics + Question
     |     Analytics: STACK/Maxima response parsing, statistics, chart
     |     JSON, PDF layout)
     |
     +--> classes/stack/analytics/*.php   (Model Analytics + Diagnostics
           Analytics: indicators, targets, report builders, PDF layout)
     |
     v
Plotly.js / KaTeX (client-side rendering) or TCPDF (server-side PDF)
```

- **No CSV round-trip, no external service.** `classes/quiz/data_fetcher.php`
  reads finished attempts straight out of `{quiz_attempts}` via Moodle's
  question engine; `classes/stack/local/stack_attempt_reader.php` and
  friends do the same for Model Analytics/Diagnostics Analytics's
  indicators.
- **STACK question text is rendered through STACK's own CAS engine**
  (`castext2_qa_processor`), not read as the raw stored `questiontext`.
- **Every computation is pure PHP** — no Python, no external service, no
  Composer dependencies at runtime. Quiz Analytics/Question Analytics
  assemble charts as plain Plotly `{data, layout}` JSON rendered
  client-side by the vendored Plotly.js; their PDF exports use a vendored
  TCPDF, embedding chart images captured client-side. Model
  Analytics/Diagnostics Analytics's PDF exports instead use Moodle core's
  own bundled TCPDF (`lib/pdflib.php`) — no need to vendor a second copy of
  the same ~5MB library for tables that need no charts.
- **Math rendering** is via a locally-vendored KaTeX (`js/vendor/katex/`) —
  not a CDN, and not routed through Moodle's `$PAGE->requires->js()`/`->css()`
  (that path re-minifies already-minified vendor bundles and has been
  observed to corrupt them). Same reasoning for the vendored Plotly.js and
  TCPDF.
- **Caching** (Quiz Analytics/Question Analytics): every data-fetch/
  computation path is backed by a Moodle MUC cache area (`db/caches.php`),
  keyed on a cheap SQL fingerprint (attempt count + latest `timefinish` +
  summed grades) rather than a fixed TTL alone — a cache entry is only ever
  served while that fingerprint still matches.
- **Analytics API integration** (Model Analytics): `db/analytics.php`
  registers both prediction models via
  `\core_analytics\manager::update_default_models_for_component()`, consumed
  automatically by core on install/upgrade. Diagnostics Analytics's reports
  are computed directly, outside this API.

## Where this came from

This plugin merges two previously-separate, independently-installed
plugins — the original, standalone `local_quizanalytics` and
`local_stackanalytics` — into one, replacing the former's own Moodle
Plugins directory listing (hence sharing its frankenstyle component name —
see `CHANGELOG.md`'s rename entry), so a teacher installs a single plugin
and sees a single "Analytics" entry rather than two unrelated ones under a
course's "More" menu. Each section's
computation logic is carried over essentially unchanged from its own
plugin (only namespaces, the capability, and the navigation/entry points
changed to make the merge coherent) — both had already been independently
built and verified. The design rationale for Model & Diagnostics
Analytics's targets/indicators — why each detection is a target, an
indicator, or a diagnostic rather than shoehorned into the ML pipeline —
lives in
[`docs/moodle-stack-analytics-architecture.md`](docs/moodle-stack-analytics-architecture.md).

## Status

Alpha, under active phased development — see `CHANGELOG.md` for what has
landed so far, phase by phase. Both Analytics API models ship **disabled**
by default; review `INSTALL.md` before enabling either on a live site.

Known gaps, tracked rather than hidden:
- Two indicators are documented simplifications of the architecture doc's
  literal spec (`question_difficulty_irt`'s classical-test-theory proxy
  instead of a jointly-fitted 2PL IRT model; `feedback_ineffectiveness`'s
  aggregate log-odds effect size instead of a per-branch paired McNemar's
  test) — both because the full version needs data or a batch step the
  Analytics API's per-sample indicator model doesn't provide.
- Some PHPUnit coverage needing real STACK *attempt* data (as opposed to
  just a STACK question existing) needs a full PHPUnit/Behat environment
  (`composer install` + `admin/tool/phpunit/cli/init.php`) to actually
  execute — see `CHANGELOG.md`'s Phase 16 entry.

## Deployment & sizing

**Moodle cron must actually be running — this is a hard requirement, not a
performance nice-to-have.** Quiz Analytics/Question Analytics on a large
quiz or course depend entirely on cron for both proactive cache-warming and
the on-demand background-compute safeguard; without it, a large view shows
"generating in the background" and never resolves. See
[INSTALL.md](INSTALL.md)'s Prerequisites section for how to verify cron is
running, and its own "How do I know it's working" checklist.

Worker count, worker memory, and the on-demand background-compute time
budget are all auto-detected/self-calibrating rather than fixed numbers
tuned to one machine — see INSTALL.md for what's detected automatically on
install/upgrade, the live readout on this plugin's own settings page, and
how to override any of it by hand.

**Tested ceiling: 50 quizzes, 1,000 students** (see `CHANGELOG.md` for the
specific benchmark) — the scale this plugin has actually been verified
against, not a claimed unlimited capacity. A course well beyond that may
still work, just without a direct benchmark backing it.

## Installation

See [INSTALL.md](INSTALL.md) for the full step-by-step setup. See
[CHANGELOG.md](CHANGELOG.md) for release notes.

## Documentation

Full instructor/administrator documentation lives in
[`docs/guide/`](docs/guide/index.md) — start at
[Getting Started](docs/guide/getting-started.md) if you're new, or go
straight to [How Everything Is Calculated](docs/guide/calculations.md)
for the exact formula and data source behind every statistic, indicator,
and model this plugin produces. [Privacy & Security](docs/guide/privacy-and-security.md)
covers what data this plugin touches and why none of it can leave the
server.

## Reference

- `thirdpartylibs.xml` — vendored library manifest (Plotly.js, KaTeX,
  TCPDF — the latter for Quiz Analytics/Question Analytics's PDF exports
  only; Model Analytics/Diagnostics Analytics use core's own bundled copy),
  required by the Moodle Plugins directory.
- Standard Moodle `local_` plugin conventions throughout (`version.php`,
  `db/access.php`, `db/caches.php`, `db/analytics.php`, `lang/en/*.php`,
  `settings.php`, `lib.php`'s navigation hooks) — nothing here needs a
  custom install script beyond Moodle's own "Site administration" upgrade
  screen.
- License: GNU GPL v3 or later (see `LICENSE`), matching Moodle core's own
  license. TCPDF is vendored under its own LGPLv3 license (GPL-compatible)
  — see `classes/quiz/vendor/tcpdf/LICENSE.TXT`.
