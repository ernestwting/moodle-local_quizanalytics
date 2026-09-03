\[ [STACK q-type Analytics Docs](index.md) → Getting Started \]

# Getting Started

The fastest path from "just installed" to your first report on screen.
For every option, every setting, and full troubleshooting, see
[Installation](installation.md) and the [Instructor Guide](instructor-guide.md) —
this page is the short version.

## Video walkthrough

> **[Placeholder: add tutorial video link/embed here]**
>
> _A short screen recording walking through installation and a first look
> at each section will go here._

## Before you start

You need, in order:

1. A Moodle site with **`qtype_stack`** already installed — this plugin has
   nothing to analyze without it.
2. Admin access to install a plugin (either the web uploader or shell/SFTP
   access — see [Installation](installation.md)).
3. **Moodle cron running on a schedule.** This is not optional — see
   [Installation § Prerequisites](installation.md#prerequisites) for why,
   and how to check.
4. At least one course with a quiz that has a STACK question **added
   directly to a slot** (not pulled in only via "random question from
   category"), with at least one **finished** attempt.

## Five-minute path

1. **Install.** Site administration → Plugins → Install plugins → upload a
   zip of this repository. Full detail: [Installation § 1](installation.md#1-place-the-files).
2. **Upgrade.** Visit Site administration once more — this registers the
   capability, the caches, both models (disabled), and the scheduled task.
   Full detail: [Installation § 2](installation.md#2-run-the-moodle-upgrade).
3. **Open a course** that has a STACK quiz with at least one finished
   attempt. Look for an **Analytics** entry in the course's secondary
   navigation (check inside **More** if the bar is full).
4. You land on **Quiz Analytics** — the course-wide view. Use the
   **Section:** switcher at the top to try **Question Analytics** and
   **Model Analytics** too.
5. Try **Download PDF** on whichever view is showing.

That's the whole loop. For what each section actually shows and how to
read it, go to the [Instructor Guide](instructor-guide.md) next.

## Tested at

This isn't a claimed-unlimited system — it's been directly benchmarked
against real and synthetic data, not just assumed to scale:

- A real production course: **38 quizzes, 48,445 finished attempts.**
- A synthetic stress test: **50 quizzes × 1,000 students** (50,000 total
  attempts).

See [`CHANGELOG.md`](../../CHANGELOG.md) for the specific timings at each
scale, and [Installation](installation.md) for the sizing settings that
make a large course practical (parallel cache-warming workers, the
on-demand background-compute safeguard).

## If something doesn't show up

Jump straight to [Installation § Troubleshooting quick-reference](installation.md#troubleshooting-quick-reference) —
most "nothing appeared" cases come down to one of: the plugin not
installed, no STACK question directly in a slot, no finished attempts yet,
or Moodle cron not actually running.
