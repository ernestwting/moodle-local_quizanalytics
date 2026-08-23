# A Two-Model Learning Analytics System for STACK Question Behavior in Moodle
### Research Architecture Outline

---

## 0. Ground Rules from the Moodle Analytics API (read before designing targets)

These four constraints from `moodledev.io` and `docs.moodle.org` reshape parts of your original blueprint. State them explicitly in your report's "Design Constraints" section — a reviewer familiar with Moodle internals will look for this.

| Constraint | Source | Implication for your models |
|---|---|---|
| Core ML backends support **binary classification only** (multi-class/regression targets exist as classes but aren't supported by shipped backends) | Analytics API docs, Target section | "Predict final grade" must become a binary target (e.g. *will pass ≥ threshold: yes/no*), or you write a custom `mlbackend` plugin |
| Every Indicator must return a float in **[-1, 1]** | Analytics API docs, Indicator section | Raw counts (time-deltas, edit distances, F-statistics) must be normalized/bounded before they're legal Indicators |
| Moodle Analytics currently supports **supervised learning only** — no built-in clustering/unsupervised backend | "Using analytics" docs, Training section | Several of your detections (seed bias, bloated PRTs) have no natural ground-truth label. These need either a **proxy label**, a **static rule-based model**, or reframing as descriptive statistics outside the ML pipeline |
| A Model = one Target + N Indicators + one Analyser (via the Target) + one time-splitting method + one backend | Analytics API docs, Prediction model | You cannot smuggle two objectives into one Target. Every sub-task in your blueprint needs to be sorted into "goes in Model 1's target," "goes in Model 2's target," "becomes an Indicator (reusable, not itself predicted)," or "isn't a supervised-ML task at all"

**The single biggest design decision your report needs to defend explicitly:** for each of the ~11 detections you listed, is it (a) *the thing being predicted* (target), (b) *evidence used to predict something else* (indicator), or (c) *a statistical/diagnostic report* that doesn't belong in the ML pipeline at all? Section 3 works through this triage for Model 2, where most of the ambiguity lives.

---

## 1. System-Level Architecture

**Figure 1.** End-to-end architecture of the two-model system.

---

## 2. Model 1 — Student-Level Performance & Behavior Model

### 2.1 Target Definition
- **Class:** extends `\core_analytics\local\target\binary`
- **Analyser:** `\core_analytics\course` — sample = **enrolment** (matches your original spec)
- **Target value:** `1` = student will not achieve course success (fails a defined grade/completion threshold), `0` = success
- **Why binary, not the raw grade:** core backends can't regress. If you want continuous grade prediction for the paper, propose it as **future work requiring a custom backend**, and note this is itself a publishable methodological contribution (custom `mlbackend_sklearn` supporting regression targets).
- **is_valid_analysable():** course has ended (training) / course is ongoing (prediction); course contains ≥1 STACK activity — **this is where you enforce the "STACK courses only" restriction**, by checking for `qtype_stack` question usage in the course's question bank, not at the analyser level.

### 2.2 Indicator Catalog

For each indicator: a construct definition, the raw signal, the normalization to [-1,1], and `required_sample_data()`.

**(a) Grade-trajectory indicator** (baseline, supervised-friendly)
- Raw: rolling average of STACK question grades within the time-split window
- Normalize: `2 * (mean_grade / max_grade) - 1`
- Required data: `grade_grades`, `question_attempts`

**(b) Cheating-anomaly indicator**
- Raw signal: `Δt = t_submit − t_view` for algebraically complex questions (PRT complexity above a threshold), compared against the cohort distribution
- Statistic: z-score `z = (Δt − μ_cohort) / σ_cohort`, flagged when `z < −2` (submission implausibly fast for a "perfect" input)
- Normalize: `clip(-z / 3, -1, 1)` so extremely fast, high-scoring answers push toward +1 (suspicious), typical timing sits near 0
- Caveat for your report: this is a **correlational anomaly flag, not evidence of cheating** — name it "anomalous response latency," not "cheating," in the actual PHP class and in the paper's ethics section

**(c) Rage-quit / frustration indicator**
- Raw signal: sequence of attempt inter-arrival times and score deltas within a single question session; detect a burst of low-effort submissions (short Δt, near-zero PRT node depth reached) followed by session termination before question closure
- Formalize via **Shannon entropy of the attempt-gap sequence** — low entropy + declining effort score = mechanical guessing:
  `H = −Σ pᵢ log₂ pᵢ` over binned inter-attempt intervals
- Normalize: combine entropy term and an abandonment flag into a weighted composite bounded to [-1,1]

**(d) Help-seeking gap indicator**
- Raw signal: co-occurrence (or its absence) between repeated STACK failures and access events to forums / glossary / hint resources in `logstore_standard_log`, within a lookback window after each failure
- Statistic: conditional probability `P(resource_access | recent_failure)` compared to the course-wide baseline rate
- Normalize: `2 * (P_student / P_baseline_capped_at_2) - 1`

**(e) Feedback-ignoring indicator**
- Raw signal: similarity between consecutive attempt inputs on the same PRT node after specific feedback was shown
- Metric: **normalized Levenshtein / token edit distance** between attempt *n* and attempt *n+1* algebraic input strings
- Normalize: `1 − 2 * (edit_distance / max_len)` — near 1 means no change was made despite feedback

### 2.2b Model 1 Data → Indicator → Target Flow

**Figure 2.** Feature construction for Model 1.

### 2.3 Time-Splitting Method
`quarters_accumulative` (or `deciles_accumulative` for finer granularity) — matches the "students at risk" precedent in core, and lets early-warning predictions fire before the course ends, which the "Upcoming due" and "at risk" core models both depend on.

**How the cumulative windows stack** (each quarter's prediction sees all data from course start to that point, not just that slice):

**Figure 3.** Cumulative time-splitting schedule.

### 2.4 The Labeling Problem (be explicit in your methodology section)
(a) and are directly supervised-trainable against final grade/completion. (b)–(e) are indicators feeding into the *same* target, not separately-labeled targets — this is important because you have **no ground-truth label for "this student was cheating"** or "this student ignored feedback" as an outcome; they're only usable as *predictors* of course success/failure, not as things Moodle will learn to classify on their own. If you want them evaluated as standalone detectors (e.g., a cheating classifier), you need a separate labeled dataset (e.g., academic integrity case records) — flag this as a limitation or a stretch goal requiring institutional data outside Moodle.

### 2.5 ML Backend & Math
- **PHP backend (logistic regression via php-ml):**
  `P(y=1|x) = 1 / (1 + e^{-(w·x + b)})`, trained via maximum likelihood / gradient descent on cross-entropy loss
  `L = −Σ [yᵢ log(p̂ᵢ) + (1−yᵢ) log(1−p̂ᵢ)]`
- **Python backend (1-hidden-layer TensorFlow FFNN):**
  `h = σ(W₁x + b₁)`, `ŷ = σ(W₂h + b₂)`, same cross-entropy loss, trained via backprop/Adam
- Recommend running **both backends as an A/B comparison** (Moodle explicitly supports multiple models per site for this) — a nice methods-section contribution: does the extra capacity of the FFNN meaningfully outperform logistic regression on this indicator set?

### 2.6 Evaluation
- `evaluate_model.php` splits data, computes **weighted F1** (Moodle's built-in metric), plus review the log for ROC/Matthews correlation coefficient if using the Python backend
- Report per-indicator weights (importance) alongside F1 — this is what makes the paper interpretable, not just accurate

---

## 3. Model 2 — Question & PRT Diagnostic Model

### 3.1 Triage: What's Actually a Supervised-ML Task Here?

| Your task | Category | Why |
|---|---|---|
| Question too hard | ✅ ML Target candidate | Has a natural proxy label: historical pass rate below threshold |
| Bad PRTs / unhandled nodes | ⚠️ Descriptive analytics | Directly computable from PRT tree structure + attempt logs; no "learning" needed, just aggregation — a static/rule-based model, not a trained target |
| Syntax errors vs. math misconceptions | ✅ Indicator (feeds "needs review" target) | Computable per-attempt from STACK's `AnswerTest` validation results |
| Feedback ineffectiveness | ✅ Indicator / ⚠️ could be its own small supervised task if you have "feedback text later edited by teacher" as a proxy label | See labeling note below |
| Seed bias | ⚠️ Statistical hypothesis test, not ML | This is inferential statistics (ANOVA/chi-square across seed groups) — better placed as a diagnostic report than a Target |
| Bloated response trees | ⚠️ Descriptive analytics | Pure graph analysis of the PRT structure (unreached-node ratio) |
| Concept-dependency mapping (bonus) | ⚠️ Sequence/graph mining, unsupervised | Outside current Moodle ML backend scope; implement as a standalone analytics report or Python offline job reading exported CSV, not as a Moodle Target |

**The triage logic as a decision flow** (apply this to each of your original detections to reproduce the table above):

**Figure 4.** Decision procedure for allocating candidate detection tasks.

**Recommendation for the report:** define Model 2 as **one supervised binary Target** ("this question/PRT needs instructor review") whose Indicators absorb the syntax-error, feedback-ineffectiveness, and difficulty signals — and present seed bias, bloated trees, and concept mapping as a **separate "STACK Diagnostics Dashboard"**: rule-based/statistical, computed directly from the same underlying data pipeline but outside the Analytics API's ML machinery. This is architecturally honest and will read as more rigorous to a reviewer than shoehorning hypothesis tests into a classifier.

### 3.2 Custom Analysable / Analyser

Moodle has no built-in "question" or "PRT node" analysable. You'll need:
- **Analysable:** a per-question (or per-question-per-course-offering) unit — `get_id()`, `get_context()`, `get_start()/get_end()` mapped to the question's active-usage window
- **Analyser:** extends `\core_analytics\local\analyser\by_course` (per the dev docs' guidance — course-scoped processing avoids the memory blowup of a site-wide question analyser), aggregating over `mdl_question_attempts` + `mdl_question_attempt_steps` joined to `mdl_qtype_stack_prts`
- Sample origin: the question usage id / question id pairing (you'll define the exact grain — likely *question-in-course*, not *question globally*, since difficulty and seed behavior can differ by cohort)

### 3.3 Target: "Question/PRT Needs Review"
- **Proxy label problem:** you need historical ground truth. Two realistic options — (1) *questions a teacher later edited* (STACK version history) as a positive label, or (2) *questions below an empirically-set pass-rate threshold* as a heuristic label. Option (2) is simpler but risks circularity (you're partly predicting the indicator you built it from) — discuss this explicitly as a validity threat in your methodology.

### 3.4 Indicator Catalog

**(a) Difficulty indicator (IRT-based)**
- Fit a **2-parameter logistic IRT model** per question:
  `P(correct | θ) = c + (1−c) / (1 + e^{−a(θ−b)})`
  where `b` = difficulty, `a` = discrimination, `θ` = latent student ability (estimable from overall STACK performance)
- Normalize `b` (typically in [-3,3] logit units) to [-1,1] via `clip(b/3, -1, 1)`

**(b) Syntax-error rate indicator**
- Raw: proportion of failed attempts whose STACK `AnswerTest` result is an input-validation/syntax failure rather than a mathematical-equivalence failure (STACK tags these distinctly in its validation output)
- Normalize: `2 * proportion − 1`

**(c) Unhandled-node indicator**
- Raw: proportion of possible PRT paths (tree traversal) never reached by any student across all recorded attempts
- Represent the PRT as a **directed graph** `G = (V, E)`; compute `unreached_ratio = |V_unreached| / |V|`
- Normalize: `2 * unreached_ratio − 1`

**(d) Feedback-ineffectiveness indicator**
- Raw: for each PRT branch, `Δsuccess = P(correct | attempt n+1) − P(correct | attempt n)` conditioned on having received that branch's feedback
- Statistical test: **McNemar's test** on the paired before/after outcomes to establish whether the improvement is significant, not noise
- Normalize the effect size (not the p-value) to [-1,1], e.g. via the log-odds ratio clipped to a reasonable range

**(e) Seed-bias diagnostic** *(goes in the diagnostics dashboard, not the Target — see 3.1)*
- One-way **ANOVA**: `F = MS_between / MS_within` across score distributions grouped by random seed instantiation
- Report effect size (η²) alongside F and p — a "statistically significant but small effect" is a very different finding than a large one, and this distinction matters if you're publishing

**(f) Bloated-tree diagnostic** *(diagnostics dashboard)*
- Same graph representation as (c); report `unreached_ratio` per question directly as a dashboard metric, with a simplification suggestion (prune nodes with zero traversals across ≥2 semesters)

**Worked example — a PRT tree with one unreached branch:**

**Figure 5.** Potential response tree traversal coverage.

Here `unreached_ratio = 1/7 ≈ 0.14`. Node 7 (red) is a strong pruning candidate; Node 6 (orange) is low-traffic and worth a second look before deciding whether it's a legitimate rare edge case or dead weight.

**(g) Concept-dependency mapping (bonus)** *(diagnostics dashboard / offline analysis)*
- Model sequential failures as a **first-order Markov chain** over question/concept nodes; transition probability `P(fail_j | fail_i)` estimated from attempt sequences, visualized as a directed weighted graph. This is unsupervised sequence mining — good material for the report's "future work" or a secondary offline Python analysis (pandas/networkx) outside the live Moodle ML pipeline.

### 3.5 Time-Splitting Method
`single_range` or `no_splitting` — PRT/question quality doesn't have the same within-course temporal structure that student risk does; you want cumulative behavior across all historical attempts, refreshed periodically (e.g., after each semester), which matches how Moodle's own examples describe static, assumption-light models like "No teaching."

---

## 4. Data Layer Reference (for your Methods section, not for coding yet)

| Table | Role |
|---|---|
| `mdl_question_attempts` | one row per question instance attempt; links question, usage, user |
| `mdl_question_attempt_steps` | every state transition within an attempt — this is where your timing deltas (b) and answer-text deltas (e) come from |
| `mdl_question_attempt_step_data` | the actual submitted response data per step (needed for edit-distance and syntax-validation flags) |
| `mdl_qtype_stack_prts` / `mdl_qtype_stack_options` | PRT tree definitions and configured seeds — needed for graph construction (Model 2) |
| `mdl_logstore_standard_log` | forum/resource access events — needed for the help-seeking indicator |
| `mdl_grade_grades` | final and component grades — target ground truth for Model 1 |

---

## 5. PHP Class Structure (design only, no implementation yet)

**Figure 6.** Class structure of the proposed components.

---

## 5.5 Training & Prediction Lifecycle (applies to both models)

**Figure 7.** Training and inference execution sequence.

This is the same lifecycle for both models — the only difference is *what* counts as an analysable/sample at each stage (course enrolment for Model 1, question/PRT unit for Model 2) and whether the run is scheduled per-course-quarter (Model 1) or per-semester-refresh (Model 2).

---

## 6. Research Report Skeleton (suggested section order)

1. Introduction & motivation (STACK adoption, gap in question-level analytics)
2. Related work (Moodle Analytics API literature, IRT in adaptive assessment, PRT/STACK pedagogy papers)
3. System architecture (Sections 1–2 above)
4. Model 1 design & indicator formalization
5. Model 2 design & the target/indicator/diagnostic triage (this triage *is* a contribution — most papers don't separate these cleanly)
6. Data & ethics (FERPA/GDPR-style considerations, since this touches student behavioral inference — see Section 8)
7. Evaluation methodology (F1, ROC, IRT model fit statistics, ANOVA results)
8. Limitations (proxy-label validity, binary-only backend constraint, cold-start for new courses)
9. Future work (custom regression backend, unsupervised concept mapping at scale)

---

## 7. Limitations & Ethics to Flag Explicitly

- **Anomaly ≠ misconduct.** The "cheating" and "rage-quit" indicators are statistical outliers, not verified behavior — any production deployment needs a human-in-the-loop review step before action is taken on a student, and your paper should state this as a design principle, not an afterthought.
- **Comparative indicators** (student vs. cohort mean) implicitly rank students — Moodle's own docs flag this as a pedagogical concern worth addressing in your discussion section.
- **Small-course cold start**: STACK courses with low enrolment will produce noisy indicator estimates (especially seed-bias ANOVA and IRT difficulty fits, which need reasonable sample sizes per question).
- **Data minimization**: keep the diagnostics dashboard (Model 2's descriptive half) free of student-identifying data where possible — it's about questions, not people.

---

## 8. Suggested Phased Roadmap

1. **Phase 1:** Build Model 1 with indicators (a) and (b) only [grade trajectory + basic activity], validate against core's existing "Students at risk" model as a baseline
2. **Phase 2:** Add behavioral indicators (c)-(e) to Model 1, re-evaluate F1/feature importance
3. **Phase 3:** Build the custom Analysable/Analyser for Model 2; implement difficulty (IRT) + syntax-error indicators first since they don't need a proxy-label workaround
4. **Phase 4:** Build the non-ML diagnostics dashboard (seed bias, bloated trees) as a parallel deliverable
5. **Phase 5:** Evaluate proxy-label validity for "needs review," iterate
6. **Phase 6 (stretch):** Custom `mlbackend` plugin for regression/scikit-learn if the paper's contribution depends on beating the binary-only constraint
