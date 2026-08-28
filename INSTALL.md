# Installing STACK q-type Analytics

This is a single Moodle plugin — installing `local_quizanalytics` is
the whole install. All four sections (Quiz Analytics, Question Analytics,
Model Analytics, Diagnostics Analytics) run entirely in-process; there's no
separate service to deploy and nothing here ever talks to the public
internet.

## Prerequisites

- Admin access to the Moodle site, plus shell/SFTP access to the Moodle
  codebase (not just the web UI).
- Moodle 4.0+ (`version.php`'s `requires`; raise or lower it if your target
  Moodle needs a different floor).
- `mod_quiz` (core, always present) and `qtype_stack` installed — this
  plugin is a no-op without STACK questions to analyze.
- **Moodle cron running on a real schedule — a hard requirement, not just
  a performance nice-to-have.** This plugin's cache-warming scheduled task
  (step 2 below) and its on-demand background-compute safeguard (a quiz or
  course too large to analyze inline hands the work to a queued adhoc task
  instead of blocking the request — see step 5) both depend entirely on
  `php admin/cli/cron.php` actually running, same entry point either way.
  Without it, a large quiz/course's Quiz/Question Analytics page shows a
  "generating in the background" notice that **never resolves** — not a
  bug in the page, the compute really is just queued and waiting for
  something to run it. Confirm cron is wired up before relying on this
  plugin for anything beyond a small course: **Site administration →
  Server → Scheduled tasks** should show recent "Last run" times (not
  blank/very old) for tasks in general, **Site administration → Server →
  Tasks → Task logs** should show `local_quizanalytics` task runs actually
  completing, and this plugin's own settings page (**Site administration →
  Plugins → Local plugins → STACK q-type Analytics**) shows a warning
  banner if Moodle's own last-cron-run time looks stale.
  A typical hosting setup runs cron via a system crontab entry hitting
  `admin/cli/cron.php` every minute; this repo's own local Docker dev
  environment runs it as a separate `moodle-cron` service in
  `docker-compose.yml` for the same reason.

## 1. Place the files

This repository's own root **is** the plugin (`version.php`, `classes/`,
`lang/`, etc. sit directly here) — a plain GitHub "Download ZIP" of this
repo can go straight into Moodle's plugin uploader.

**Option A — Moodle's own plugin installer (easiest, no shell access
needed):** Site administration → Plugins → Install plugins → upload a zip
of this repository's contents. Moodle places it at the right path
(`local/quizanalytics/`) itself.

**Option B — shell/SFTP, for admins who prefer it:**

```bash
# From a clone/extract of this repo:
cp -r . <moodleroot>/local/quizanalytics
chown -R www-data:www-data <moodleroot>/local/quizanalytics
# (use whatever user your web server actually runs as)
```

Either way, the folder Moodle sees it at must be exactly
`<moodleroot>/local/quizanalytics` — Moodle derives the component name
`local_quizanalytics` from that path. Plotly.js, KaTeX, and TCPDF are
already vendored inside `js/vendor/` and `classes/quiz/vendor/`; no separate
download step is needed.

## 2. Run the Moodle upgrade

Log in as an admin and visit **Site administration** (or
`<yoursite>/admin/index.php` directly if the upgrade screen doesn't appear
on its own). This single step:

- Registers the `local/quizanalytics:view` capability (`db/access.php`).
- Registers the Quiz Analytics/Question Analytics cache areas
  (`db/caches.php`).
- Registers both Model Analytics prediction models —
  **Student at risk in a STACK-based course** (Model 1) and **STACK
  question/PRT needs review** (Model 2) — from `db/analytics.php`, via
  `\core_analytics\manager::update_default_models_for_component()`. Both
  are created **disabled**; see step 4.
- Registers the **Warm STACK q-type Analytics result caches** scheduled task
  (`db/tasks.php`, every 15 minutes) — proactively recomputes the Quiz
  Analytics/Question Analytics result cache for any course whose entry is
  missing or stale, so a real visitor essentially never has to wait through
  a cold compute themselves.
