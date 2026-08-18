# Changelog

All notable changes to `local_quizanalytics` are documented here. Version
numbers match `version.php`'s `$plugin->release`.

## 1.0.1 — Plugins directory review fixes

- Fixed hard-coded language strings: PDF section checkboxes, PDF
  titles/captions, and PDF body text now go through `get_string()`
  instead of literal English, so they translate with the site
  language. The PDF section checkboxes previously posted their own
  label text as the form value; they now post a stable internal id,
  independent of the display language.
- Added the four `cachedef_*` language strings the plugin's MUC cache
  areas (`db/caches.php`) were missing, so they display properly on
  the site admin's cache configuration screen.

## 1.0.0 — Initial release

First public release. Everything runs as plain PHP inside the plugin
itself — no separate service, no external dependency beyond PHP.

**Features**
- Course-wide cross-quiz comparison (grade distribution, engagement over
  time, attempts-vs-grade scatter, per-quiz stats and trend lines) across
  every STACK quiz in a course.
- Per-quiz **Question Analytics**: difficulty and discrimination indices,
  response distribution, a per-question error drill-down, a student
  performance matrix, and consolidated question metrics.
- Per-quiz **Solution Process Visualization**: class-wide PRT answer
  transition graphs, per-node network centrality, PRT/tree-edit-distance
  3D distance charts, and a cross-attempt comparison with a clickable
  per-student drill-down.
- **Generate PDF Report** on every view, with section checkboxes, a
  colorblind-mode toggle, and an anonymize-student-data toggle (replaces
  real names/emails with stable per-student pseudonyms, consistent
  across every table, chart, and PDF) — charts are captured client-side
  from the already-rendered page and embedded into a PHP-generated PDF
  (TCPDF).
- Reachable from a course's secondary navigation, and from an **Analytics**
  link this plugin adds to each STACK quiz's own settings menu.
- Colorblind-safe chart palettes throughout, toggled next to the
  anonymize-student-data checkbox.
