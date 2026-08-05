# Installing STACK Quiz Analytics — full stack

Four pieces, installed in this order — each later plugin depends on the ones
before it, and Moodle's own upgrade screen will refuse to enable a plugin
whose declared dependency is missing or too old, so this order isn't just a
suggestion:

1. **The analytics microservice** (`analytics-service/`) — a small Python
   process on the same server (or private network) as Moodle.
2. **`quiz_quizanalytics`** (`mod/quiz/report/quizanalytics/`) — the
   per-quiz **Question Analytics** tab. Everything else depends on this.
3. **`quiz_solutionprocess`** (`mod/quiz/report/solutionprocess/`) — the
   per-quiz **Solution Process Visualization** tab. Depends on (2).
4. **`local_quizanalytics`** (`local/quizanalytics/`) — the course-level
   **Analytics** page. Depends on (2) and (3).

All four live on the same machine (or same private network) as your Moodle
install. Nothing here talks to the public internet.

---

## Prerequisites

- Admin access to the Moodle site, plus shell/SFTP access to the Moodle
  codebase (not just the web UI).
- Moodle 4.0+ (adjust each plugin's `version.php`'s `requires` value if
  you're on something else — check your target Moodle's own `version.php`
  for the right number).
- Docker + Docker Compose (recommended for the analytics service), or Python
  3.11+ if you'd rather run it directly via systemd.

---

## Part 1 — The analytics microservice

### 1.1 Get the code onto the server

You need, side by side, on the server:
- `app.py`, `requirements.txt`, `Dockerfile` (all included in this repo,
  under `analytics-service/`)
- the `analytics/` folder (also included) — this is the same Python package
  the companion Streamlit app uses; see the top-level `README.md`'s "Keeping
  this in sync" section if you also run that app and want to keep both in
  sync going forward.

### 1.2 Docker (recommended)

```bash
cd analytics-service
docker build -t quiz-quizanalytics-service .
docker run -d \
  --name quiz-quizanalytics-service \
  --restart unless-stopped \
  -p 127.0.0.1:8600:8600 \
  quiz-quizanalytics-service
```

The `-p 127.0.0.1:8600:8600` is the important part — it only publishes the
port to the host's loopback interface, so nothing outside the machine can
reach it. If Moodle runs in a *different* container on the same Docker
network instead of directly on the host, use `--network <that-network>`
instead of `-p 127.0.0.1:...` and point the plugins at the container's
service name (e.g. `http://quiz-quizanalytics-service:8600`) rather than
`127.0.0.1`.

**If your Docker host is arm64 (Apple Silicon, AWS Graviton, etc.):** the
Dockerfile already installs a native `chromium` package specifically so PDF
export works. Chart rasterization for PDFs (via the `kaleido` package) needs
a real headless Chrome/Chromium; kaleido's own downloader only publishes
`linux64` (x86_64) builds, which don't reliably run on an arm64 host even
under emulation. `pdf_export.py` prefers a system-installed `chromium` (found
via `PATH`) over that download automatically — you don't need to do anything
extra, but if you ever see PDF generation fail with a browser-launch error,
this is the first thing to check (`docker exec <container> which chromium`).

### 1.3 systemd (no Docker)

```bash
cd analytics-service
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt

sudo cp quizanalytics.service /etc/systemd/system/
# Edit the WorkingDirectory/ExecStart paths in that file first if you didn't
# deploy to /opt/quizanalytics.
sudo systemctl daemon-reload
sudo systemctl enable --now quizanalytics
sudo systemctl status quizanalytics   # should say "active (running)"
```

PDF export's chart rasterization needs a system Chrome/Chromium available on
`PATH` here too (`apt install chromium` on Debian/Ubuntu, or similar for your
distro) — without it, PDF generation still works but chart images inside the
PDF are replaced with a "chart image unavailable" placeholder.

### 1.4 Verify it's up, and only locally reachable

From the Moodle server itself:

```bash
curl http://127.0.0.1:8600/report-sections/question
# → {"sections": ["1. Question Summary", "2. Question Difficulty Analysis", ...]}
```

From any *other* machine, confirm this fails (connection refused/timeout) —
that failure is the point. If it succeeds from outside, stop and fix your
firewall before going further; the whole privacy argument for this design
depends on that port never being reachable from outside the server.

---

## Part 2 — `quiz_quizanalytics` (Question Analytics)

### 2.1 Place the files

```bash
cp -r mod/quiz/report/quizanalytics <moodleroot>/mod/quiz/report/quizanalytics
chown -R www-data:www-data <moodleroot>/mod/quiz/report/quizanalytics
# (use whatever user your web server actually runs as)
```

The folder name must stay exactly `quizanalytics` — Moodle derives the
component name `quiz_quizanalytics` from that path. Plotly.js and KaTeX are
already vendored inside `js/vendor/`; no separate download step is needed.

### 2.2 Run the Moodle upgrade

Log in as an admin and go to **Site administration**. Moodle detects the new
plugin and shows the plugins-check/upgrade screen — click through it. This
registers the `quiz/quizanalytics:view` capability from `db/access.php` and
the cache areas from `db/caches.php`; no separate manual step is needed for
either.

If the upgrade screen doesn't appear automatically, force it by visiting
`<yoursite>/admin/index.php`.

### 2.3 Confirm the report is enabled

**Site administration → Plugins → Activity modules → Quiz → Quiz reports** —
find "Question Analytics" in the list and make sure it isn't disabled.

