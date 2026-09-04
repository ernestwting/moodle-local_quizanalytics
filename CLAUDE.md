# Commit policy

Never add a `Co-Authored-By: Claude` (or any Anthropic/Claude) trailer to
commit messages in this repo, and never set the commit author/committer to
Claude. All commits must be authored solely by the human contributor who
made them.

This has been the standing instruction since at least 2026-08-19. It was
violated twice despite that (commits that shipped with the trailer were
found and rewritten out of `main`'s history on 2026-08-29 — see
`git log` around that date if the context is ever needed). Treat this file,
not memory of a past session, as the source of truth going forward.

Only commit (or push) when explicitly asked in that session. Finishing a
task — even a large one — is not by itself a request to commit; leave
changes in the working tree and say what's ready, then wait to be told to
commit.

# Documentation update policy

When a change is big enough to affect what a user/instructor or the
Marketplace listing would need to know — a new feature, a section moving
in/out of the nav, a changed default, a behavior change worth a
release note — update the relevant docs as part of that same piece of
work, not as a separate follow-up someone has to remember to ask for:

- [`README.md`](README.md) — if what's included or the architecture
  summary changed.
- [`MARKETPLACE_LISTING.md`](MARKETPLACE_LISTING.md) — if the long
  description, what's-included list, or a release-notes-worthy change
  happened.
- [`docs/guide/`](docs/guide/index.md) — if instructor-facing behavior,
  installation steps, or a calculation/formula changed. See
  `docs/guide/calculations.md` in particular for anything touching how a
  statistic, indicator, or model is computed. **This folder is the source
  of truth for the published documentation site** (see
  `docs/guide/index.md`'s own "Published site" section) — a scheduled
  GitHub Action in the
  [`moodle-local_quizanalytics_documentation.github.io`](https://github.com/ernestwting/moodle-local_quizanalytics_documentation.github.io)
  repo pulls this folder's Markdown automatically every few hours and
  republishes it, so an edit here reaches the live site on its own once
  it's committed to `main` — no separate step needed in that other repo.
  Keep math formulas in real `$$...$$`/`$...$` LaTeX (not just prose),
  since that's what carries through the sync to render on the site.

**Do not** touch these for a small, self-contained bug fix (wrong string,
off-by-one, a fixed regression back to already-documented behavior) —
only for changes big enough that a reader relying on the current docs
would otherwise be misled or missing something real.
