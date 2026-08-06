# Marketplace compliance check — reusable prompt

Copy everything below this line and paste it as a message to Claude Code
whenever you've made changes to this plugin and want a full compliance pass
before pushing / before a Marketplace update.

---

I've made changes to the `local_quizanalytics` Moodle plugin in this repo
(`moodle-local_quizanalytics`). Before we're done, run the full verification
+ Moodle Marketplace compliance pass described below, fix whatever is
fixable, and give me a clear report of what's done vs. what still needs my
decision or a manual step. Standing rules for this repo:

- Every commit must be authored as `Ernest Ting <et26934@gmail.com>` (use
  `git commit --author=...`) with **no** `Co-Authored-By: Claude` trailer.
- Verify real behavior before committing — don't just eyeball a diff. This
  plugin has an established oracle-regression pattern (see below); use it.
- Push after each logically-complete fix, not one giant commit at the end,
  so I can check in as we go.
- If something is a large, genuinely optional undertaking (not a hard
  blocker), tell me the real scope and let me decide — don't silently do
  hours of mechanical work, and don't silently skip it either.

## 1. Functional verification (do this first, before the compliance pass)

The plugin runs inside a Docker container named `moodle-moodle-1` (check
`docker ps`; if it's not running or doesn't exist, tell me — the rest of
this depends on it). Deploy and test:

```bash
docker exec moodle-moodle-1 rm -rf /var/www/html/local/quizanalytics
docker cp local/quizanalytics moodle-moodle-1:/var/www/html/local/quizanalytics
docker exec moodle-moodle-1 php /var/www/html/admin/cli/purge_caches.php
```

- **Syntax**: `php -l` every `.php` file (including `classes/vendor/`).
- **Regression**: there's a standing harness at `/tmp/full_regression.php`
  inside the container (real quiz/course data, hashes the JSON output of
  every analytics view + PDF content assembly). If it's missing, ask me
  which real quiz/course IDs to use and rebuild it — the pattern is: call
  each `classes/analytics/*.php` entry point (`question_analysis::
  build_analysis()`, `course_analysis::build_analysis()`,
  `solution_process_analysis::build_meta()`/`build_analysis()`,
  `pdf_content::build_*_content()`) with real records fetched via
  `local_quizanalytics_data_fetcher`, `json_encode` + `sha256` each result,
  and diff against a baseline captured before your changes.
- If you touched PDF rendering, also regenerate a real PDF via
  `pdf_builder::build()` and confirm it's still a valid multi-page PDF
  (`file` command, or PyMuPDF/pypdf if available) — not just that it didn't
  throw.

## 2. Local PHPCS (moodle-cs) — fast local check before pushing

There's already a working PHPCS 3.13.5 + moodle-cs setup at
`/tmp/phpcs-tools/` inside `moodle-moodle-1`
(`phpcs_313.phar`, cloned `moodle-cs/`, its Composer deps installed,
`installed_paths` registered). Reuse it — don't reinstall:

```bash
docker cp local/quizanalytics moodle-moodle-1:/tmp/qa_check/local/quizanalytics
docker exec moodle-moodle-1 php /tmp/phpcs-tools/phpcs_313.phar \
  --standard=moodle --extensions=php --ignore="*/vendor/*" \
  --report=summary /tmp/qa_check/local/quizanalytics
```

**Important**: the scratch copy must be placed at a path ending in
`local/quizanalytics` (matching the real component path) — moodle-cs's
`PackageSniff` infers the expected `@package` name from the containing
directory name, so a differently-named scratch dir produces false
`@package` mismatch findings.

Expected clean state: **0 errors**, except two known, deliberate,
unfixable exceptions in `quizanalytics_tcpdf.php` (`Header()`/`Footer()`
keep TCPDF's own PascalCase names — it calls them back by that exact name,
so lower-casing them would silently break PDF rendering; both are
suppressed with an inline `// phpcs:ignore
moodle.NamingConventions.ValidFunctionName.LowercaseMethod` comment on the
function line itself — **not** a separate line above it, that breaks
PHPCS's docblock-to-function association and produces a different false
error). If PHPCS reports anything else, that's real — fix it.

## 3. GitHub Actions CI (moodle-plugin-ci)

