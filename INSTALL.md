# Installing quiz_quizanalytics — full stack

Two pieces, installed in this order (the plugin is useless without the
service running first, so build the service, confirm it works standalone,
*then* install the plugin):

1. **The analytics microservice** (`app.py` + your existing `analytics/`
   package) — a small Python process running on the same server as Moodle.
2. **The Moodle plugin** (`mod/quiz/report/quizanalytics/`) — the PHP tab
   that calls it.

Both live on the same machine (or same private network) as your Moodle
install. Nothing here talks to the public internet.

---

## Part 1 — The analytics microservice

### 1.1 Get the code onto the server

You need two things side by side on the server:
- `app.py`, `requirements.txt`, `Dockerfile`, `quizanalytics.service` (all
  included in this package)
- your **existing** `analytics/` folder from the
  `Interactive-quiz-analytics` repo, copied in unmodified, sitting directly
  next to `app.py` (not nested one level deeper)

If you're not using Docker, pick a location such as `/opt/quizanalytics/` and
lay it out like:

```
/opt/quizanalytics/
├── app.py
├── requirements.txt
└── analytics/           ← copied straight from your existing repo
    ├── __init__.py
    ├── parser.py
    ├── quiz_metrics.py
    └── ... (all the other analytics/*.py files, unchanged)
```

A quick way to keep this in sync as your Streamlit app evolves: make
`/opt/quizanalytics/analytics` a symlink to the `analytics/` folder inside
wherever your Streamlit app's repo is checked out on the same server, e.g.:

```bash
ln -s /opt/streamlit-app/Interactive-quiz-analytics-main/analytics \
      /opt/quizanalytics/analytics
```

That way a `git pull` on your Streamlit app's repo updates the microservice
too, with no separate copy step.

### 1.2 Choose Docker or systemd

**Option A — Docker (recommended if Moodle itself runs in containers):**

```bash
cd /opt/quizanalytics
docker build -t quiz-quizanalytics-service .
docker run -d \
  --name quiz-quizanalytics-service \
  --restart unless-stopped \
  -p 127.0.0.1:8600:8600 \
  quiz-quizanalytics-service
```

The `-p 127.0.0.1:8600:8600` is the important part — it only publishes the
port to the host's loopback interface, so nothing outside the machine can
reach it, even if the container's own internal network would otherwise allow
it. If Moodle runs in a *different* container on the same Docker network
instead of directly on the host, use `--network <that-network>` instead of
`-p 127.0.0.1:...` and skip straight to pointing the plugin at the
container's service name (e.g. `http://quiz-quizanalytics-service:8600/analyze`)
rather than `127.0.0.1`.

**Option B — systemd, running directly on the host (no Docker):**

```bash
cd /opt/quizanalytics
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt

sudo cp quizanalytics.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now quizanalytics
sudo systemctl status quizanalytics   # should say "active (running)"
```

### 1.3 Verify it's actually up, and only locally reachable

From the Moodle server itself:

```bash
curl http://127.0.0.1:8600/health
# → {"status":"ok"}
```

From any *other* machine, confirm this fails (connection refused/timeout) —
that failure is the point:

```bash
curl http://<moodle-server-ip>:8600/health
```

If that second call succeeds from outside, stop here and fix your firewall
before going any further — the whole privacy argument for this design
depends on that port never being reachable from outside the server.

### 1.4 Smoke-test with a synthetic request

```bash
curl -X POST http://127.0.0.1:8600/analyze \
  -H "Content-Type: application/json" \
  -d '{
        "quiz_name": "Smoke Test Quiz",
        "records": [{
          "last_name": "Doe", "first_name": "Jane", "email": "jane@example.edu",
          "state": "finished", "started_on": "2026-08-01 09:00:00",
          "completed": "2026-08-01 09:20:00", "time_taken_secs": 1200,
          "grade": 8.5, "max_grade": 10.0, "attempt_number": 1,
          "question_1_text": "Simplify.", "response_1": "ans1: x^2+1 [valid] prt1: 1: # = 1.0 | Correct",
          "right_answer_1": "x^2+1", "question_1_mark": 1.0, "question_1_maxmark": 1.0
        }]
      }'
```

You should get back JSON with a `summary` object and a `figures` array. If
you get a 4xx/5xx, check the service logs (`journalctl -u quizanalytics -f`
for systemd, or `docker logs quiz-quizanalytics-service` for Docker) before
moving to Part 2 — it's much easier to debug the service directly with curl
than through the Moodle UI.

---

## Part 2 — The Moodle plugin

### 2.1 Place the files

Copy `mod/quiz/report/quizanalytics/` from this package into your Moodle
codebase at the identical relative path:

```bash
cp -r mod/quiz/report/quizanalytics <moodleroot>/mod/quiz/report/quizanalytics
```

The folder name must stay exactly `quizanalytics` — Moodle derives the
component name `quiz_quizanalytics` from that path.

### 2.2 Vendor Plotly.js

