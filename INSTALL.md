# Installing STACK Quiz Analytics

This is a single Moodle plugin — installing `local_quizanalytics` is the
whole install. There's no separate service to deploy, no server-side
dependency beyond PHP itself, and nothing here ever talks to the public
internet or any other host.

## Prerequisites

- Admin access to the Moodle site, plus shell/SFTP access to the Moodle
  codebase (not just the web UI).
- Moodle 4.0+ (adjust `version.php`'s `requires` value if you're on
  something else — check your target Moodle's own `version.php` for the
  right number).
- `qtype_stack` installed (this plugin analyzes STACK question responses).

That's the whole list — no Docker, no Python, no Composer, no second
process to keep running.

---

## 1. Place the files

```bash
cp -r local/quizanalytics <moodleroot>/local/quizanalytics
chown -R www-data:www-data <moodleroot>/local/quizanalytics
# (use whatever user your web server actually runs as)
```

The folder name must stay exactly `quizanalytics` — Moodle derives the
component name `local_quizanalytics` from that path. Plotly.js, KaTeX, and
TCPDF are already vendored inside `js/vendor/` and `classes/vendor/`; no
separate download step is needed.

## 2. Run the Moodle upgrade

Log in as an admin and go to **Site administration**. Moodle detects the new
plugin and shows the plugins-check/upgrade screen — click through it. This
registers the `local/quizanalytics:view` capability from `db/access.php` and
the cache areas from `db/caches.php`; no separate manual step is needed for
either.

If the upgrade screen doesn't appear automatically, force it by visiting
`<yoursite>/admin/index.php`.

## 3. (Optional) Adjust the computation time limit

**Site administration → Plugins → Local plugins → Analytics**:

- **Computation time limit** → 120 seconds by default. Only relevant for a
  course with many STACK quizzes and/or students — the course-wide view and
  PDF export are the two paths whose cost scales with the whole course
  rather than a single quiz. Raise this if you see a timeout on a large
  course specifically; 0 removes PHP's execution-time limit entirely for
  this plugin's own requests.

## 4. Test the course-level page

1. Go to a course with **at least one quiz containing a STACK question**
   (added directly to a slot, not pulled in only via "random question from
   category" — that's a deliberately narrow, fast detection query, not a
   bug), with at least one **finished** attempt.
2. Look at the course's secondary navigation bar
   (`Course | Settings | Participants | Grades | Reports | ...`) for an
   **Analytics** entry — check inside **More** too if the bar is full;
   Moodle caps visible entries and collapses the rest there.
3. Click it. You should land on the course-wide cross-quiz comparison, with
   a dropdown to drill into any single quiz.
4. Pick a quiz. You should see a "View:" selector — **Question Analytics**
   is the default; picking **Solution Process Visualization** reloads the
   page showing that instead (only the selected view's data is computed per
   load, not both at once).
5. Try **Generate PDF Report** at the bottom of whichever view is showing.
6. Confirm the page is correctly **hidden** on a course with no STACK
   quizzes, and that a student account gets Moodle's standard
   permission-denied error if they navigate to
   `local/quizanalytics/index.php?id=<courseid>` directly.

## 5. Test the per-quiz shortcut

1. Open a STACK quiz directly (not through the course-level page) and find
   its settings/administration menu (the gear icon, or wherever your theme
   surfaces activity settings).
2. You should see an **Analytics** entry there too — it jumps straight to
   this same quiz's drill-down on the course-level page (step 4.4 above),
   just reached in one click from the quiz itself instead of via the course
   nav and its quiz selector.

If either entry point doesn't appear at all, check that the quiz actually
has finished attempts and a STACK question added directly to a slot — first
load computes everything fresh (a few seconds); reloading the same page
should be near-instant afterward (cache hit) until a new attempt is
submitted.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| "Analytics" doesn't appear anywhere, course nav or quiz settings menu | Plugin not installed, or no STACK quiz/finished attempts (both entry points are gated on this) |
| "No attempts yet" / "...has no finished attempts" | No attempts in `state = finished` for the quiz(zes) in question |
| "Analytics could not be computed for this quiz" | An unexpected error in the analytics computation — check Moodle's debugging messages/logs (Site administration → Reports → Logs, or your server's PHP error log) for the underlying exception |
| A large course's course-wide view or PDF export times out | Raise **Computation time limit** in the plugin's settings (see step 3 above) |
| Charts blank / JS console errors | Check the browser console for a 404 on `js/vendor/plotly.min.js` or `js/vendor/katex/*` — those ship inside this repo already, so a 404 usually means the plugin folder wasn't copied completely |
| Math renders as literal `\(...\)` text instead of typeset symbols | KaTeX's CSS/font files (`js/vendor/katex/fonts/`) didn't come along with the rest of `js/vendor/katex/` — re-copy the whole folder |
| PDF download fails, or shows "chart image unavailable" for a chart | The browser couldn't capture that chart via `Plotly.toImage()` before the form submitted (e.g. a very slow client, or the chart hadn't finished rendering) — check the browser console for the alert this raises, and try again |
| Math in the PDF shows raw LaTeX source (`\(...\)`, `$...$`) instead of typeset symbols | Expected, current behavior: the PDF prints STACK/Maxima math as its source text rather than rendering it — the on-screen page still typesets it via KaTeX |
| Question text shows `@variable@` placeholders or both languages' `[[lang]]` blocks at once | `castext2_qa_processor`/`stack_outofcontext_process` couldn't be loaded — check `qtype_stack` is installed and up to date; `data_fetcher.php` falls back to the raw text rather than failing, so this degrades rather than breaking the page |
| Numbers look wrong for STACK questions specifically | `data_fetcher.php` uses `$quba->get_response_summary($slot)` — the same method core's "Responses" report uses — to build the response text; compare against a manual CSV export of the same quiz's Responses report if you suspect a mismatch |