### 2.4 Point the plugin at your analytics service

**Site administration → Plugins → Quiz reports → Question Analytics**:

- **Analytics service URL** → `http://127.0.0.1:8600/analyze` (or your
  container's internal address if you used the Docker+network route above).
- **Analytics service timeout** → 30 is a reasonable default.
- **PDF export timeout** → 90 by default; raise it for quizzes with a lot of
  attempts/questions, since PDF generation rasterizes every chart.

### 2.5 Test on a real quiz

1. Go to a course with a STACK quiz that has at least one **finished**
   attempt.
2. Open the quiz → **Results**. You should see a **Question Analytics** tab.
3. First load computes everything fresh (a few seconds); reloading the same
   page should be near-instant (cache hit) until a new attempt is submitted.
4. Try **Generate PDF Report** at the bottom of the page.

If you see "No attempts yet," check that the quiz has attempts in the
`state = finished` state, not just in-progress ones. If you see "The
analytics service could not be reached," re-check 2.4's URL against the
`curl` test from 1.4.

---

## Part 3 — `quiz_solutionprocess` (Solution Process Visualization)

Requires Part 2 to already be installed and working.

```bash
cp -r mod/quiz/report/solutionprocess <moodleroot>/mod/quiz/report/solutionprocess
chown -R www-data:www-data <moodleroot>/mod/quiz/report/solutionprocess
```

Then repeat 2.2–2.4 for this plugin: run the Moodle upgrade (if it blocks
with a "missing dependency" error, `quiz_quizanalytics` isn't installed or is
older than this plugin's declared dependency — go fix Part 2 first), confirm
it's enabled under **Quiz reports**, and set **Analytics service base URL**
(just the base, e.g. `http://127.0.0.1:8600` — this plugin appends
`/solution-process/meta` and `/solution-process` itself, unlike
`quiz_quizanalytics`'s single-endpoint setting) plus the timeout settings
under **Site administration → Plugins → Quiz reports → Solution Process
Visualization**.

**Test:** open the same quiz's **Solution Process Visualization** tab, pick a
question/part with STACK PRTs, and confirm the transition graph and 3D charts
render. In the **Cross-Attempt Comparison** table, click a student's name —
you should land on their own attempt-by-attempt drill-down.

---

## Part 4 — `local_quizanalytics` (course-level Analytics)

Requires Parts 2 and 3 to already be installed and working.

```bash
cp -r local/quizanalytics <moodleroot>/local/quizanalytics
chown -R www-data:www-data <moodleroot>/local/quizanalytics
```

Run the Moodle upgrade (again, it'll block on a missing-dependency error if
either plugin above isn't installed yet), then set **Analytics service base
URL**/timeouts under **Site administration → Plugins → Local plugins →
Analytics**.

**Test — the tab appearing at all is the highest-risk step, verify it
directly:**

1. Go to a course with **at least one quiz containing a STACK question**
   (added directly to a slot, not pulled in only via "random question from
   category" — that's a deliberately narrow, fast detection query, not a
   bug).
2. Look at the course's secondary navigation bar
   (`Course | Settings | Participants | Grades | Reports | ...`) for an
   **Analytics** entry — check inside **More** too if the bar is full;
   Moodle caps visible entries and collapses the rest there.
3. Click it. You should land on the course-wide cross-quiz view, with a
   dropdown to drill into any single quiz (showing the same Question
   Analytics + Solution Process Visualization as the per-quiz tabs, plus
   their own PDF export buttons).
4. Confirm it's correctly **hidden** on a course with no STACK quizzes, and
   that a student account gets Moodle's standard permission-denied error if
   they navigate to `local/quizanalytics/index.php?id=<courseid>` directly.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| A tab/nav entry doesn't appear at all | Plugin not installed, or disabled under **Quiz reports** / no STACK quiz in the course (`local_quizanalytics` only) |
| Upgrade blocks with a dependency error | Install the plugin it depends on first (Part 2 before 3, Parts 2+3 before 4) |
| "The analytics service could not be reached" | URL/port mismatch, service not running (`docker ps` / `systemctl status quizanalytics`), or a firewall blocking even localhost |
| "No attempts yet" / "...has no finished attempts" | No attempts in `state = finished` for the quiz(zes) in question |
| Charts blank / JS console errors | Check the browser console for a 404 on `js/vendor/plotly.min.js` or `js/vendor/katex/*` — those ship inside this repo already, so a 404 usually means the plugin folder wasn't copied completely |
| Math renders as literal `\(...\)` text instead of typeset symbols | KaTeX's CSS/font files (`js/vendor/katex/fonts/`) didn't come along with the rest of `js/vendor/katex/` — re-copy the whole folder |
| PDF download fails / "chart image unavailable" placeholders in the PDF | No system Chrome/Chromium available to kaleido — see 1.2/1.3's PDF note |
| Question text shows `@variable@` placeholders or both languages' `[[lang]]` blocks at once | `castext2_qa_processor`/`stack_outofcontext_process` couldn't be loaded — check `qtype_stack` is installed and up to date; `data_fetcher.php` falls back to the raw text rather than failing, so this degrades rather than breaking the page |
| Numbers look wrong for STACK questions specifically | `data_fetcher.php` uses `$quba->get_response_summary($slot)` — the same method core's "Responses" report uses — to build the response text; compare against a manual CSV export of the same quiz's Responses report if you suspect a mismatch |
