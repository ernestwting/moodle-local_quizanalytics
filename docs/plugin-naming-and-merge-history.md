# Plugin naming and merge history

This repository's identity has changed several times — two originally
separate plugins merged into one codebase, and that merged codebase was
then renamed twice before settling on its current, correct name. This
document exists so that history doesn't have to be reconstructed from
scratch every time it matters (it resurfaces constantly: reading old
commit messages, understanding why some class namespaces still say
`stack`/`quiz`, or explaining a `[[stringid]]`-style typo caused by a
rename that missed a spot). For the full blow-by-blow, see `CHANGELOG.md`
— this is the condensed, narrative version of the same facts.

## The starting point: two separate, independently-installed plugins

- **`local_quizanalytics`** (Moodle Plugins directory id 3995) — the
  original standalone plugin. Course-wide cross-quiz comparison, per-quiz
  Question Analytics (difficulty/discrimination indices, response
  distribution, error drill-down, student performance matrix, question
  metrics), and Solution Process Visualization (PRT transition graphs,
  network centrality, tree-edit-distance charts, cross-attempt
  comparison). Its own pre-merge v1.0.0/v1.0.1 history is preserved
  verbatim at the bottom of `CHANGELOG.md` under "Pre-merge history:
  `local_quizanalytics`".
- **`local_stackanalytics`** — a separate plugin covering the Analytics
  API side: Model 1 ("Student at risk in a STACK-based course") and
  Model 2 ("STACK question/PRT needs review") prediction models, plus a
  non-ML Diagnostics Dashboard (seed-bias ANOVA, PRT branch-coverage
  reports) kept outside the ML pipeline since it has no ground-truth
  label to predict against.

Two teachers installing STACK analytics tooling on the same site
previously had to find, install, and maintain both of these
independently.

## The merge: working name `local_stackquizanalytics`

A 21-phase merge (`CHANGELOG.md`'s `[1.0.0] — Merge phases 1–21` entry)
combined both plugins' code into one, under the working frankenstyle
component name **`local_stackquizanalytics`** — literally "stack" +
"quiz" + "analytics", reflecting that it now covered both source
plugins' territory. Concretely:

- Quiz Analytics's own code (data fetcher, API client, cache helper,
  ~29 analytics/chart/PDF classes, vendored TCPDF/KaTeX/Plotly) moved
  into `classes/quiz/`, namespace `local_stackquizanalytics\quiz\...`.
- Model & Diagnostics Analytics's code (indicators, targets, analysers,
  diagnostics reports, dashboard renderer, PDF system) moved into
  `classes/stack/`, namespace `local_stackquizanalytics\stack\...`.
- A brand-new `classes/section_selector.php` — the one genuinely new
  piece of UI the merge itself added — gave the combined plugin a
  "Section:" switcher between "Quiz Analytics" and "Model & Diagnostics
  Analytics".
- The GitHub repository was itself named to match:
  `moodle-local_stackquizanalytics` (`[2.0.0]`'s entry notes it was
  renamed to this from an even earlier working name, `moodle_analytics`).

This pre-rename, pre-restructure codebase is also what some contributor
branches were built against — e.g. `juma_test_branch_personal`, which
still lives on today's `origin` but was forked from this old layout
before the merge/renames above and before the later `classes/quiz/`
restructuring. Porting work from a branch like that onto current `main`
means mapping old paths (`classes/analytics/`, `classes/api_client.php`)
to their current locations (`classes/quiz/analytics/`,
`classes/quiz/api_client.php`) — not a plain cherry-pick.

## First rename: `local_stackanalytics` (a mistake)

Uploading the merged plugin to the Moodle Plugins directory failed
validation: *"the frankenstyle component name in the uploaded plugin
does not match."* The assumption at the time (`CHANGELOG.md`'s
`[2.2.0]` entry) was that this merged plugin should replace the
*`local_stackanalytics`* listing specifically, since that was the
source plugin whose name it most resembled. So the component was
renamed throughout — `version.php`, every class namespace, the
capability id, legacy global-namespace class prefixes, cache/Analytics
API identifiers, every hardcoded URL, the privacy provider, all three
doc files — to **`local_stackanalytics`**.

## Second rename: `local_quizanalytics` (the correction)

