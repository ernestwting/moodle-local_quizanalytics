# quiz_quizanalytics — Moodle quiz report subplugin

Adds an **"Interactive Analytics"** tab next to Grades / Responses / Statistics
on every quiz's results page. It reads finished attempts straight out of
Moodle's database (no CSV, no upload) and POSTs them to a local analytics
service, which is your existing `Interactive-quiz-analytics` Python codebase
wrapped in a small API — see **Part 2** below for that side, which is not yet
included here.

## What's in this package

```
mod/quiz/report/quizanalytics/
├── version.php              Plugin metadata (component name, version, deps)
├── report.php                Main report class — Moodle calls display() on this
├── settings.php               Admin settings page (analytics service URL/timeout)
├── classes/
│   ├── data_fetcher.php       Pulls attempts+responses out of Moodle's DB
│   └── api_client.php         Talks to your local analytics microservice
├── db/
│   └── access.php             Defines the quiz/quizanalytics:view capability
├── lang/en/
│   └── quiz_quizanalytics.php Language strings
└── js/
    ├── render.js               Renders returned charts/summary into the page
    └── PLOTLY_GOES_HERE.txt    Instructions for vendoring Plotly.js locally
```

## Part 1 — Install the plugin

**Prerequisites:** admin access to the Moodle site, shell/SFTP access to the
Moodle codebase (not just the web UI), and a Moodle 4.0+ install (adjust
`version.php`'s `requires` value if you're on something else).

1. **Place the folder.** Copy `mod/quiz/report/quizanalytics/` from this
   package into your Moodle codebase at the same relative path, i.e.
   `<moodleroot>/mod/quiz/report/quizanalytics/`. The folder name must be
   exactly `quizanalytics` — Moodle derives the plugin's identity
   (`quiz_quizanalytics`) from the path itself.

2. **Vendor Plotly.js.** Follow the instructions in
   `js/PLOTLY_GOES_HERE.txt` — download `plotly.min.js` and drop it into the
   `js/` folder, replacing the placeholder.

3. **Set file ownership/permissions** to match the rest of your Moodle
   codebase (typically the web server user, e.g. `www-data`).

4. **Trigger the install.** Log in as an admin and visit
   `Site administration`. Moodle detects the new plugin automatically and
   shows the "Plugins check" upgrade screen — click through it. This is what
   actually runs `db/access.php` and registers the capability; nothing else
   needs to be run manually.

5. **Configure the service URL.** Go to
   `Site administration → Plugins → Quiz reports → Interactive Analytics`
   and confirm/edit the **Analytics service URL** setting (defaults to
   `http://127.0.0.1:8600/analyze` — leave it pointed at localhost or your
   private network; never a public URL).

6. **Enable the report** (if your Moodle hides new quiz reports by default):
   `Site administration → Plugins → Quiz reports` — make sure
   "Interactive Analytics" isn't greyed out/disabled in that list.

## Part 2 — Verify against a real quiz *before* trusting it

The riskiest line in this whole plugin is in `data_fetcher.php`:

```php
$row["response_{$qnum}"] = $quba->get_response_summary($slot) ?? '';
```

This calls the same Moodle question-engine method the core "Responses"
report itself uses to build its "Response N" column, so for STACK questions
it *should* come back in the same `ansK: ... [valid] prtK: ...` shape your
existing `analytics/parser.py::parse_response_cell()` already knows how to
read — but exact behavior can vary by Moodle version and `qtype_stack`
version. Before wiring this into the real analytics call:

1. Pick one quiz with a handful of finished STACK attempts.
2. Temporarily add `var_dump($records); die();` right after the
   `get_response_records()` call in `report.php`.
3. Compare the `response_N` values you see against a manual CSV export of
   the same quiz's Responses report (Quiz results → Responses →
   check "Response" → Display report → download).
4. If they don't match, the fix is almost always in `data_fetcher.php`
   (e.g. using a different question-engine accessor), not in your Python
   parser — keep `parser.py` as the source of truth for the *target* format
   and adapt the PHP side to hit it.

## The JSON contract with your analytics service

`api_client.php` POSTs this to the configured URL:

```json
{
  "quiz_name": "Week 3 Quiz",
  "records": [
    {
      "last_name": "Smith", "first_name": "Jordan", "email": "jordan@example.edu",
      "state": "finished", "started_on": "...", "completed": "...",
      "time_taken_secs": 612, "grade": 8.5, "max_grade": 10.0, "attempt_number": 1,
      "question_1_text": "...", "response_1": "ans1: sqrt(x) [valid] prt1: ...",
      "right_answer_1": "...", "question_1_mark": 1.0, "question_1_maxmark": 1.0,
      "question_2_text": "...", "response_2": "...", "...": "..."
    }
  ]
}
```

...and expects back:

```json
{
  "summary": { "attempts": 42, "mean_grade": 7.1, "...": "..." },
  "figures": [
    { "title": "Grade distribution", "plotly_json": { "data": [...], "layout": {...} } }
  ]
}
```

`render.js` is written against that exact shape. On the Python side this
means: take the existing `analytics/parser.py::build_response_rows` and add a
sibling function that accepts this JSON `records` list directly (same
row-shape reasoning, different transport) instead of a parsed CSV/XLS
DataFrame, run it through your existing `analytics/*` modules unchanged, and
have each Plotly figure serialize via `fig.to_plotly_json()` (or
`json.loads(fig.to_json())`) into the `figures` array above, wrapped in a tiny
FastAPI app exposing `POST /analyze`, bound to `127.0.0.1:8600`.

That FastAPI service isn't included in this package — say the word and I'll
build it next, matching this exact contract.

## Security notes

- `quiz/quizanalytics:view` is granted to `editingteacher`, `teacher`, and
  `manager` only — students never see this tab.
- `api_client.php` only ever calls the URL in `Site administration` settings.
  Keep that pointed at `127.0.0.1` or a private network address — the whole
  privacy argument for this design depends on that URL never being public.
- Nothing here caches response data to disk; each request re-fetches from
  Moodle's DB. If you add caching later, put it on the same server/volume
  Moodle's own DB lives on and apply the same retention policy.

## Known gaps / next steps

- **Cross-quiz comparison** ("quiz to quiz in the course"): `data_fetcher.php`
  already includes `get_course_response_records()` for this, but there's no
  UI entry point yet — that needs a course-level report or block, which
  reuses everything here.
- **The FastAPI microservice** (Part 2 of the overall plan) isn't built yet —
  this plugin will show "analytics service could not be reached" until it
  exists at the configured URL.
- **Caching**: right now every page load re-fetches and re-analyzes; fine for
  a pilot, worth adding a short-lived cache keyed on quiz-id +
  attempt-count once this is used regularly.