- Also relies on Moodle's **adhoc task** mechanism (no separate
  registration needed — adhoc tasks queue dynamically) for
  `warm_single_view_adhoc_task`: when a visitor hits a cold cache on a
  quiz/course above the background-compute threshold, this queues one of
  these instead of computing inline. Both this and the scheduled task
  above run only via cron (`php admin/cli/cron.php`, or whatever your
  hosting's cron job/systemd timer runs) — see the Prerequisites section
  above, this is a hard requirement for this plugin now, not just a
  performance benefit.

## 3. (Optional) Review the performance/sizing settings

**Site administration → Plugins → Local plugins → STACK q-type Analytics.**
Most of these are auto-configured against your actual server the first time
this plugin is installed or upgraded onto it — this step is about knowing
they exist and reviewing them, not something you need to configure by hand
on a normal install.

- **Detected server resources** (a readout, not a setting) — shows this
  server's own detected CPU core count and RAM, and the "Cache-warming
  parallel workers" value calculated from them. Set automatically on
  install/upgrade (see `classes/task/resource_detector.php`'s own
  docblock for exactly how); press **Re-detect and apply now** if this
  server's hardware changes later (e.g. moving from a smaller box to a
  larger one, or the reverse) — nothing re-runs this automatically outside
  of install/upgrade. If detection fails (an unusual host or a restricted
  container that doesn't expose `/proc` or cgroup limits), this falls back
  to a conservative static default and says so plainly rather than leaving
  the setting blank.
- **Cache-warming parallel workers** — how many worker processes the
  "Warm STACK q-type Analytics result caches" scheduled task forks to
  fetch several quizzes concurrently (CLI/cron only; the on-demand web
  pages always fetch serially). The detected-resources value above is a
  safe starting point, not a ceiling — raise it by hand if you've confirmed
  your server can handle more (see `CHANGELOG.md` for this session's own
  worker-count benchmarking methodology if you want to do the same).
- **Cache-warming worker memory limit** — the PHP memory limit each worker
  gets. Sized together with the setting above: workers × this value should
  comfortably fit under your server's real available RAM, with room left
  for the database, the Maxima backend, and everything else already
  running.
- **On-demand background-compute time budget** → 20 seconds by default.
  When a visitor hits a cold cache, this plugin times a real, small sample
  fetch (not a fixed attempt-count guess) and extrapolates the full cost;
  if that estimate exceeds this many seconds, the compute is handed to a
  background task instead of run on that request, and the visitor sees a
  "generating in the background" notice. Depends on cron — see
  Prerequisites above. Set to 0 to disable and always compute inline (pre-
  this-feature behaviour).
- **Computation time limit** → 120 seconds by default. This only raises
  *PHP's* own execution-time limit for the Quiz Analytics course-wide view
  and PDF export (the paths whose cost scales with the whole course) — it
  does nothing for a timeout enforced by a reverse proxy/CDN in front of
  your site (e.g. Cloudflare's default ~100s edge timeout, surfaced to a
  visitor as a 524); the background-compute time budget above, backed by
  cron, is what actually keeps a real visitor off that path on a large
  course or quiz.

## 4. Review and enable the Model Analytics models

**Site administration → Analytics → Models.** Both models this plugin
registers appear here, disabled by default (deliberately — see
`db/analytics.php`'s own comment for why). Before enabling either:

- Review the thresholds on the same settings page as step 3:
  **Question-needs-review pass-rate threshold** (default 0.5, Model 2's
  proxy label), **Bloated-tree "low traffic" floor** (default 2, on the
  Diagnostics Dashboard), and **Help-seeking lookback window** (default
  3600 seconds).
- Note the proxy-label circularity caveat on "STACK question/PRT needs
  review" (architecture doc §3.3) before relying on its predictions.
- A model needs training data before it predicts anything — use the
  **Evaluate**/**Get predictions** actions on the model's own page once
  enabled.
- If those actions are missing from a model's **Actions** menu, Moodle's
  **Restrict processing to CLI only** analytics setting (`onlycli`) is on —
  a banner on the Models page says so directly. With it on, predictions
  still happen automatically via the `\core\task\analytics_process_models`
  scheduled task (cron), just not on-demand from the web UI. Either wait for
  cron, force that task from the CLI
  (`php admin/cli/scheduled_task.php
  --execute='\core\task\analytics_process_models'`), or disable `onlycli`
  (Site administration → Analytics → Analytics settings) to get the
  **Evaluate**/**Get predictions**/**Log** buttons back for iterative
  testing — worth switching back on once you're done.

## 5. Test the course-level page

1. Go to a course with **at least one quiz containing a STACK question**
   (added directly to a slot, not pulled in only via "random question from
   category"), with at least one **finished** attempt.
2. Look at the course's secondary navigation bar
   (`Course | Settings | Participants | Grades | Reports | ...`) for an
   **Analytics** entry — check inside **More** too if the bar is full.
3. Click it. You should land on **Quiz Analytics**'s course-wide cross-quiz
   comparison, with a "Section:" switcher at the top linking to the other
   three sections.
4. Click **Question Analytics** in the "Section:" switcher. It defaults to
   the course's first STACK quiz, with a "View:" selector — **Question
   Analytics** is the default view; picking **Solution Process
   Visualization** reloads the page showing that instead. Use the quiz
   dropdown to switch quizzes.
5. Click **Model Analytics** in the "Section:" switcher. You should land on
   Model 1's table; the "View:" selector there switches between Model 1
   and Model 2.
6. Click **Diagnostics Analytics** in the "Section:" switcher. You should
   land on the Diagnostics Dashboard.
7. Try the PDF export button on whichever view is showing, in any section.
8. Confirm the page is correctly **hidden** on a course with no STACK
   quizzes, and that a student account gets Moodle's standard
   permission-denied error if they navigate to
   `local/quizanalytics/index.php?id=<courseid>` (or
   `questionanalytics.php`, `modelanalytics.php`,
   `diagnosticsanalytics.php`) directly.

## 6. Test the per-quiz shortcut

1. Open a STACK quiz directly (not through the course-level page) and find
   its settings/administration menu (the gear icon, or wherever your theme
   surfaces activity settings).
2. You should see an **Analytics** entry there too — it jumps straight to
   Question Analytics's drill-down for this same quiz (step 5.4 above),
   just reached in one click from the quiz itself.

If either entry point doesn't appear at all, check that the quiz actually
has finished attempts and a STACK question added directly to a slot — first
load computes everything fresh (a few seconds); reloading the same page
should be near-instant afterward (cache hit, Quiz Analytics/Question
Analytics only) until a new attempt is submitted.

## 7. Test the prediction models

Once a model is enabled (step 4) and has run at least once (Moodle's own
scheduled task, `\core\task\analytics_process_models`, or the model's own
**Get predictions now** action), check **Site administration → Analytics →
Insights** for generated predictions. If nothing appears, confirm the target
course has enough data — `is_valid_analysable()`/`is_valid_sample()` on both
targets reject courses/samples without enough to work with rather than
producing a misleading prediction.

## 8. How do I know it's working

A short checklist for confirming this plugin's cron-dependent features are
actually functioning, not just installed:

1. **Site administration → Server → Scheduled tasks**: find "Warm STACK
   q-type Analytics result caches" and confirm **Last run** is recent (not
   blank, not hours old) and not stuck retrying.
2. **Site administration → Server → Tasks → Task logs**: filter for
   `local_quizanalytics` and confirm runs show as completed, not failed —
   this also covers `warm_single_view_adhoc_task`, the on-demand
   background-compute safeguard, which only ever shows up here (it's an
   adhoc task, not a scheduled one, so it won't appear in the scheduled
   tasks list above).
3. **This plugin's own settings page** (Site administration → Plugins →
   Local plugins → STACK q-type Analytics): no cron-status warning banner
   at the top, and the **Detected server resources** readout shows real
   numbers (not "could not detect").
4. Visit Quiz Analytics or Question Analytics on a course/quiz large enough
   to exceed the background-compute time budget (step 3): you should see a
   "generating in the background" notice, not a timeout or a blank page —
   and revisiting that same page a short while later should show the real
   report. If that notice is still showing after a genuinely long wait
   (15+ minutes), it says so directly rather than repeating the same calm
   message indefinitely — treat that as a real failure and check steps 1-2
   above.

## Tested scale

This plugin has been directly benchmarked (not just assumed to scale)
against courses ranging from real production data (a 38-quiz, 48,445-
attempt course) up to a synthetic **50 quizzes × 1,000 students** dataset —
see `CHANGELOG.md` for the specific numbers (cold cron warm time, on-demand
single-view time, worker memory) at that scale. Treat that as the
tested ceiling, not a guaranteed limit — a course well beyond it may still
work fine, it just isn't backed by a direct measurement the way everything
up to that point is.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| "Analytics" doesn't appear anywhere, course nav or quiz settings menu | Plugin not installed, or no STACK quiz/finished attempts (both entry points are gated on this) |
| "No attempts yet" / "...has no finished attempts" (Quiz Analytics) | No attempts in `state = finished` for the quiz(zes) in question |
| "Analytics could not be computed for this quiz" | An unexpected error — check Moodle's debugging messages/logs (Site administration → Reports → Logs, or your server's PHP error log) for the underlying exception |
| A large course's course-wide view or PDF export times out | Raise **Computation time limit** in the plugin's settings (see step 3 above) |
| Quiz/Question Analytics 524s (or otherwise times out) on a large course (500+ attempts) | A reverse proxy/CDN in front of the site (e.g. Cloudflare), not PHP, is giving up first — **Computation time limit** won't fix this. Confirm Moodle cron is actually running so the **Warm STACK q-type Analytics result caches** scheduled task can keep the cache warm ahead of real visitors (Site administration → Server → Scheduled tasks); you can also run it once by hand (`php admin/cli/scheduled_task.php --execute='\local_quizanalytics\task\warm_analytics_cache'`) to warm a course immediately rather than waiting up to 15 minutes |
| Quiz/Question Analytics shows "generating in the background" and never resolves, even after a long wait | Cron isn't running at all (most common cause — see Prerequisites above and step 8's checklist), or the background task crashed/is stuck in a fail-delay retry loop. Check this plugin's own settings page for a cron-status warning banner first, then Site administration → Server → Tasks → Task logs for `warm_single_view_adhoc_task` failures |
| Charts blank / JS console errors (Quiz Analytics) | Check the browser console for a 404 on `js/vendor/plotly.min.js` or `js/vendor/katex/*` — those ship inside this repo already, so a 404 usually means the plugin folder wasn't copied completely |
| Math renders as literal `\(...\)` text instead of typeset symbols | KaTeX's CSS/font files (`js/vendor/katex/fonts/`) didn't come along with the rest of `js/vendor/katex/` — re-copy the whole folder |
| Question text shows `@variable@` placeholders or both languages' `[[lang]]` blocks at once | `castext2_qa_processor`/`stack_outofcontext_process` couldn't be loaded — check `qtype_stack` is installed and up to date |
| "STACK q-type Analytics" / Model Analytics or Diagnostics Analytics section shows nothing | The course has no STACK question in any quiz slot (`stack_course_helper::course_has_stack_activity()` gates this) |
| A model shows 0 samples after training/prediction | `is_valid_analysable()` or `is_valid_sample()` rejected the course/sample — check the model's own log in Site administration → Analytics → Models → (model) → Log for the specific reason string |
| A model's **Actions** menu has no **Evaluate**/**Get predictions**/**Log** | `onlycli` analytics setting is on, restricting those to CLI/cron only — see step 4 |
| Seed-bias table says "Not enough attempt data yet" | Fewer than 2 distinct STACK seeds have recorded attempts for that quiz slot yet |
| PRT branch-coverage table is empty for a question | That question's PRT nodes have no non-blank `trueanswernote`/`falseanswernote` set — coverage can't be observed without one |
| "STACK question/PRT needs review" predictions look circular/self-fulfilling | Expected, documented limitation — see the architecture doc's §3.3 and this target's own class docblock |
| CI fails on Code Checker / PHPDoc Checker but PHPUnit passes | Those two steps are `continue-on-error` for now — see `CHANGELOG.md`'s Phase 17/20 entries |
