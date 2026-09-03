\[ [STACK q-type Analytics Docs](index.md) → Installation \]

# Installation

This is a single Moodle plugin — installing `local_quizanalytics` is the
whole install. Every section runs entirely in-process; there is no
separate service to deploy, and nothing here ever talks to the public
internet (see [Privacy & Security](privacy-and-security.md)).

For the exhaustive version of everything on this page — full rationale,
every setting's own docblock reference, and a longer troubleshooting
table — see [`INSTALL.md`](../../INSTALL.md) at the repo root. This page
is the condensed walkthrough.

## Prerequisites

- Admin access to the Moodle site, plus shell/SFTP access to the Moodle
  codebase (not just the web UI).
- Moodle 4.0+ (`version.php`'s `requires`).
- `mod_quiz` (core) and **`qtype_stack`** installed — this plugin is a
  no-op without STACK questions to analyze.
- **Moodle cron running on a real schedule.** This is a hard requirement,
  not a performance nice-to-have: the cache-warming scheduled task and the
  on-demand background-compute safeguard for large courses both depend on
  `php admin/cli/cron.php` actually running. Without it, a large
  quiz/course's Analytics page shows "generating in the background" and
  **never resolves.** Confirm cron is running (Site administration →
  Server → Scheduled tasks should show recent "Last run" times) before
  relying on this plugin for anything beyond a small course.

## 1. Place the files

This repository's own root **is** the plugin — a plain "Download ZIP" of
this repo can go straight into Moodle's plugin uploader.

- **Easiest:** Site administration → Plugins → Install plugins → upload a
  zip of this repository's contents.
- **Shell/SFTP:**
  ```bash
  cp -r . <moodleroot>/local/quizanalytics
  chown -R www-data:www-data <moodleroot>/local/quizanalytics
  ```

Either way, the folder must land at exactly
`<moodleroot>/local/quizanalytics` — Moodle derives the component name
from that path. Plotly.js, KaTeX, and TCPDF are already vendored inside
the plugin; no separate download step is needed.

## 2. Run the Moodle upgrade

Log in as an admin and visit Site administration. This one step:

- Registers the `local/quizanalytics:view` capability.
- Registers the Quiz Analytics/Question Analytics caches.
- Registers **Model 1** ("Student at risk in a STACK-based course") and
  **Model 2** ("STACK question/PRT needs review") — both created
  **disabled**.
- Registers the "Warm STACK q-type Analytics result caches" scheduled task
  (every 15 minutes).

## 3. (Optional) Review performance settings

**Site administration → Plugins → Local plugins → STACK q-type
Analytics.** Most of these auto-configure themselves against your actual
server on first install/upgrade — review, don't feel you need to
hand-tune them on a normal install:

| Setting | Default | What it does |
|---|---|---|
| Detected server resources | (readout) | Detected CPU cores/RAM and the cache-warming worker count calculated from them. "Re-detect and apply now" if the server's hardware changes later. |
| Cache-warming parallel workers | auto-detected | How many worker processes the scheduled task forks to fetch several quizzes concurrently (CLI/cron only). |
| Cache-warming worker memory limit | 2048 MB | PHP memory limit per worker. Size `workers × this` comfortably under real available RAM. |
| On-demand background-compute time budget | 20s | If a cold-cache request is estimated to exceed this, it's handed to a background task instead of blocking. Set to `0` to always compute inline. |
| Computation time limit | 120s | Raises PHP's own execution-time limit for the course-wide view and PDF export only — doesn't help a reverse-proxy timeout in front of your site. |

## 4. Review and enable the Model Analytics models

**Site administration → Analytics → Models.** Both models appear here,
disabled by default. Before enabling either:

- Review the thresholds on the same settings page as step 3:
  **Question-needs-review pass-rate threshold** (default `0.5`),
  **Bloated-tree "low traffic" floor** (default `2`), **Help-seeking
  lookback window** (default `3600` seconds).
- Read the proxy-label circularity caveat on Model 2 in
  [How Everything Is Calculated](calculations.md) before relying on its
  predictions.
- A model needs training data before it predicts anything — use
  **Evaluate**/**Get predictions** on the model's own page once enabled.
- If those actions are missing, Moodle's **Restrict processing to CLI
  only** analytics setting (`onlycli`) is on — predictions still happen
  automatically via cron either way.

## 5–7. Test it

1. Open a course with a STACK quiz that has a finished attempt. Find
   **Analytics** in the course's secondary navigation (check **More** if
   the bar is full).
2. You land on **Quiz Analytics**. Use the **Section:** switcher to reach
   **Question Analytics** and **Model Analytics**. **Diagnostics
   Analytics** isn't in that switcher yet — reach it directly at
   `local/quizanalytics/diagnosticsanalytics.php?id=<courseid>`.
3. Open a STACK quiz directly — its settings/administration menu should
   also have an **Analytics** entry, jumping straight to that quiz's
   Question Analytics.
4. Confirm a student account gets a standard permission-denied error on
   any of this plugin's pages — this plugin is teacher/manager-only by
   design (see [Privacy & Security](privacy-and-security.md)).
5. Once a model is enabled and has run at least once, check **Site
   administration → Analytics → Insights** for predictions.

## How do I know it's working

1. **Site administration → Server → Scheduled tasks**: "Warm STACK q-type
   Analytics result caches" should show a recent **Last run**.
2. **Site administration → Server → Tasks → Task logs**: filter for
   `local_quizanalytics`, confirm runs complete (not fail).
3. This plugin's own settings page shows no cron-status warning banner,
   and "Detected server resources" shows real numbers.
4. On a course/quiz large enough to exceed the background-compute budget,
   you should see "generating in the background," not a timeout — and the
   real report a short while later on revisit.

## Tested scale

Benchmarked against a real 38-quiz, 48,445-attempt production course, and
a synthetic 50 quizzes × 1,000 students dataset. See
[Getting Started § Tested at](getting-started.md#tested-at) and
[`CHANGELOG.md`](../../CHANGELOG.md) for the specific numbers.

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| "Analytics" doesn't appear anywhere | Plugin not installed, or no STACK quiz/finished attempts |
| "No attempts yet" | No `state = finished` attempts for the quiz |
| A large course's view or PDF export times out | Raise **Computation time limit** (step 3) |
| Page 524s / times out on a large course | A reverse proxy/CDN in front of the site is giving up first, not PHP — confirm cron is actually running so the cache stays warm ahead of real visitors |
| "Generating in the background" never resolves | Cron isn't running, or the background task crashed/is stuck retrying — check the settings page's cron-status banner, then Task logs |
| Charts blank / JS console errors | Check for a 404 on `js/vendor/plotly.min.js` or `js/vendor/katex/*` — the plugin folder wasn't copied completely |
| Math renders as literal `\(...\)` text | `js/vendor/katex/fonts/` didn't come along — re-copy the whole `js/vendor/katex/` folder |
| A model's Actions menu is missing Evaluate/Get predictions | `onlycli` analytics setting is on — see step 4 |

Full table, with more rows: [`INSTALL.md`](../../INSTALL.md#troubleshooting-quick-reference).
