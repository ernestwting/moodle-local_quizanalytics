\[ [STACK q-type Analytics Docs](index.md) → About \]

# About

**STACK q-type Analytics** is a Moodle `local_` plugin that turns STACK
(Maxima CAS) quiz activity into analytics for teachers and question
authors: course-wide statistics, per-quiz drill-downs, two Analytics-API
prediction models, and diagnostic reports — all computed in plain PHP,
in-process, on the Moodle server itself.

## What it is

One installable plugin, four sections, reached from a single "Analytics"
entry in a course's navigation:

- **Quiz Analytics** — course-wide, cross-quiz comparison.
- **Question Analytics** — a single quiz's response statistics and review,
  plus a currently-hidden Solution Process Visualization view (PRT
  transition graphs, response-tree distance charts).
- **Model Analytics** — Model 1 ("student at risk") and Model 2
  ("question/PRT needs review"), both Moodle Analytics API models.
- **Diagnostics Analytics** — seed-bias and PRT branch-coverage reports,
  deliberately kept outside the prediction pipeline since they have no
  natural ground-truth label to predict against.

See [How Everything Is Calculated](calculations.md) for exactly what each
of these computes, and [Instructor Guide](instructor-guide.md) for how to
use them day to day.

## What it deliberately is not

- **Not a separate service.** There is no analytics server, no Python
  process, no queue to run — every computation is plain PHP running inside
  the same Moodle request (or the same Moodle cron run) that serves the
  page. Installing the plugin is the entire install.
- **Not a black box.** Both Analytics API models ship **disabled** by
  default. Until an administrator reviews and enables one, Model Analytics
  shows each indicator's *live reading* — a real number computed from real
  attempt data — never a trained AI prediction dressed up as one.
- **Not a data exporter.** Nothing in this plugin makes an outbound network
  call. The only way data leaves the server at all is a PDF an authorized
  teacher or manager explicitly downloads to their own browser — the exact
  same data they were already looking at on screen. See
  [Privacy & Security](privacy-and-security.md).
- **Not a replacement for STACK's own grading.** Every response is graded
  through STACK's own CAS engine (`qtype_stack`'s question-engine API) —
  this plugin reads the result, it never recomputes or second-guesses a
  grade.

## Where it came from

This plugin is the merger of two previously separate, independently
installed Moodle plugins:

- **`local_quizanalytics`** (the original) — course-wide comparison,
  per-quiz Question Analytics, and Solution Process Visualization.
- **`local_stackanalytics`** — the Analytics API models (Model 1, Model 2)
  and the non-ML Diagnostics Dashboard.

They now ship as one plugin, under `local_quizanalytics`'s own component
name, with one capability and one navigation entry — a teacher installs
one plugin and sees one "Analytics" link, rather than finding, installing,
and using two unrelated ones. The full account of the merge, including why
the plugin's own name changed twice along the way, is in
[Architecture & Design](architecture.md).

## Status

**Stable** as of v3.0.1, after real-course stress testing (see
[Getting Started](getting-started.md#tested-at) for the numbers) and
several rounds of bug fixes against real production data. Both Analytics
API models still ship **disabled** by default, and Diagnostics Analytics
and Solution Process Visualization are currently reachable by direct URL
only, pending a redesign of how they're surfaced in the navigation — see
[Instructor Guide](instructor-guide.md) for exactly where to find them
today.

Full, phase-by-phase release history: [`CHANGELOG.md`](../../CHANGELOG.md).
