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

**PDF export** on every view, with section checkboxes and a colorblind
mode — charts are captured from the already-rendered page and assembled
into a downloadable PDF entirely server-side, no headless browser or
external rasterization service involved.

Reachable from a course's own navigation, and from an "Analytics" link
this plugin adds directly to each STACK quiz's own settings menu.

Requires `qtype_stack` (the STACK question type) to have anything to show.

## Suggested category

Analytics (or Reports, if "Analytics" isn't offered as a category on the
current Marketplace form)

## Maintainer

Ernest Ting — eting@caltech.edu

## Supported Moodle versions

Moodle 4.0 and later (`$plugin->requires` in `version.php` — adjust if you
want to state a narrower/wider range on the listing than what the code
actually enforces).

## Repository

https://github.com/ernestwting/quiz-quizanalytics-plugin

## License

GNU GPL v3 or later (see `LICENSE`). TCPDF is vendored under LGPLv3
(GPL-compatible) — see `local/quizanalytics/classes/vendor/tcpdf/LICENSE.TXT`.
