\[ [STACK q-type Analytics Docs](index.md) → Glossary \]

# Glossary

| Term | Meaning |
|---|---|
| **STACK** | System for Teaching and Assessment using a Computer algebra Kernel, a Moodle question type that grades mathematics by evaluating a student's algebraic answer with a CAS (Maxima), rather than matching text. |
| **PRT** | Potential Response Tree, the decision logic inside a STACK question that compares a student's answer against various tests and assigns a mark and feedback based on which branch of the tree the answer lands on. |
| **Seed** | A random number Moodle assigns to an attempt at a randomized STACK question, determining which specific instantiated values (and therefore which "version") of the question a student receives. |
| **Facility Index** | Moodle's own built-in measure of how easy a question was in practice, essentially its average score across attempts, on a 0 to 1 scale. |
| **Pool A / Pool B** | This plugin's own internal terms for two different sets of response rows: Pool A is every attempt (participation); Pool B is one best attempt per student (performance). See [How Everything Is Calculated § Two response pools](calculations.md#two-response-pools). |
| **Indicator** | In Moodle's Analytics API, a single normalized signal (bounded to `[-1, 1]`) that feeds into a prediction model, but is not itself the thing being predicted. |
| **Target** | In Moodle's Analytics API, the actual outcome a model is trained to predict: in this plugin, either "will this student's grade fall below the pass threshold" (Model 1) or "does this question's pass rate suggest it needs review" (Model 2). |
| **Diagnostic report** | A statistical calculation (seed bias, PRT branch coverage) deliberately kept outside the Analytics API's machine-learning pipeline, because it has no natural ground-truth label to predict against. |
| **Tree edit distance (Zhang and Shasha)** | A standard algorithm for measuring how different two tree structures are, by counting the minimum number of node insertions, deletions, and renames needed to turn one into the other. |
| **Cohen's *d* / η² bands** | Conventional thresholds (Jacob Cohen, 1988) for calling an effect size negligible, small, medium, or large, used here to interpret the seed-bias ANOVA's η² without needing an exact *p*-value. |

Full context for every term above: [How Everything Is Calculated](calculations.md).