```bash
cd <moodleroot>/mod/quiz/report/quizanalytics/js
rm PLOTLY_GOES_HERE.txt
curl -L -o plotly.min.js \
  https://cdn.plot.ly/plotly-2.32.0.min.js   # or download via npm — see the file
                                              # this replaces for both options
```
(If you'd rather not even do a one-time fetch, download this on your own
machine and `scp` it to the server — the point is just that it ends up in
`js/plotly.min.js` before you continue.)

### 2.3 Fix file ownership

```bash
chown -R www-data:www-data <moodleroot>/mod/quiz/report/quizanalytics
# (use whatever user your web server actually runs as — check your existing
# mod/quiz/report/responses/ folder's ownership and match it)
```

### 2.4 Run the Moodle upgrade

Log into Moodle as an admin and go to **Site administration**. Moodle
detects the new plugin automatically and shows the plugins-check/upgrade
screen — click **Upgrade Moodle database now**. This is what registers the
`quiz/quizanalytics:view` capability from `db/access.php`; there's no other
manual step for that.

If nothing happens automatically, force it by visiting:
```
<yoursite>/admin/index.php
```

### 2.5 Confirm the report is enabled

**Site administration → Plugins → Activity modules → Quiz → Quiz reports**
— find "Interactive Analytics" in the list and make sure it isn't disabled
(some Moodle configs disable newly-installed reports by default).

### 2.6 Point the plugin at your microservice

**Site administration → Plugins → Activity modules → Quiz → Quiz reports →
Interactive Analytics** (or search "Interactive Analytics" in the admin
search box):

- **Analytics service URL** → `http://127.0.0.1:8600/analyze` (or your
  container's internal address from step 1.2 if you went the Docker+network
  route). Confirm it matches whatever you tested with `curl` in step 1.4
  exactly.
- **Analytics service timeout** → 30 is a reasonable default; raise it if
  you have quizzes with hundreds of attempts and see timeouts.

### 2.7 Test on a real quiz

1. Go to a course with a STACK quiz that has at least one **finished**
   attempt.
2. Open the quiz → **Results** (the same place Grades/Responses/Statistics
   live).
3. You should now see an **Interactive Analytics** tab. Click it.
4. First load: you'll likely see the grade distribution, engagement, and
   question-difficulty charts render. If you see "No attempts yet," check
   that the quiz actually has attempts in the `state = finished` state, not
   just in-progress ones.
5. If you see "The analytics service could not be reached," re-check step
   2.6's URL and re-run the `curl` test from step 1.3/1.4 — the plugin and a
   working `curl` call use the exact same network path, so if `curl` works
   and the plugin doesn't, the URL or timeout setting is the usual culprit.

### 2.8 Before trusting the numbers: verify the response text

This is the step called out in the plugin's own README, worth repeating
here because it's the one place a wrong assumption could silently produce
wrong statistics rather than an obvious error. `data_fetcher.php` calls
Moodle's `$quba->get_response_summary($slot)` to reconstruct the `ansK: ...
[valid] prtK: ...` text your parser expects. Before relying on this for a
real course:

1. Pick one quiz with a few finished STACK attempts.
2. Manually export its Responses report as CSV (Quiz results → Responses →
   check "Response" → Display report → download) for comparison.
3. Temporarily add `var_dump($records); die();` right after the
   `get_response_records()` call in `report.php`, reload the Interactive
   Analytics tab, and compare the `response_N` values against the CSV.
4. They should match. If they don't, the fix belongs in
   `data_fetcher.php` (a different question-engine accessor for your Moodle/
   qtype_stack version) — leave `analytics/parser.py` untouched, since it's
   already the proven, working target format.
5. Remove the `var_dump`/`die()` once confirmed.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| Tab doesn't appear at all | Plugin not installed (re-check 2.4) or disabled (2.5) |
| "No attempts yet" | No attempts in `state = finished`, or `quiz->sumgrades` is 0 |
| "Analytics service could not be reached" | URL/port mismatch (2.6), service not running (1.3), or a firewall blocking even localhost — check `systemctl status quizanalytics` / `docker ps` |
| Charts blank/JS console errors | `plotly.min.js` missing or not actually vendored (2.2) |
| Numbers look wrong for STACK questions specifically | Response-summary format mismatch — do the verification in 2.8 |
| 422 error from the service | `build_response_rows()` found no rows with `State == "Finished"` — check the `state` field mapping in `_records_to_moodle_df()` in `app.py` |

## What's still not built

- The **course-level "compare quizzes"** UI entry point. `analyze-course` on
  the service side is done and tested; `data_fetcher.php`'s
  `get_course_response_records()` is written; what's missing is a course
  navigation node or block in Moodle that calls them and renders the result.
  Say the word if you want that built next.
- **Caching.** Every page load currently re-fetches from the DB and
  re-runs the full analysis. Fine for a pilot; worth adding a short cache
  (keyed on quiz id + attempt count) once more than a couple of instructors
  are using it regularly.
