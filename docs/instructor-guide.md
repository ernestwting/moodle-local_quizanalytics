\[ [STACK q-type Analytics Docs](index.md) → Instructor Guide \]

# Instructor Guide

How to use each of the four sections day to day, once the plugin is
installed (see [Installation](installation.md) if it isn't yet). This
plugin is visible to **teachers, editing teachers, and managers only** —
not students — on any course that has at least one STACK question added
directly to a quiz slot.

## Getting to it

- **From a course:** open the course, look for an **Analytics** entry in
  the secondary navigation bar (`Course | Settings | Participants |
  Grades | Reports | ...` — check inside **More** if the bar is full).
  This lands you on Quiz Analytics.
- **From a quiz:** open any STACK quiz directly and look in its own
  settings/administration menu for an **Analytics** entry — this jumps
  straight to that quiz's Question Analytics.
- **Switching sections:** every page has a **Section:** switcher at the
  top — Quiz Analytics, Question Analytics, Model Analytics. (Diagnostics
  Analytics isn't in this switcher yet — see
  [§ Diagnostics Analytics](#diagnostics-analytics) below.)

If nothing appears at all, the most common reasons are: no STACK question
added directly to a slot in that course, or no finished attempts yet.

## Quiz Analytics

The course-wide view — every STACK quiz in the course, compared side by
side. Shows an attempts-vs-grades scatter plot and aggregated
difficulty/response distributions. Use the **Compare attempts against:**
selector to switch which grade (average, highest, or minimum) the scatter
plot compares attempt counts to.

Two display toggles apply across this and Question Analytics:

- **Colorblind mode** — swaps chart color palettes for a colorblind-safe
  set.
- **Anonymize student data** — replaces every real student name/email on
  screen (and in the PDF) with a stable pseudonym ("Student 1", "Student
  2", ...). This is a **display option only** — it doesn't change what's
  computed, doesn't persist anywhere, and doesn't restrict who can see the
  page; it's for taking a screenshot or PDF you want to share (e.g. in a
  department meeting) without real names in it. See
  [Privacy & Security](privacy-and-security.md) for what this does and
  doesn't protect against.

## Question Analytics

Pick a quiz from the dropdown (defaults to the course's first STACK quiz)
to see:

- **Quiz snapshot** — attempt counts (finished/in-progress/other) and the
  overall average, read straight from Moodle's own attempt data.
- **Question Response Overview** — one chart per question, sized to
  Moodle's own Facility Index and mean mark (the same numbers Quiz →
  Results → Statistics shows), not a separately-computed figure.
- **Question Review** — click into a question to see its expected answer
  and common response patterns grouped by instantiated variant, with the
  question text rendered as real HTML.

**Solution Process Visualization** (PRT transition graphs, network
features, response-tree distance charts, cross-attempt comparison) is
fully implemented but temporarily not offered in the on-screen **View:**
selector, pending a redesign. It's still reachable directly:

```
local/quizanalytics/questionanalytics.php?id=<courseid>&quizid=<quizid>&view=solutionprocess
```

## Model Analytics

Use the **View:** selector to switch between the two models. **Both ship
disabled by default** — until an administrator enables and trains one
(Site administration → Analytics → Models), what you see here is each
indicator's *live reading*, computed directly from real attempt data, not
a trained prediction. See
[How Everything Is Calculated](calculations.md#model-analytics) for the
exact math behind every number on this page.

- **Model 1 — Student Risk & Behavior.** One row per enrolled student:
  their current grade status against the course's pass grade, and five
  behavioral indicators (grade trajectory, response-latency anomaly,
  disengagement, help-seeking gap, feedback-revision distance), each shown
  as a badge (Good / Typical / Worth a look) with a one-line plain-English
  explanation. Each student's name links to their Moodle profile (skipped
  when Anonymize mode is on).
- **Model 2 — Question & PRT Quality.** One row per STACK question, with
  the quiz it belongs to shown underneath: a pass-rate-based "needs
  review" flag, and four indicators (difficulty, syntax-error rate,
  unreached PRT branches, feedback ineffectiveness).

Read the **About this model** panel on each view before acting on a flag —
in particular, Model 2's "needs review" flag and its difficulty indicator
both derive from the same underlying pass rate, so treat them as related
signals, not independent confirmation of each other.

**A flag is a prompt to look, never proof of anything on its own** — see
the "Responsible use" note shown on the page itself, and repeated in
[Privacy & Security](privacy-and-security.md#responsible-use).

## Diagnostics Analytics

Two statistical reports for question authors — **not** model predictions,
just direct calculations with no ground-truth label involved. Currently
not in the **Section:** switcher (pending a redesign of how it's
surfaced), but fully functional at:

```
local/quizanalytics/diagnosticsanalytics.php?id=<courseid>
```

- **Seed bias** — a one-way ANOVA across a question's random seeds,
  checking whether some randomized variants are unfairly harder or easier
  than others (so a low grade isn't just "you got the harder version").
- **PRT branch coverage** — which of a question's PRT branches have
  actually been triggered by a real student answer. A branch that's never
  reached is either feedback nobody's needed yet, or dead logic worth
  simplifying.

A "Worth a look" badge here is a prompt to open the question and check it
still makes sense for how you designed it — not proof something is
broken. Full formulas: [How Everything Is Calculated](calculations.md#diagnostics-analytics).

## PDF exports

Every view in every section has a **Download PDF** button. It re-derives
the same content server-side (not a screenshot) into a downloadable,
landscape-oriented report with checkboxes for which sections to include.
Quiz Analytics/Question Analytics embed the same charts you see on
screen; Model Analytics/Diagnostics Analytics render the same tables as
plain text. The PDF streams straight from the server to your browser as a
download — nothing is stored anywhere in the process. Large courses may
take a while to generate; the page tells you to wait for the download
rather than reload.

## If a report says "generating in the background"

Large quizzes/courses hand their computation to a background task instead
of making you wait on the page. Revisit the same page in a minute or two
and it should be ready — the page itself re-checks automatically every 20
seconds. If this message is still showing after a genuinely long wait (15+
minutes), that's a real problem, not normal — see
[Installation § Troubleshooting](installation.md#troubleshooting-quick-reference)
and flag it to your Moodle administrator.
