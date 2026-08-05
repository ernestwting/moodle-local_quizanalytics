# STACK Quiz Analytics for Moodle

Brings the analytics from the companion Streamlit app
([`Interactive-quiz-analytics`](../Interactive-quiz-analytics)) directly into
Moodle, scoped correctly per quiz and per course, with no CSV export/upload
step — data is read straight out of Moodle's own database.

This repository contains one Moodle plugin and one small backend service.
Nothing here ever sends data to the public internet: the backend is a local
microservice the plugin talks to over `127.0.0.1` (or a private network),
and student response data never leaves that boundary.

**This isn't a one-click Marketplace install** — the backend service has to
be deployed separately, by whoever administers the Moodle server, on that
same server or private network. See [INSTALL.md](INSTALL.md)'s "Before you
start" section before going further if you don't have that kind of server
access.

## What's included

| Component | Path | What it does |
|---|---|---|
| `local_quizanalytics` | `local/quizanalytics/` | One "Analytics" entry point, reached three ways: the course's secondary navigation (course-wide cross-quiz comparison, or drill into any one quiz), a link this plugin adds to each STACK quiz's own settings menu (jumps straight to that quiz's drill-down), and — once on a quiz's drill-down — a "View:" selector between **Question Analytics** (difficulty analysis, response distribution, per-question error drill-down, student performance matrix, question metrics) and **Solution Process Visualization** (PRT transition graphs, network features, PRT/TED 3D distance charts, cross-attempt comparison with clickable per-student drill-down). |
| analytics service | `analytics-service/` | A small FastAPI app wrapping the `analytics/` Python package (shared, byte-for-byte where possible, with the Streamlit app — see "Keeping this in sync" below). Runs as a local Docker container or systemd service; the plugin above is its only client. |

Every view also has a **Generate PDF Report** button (section checkboxes,
colorblind-mode toggle) that renders the same charts to a downloadable PDF.

## Architecture

```
Moodle (PHP)  --HTTP, localhost only-->  analytics-service (FastAPI)  -->  analytics/ (pure Python)
     |                                          |
     | reads quiz_attempts via the              | same package the Streamlit
     | question engine directly                 | app's pages/ call directly
     v                                           v
  Moodle DB                              Maxima/STACK CAS evaluation
                                          already done at attempt time
```

- **No CSV round-trip.** `classes/data_fetcher.php` reads finished attempts
  straight out of `{quiz_attempts}` via Moodle's question engine
  (`question_engine::load_questions_usage_by_activity()`), reconstructing
  the same row shape the Streamlit app expects from an uploaded Moodle CSV
  export.
- **STACK question text is rendered through STACK's own CAS engine**
  (`castext2_qa_processor`), not read as the raw stored `questiontext` —
  otherwise you'd see unresolved `@variable@` placeholders and every
  `[[lang code=...]]` block concatenated instead of just the current
  language's.
- **Math rendering** is via a locally-vendored KaTeX (`js/vendor/katex/`) —
  not a CDN, and not routed through Moodle's `$PAGE->requires->js()`/`->css()`
  (that path re-minifies already-minified vendor bundles and has been
  observed to corrupt them). Same reasoning for the vendored Plotly.js.
- **Caching**: every data-fetch path is backed by a Moodle MUC cache area
  (`db/caches.php`), keyed on a cheap SQL fingerprint (attempt count +
  latest `timefinish` + summed grades) rather than a fixed TTL alone — a
  cache entry is only ever served while that fingerprint still matches, so
  new or regraded attempts are reflected immediately rather than waiting
  out the 1-hour TTL backstop.
- **One plugin, not three.** Question Analytics and Solution Process
  Visualization used to be separate `mod_quiz` report subplugins (each its
  own tab on a quiz's results page). Moodle's quiz-report system doesn't
  let a `local_` plugin add a tab to that strip, so instead this plugin adds
  a link to each STACK quiz's own settings/administration menu that jumps
  to the same drill-down reachable from the course-level page — one plugin
  to install, configure, and submit to the Plugins directory, at the cost
  of one extra click from a quiz's own page instead of a dedicated tab.

## Keeping this in sync with the Streamlit app

`analytics-service/analytics/` is meant to be interchangeable with
`Interactive-quiz-analytics/analytics/` — same files, same behavior. If you
change something in the Streamlit app's `analytics/` package, copy the
changed files into `analytics-service/analytics/` (or vice versa) and it
should just work; the FastAPI routes in `app.py` call the exact same
functions the Streamlit pages do.

The two folders are not byte-identical forever — a couple of files exist
only on this side, or have small additions here:

- `question_charts.py`, `spv_charts.py` — chart-builder functions extracted
  out of what was inline logic, so the on-screen API routes and the PDF
  export routes call the exact same code and can't drift apart. Safe to
  copy into the Streamlit app's `analytics/` folder too (harmless if unused
  there).
- `pdf_export.py` has two additions specific to running many concurrent
  requests in a shared FastAPI worker process (a `contextvars`-based fix so
  one request's chart-rasterization error can't leak into another's PDF) and
  to running kaleido inside an arm64 Linux container (preferring a
  system-installed Chromium over kaleido's own downloader, since kaleido's
  "Chrome for Testing" download has no official Linux arm64 build). Both are
  no-ops in the plain Streamlit/desktop case.

Everything else under `analytics/` should stay identical between the two
projects — that's the whole point of the split.

## Installation

See [INSTALL.md](INSTALL.md) for the full step-by-step setup (the analytics
service, then the plugin, then how to verify it against a real quiz).

## Reference

- `local/quizanalytics/thirdpartylibs.xml` — vendored library manifest
  (Plotly.js, KaTeX), required by the Moodle Plugins directory.
- Standard Moodle `local_` plugin conventions throughout (`version.php`,
  `db/access.php`, `db/caches.php`, `lang/en/*.php`, `settings.php`,
  `lib.php`'s navigation hooks) — nothing here needs a custom install script
  beyond Moodle's own "Site administration" upgrade screen.
- License: GNU GPL v3 or later (see `LICENSE`), matching Moodle core's own
  license — required for anything distributed through the Moodle Plugins
  directory.
