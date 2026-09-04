\[ [STACK q-type Analytics Docs](index.md) → How Everything Is Calculated \]

# How Everything Is Calculated

Every statistic, chart, indicator, and model this plugin produces — the
exact formula, what data feeds it, and any approximation or design
tradeoff behind it. This page exists so nothing here is a black box: if a
number on screen looks surprising, the explanation should be findable
here rather than only in the source.

Two things worth reading before the rest of this page:

- **All of this runs on data already inside Moodle's own database.**
  Nothing here is estimated from an external source or a separate model
  trained elsewhere — see [Privacy & Security](privacy-and-security.md).
- **A handful of calculations are documented simplifications**, not silent
  approximations — each one is called out explicitly below, with why.

## Contents

- [Quiz Analytics & Question Analytics](#quiz-analytics--question-analytics)
- [Model Analytics](#model-analytics)
- [Diagnostics Analytics](#diagnostics-analytics)
- [Hidden / not shown on screen today](#hidden--not-shown-on-screen-today)

---

## Quiz Analytics & Question Analytics

### Where the underlying data comes from

Every response is read straight from Moodle's own `quiz_attempts` (finished
attempts by default), by joining `quiz_slots` → `question_references` →
`question_bank_entries` → `question_versions` → `question` filtered to
`qtype = 'stack'`. **Only questions added directly to a quiz slot are
detected** — a STACK question pulled in only via "random question from
category" is not counted, a deliberately narrow scope for a fast
nav-gating check.

Each response is parsed from Moodle's own response-summary text
(`question_usage_by_activity::get_response_summary()` — the same string
Moodle's core "Responses" report shows), which encodes both the submitted
answer per input (`ansN: ... [valid|invalid|score]`) and each PRT's
terminal result (`<prtname>: ! ` for a syntax error, or `<prtname>: # =
<fraction>`). Question text is re-rendered through STACK's own CAS engine
(`castext2_qa_processor`) rather than read raw, since the stored
`questiontext` still has unresolved `@variable@` placeholders.

**Every response is classified**, in this precedence order:

1. **`blank`** — no `ansN:` fields at all.
2. **`invalid`** — any `ansN:` field tagged `invalid`.
3. **`ungraded`** — has PRT fields, but none carry a fraction (or the
   Moodle question state is `todo`/`complete`/`needsgrading`).
4. **`correct`** — score (see below) equals exactly `1.0`.
5. **`incorrect`** — anything else.

**Score for a question with `M` parts** = the mean of each part's PRT
fraction, treating a missing/null PRT part as `0` credit — not excluded
from the average:

$$
\text{score} = \frac{1}{M}\sum_{i=1}^{M}\text{fraction}_i
$$

**Only `correct`/`incorrect` responses count as "graded"** for facility,
percent-correct, and average-score calculations everywhere in this
plugin. This exclusion exists specifically because an earlier version
counted `blank`/`ungraded` responses (e.g. a question nobody reached yet)
as automatic failures, which measurably dragged down course-wide averages
on real courses that had questions with zero attempts.

### Two response pools

Nearly every Quiz/Question Analytics calculation uses one of two
"pools" of response rows:

- **Pool A (participation)** — every attempt, unfiltered. Used for
  invalid/blank rates, reattempt share, and anywhere retries themselves
  matter (Solution Process Visualization).
- **Pool B (performance)** — exactly **one attempt per student**: the
  highest-grade attempt, ties broken by most recent completion, then by
  highest attempt number. Used for difficulty, discrimination, facility,
  and the Question Review drill-down.

### Course-wide Quiz Analytics

- **Summary of Quiz Stats** — per quiz: student count, mean grade,
  sample variance of grade (÷ by `n-1`, since this is a sample of
  students, not a full population), mean of each student's own best
  grade, attempt count, and mean attempts-per-student:

  $$
  s^2 = \frac{1}{n-1}\sum_{i=1}^{n}(x_i-\bar{x})^2
  $$
- **Quiz Grade Distribution (boxplot)** — one box per quiz over all grades,
  with a marker overlay at each quiz's mean.
- **Attempts vs. Grades (scatter)** — one point per student per quiz,
  plotted against whichever grade type you pick (Average / Highest /
  Minimum). The reported correlation is the standard Pearson coefficient:

  $$
  r = \frac{\sum(x_i-\bar{x})(y_i-\bar{y})}{\sqrt{\sum(x_i-\bar{x})^2\sum(y_i-\bar{y})^2}}
  $$

  The **displayed marker position is jittered** — see
  [§ Hidden / not shown on screen today](#the-scatter-plots-jitter) — but
  the reported Pearson correlation coefficient always uses the true,
  unjittered values.
- **Engagement Over Time** — a Gaussian kernel-density estimate of attempt
  start dates per quiz, using Scott's rule for bandwidth (the same default
  `scipy`/seaborn use):

  $$
  h = n^{-1/(d+4)}\,\sigma \qquad (d = 1 \text{ for a single time dimension})
  $$

  Omitted entirely if any quiz is missing timestamps
  or has fewer than 2 distinct dates.
- **Line Graph of Various Metrics** — the same course-wide metrics as the
  stats table, plotted as a trend line across quizzes in **chronological
  order** (by quiz open date, falling back to creation order) — not
  alphabetical.

### Question Analytics (per quiz)

- **Quiz snapshot** — attempt counts and the overall average, read from
  Moodle's own attempt data (`AVG(sumgrades)`, rescaled the same way
  Moodle's own Quiz Overview page does it) — not self-computed.
- **Question Response Overview** — one bar per question showing the split
  of `correct` / `incorrect` / `invalid` / `no_response` (one row per
  student, their most-recent reached attempt). **The mean mark shown is
  read directly from Moodle's own cached Facility Index**
  (`quiz_statistics_report`), not recomputed — so this always agrees with
  Quiz → Results → Statistics.
- **Question Review** — responses grouped by **instantiated variant**: two
  attempts are treated as "the same version" of a randomized question only
  if both their rendered question text and right-answer text match
  exactly. Wrong responses within a version are tallied by exact text and
  sorted by frequency. A genuinely blank response shows as `(No
  response)`, never Moodle's raw internal bookkeeping text.
- **Difficulty & discrimination** (computed, though not currently surfaced
  on this simplified page — see [below](#hidden--not-shown-on-screen-today)) —
  the classic Kelley 27% top/bottom-group method: students are ranked by
  grade, the top and bottom 27% (`round(0.27 × n)`, minimum 1 student each)
  form two cohorts:

  $$
  \text{discrimination} = (\text{top cohort fraction at full credit}) - (\text{bottom cohort fraction at full credit})
  $$

  Difficulty index and facility are computed over graded Pool B
  responses only.

### Solution Process Visualization

Fully implemented, reachable at
`questionanalytics.php?id=<courseid>&quizid=<quizid>&view=solutionprocess`
(not currently offered as an on-screen selector — see
[Instructor Guide](instructor-guide.md#question-analytics)). Uses **Pool
A** throughout, since seeing retries is the point.

- **Class-Wide Transition Graph** — for each STACK question, every
  student's ordered sequence of PRT outcomes across their attempts (node
  `"0"` = failed/no trace, `"c"` = full marks, a number = the PRT node
  where the attempt terminated True) is tallied into consecutive
  transition pairs across the whole class. Edge width and color scale with
  how often each transition occurred.
- **Network Features per Node** — in-degree, out-degree, and total degree
  for each node in that graph, normalized by `(node count − 1)`.
- **PRT-Distance 3D Chart** — the PRT node an attempt terminated at,
  plotted per student across their attempts; a response with no
  classifiable node (never even reached a numbered node) is bucketed into
  a shared "other" sentinel distance placed *past* the worst real
  distance, rather than mixed in with distance 0 — deliberately, so "no
  attempt" is never visually equated with "perfect."
- **Tree Edit Distance (TED) 3D Chart** — the Zhang-Shasha tree edit
  distance (unweighted insert/delete/rename, cost 1 each) between the
  submitted CAS expression and the correct answer's expression, both
  parsed into expression trees. Display values are capped at **20** (the
  underlying comparison isn't). A submission that can't be parsed into a
  valid expression tree (e.g. non-arithmetic CAS syntax) is excluded from
  this chart, not shown as 0 or as an error.
- **Cross-Attempt Comparison** — students with 2+ qualifying attempts on a
  question, classified as Improved / Regressed / Flat between their first
  and last attempt by grade (a change of ≤ 0.000001 counts as flat).

## Model Analytics

Both models are built on Moodle's own Analytics API. **They ship
disabled by default** — what's described below as "Model 1"/"Model 2"
is, until an administrator enables and trains one, a **live indicator
reading only**: a real number computed fresh from real attempt data, not
a trained prediction.

### Model 1 — Student Risk & Behavior

**Target ("what's predicted"):** reused directly, unmodified, from Moodle
core's own `course_gradetopass` target — will this student's final grade
fall below the course's own grade-to-pass? The only addition is an extra
eligibility check requiring the course to actually contain a STACK
activity.

**The five indicators**, each producing a number in **[-1, 1]** (the
Analytics API's own required indicator range) and a plain-language
sentence:

| Indicator | What it measures | How |
|---|---|---|
| **Grade trajectory** | This student's STACK scores vs. full marks | Mean grade across finished attempts, linearly rescaled from `[0, max]` to `[-1, 1]`. |
| **Response-latency anomaly** | Implausibly *fast* answering vs. the class | A z-score of this student's mean inter-step gap against the whole course's own gap distribution, scaled by ÷3 and negated (faster than the cohort → positive/"watch" indicator). Only ever flags *too fast* — a slower-than-average student is never flagged by this measure. |
| **Disengagement entropy** | Mechanical-looking submission timing + question abandonment | Half weight on `(1 − normalized entropy)` of inter-step time gaps (regular, robotic timing → low entropy), half weight on the fraction of attempts abandoned (last step state = "gave up"). |
| **Help-seeking gap** | Whether this student seeks help after a wrong answer as often as classmates | For each STACK failure, checks whether a forum/glossary/resource/page/URL/book access happened within a lookback window (default 1 hour, admin-configurable). Student's own rate compared to the course-wide baseline rate. |
| **Feedback revision distance** | Whether an answer meaningfully changes after feedback | Normalized Levenshtein edit distance between consecutive tries at the same input, averaged and rescaled — heavily revised (good) vs. barely changed (watch-worthy). |

The z-score behind response-latency anomaly:

$$
z = \frac{\Delta t_{\text{student}} - \mu_{\text{cohort}}}{\sigma_{\text{cohort}}}
$$

The entropy term behind disengagement, Shannon entropy over binned
inter-attempt intervals:

$$
H = -\sum_i p_i \log_2 p_i
$$

**Explicit ethics note carried in the code itself:** response-latency
anomaly is described as "a correlational flag only, never evidence of
misconduct on its own" — worth repeating here directly, not just in the
UI copy.

### Model 2 — Question & PRT Quality

**Target:** a proxy label — is this question's empirical pass rate below
a threshold (default **50%**, admin-configurable)? Computed as its own
independent query, not by reusing the difficulty indicator's own number.

**⚠ Documented circularity caveat:** the "needs review" flag and the
difficulty indicator below both ultimately derive from the *same*
underlying pass rate. Computing the target as a separate query avoids
literally reusing one float, but does not remove the underlying
statistical circularity — treat a "needs review" flag and a "difficult"
reading as **one signal, not two independent confirmations of each
other.**

**The four indicators:**

| Indicator | What it measures | How |
|---|---|---|
| **Question difficulty** | How hard the question is empirically | Pass rate converted to a logit (log-odds), clipped to ±3 logit units, scaled to [-1,1]. This is a **classical-test-theory proxy**, not a jointly-fitted 2-parameter IRT model — see [caveat below](#documented-simplifications). |
| **Syntax-error rate** | Whether wrong answers are input mistakes rather than maths mistakes | Of all failed attempts, the share whose final state was Moodle's `invalid` engine state (input/format rejected before it was even graded). |
| **Unreached PRT branches** | How much of the question's marking logic has never fired | For every PRT branch (a node's true/false answer-note), checks whether that answer-note text appears as a substring in any attempt's response summary. Ratio of never-matched branches. Branches with a blank answer-note can't be observed this way and are excluded — a known data-model limitation. |
| **Feedback ineffectiveness** | Whether students improve after a wrong try, more than a fresh baseline | Log-odds of (improve rate on a next quiz attempt after a wrong one) vs. (first-attempt pass rate baseline), clipped to ±3, scaled to [-1,1]. See [caveat below](#documented-simplifications) — this is measured across a student's *repeated quiz attempts*, not within-attempt retries. |

The difficulty logit transform:

$$
\text{logit}(p) = \ln\!\left(\frac{p}{1-p}\right) \xrightarrow{\text{clip to } [-3,3]} \xrightarrow{\text{scale}} [-1,1]
$$

### Documented simplifications

Two indicators are explicit, intentional simplifications of a more
rigorous design, not silent shortcuts — each because the Analytics API's
per-sample indicator model (one score computed independently per student
or per question) has no hook for the batch step the fuller version needs:

- **Question difficulty** uses a classical-test-theory pass-rate-to-logit
  proxy instead of a full 2-parameter logistic IRT model
  jointly fit across every student's ability and every question's
  difficulty/discrimination at once:

  $$
  P(\text{correct} \mid \theta) = c + \frac{1-c}{1+e^{-a(\theta-b)}}
  $$

  That joint fit needs a batch calibration pass across the whole
  item bank, which nothing in the Analytics API's `calculate_sample()`
  contract provides a hook for.
- **Feedback ineffectiveness** was originally designed to compare
  consecutive *tries within one question attempt* (matching STACK's
  "interactive with multiple tries" behavior) via a per-PRT-branch paired
  statistical test. Under **deferred feedback** — the behavior this
  plugin's own test courses actually use, where only the last step of an
  attempt is ever graded — that produced zero signal, since there's no
  earlier graded try to compare against. It was redefined to compare a
  student's own repeated **quiz attempts** instead, and the per-branch
  attribution was dropped for an aggregate log-odds effect size, since
  Moodle only stores an attempt's *current* response summary, not a
  per-step history a per-branch test would need.

Full design rationale for every target/indicator/diagnostic split — why
each is one or the other — lives in
[`moodle-stack-analytics-architecture.md`](../moodle-stack-analytics-architecture.md).

## Diagnostics Analytics

Statistical reports, not model predictions — no ground-truth label is
involved, so there's nothing to train or predict. Currently not in the
on-screen section switcher, reachable directly at
`diagnosticsanalytics.php?id=<courseid>` (see
[Instructor Guide](instructor-guide.md#diagnostics-analytics)).

### Seed bias

A one-way ANOVA of question score, grouped by the random "seed" Moodle
assigned that attempt (STACK's mechanism for randomizing a question's
numbers while keeping its structure). Reports:

- **η² (eta-squared)** — the share of total score variance attributable
  to which seed a student got:

  $$
  \eta^2 = \frac{SS_{\text{between}}}{SS_{\text{total}}}
  $$
- **Effect-size magnitude** — Cohen's conventional bands: `< 0.01`
  negligible, `< 0.06` small, `< 0.14` medium, else large.

**Deliberately does not compute an exact p-value** — that needs the
regularized incomplete beta function / F-distribution CDF, judged (per an
explicit code comment) "a numerical routine easy to get subtly wrong
without a reference implementation to check it against." η² read against
Cohen's thresholds is treated as sufficient for an exploratory dashboard;
an exact significance test is left to a real statistics package if you
need one.

### PRT branch coverage

For every PRT branch, how many recorded attempts' response summaries
actually contain that branch's answer-note text:

- **`0` traversals** → "Never reached: pruning candidate."
- **`1` to (floor − 1) traversals** → "Low traffic: review before
  pruning" (floor default **2**, admin-configurable).
- **≥ floor traversals** → "Adequately traversed."

### Not yet implemented

**Concept-dependency mapping** (finding which questions' failures tend to
predict failures on others) has a placeholder class but is not
implemented — the architecture doc frames it as offline sequence-mining
work, a better fit outside a live dashboard page than a Phase-6-style
in-process addition. It says so directly on the Diagnostics page rather
than just silently not appearing.

## Hidden / not shown on screen today

A few real, working calculations exist in the codebase but aren't
currently wired into any on-screen page — kept here rather than left
undiscoverable:

- **Difficulty & discrimination indices, response outcome/valid-invalid
  breakdowns, and the student×question performance-matrix heatmap**
  (`classes/quiz/analytics/difficulty.php`, `question_charts.php`,
  `response_analysis::compute_response_outcomes()`) are fully implemented
  and correct, but the current simplified Question Analytics page (since
  the 3.0.0 redesign — see [`CHANGELOG.md`](../../CHANGELOG.md)) shows only
  the Question Response Overview and Question Review sections. These
  functions have no on-screen caller today; they remain available for a
  future redesign or for direct use.
- **The scatter plot's jitter.** The "Attempts vs. Grades" scatter chart
  nudges each point's *displayed position only* by a small deterministic
  offset (±0.15, seeded from an MD5 hash of the student and quiz, so the
  same student always gets the same nudge on every reload — not
  re-randomized per view). This exists purely so students who land on the
  exact same (attempt count, grade) coordinate don't render as one
  indistinguishable overlapping dot — marker size also scales up with how
  many students actually share a point. **The real, unjittered values are
  what every statistic (including the displayed Pearson correlation) is
  computed from** — jitter never touches a number, only a pixel position.
- **`validation.php`'s grade audit.** Every Question Analytics computation
  cross-checks its own calculated grade against Moodle's authoritative
  recorded grade for each attempt, flagging any mismatch ≥ 0.01 — and
  specifically distinguishes a STACK "validated but not regraded" quirk
  from a generic manual-override explanation. This runs but isn't surfaced
  as a user-facing report; it exists as a built-in correctness check.

Two admin-facing performance settings also shape what you see but aren't
"calculations" in the statistical sense — see
[Installation § 3](installation.md#3-optional-review-performance-settings)
for the on-demand background-compute time budget and the course-wide
computation time limit.
