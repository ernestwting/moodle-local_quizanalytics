# Installing local_quizanalytics — course-level Analytics tab

This plugin adds an **Analytics** entry to a course's secondary navigation bar
(the "Course | Settings | Participants | Grades | Reports | More" strip)
that shows cross-quiz, course-wide STACK analytics by default, with a
selector to drill into any single quiz's full question-level analytics.

It **requires two things to already exist** — it has no analytics logic of
its own, it only reads Moodle's DB and calls the same local microservice a
sibling plugin already uses:

1. **The analytics microservice** (`analytics-service/app.py` + the
   `analytics/` Python package) — a small local process on the Moodle
   server, already covered in this package's top-level `INSTALL.md`.
2. **The `quiz_quizanalytics` report subplugin**
   (`mod/quiz/report/quizanalytics/`) — this plugin's `data_fetcher.php`
   calls straight into that plugin's `quiz_quizanalytics_data_fetcher::
   get_response_records()` rather than reimplementing attempt extraction a
   second time. `version.php` declares this as a hard dependency, so Moodle
   will refuse to enable `local_quizanalytics` if it's missing.

If you haven't installed those two yet, do that first (follow the top-level
`INSTALL.md` Parts 1 and 2 in this package), confirm the per-quiz
"Interactive Analytics" tab works on at least one quiz, **then** come back
here.

---

## Part 1 — Place the files

Copy `local/quizanalytics/` from this package into your Moodle codebase at
the identical relative path:

```bash
cp -r local/quizanalytics <moodleroot>/local/quizanalytics
```

The folder name must stay exactly `quizanalytics` — Moodle derives the
component name `local_quizanalytics` from that path.

## Part 2 — Vendor Plotly.js (separately from the per-quiz plugin)

This plugin keeps its own copy of Plotly rather than depending on the
per-quiz plugin's copy (the two plugins are enabled/disabled independently):

```bash
cd <moodleroot>/local/quizanalytics/js
rm PLOTLY_GOES_HERE.txt
curl -L -o plotly.min.js \
  https://cdn.plot.ly/plotly-2.32.0.min.js
```

(As with the per-quiz plugin, downloading it on your own machine and `scp`ing
it to the server works just as well — the point is that `js/plotly.min.js`
exists before you continue.)

## Part 3 — Fix file ownership

```bash
chown -R www-data:www-data <moodleroot>/local/quizanalytics
# use whatever user your web server actually runs as
```

## Part 4 — Run the Moodle upgrade

Log in as an admin and go to **Site administration**. Moodle detects the new
plugin and shows the plugins-check/upgrade screen — click **Upgrade Moodle
database now**.

This is also the point where Moodle checks `version.php`'s
`$plugin->dependencies`. **If `quiz_quizanalytics` isn't installed (or is
older than the version this plugin depends on), the upgrade screen will
block with a "missing dependency" error rather than silently letting you
enable a broken plugin.** If you see that, go install/update
`quiz_quizanalytics` first (Part 1/2 of the top-level `INSTALL.md`), then
retry this step.

If the upgrade screen doesn't appear automatically, force it by visiting:
```
<yoursite>/admin/index.php
```

This is also what registers the `local/quizanalytics:view` capability from
`db/access.php` — no separate manual step is needed for that. By default it's
granted to `editingteacher`, `teacher`, and `manager` archetypes and
deliberately **not** to `student`.

## Part 5 — Point the plugin at your analytics service

**Site administration → Plugins → Local plugins → Analytics** (or search
"Analytics" in the admin search box):

- **Analytics service base URL** → `http://127.0.0.1:8600` (just the base —
  this plugin appends `/analyze` and `/analyze-course` itself). This should
  be the same host:port you already configured for `quiz_quizanalytics`,
  minus the `/analyze` suffix that plugin's setting includes.
- **Analytics service timeout** → 30 is a reasonable default; course-wide
  requests bundle every STACK quiz's attempts into one call, so raise this
  if you have courses with many large quizzes and see timeouts on the
  course-wide view specifically (the per-quiz drill-down view will still
  work fine at a lower timeout).

## Part 6 — Verify the tab appears (the highest-risk step)

Navigation registration is the part most likely to silently fail, so verify
it directly rather than assuming:

1. Go to a course that has **at least one quiz containing a STACK
   question** (any qtype_stack question added directly to a slot — not one
   pulled in only via "random question from category").
2. Look at the secondary navigation bar under the course's page header:
   `Course | Settings | Participants | Grades | Reports | ...`. You should
   see an **Analytics** entry. If the bar is already full, Moodle collapses
   extra entries into a **More** dropdown — check there too before assuming
   it's missing; that's normal Moodle behavior for this navigation bar
   (`core\navigation\views\secondary::MAX_DISPLAYED_NAV_NODES` caps it at 5
   visible entries), not a bug in this plugin.
