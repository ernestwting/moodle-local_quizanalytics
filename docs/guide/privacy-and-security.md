\[ [STACK q-type Analytics Docs](index.md) → Privacy & Security \]

# Privacy & Security

This page documents exactly what this plugin does — and does not do —
with student and course data, and why. It reflects a direct audit of the
codebase, not a policy statement written separately from the code.

## The short version

**No student or course data ever leaves the Moodle server.** Every
computation runs in plain PHP, in-process, reading directly from Moodle's
own database. There is no analytics service to call, no telemetry, no
third-party SDK, and no code path anywhere in this plugin that makes an
outbound network request.

## What was checked

A direct audit of every `.php` and `.js` file in this plugin (excluding
vendored third-party libraries) found:

- **Zero outbound network calls.** No `curl_*`, no `file_get_contents()`/
  `fopen()` against a URL, no `fsockopen`, no HTTP client library, no
  `mail()`, in any file this plugin owns.
- **Zero external script/asset loading.** Every JS/CSS dependency —
  Plotly.js, KaTeX, TCPDF — is vendored locally inside the plugin (see
  [`thirdpartylibs.xml`](../../thirdpartylibs.xml)), specifically so that
  no chart, no rendered math, and no PDF-generation step ever has to reach
  a CDN. There is no `<script src="https://...">` or `<link
  href="https://...">` pointing off-server anywhere in the codebase.
- **Zero telemetry/analytics/error-reporting integrations.** No Sentry,
  Bugsnag, Google Analytics, Mixpanel, or similar — checked directly, not
  assumed absent.
- **Zero registered Moodle web services.** There is no `db/services.php`
  and no external-function class anywhere in this plugin, so there's no
  API surface a token or third-party integration could pull data through.
- **Zero file storage of exported data.** PDF exports are streamed
  straight from server memory to the requesting browser via Moodle's own
  `send_file()` — never written to Moodle's file storage, never given a
  public or guessable URL. Generating a PDF and closing the tab without
  downloading it leaves nothing behind.

The only way data leaves the server at all is a PDF an authorized user
explicitly clicks **Download PDF** for — and that's the same data they
were already looking at on screen, going to their own browser, not a third
party.

## Who can see what

Every entry point (`index.php`, `questionanalytics.php`,
`modelanalytics.php`, `diagnosticsanalytics.php`, and their four `*pdf.php`
counterparts) requires:

1. `require_login($course)` — a valid, logged-in session.
2. `require_capability('local/quizanalytics:view', $context)` — a course-
   context capability granted by default only to the **editing teacher**,
   **teacher**, and **manager** roles.

```php
'local/quizanalytics:view' => [
    'riskbitmask'  => RISK_PERSONAL,
    'archetypes'   => [
        'editingteacher' => CAP_ALLOW,
        'teacher'        => CAP_ALLOW,
        'manager'        => CAP_ALLOW,
        // Deliberately NOT granted to 'student'.
    ],
],
```

`RISK_PERSONAL` is set deliberately: this plugin shows individual
students' response text, grades, and behavioral indicators across a whole
course, and Moodle's own permission system flags that risk level
explicitly rather than leaving it implicit. **Students cannot see this
plugin's pages at all** — a student account visiting any of its URLs
directly gets Moodle's standard permission-denied error, the same as any
other capability-gated page.

State-changing actions (the settings page's "Re-detect" action) are
protected by `require_sesskey()`, Moodle's standard CSRF protection.

## What this plugin stores

Per its own Moodle privacy provider
(`classes/privacy/provider.php`), **this plugin stores no personal data of
its own** — it implements Moodle's `null_provider` interface, meaning it
declares zero data-export/data-deletion obligations because it has none.
Specifically:

- Every response, attempt, grade, and log event it reads comes live from
  tables Moodle core (or `qtype_stack`) already owns — `mod_quiz`, the
  question engine, `grade_grades`, `logstore_standard_log` — all already
  covered by their own privacy providers.
- The only local storage this plugin uses is Moodle's own MUC cache API
  (`db/caches.php`, Quiz Analytics/Question Analytics only). Each cache
  entry is a disposable, purely-derived recomputation of the same
  underlying data — invalidated automatically the moment a relevant
  attempt changes, and cleared by the site's normal "Purge caches" action.
  It is not a separate store of personal data with its own retention
  period.
- Model Analytics's *trained* predictions (once an administrator enables
  and trains a model) are stored by Moodle core's own `core_analytics`
  component, in its own tables — already covered by
  `\core_analytics\privacy\provider`, regardless of which plugin
  registered the model.

All of this storage is **on the Moodle server's own database** — nothing
here introduces a new place student data lives outside of it.

## Anonymize mode — what it does and doesn't do

The **Anonymize student data** toggle (Quiz Analytics, Question Analytics)
replaces every real student name with a stable pseudonym ("Student 1",
"Student 2", ...) and every email with `student<N>@anonymized.edu`,
consistently across every table, chart, and PDF derived from that page
load.

**What it's for:** sharing a screenshot or PDF (a department meeting, a
paper, a teaching-review committee) without real names visible in it.

**What it is not:** an access control. It's a display-layer substitution
computed fresh on each page load from the real underlying data — anyone
who already has `local/quizanalytics:view` on the course can toggle it
back off and see real names, because they already had access to that data
either way. It does not reduce what's computed, does not persist a
mapping anywhere, and does not limit who can view the page.

## Responsible use

Model Analytics and Diagnostics Analytics compute statistical patterns
from real behavioral data — worth reading with the same care the plugin's
own UI states directly:

> These are statistical patterns, not proof of anything. An anomalous
> response time is a prompt to check in with a student, not evidence of
> misconduct on its own. Small courses will show noisier and less reliable
> readings simply because they have fewer data points to work from. Every
> number here describes what a student did in this course, not who they
> are.

See [How Everything Is Calculated](calculations.md) for exactly what
produces each flag, including the indicators that are explicitly
documented simplifications of a more rigorous model (worth knowing before
treating a flag as more precise than it is).

## Reporting a concern

If you believe you've found a way for this plugin to expose data it
shouldn't, or send data somewhere it shouldn't, please open an issue on
this repository rather than a public discussion, so it can be assessed
before wider disclosure.
