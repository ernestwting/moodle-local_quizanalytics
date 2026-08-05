# Installing STACK Quiz Analytics — full stack

## Before you start: is this right for your Moodle site?

This is **not** a one-click Marketplace install. Installing `local_quizanalytics`
only gets you the PHP side — it also requires a second, always-running
**Python service** (`analytics-service/`) that does the actual analysis. That
service is not a Moodle plugin and is not distributed through the Plugins
directory; your own server admin has to deploy and keep it running.

That means:

- **You need shell/root access to the server Moodle runs on** (or a private
  network you control that Moodle can reach) — enough to run a Docker
  container or a systemd service. If your Moodle is hosted somewhere you
  don't have that kind of access (a shared host, MoodleCloud, a managed
  hosting plan where you only get the web UI), you cannot deploy this
  plugin's backend, full stop — there's nowhere to put it.
- **The privacy design only holds if you actually put it on the same server
  (or the same private network) as Moodle.** Nothing in the plugin *enforces*
  this — the `apibaseurl` setting will happily accept a public address if you
  type one in. The whole point of this architecture (student response data
  never leaving infrastructure your institution controls) depends on whoever
  installs it following Part 1 below correctly, not on the code stopping a
  misconfiguration.
- If that's not you — e.g. you're a single teacher on a hosted Moodle with no
  server access — this plugin isn't currently usable for you. It's built for
  an institution (or an admin) that already runs its own Moodle server.

If none of that is a blocker, continue below.

---

Two pieces, installed in this order — the plugin depends on the microservice
being reachable, so it's easiest to bring the service up first, confirm it
with `curl`, then install the plugin:

1. **The analytics microservice** (`analytics-service/`) — a small Python
   process on the same server (or private network) as Moodle.
2. **`local_quizanalytics`** (`local/quizanalytics/`) — the one Moodle
   plugin, covering course-wide comparison, per-quiz Question Analytics, and
   per-quiz Solution Process Visualization.

Both live on the same machine (or same private network) as your Moodle
install. Nothing here talks to the public internet.

---

## Prerequisites

- Admin access to the Moodle site, plus shell/SFTP access to the Moodle
  codebase (not just the web UI).
- Moodle 4.0+ (adjust `version.php`'s `requires` value if you're on
  something else — check your target Moodle's own `version.php` for the
  right number).
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
instead of `-p 127.0.0.1:...` and point the plugin at the container's
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

## Part 2 — `local_quizanalytics`

### 2.1 Place the files

```bash
cp -r local/quizanalytics <moodleroot>/local/quizanalytics
chown -R www-data:www-data <moodleroot>/local/quizanalytics
# (use whatever user your web server actually runs as)
```

The folder name must stay exactly `quizanalytics` — Moodle derives the
component name `local_quizanalytics` from that path. Plotly.js and KaTeX are
already vendored inside `js/vendor/`; no separate download step is needed.

### 2.2 Run the Moodle upgrade

Log in as an admin and go to **Site administration**. Moodle detects the new
plugin and shows the plugins-check/upgrade screen — click through it. This
registers the `local/quizanalytics:view` capability from `db/access.php` and
the cache areas from `db/caches.php`; no separate manual step is needed for
either.

If the upgrade screen doesn't appear automatically, force it by visiting
`<yoursite>/admin/index.php`.

### 2.3 Point the plugin at your analytics service

**Site administration → Plugins → Local plugins → Analytics**:

- **Analytics service base URL** → `http://127.0.0.1:8600` (or your
  container's internal address if you used the Docker+network route above).
  Just the base — the plugin appends `/analyze`, `/analyze-course`,
  `/solution-process`, and the PDF export paths itself.
- **Analytics service timeout** → 30 is a reasonable default; course-wide
  requests bundle every STACK quiz's attempts into one call, so raise this
  on courses with many large quizzes if you see timeouts there specifically.
- **PDF export timeout** → 90 by default; raise it for quizzes with a lot of
  attempts/questions, since PDF generation rasterizes every chart.

### 2.4 Test the course-level page

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
   page showing that instead (only the selected view's data is fetched per
   load, not both at once).
5. Try **Generate PDF Report** at the bottom of whichever view is showing.
6. Confirm the page is correctly **hidden** on a course with no STACK
   quizzes, and that a student account gets Moodle's standard
   permission-denied error if they navigate to
   `local/quizanalytics/index.php?id=<courseid>` directly.

### 2.5 Test the per-quiz shortcut

1. Open a STACK quiz directly (not through the course-level page) and find
   its settings/administration menu (the gear icon, or wherever your theme
   surfaces activity settings).
2. You should see an **Analytics** entry there too — it jumps straight to
   this same quiz's drill-down on the course-level page (step 2.4.4 above),
   just reached in one click from the quiz itself instead of via the course
   nav and its quiz selector.

If either entry point doesn't appear at all, check that the quiz actually
has finished attempts and a STACK question added directly to a slot — first
load computes everything fresh (a few seconds); reloading the same page
should be near-instant afterward (cache hit) until a new attempt is
submitted. If you see "The analytics service could not be reached," re-check
2.3's URL against the `curl` test from 1.4.

---

## Troubleshooting quick-reference

| Symptom | Likely cause |
|---|---|
| "Analytics" doesn't appear anywhere, course nav or quiz settings menu | Plugin not installed, or no STACK quiz/finished attempts (both entry points are gated on this) |
| "The analytics service could not be reached" | URL/port mismatch, service not running (`docker ps` / `systemctl status quizanalytics`), or a firewall blocking even localhost |
| "No attempts yet" / "...has no finished attempts" | No attempts in `state = finished` for the quiz(zes) in question |
| Charts blank / JS console errors | Check the browser console for a 404 on `js/vendor/plotly.min.js` or `js/vendor/katex/*` — those ship inside this repo already, so a 404 usually means the plugin folder wasn't copied completely |
| Math renders as literal `\(...\)` text instead of typeset symbols | KaTeX's CSS/font files (`js/vendor/katex/fonts/`) didn't come along with the rest of `js/vendor/katex/` — re-copy the whole folder |
| PDF download fails / "chart image unavailable" placeholders in the PDF | No system Chrome/Chromium available to kaleido — see 1.2/1.3's PDF note |
| Question text shows `@variable@` placeholders or both languages' `[[lang]]` blocks at once | `castext2_qa_processor`/`stack_outofcontext_process` couldn't be loaded — check `qtype_stack` is installed and up to date; `data_fetcher.php` falls back to the raw text rather than failing, so this degrades rather than breaking the page |
| Numbers look wrong for STACK questions specifically | `data_fetcher.php` uses `$quba->get_response_summary($slot)` — the same method core's "Responses" report uses — to build the response text; compare against a manual CSV export of the same quiz's Responses report if you suspect a mismatch |