This turned out to be the wrong target. Checking the actual Marketplace
submission page directly (plugin id 3995,
`marketplace.moodle.com/plugins/3995`) showed it was registered as
**`local_quizanalytics`**, not `local_stackanalytics` — its *display
title* ("STACK Analytics") just happened to read like the latter, which
is what caused the mix-up. `CHANGELOG.md`'s `[2.3.0]` entry documents
the full correction: every identifier renamed a second time, from
`local_stackanalytics` to `local_quizanalytics`, through the same list
of files `[2.2.0]` had already touched once. This rename touched more
prose than the first one, because `local_quizanalytics` collides with
the name of the *other* original source plugin (the standalone
Quiz Analytics one this merge's `classes/quiz/` subsystem was ported
from) — several docblocks that used to name both source plugins side by
side needed hand-fixing so they didn't read as naming the same plugin
twice.

**This is the name that stuck.** `local_quizanalytics` is the current,
correct frankenstyle component — confirmed to match the live Marketplace
listing it replaces, not just an internal preference. The merged plugin
ships as a new major version of that existing listing rather than a
separate submission.

Two more naming details worth knowing:

- **Display name vs. component name are independent.** The on-screen/PDF
  display name went through its own history — "STACK Quiz & Model
  Analytics" during the merge, then "STACK Analytics" — before
  `[2.3.3]` renamed it to the current **"STACK q-type Analytics"**, a
  display-string-only change that never touched the frankenstyle
  component, capability id, or any namespace.
- **`CHANGELOG.md` deliberately narrates each entry using whatever name
  was actually true at that point in time** — an entry from before
  `[2.3.0]` will call the plugin `local_stackquizanalytics` or
  `local_stackanalytics`, not retroactively corrected to
  `local_quizanalytics`, so the history stays an accurate record of what
  the code was actually called when each change shipped.

## After the rename: feature-level evolution

With naming settled, the plugin kept evolving as one codebase:

- **`[2.1.0]` — split into four sections.** The two merged pages each
  did double duty (Quiz Analytics mixed course-wide and per-quiz
  reporting behind a quiz picker; Model & Diagnostics mixed two real ML
  models with a non-ML dashboard behind a View: selector). Split into
  four independently-reachable sections, each with its own PDF export:
  **Quiz Analytics** (`index.php`, course-wide only), **Question
  Analytics** (`questionanalytics.php`, new — the per-quiz drill-down),
  **Model Analytics** (`modelanalytics.php`), **Diagnostics Analytics**
  (`diagnosticsanalytics.php`, new). `classes/section_selector.php` grew
  from a 2-way to a 4-way switch. Purely a page/routing split — no
  report-builder, indicator, or PDF-content class changed.
- Large-course performance work (`[2.3.2]`, `[2.4.0]`–`[2.4.15]`):
  fixing real Cloudflare 524 timeouts, batched query rewrites, a
  scheduled cache-warming task, host-adaptive parallel fetching — proving
  the merged plugin out against real courses with tens of thousands of
  attempts, not just the small courses the merge itself was verified
  against.
- **`[3.0.0]`** (current `main`): ported a contributor's simplification
  of per-quiz Question Analytics (six sections down to one, plus a
  temporarily-hidden Solution Process view), restored real HTML
  rendering to the Question Review drill-down, hid Diagnostics Analytics
  from the section switcher (code intact, still reachable by direct
  URL), and linked Model 1 student names to their Moodle profiles. The
  underlying `classes/quiz/analytics/*` computation classes this
  simplification stopped calling (`difficulty.php`, `question_metrics.php`,
  `prt_analysis.php`, `response_analysis.php`) are all still present and
  correct — only the on-screen assembly layer stopped surfacing them,
  which is exactly what the (since-reverted) Research Data Export
  feature existed to pull back out uncapped.

## Quick reference: what to call things now

| | Current, correct value |
|---|---|
| Frankenstyle component | `local_quizanalytics` |
| Display name (`pluginname`) | "STACK q-type Analytics" |
| GitHub repo | `ernestwting/moodle-local_quizanalytics` |
| Replaces Marketplace listing | plugin id 3995 (originally the standalone `local_quizanalytics`) |
| Class namespaces | `local_quizanalytics\quiz\...` (Quiz/Question Analytics side), `local_quizanalytics\stack\...` (Model/Diagnostics side) |
| Capability | `local/quizanalytics:view` (plus `local/quizanalytics:exportresearchdata` if that feature is ever re-added) |

If you're reading old code, commits, or a contributor's branch and see
`stackquizanalytics` or `stackanalytics` anywhere — component name, class
namespace, capability string, repo name — that's not a bug or a typo,
it's this history: the plugin genuinely was called that at the time.
