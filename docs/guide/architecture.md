\[ [STACK q-type Analytics Docs](index.md) → Architecture & Design \]

# Architecture & Design

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
  reads finished attempts straight out of `quiz_attempts` via Moodle's
  question engine; `classes/stack/local/stack_attempt_reader.php` does the
  same for Model Analytics/Diagnostics Analytics.
- **STACK question text is rendered through STACK's own CAS engine**
  (`castext2_qa_processor`), never read as raw stored `questiontext`.
- **Every computation is pure PHP** — no Python, no external service, no
  Composer dependencies at runtime.
- **Caching** (Quiz Analytics/Question Analytics only): every fetch/compute
  path is backed by a Moodle MUC cache area, keyed on a cheap SQL
  fingerprint (attempt count + latest `timefinish` + summed grades) rather
  than a fixed TTL — an entry is only ever served while that fingerprint
  still matches. Model Analytics/Diagnostics Analytics have **no result
  cache**; every dashboard view recomputes from scratch.
- **Analytics API integration** (Model Analytics): `db/analytics.php`
  registers both prediction models via
  `\core_analytics\manager::update_default_models_for_component()`,
  consumed automatically by core on install/upgrade.

See [How Everything Is Calculated](calculations.md) for the actual math
behind every number this produces, and
[Privacy & Security](privacy-and-security.md) for what data this touches
and where it stays.

## Two deeper reference documents live alongside this guide

- **[`moodle-stack-analytics-architecture.md`](../moodle-stack-analytics-architecture.md)**
  — the original research/design outline for Model Analytics: for each of
  the ~11 behaviors it detects, why it's a *target* (the thing being
  predicted), an *indicator* (evidence feeding a prediction), or a
  *diagnostic report* outside the ML pipeline entirely, plus the Moodle
  Analytics API's own hard constraints (binary-only core backends,
  indicators bounded to `[-1,1]`, supervised-only, one Target per Model)
  that shaped those choices. §3.3 in particular covers the proxy-label
  circularity caveat referenced throughout
  [How Everything Is Calculated](calculations.md#model-analytics).
- **[`plugin-naming-and-merge-history.md`](../plugin-naming-and-merge-history.md)**
  — this plugin is the merger of two previously separate, independently
  installed plugins (`local_quizanalytics` and `local_stackanalytics`),
  and the merged codebase itself was renamed twice before settling on its
  current name. This document is the condensed, narrative account of that
  history — useful context for why some class namespaces still say
  `stack`/`quiz` independently, or why an old commit message references a
  component name that no longer exists. The full blow-by-blow lives in
  [`CHANGELOG.md`](../../CHANGELOG.md).

## Known, tracked gaps

Documented rather than hidden — see
[`README.md`](../../README.md#status) for the current list, currently:

- Two Model Analytics indicators are documented simplifications of the
  architecture doc's literal spec — `question_difficulty_irt`'s classical-
  test-theory proxy instead of a jointly-fitted 2PL IRT model, and
  `feedback_ineffectiveness`'s aggregate log-odds effect size instead of a
  per-branch paired McNemar's test — both because the fuller version needs
  a batch step the Analytics API's per-sample indicator model doesn't
  provide. Detail: [How Everything Is Calculated](calculations.md).
- `concept_dependency_report` (concept-dependency mapping across
  questions) is an intentional placeholder, not implemented — the
  architecture doc frames it as offline sequence-mining work better suited
  outside a live dashboard page.
