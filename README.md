# STACK Quiz Analytics for Moodle

Question-level and course-wide analytics for STACK (Maxima CAS) quizzes,
scoped correctly per quiz and per course, with no CSV export/upload step —
data is read straight out of Moodle's own database.

**This is a single, self-contained Moodle plugin.** Every computation (STACK/
Maxima response parsing, difficulty and PRT-pass-rate statistics, PDF export)
runs in plain PHP, in-process — there is no separate service to deploy,
configure, or keep running, and nothing here ever sends data anywhere outside
the Moodle server itself. Installing `local_quizanalytics` is the only step.

## What's included

| Component | Path | What it does |
|---|---|---|
| `local_quizanalytics` | `local/quizanalytics/` | One "Analytics" entry point, reached three ways: the course's secondary navigation (course-wide cross-quiz comparison, or drill into any one quiz), a link this plugin adds to each STACK quiz's own settings menu (jumps straight to that quiz's drill-down), and — once on a quiz's drill-down — a "View:" selector between **Question Analytics** (difficulty analysis, response distribution, per-question error drill-down, student performance matrix, question metrics) and **Solution Process Visualization** (PRT transition graphs, network features, PRT/TED 3D distance charts, cross-attempt comparison with clickable per-student drill-down). |

Every view also has a **Generate PDF Report** button (section checkboxes,
colorblind-mode toggle) that renders the same charts to a downloadable PDF.

## Architecture

```
Moodle (PHP)
     |
     | reads quiz_attempts via the question engine directly
     v
  Moodle DB
     |
     v
local/quizanalytics/classes/analytics/*.php  (STACK/Maxima response
     |                                        parsing, statistics, chart
     |                                        JSON, PDF layout)
     v
Plotly.js / KaTeX (client-side rendering) or TCPDF (server-side PDF)
```

- **No CSV round-trip.** `classes/data_fetcher.php` reads finished attempts
  straight out of `{quiz_attempts}` via Moodle's question engine
  (`question_engine::load_questions_usage_by_activity()`).
- **STACK question text is rendered through STACK's own CAS engine**
  (`castext2_qa_processor`), not read as the raw stored `questiontext` —
  otherwise you'd see unresolved `@variable@` placeholders and every
  `[[lang code=...]]` block concatenated instead of just the current
  language's.
- **Every computation is pure PHP** (`classes/analytics/`) — no Python, no
  external service, no Composer dependencies. Charts are assembled as plain
  Plotly `{data, layout}` JSON and rendered entirely client-side by the
  already-vendored Plotly.js; the PDF export path (`classes/analytics/
  pdf_builder.php`) uses a vendored TCPDF, embedding chart images that were
  themselves captured client-side from the already-rendered on-screen chart
  via `Plotly.toImage()` — no headless-browser/rasterization dependency
  either.
- **Math rendering** is via a locally-vendored KaTeX (`js/vendor/katex/`) —
  not a CDN, and not routed through Moodle's `$PAGE->requires->js()`/`->css()`
  (that path re-minifies already-minified vendor bundles and has been
  observed to corrupt them). Same reasoning for the vendored Plotly.js and
  TCPDF.
- **Caching**: every data-fetch/computation path is backed by a Moodle MUC
  cache area (`db/caches.php`), keyed on a cheap SQL fingerprint (attempt
  count + latest `timefinish` + summed grades) rather than a fixed TTL
  alone — a cache entry is only ever served while that fingerprint still
  matches, so new or regraded attempts are reflected immediately rather than
  waiting out the 1-hour TTL backstop.

## Where this came from

The `classes/analytics/` PHP is a from-scratch port of an earlier,
Python-service-backed version of this same plugin — same statistics, same
chart shapes, verified field-by-field against the original for real quiz
data.

## Installation

See [INSTALL.md](INSTALL.md) for the full step-by-step setup. See
[CHANGELOG.md](CHANGELOG.md) for release notes.

## Reference

- `local/quizanalytics/thirdpartylibs.xml` — vendored library manifest
  (Plotly.js, KaTeX, TCPDF), required by the Moodle Plugins directory.
- Standard Moodle `local_` plugin conventions throughout (`version.php`,
  `db/access.php`, `db/caches.php`, `lang/en/*.php`, `settings.php`,
  `lib.php`'s navigation hooks) — nothing here needs a custom install script
  beyond Moodle's own "Site administration" upgrade screen.
- License: GNU GPL v3 or later (see `LICENSE`), matching Moodle core's own
  license — required for anything distributed through the Moodle Plugins
  directory. TCPDF is vendored under its own LGPLv3 license (GPL-compatible)
  — see `local/quizanalytics/classes/vendor/tcpdf/LICENSE.TXT`.