Workflow lives at `.github/workflows/moodle-ci.yml`, based on
`moodlehq/moodle-plugin-ci`'s `gha.dist.yml`, tested across PHP
8.1/8.2/8.3 × PostgreSQL/MariaDB. After pushing, check the Actions tab (or
`curl -s "https://api.github.com/repos/ernestwting/moodle-local_quizanalytics/actions/runs?per_page=1"`
— unauthenticated GitHub API is rate-limited to 60 req/hour per IP, pace
yourself, and use `check-runs/<job_id>/annotations` for error text since
job-log download needs admin rights this session won't have).

**You cannot install `moodle-plugin-ci` inside `moodle-moodle-1` to test
locally** — that container can reach `github.com` but not
`repo.packagist.org` (Composer's registry), so `composer create-project
moodlehq/moodle-plugin-ci` will hang and time out. If you need to
understand a moodle-plugin-ci command's exact behavior, `WebFetch` its
source directly from `raw.githubusercontent.com/moodlehq/moodle-plugin-ci/main/...`
rather than trying to run it.

Known gotchas already solved once (if the workflow file still has these,
good — if a well-meaning "simplification" removed them, that's a
regression):

- The plugin lives nested at `local/quizanalytics/`, not at the repo
  root — `moodle-plugin-ci install --plugin` must point at
  `./plugin/local/quizanalytics`, not `./plugin`.
- Each individual check subcommand (`phplint`, `phpcs`, `phpdoc`,
  `mustache`, ...) needs its OWN `<plugin>` argument — `install`'s
  `--plugin` flag doesn't persist across the separate shell invocations
  each GitHub Actions step runs in. Set `PLUGIN_DIR` as a job-level env var
  (`echo "PLUGIN_DIR=$(pwd)/plugin/local/quizanalytics" >> $GITHUB_ENV`)
  once, right after checkout — every subcommand defaults its argument from
  that env var.
- `thirdpartylibs.xml`'s `<location>` tag must hold exactly ONE glob
  pattern per `<library>` entry (e.g. `js/vendor/katex/*`) — moodle-plugin-ci's
  `Vendors.php` calls `glob()` directly on that string to build the
  vendor-code exclude list several checks use; a comma-separated list of
  paths in one tag breaks that `glob()` call outright.
- The Moodle PHPDoc Checker step (`phpdoc`) is intentionally
  `continue-on-error: true` — it's a separate, stricter tool from moodle-cs
  that wants an explicit `@param` for every parameter of every function;
  this codebase relies on type-hinted signatures instead almost
  everywhere, which is not what Moodle's actual coding-style checklist
  (satisfied via moodle-cs) requires. Don't "fix" this by trying to make it
  blocking again without checking with me first — it's a large, deliberate
  scope decision (last measured at ~150-200 findings across the whole
  plugin).
- Grunt and Behat steps were deliberately dropped from the upstream
  template — no `package.json`/Gruntfile (vendored pre-built JS, no build
  step) and no `tests/behat/*.feature` files exist. Add them back only once
  there's something for them to actually check.

## 4. Full Marketplace requirements checklist

Go through every item below. For each: state whether it's ✅ compliant
(with brief evidence), 🔧 fixed just now, or ⚠️ a real gap — and for any
gap, whether it's small enough to just fix, or large enough that I should
decide. Don't mark something ✅ without actually checking it against the
current code — this list gets stale as the plugin changes.

**Metadata**
- [ ] Short + full description exist (`MARKETPLACE_LISTING.md` at repo
      root), in English, and the same info also appears in `README.md`.
- [ ] `version.php`'s `$plugin->requires` states a Moodle version that is
      still actively maintained, OR the plugin demonstrably works on at
      least one currently-maintained version per CI (check
      moodledev.io/general/releases for the current list — it changes
      over time; as of this writing 4.5/5.0/5.1/5.2 are maintained, 4.0-4.4
      are EOL).
- [ ] Repo name follows `moodle-{plugintype}_{pluginname}` —
      `moodle-local_quizanalytics`. ✅ already correct, don't rename.
- [ ] Source repo URL is current everywhere it's referenced
      (`MARKETPLACE_LISTING.md`, `README.md`) — check for stale URLs after
      any repo rename.
- [ ] Issue tracker URL exists and GitHub Issues is actually enabled
      (`curl -s "https://api.github.com/repos/ernestwting/moodle-local_quizanalytics" | grep has_issues"` —
      **required**, not optional, for Marketplace approval).
- [ ] Documentation URL exists (README is fine).
- [ ] Screenshots — **manual, mine to do**, don't attempt.

**Licensing**
- [ ] `LICENSE` file present, GPL v3 or later.
- [ ] `thirdpartylibs.xml` declares every vendored library (currently
      Plotly.js, KaTeX, TCPDF) with correct `<location>` glob, name,
      version, license, repository, copyright.

**Subscriptions/external services**
- [ ] N/A check: confirm the plugin still makes zero external network
      calls and has no paid/subscription component — if that's ever
      changed, this section needs real content (what service, what
      breaks without it, how to get credentials).

**Usability**
- [ ] Installs from a ZIP with no Composer/npm/build step — confirm no
      `composer.json`/`package.json` was introduced.
- [ ] Any dependency on another plugin is declared in THREE places:
      `version.php`'s `$plugin->dependencies`, `MARKETPLACE_LISTING.md`,
      and `README.md`. (Currently: `mod_quiz` and `qtype_stack`.)

**Functionality**
- [ ] No PHP warnings/notices with `$CFG->debug = DEBUG_DEVELOPER` — if
      you can get a real browser session against `moodle-moodle-1`, click
      through course-wide view, both Question Analytics and Solution
      Process Visualization for a real quiz, and PDF export for each, and
      check the debug output/browser console.

**Cross-database compatibility**
- [ ] CI's PostgreSQL matrix jobs pass (this is the actual live proof —
      code review alone isn't sufficient).
- [ ] Any NEW raw SQL added since the last check uses `{table}`
      curly-brace syntax and parameterized placeholders — grep for
      `get_record_sql\|get_records_sql\|execute(` and read each one.

**Coding**
- [ ] moodle-cs PHPCS is clean (see section 2 above).
- [ ] All comments/identifiers in English.
- [ ] GPL boilerplate header present on every new `.php` file (copy the
      header from any existing file — it's the standard 13-line notice).
- [ ] `@copyright 2026 Ernest Ting <eting@caltech.edu>` and `@license` tags
      on every new file's docblock.
- [ ] No plugin-authored `styles.css` with unnamespaced selectors — if one
      gets added, every selector must be scoped under a `.path-local-quizanalytics`-style
      prefix, never a bare generic class name.
- [ ] No new global-scope (non-namespaced, non-frankenstyle-prefixed)
      classes/functions/constants — grep `^class \|^function \|^define(`
      across the plugin and confirm every hit is either
      `local_quizanalytics_*`-prefixed or inside a
      `namespace local_quizanalytics\...` block.
- [ ] Any new admin setting uses `admin_setting_config*` (never raw
      `$CFG`/direct table writes) and is named
      `local_quizanalytics/settingname` (with the slash) in `settings.php`.
- [ ] No hardcoded user-facing English text — **known existing gap**: as of
      the last full audit, ~40-60 section titles/captions/chart titles
      across `classes/analytics/*.php` are hardcoded string literals
      rather than `get_string()` calls (a deliberate, disclosed, non-blocking
      trade-off — not an approval blocker per the checklist, but worth
      revisiting). Don't silently "fix" this at scale without asking me
      first — it's a large mechanical change (new lang strings for every
      title). DO make sure any NEW section/chart title you add follows the
      existing pattern of the surrounding code (i.e. don't make the
      inconsistency worse without at least flagging it).
- [ ] `lang/en/*.php` stays pure data (`$string['id'] = '...';` only, no
      PHP logic beyond that), no leading/trailing whitespace in values, and
      uses sentence case (capitalize only the first word + genuine proper
      nouns/acronyms like STACK/PDF) — not Title Case.

**Privacy**
- [ ] `classes/privacy/provider.php` still implements
      `\core_privacy\local\metadata\null_provider` correctly — if the
      plugin starts storing ANY new personal data (not just reading through
      to mod_quiz/question engine/core_user), this needs a real metadata
      provider instead, not the null one. Verify via:
      `\core_privacy\manager::component_is_compliant('local_quizanalytics')`
      returning `true` in a CLI script against `moodle-moodle-1`.

**Security**
- [ ] `grep -rn '\$_GET\|\$_POST\|\$_REQUEST\|\$_COOKIE'` — should be empty
      (use `required_param()`/`optional_param()` with explicit `PARAM_*`
      types instead).
- [ ] `grep -rn "eval(\|unserialize(\|call_user_func"` — should be empty.
- [ ] Every entry point (`index.php`, `pdf.php`, any new one) calls
      `require_login($course)` then `require_capability('local/quizanalytics:view', $context)`
      before doing anything else.
- [ ] Any NEW state-changing action (not just reading/displaying data)
      checks `sesskey` — note the EXISTING colorblind/anonymize preference
      toggles do NOT currently check sesskey (they call
      `set_user_preference()` off a plain GET request); this was assessed
      as low-severity (a cosmetic display preference, not data-mutating in
      any meaningful sense) and left as-is, but a stricter reviewer could
      flag it. If you add anything that mutates real data via a request,
      it must check sesskey.

**Approval blockers (re-check these explicitly, they're binary)**
- [ ] Public, accessible issue tracker — yes (GitHub Issues, confirmed
      enabled).
- [ ] Works with PostgreSQL — yes (CI-verified).
- [ ] No namespace collisions — yes (verified above).
- [ ] Security guidelines followed — see Security section.
- [ ] Privacy API compliant if handling personal data — yes (null_provider,
      verified via core's own compliance check).
- [ ] N/A: not an activity module, no backup/restore API needed.
- [ ] N/A: no external payment flows, no third-party advertising.
- [ ] N/A: doesn't replicate Moodle Workplace functionality.
- [ ] No Moodle trademark misuse in naming/branding.

## 5. When you're done

- Push every fix as its own commit (correct author, no Claude
  co-author), in the order you made them — don't squash into one commit
  at the end.
- Give me a short final summary: what changed, what's still open (with
  real scope estimates for anything large), and what — if anything — needs
  a decision from me before the next Marketplace submission/update.