3. Click it. You should land on `local/quizanalytics/index.php?id=<courseid>`
   showing the course-wide view (or a "no attempts yet" notice if none of
   the course's STACK quizzes have finished attempts).

## Part 7 — Verify it's correctly hidden on non-STACK courses

1. Go to a course with **no** STACK questions in any quiz (or no quizzes at
   all).
2. Confirm **Analytics** does not appear anywhere in the secondary nav bar,
   including inside "More".
3. If you want to confirm this is the STACK-detection query and not a
   capability issue, try the same check as a user with the
   `local/quizanalytics:view` capability (e.g. a teacher) — it should still
   be hidden purely because the course has no STACK quiz.

## Part 8 — Verify students never see it

1. Log in as (or log in as) a student enrolled in a course that **does**
   have a qualifying STACK quiz.
2. Confirm **Analytics** does not appear in the nav, including "More".
3. As an extra check, try navigating directly to
   `<yoursite>/local/quizanalytics/index.php?id=<courseid>` as that student.
   You should get Moodle's standard "Sorry, but you do not currently have
   permission to use this page" error, not the analytics page — this is
   `require_capability('local/quizanalytics:view', $context)` in `index.php`
   enforcing the capability independently of whether the nav tab happened to
   render.

## Part 9 — Verify the course-wide view

1. Pick a course with **two or more** STACK quizzes that each have at least
   one finished attempt.
2. Open **Analytics** from the course nav. You should land on the
   course-wide view by default (no quiz selected in the dropdown) showing a
   grade-distribution-by-quiz chart, an engagement-over-time chart, and a
   summary table with one row per quiz (student count, mean grade, grade
   variance, attempt count, attempt rate).
3. If you see "None of this course's STACK quizzes have finished attempts
   yet," double check attempts are in the `state = finished` state, matching
   the same requirement as the per-quiz report.

## Part 10 — Verify the single-quiz drill-down

1. On the same page, use the **"View a single quiz's analytics"** dropdown
   to pick one of the course's STACK quizzes, then click **View**.
2. The page reloads with `&quizid=<id>` and should show that quiz's full
   question-level analytics — grade distribution, engagement, and question
   difficulty (facility) — matching what the **existing per-quiz
   "Interactive Analytics" tab** (`mod/quiz/report/quizanalytics`) already
   shows for the exact same quiz. Compare the two side by side; the numbers
   should match, since both ultimately call `/analyze` with records built by
   the same `quiz_quizanalytics_data_fetcher::get_response_records()`.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| "Analytics" tab never appears anywhere, even in "More" | Course has no STACK quiz (check Part 7's detection scope — random-question slots aren't detected), capability not granted to your role, or plugin not installed (re-check Part 4) |
| Upgrade blocks with a dependency error | `quiz_quizanalytics` isn't installed or is older than this plugin's declared dependency version in `version.php` |
| "The analytics service could not be reached" | Base URL/port mismatch (Part 5), service not running, or a firewall blocking even localhost |
| Course-wide view times out but single-quiz drill-down works | Raise **Analytics service timeout** (Part 5) — course-wide bundles every quiz's attempts into one request |
| "None of this course's STACK quizzes have finished attempts yet" | No attempts in `state = finished` for any STACK quiz in the course |
| Charts blank / JS console errors | `plotly.min.js` missing (Part 2) |
| Drill-down numbers don't match the per-quiz report for the same quiz | Both plugins call the exact same `quiz_quizanalytics_data_fetcher` method, so a mismatch here means something changed between page loads (e.g. a new attempt was submitted) — reload both and recompare |

## Known scope limits (by design, not oversights)

- **STACK detection only follows direct question-to-slot references.** A
  quiz that pulls STACK questions in exclusively via "random question from
  category" slots won't be detected by `course_has_stack_quiz()` /
  `get_course_stack_quizzes()` in `classes/data_fetcher.php`. This mirrors
  the narrow, fast-check scope intentionally chosen for a nav-gating query
  that runs on every course page load — see the comment there if you need to
  extend it.
- **No caching yet.** Every page load (including the nav-gating check on
  every course page view) re-queries the database, and every `index.php`
  load re-calls the analytics service fresh. This is the same tradeoff the
  per-quiz plugin's own `INSTALL.md` already flags as a "fine for a pilot,
  worth adding once more than a couple of instructors use it regularly"
  item — the same applies here, doubly so since the nav-gating query runs
  on *every* course page, not just the analytics page itself.
