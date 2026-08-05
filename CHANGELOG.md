# Changelog

All notable changes to `local_quizanalytics` are documented here. Version
numbers match `version.php`'s `$plugin->release`.

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
- **Generate PDF Report** on every view, with section checkboxes and a
  colorblind-mode toggle — charts are captured client-side from the
  already-rendered page and embedded into a PHP-generated PDF (TCPDF).
- Reachable from a course's secondary navigation, and from an **Analytics**
  link this plugin adds to each STACK quiz's own settings menu.
- Colorblind-safe chart palettes throughout, toggled from the settings
  navigation.
