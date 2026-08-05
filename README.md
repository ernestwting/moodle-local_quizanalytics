# STACK Quiz Analytics for Moodle

Brings the analytics from the companion Streamlit app
([`Interactive-quiz-analytics`](../Interactive-quiz-analytics)) directly into
Moodle, scoped correctly per quiz and per course, with no CSV export/upload
step — data is read straight out of Moodle's own database.

This repository contains three Moodle plugins and one small backend service.
Nothing here ever sends data to the public internet: the backend is a local
microservice the plugins talk to over `127.0.0.1` (or a private network),
and student response data never leaves that boundary.

## What's included

| Component | Path | What it does |
|---|---|---|
| `quiz_quizanalytics` | `mod/quiz/report/quizanalytics/` | Adds a **Question Analytics** tab to a quiz's results page (next to Grades/Responses/Statistics): difficulty analysis, response distribution, per-question error drill-down, student performance matrix, question metrics. |
| `quiz_solutionprocess` | `mod/quiz/report/solutionprocess/` | Adds a **Solution Process Visualization** tab next to it: PRT transition graphs, network features, PRT/TED 3D distance charts, cross-attempt comparison with clickable per-student drill-down. Depends on `quiz_quizanalytics`. |
| `local_quizanalytics` | `local/quizanalytics/` | Adds a course-level **Analytics** entry to the course navigation: cross-quiz comparison across every STACK quiz in the course, or drill into one quiz to see the same Question Analytics + Solution Process Visualization as the per-quiz tabs. Depends on both plugins above. |
| analytics service | `analytics-service/` | A small FastAPI app wrapping the `analytics/` Python package (shared, byte-for-byte where possible, with the Streamlit app — see "Keeping this in sync" below). Runs as a local Docker container or systemd service; the three plugins above are its only clients. |

Every surface also has a **Generate PDF Report** button (section checkboxes,
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

- **No CSV round-trip.** `classes/data_fetcher.php` in `quiz_quizanalytics`
  reads finished attempts straight out of `{quiz_attempts}` via Moodle's
  question engine (`question_engine::load_questions_usage_by_activity()`),
  reconstructing the same row shape the Streamlit app expects from an
  uploaded Moodle CSV export — the two other plugins reuse this same class
  rather than re-implementing attempt extraction.
- **STACK question text is rendered through STACK's own CAS engine**
  (`castext2_qa_processor`), not read as the raw stored `questiontext` —
  otherwise you'd see unresolved `@variable@` placeholders and every
  `[[lang code=...]]` block concatenated instead of just the current
  language's.
- **Math rendering** is via a locally-vendored KaTeX (`js/vendor/katex/`) —
  not a CDN, and not routed through Moodle's `$PAGE->requires->js()`/`->css()`
  (that path re-minifies already-minified vendor bundles and has been
  observed to corrupt them). Same reasoning for the vendored Plotly.js.
- **Caching**: each of the three plugins' data-fetch paths is backed by a
  Moodle MUC cache area (`mod/quiz/report/quizanalytics/db/caches.php`),
  keyed on a cheap SQL fingerprint (attempt count + latest `timefinish` +
  summed grades) rather than a fixed TTL alone — a cache entry is only ever
  served while that fingerprint still matches, so new or regraded attempts
  are reflected immediately rather than waiting out the 1-hour TTL backstop.

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
service, then the three plugins in dependency order, then how to verify each
one against a real quiz).

## Reference

- `mod/quiz/report/quizanalytics/thirdpartylibs.xml` — vendored library
  manifest (Plotly.js, KaTeX), required by the Moodle Plugins directory.
- Every quiz-report and local plugin here follows Moodle's standard
  subplugin/local-plugin conventions (`version.php`, `db/access.php`,
  `lang/en/*.php`, `settings.php`) — nothing here needs a custom install
  script beyond Moodle's own "Site administration" upgrade screen.
- License: GNU GPL v3 or later (see `LICENSE`), matching Moodle core's own
  license — required for anything distributed through the Moodle Plugins
  directory.
