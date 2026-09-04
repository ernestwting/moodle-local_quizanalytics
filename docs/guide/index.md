\[ [STACK q-type Analytics Docs](index.md) \]

# STACK q-type Analytics

**STACK q-type Analytics** is a single, self-contained Moodle plugin that turns STACK
(Maxima CAS) quiz attempts into course-wide statistics, per-question drill-downs,
behavioral risk/review prediction models, and diagnostic reports for question
authors — with **nothing ever leaving the Moodle server it runs on**.

- Course-wide and per-quiz statistics, computed directly from Moodle's own
  database — no export, no separate service, no CSV round-trip.
- Every response is graded through STACK's own CAS engine, the same one your
  quiz already uses, never approximated.
- Prediction models (student risk, question/PRT quality) built on Moodle's
  own Analytics API, shipped disabled until an administrator chooses to
  train them.
- Diagnostic reports (seed bias, PRT branch coverage) for question authors,
  computed directly, not as a black-box prediction.
- Every screen has a **Download PDF** button for offline/shareable reports,
  generated server-side and streamed straight to your browser.
- No student or course data is ever transmitted anywhere outside your
  Moodle server — see [Privacy & Security](privacy-and-security.md) for the
  full audit.

## Table of contents

| Page | What's in it |
|---|---|
| [Getting Started](getting-started.md) | The fastest path from "just installed" to your first report on screen. |
| [About](about.md) | What this plugin is, where it came from, and what it deliberately doesn't do. |
| [Installation](installation.md) | Full step-by-step setup, sizing, and the troubleshooting quick-reference. |
| [Instructor Guide](instructor-guide.md) | Using each of the four sections day to day, once it's installed. |
| [How Everything Is Calculated](calculations.md) | Every statistic, indicator, and model — the exact formula, data source, and any approximation behind it. |
| [Privacy & Security](privacy-and-security.md) | What data this plugin touches, who can see it, and why none of it can leave the server. |
| [Architecture & Design](architecture.md) | How the codebase fits together, and the deeper design-rationale documents behind the two prediction models. |
| [Glossary](glossary.md) | Short definitions of every plugin- and Analytics-API-specific term used across this guide. |
| [References](references.md) | Citations behind Solution Process Visualization and tree-edit-distance, plus acknowledgements. |

## The four sections

| Section | What it's for |
|---|---|
| **Quiz Analytics** | Course-wide, cross-quiz comparison — attempts vs. grades, difficulty and response distributions aggregated across every STACK quiz in the course. |
| **Question Analytics** | Drill into one quiz: a quiz snapshot, a per-question response overview, and a response-by-response review grouped by question variant. |
| **Model Analytics** | Two Analytics API models: **Model 1** flags students who may be at risk of not passing; **Model 2** flags STACK questions whose marking logic may be worth a review. |
| **Diagnostics Analytics** | Seed-bias and PRT branch-coverage reports for question authors — statistical checks, not predictions. Currently reached by direct link only; see [Instructor Guide](instructor-guide.md#diagnostics-analytics). |

New here? Start with [Getting Started](getting-started.md).

## Published site

This Markdown guide is the source of truth for this plugin's
documentation — edit it here first. A styled, browsable version is
published automatically at
[ernestwting.github.io/moodle-local_quizanalytics_documentation.github.io](https://ernestwting.github.io/moodle-local_quizanalytics_documentation.github.io/),
built from [`moodle-local_quizanalytics_documentation.github.io`](https://github.com/ernestwting/moodle-local_quizanalytics_documentation.github.io).
A scheduled GitHub Action in that repo pulls this folder's Markdown every
few hours (or on manual trigger), converts it, and republishes the site —
see that repo's own `CLAUDE.md`/`README.md` for exactly how. Nothing needs
to be done here to publish an edit beyond committing it to `main`; the
site catches up on its own within a few hours.
