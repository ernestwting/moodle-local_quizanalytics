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
