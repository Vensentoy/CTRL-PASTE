# QReport — Meeting-Based Submission Redesign (handoff for opencode)

**PLAN MODE ONLY. Do not touch code yet.** Per the project's established
pattern, produce a plan first; it will be reviewed critically before any
build step is approved. If anything below is ambiguous, ask rather than
guess — a wrong guess here touches business rules (BR-5/BR-6/BR-8), not
just a template.

## Why (real-world context, not invented)

The system is LAN-only; students only get access at OJT-coordinator-called
"meetings" (ad hoc — e.g. "4th OJT Meeting," no fixed weekly/monthly
schedule). Students prepare their AR content offline/at their company, and
only encode + submit it in the system in one sitting, at a meeting. A real
past OJT student's actual submitted forms (used as reference data) and a
direct confirmation from him establish: **there is no "late" concept.**
Students submit whatever complete report block they have, whenever they
next get a meeting. An incomplete block (e.g. only 3 of 5 DAR days done)
just waits for the next meeting — no penalty, no forced partial submission.

## Confirmed decisions — do not re-litigate these

1. **"Meeting" is a real system concept** (not just a date stamp). It maps
   onto the *existing* `SubmissionCycle` model — no rename, no new table.
   `SubmissionCycle` already has `cycle_name`, `coverage_start_date`,
   `coverage_end_date`, `deadline_date`, and is already coordinator-created
   manually with no auto-generation. It already fits.
2. **DAR needs no structural change.** It already batches any number of
   un-submitted (`cycle_id = null`) rows into whichever cycle is open —
   this already matches "submit a complete block whenever ready." The only
   change is removing 'Late' as a possible outcome (see #5).
3. **WAR needs a real structural restructure** (see schema section below).
4. **MAR needs no schema change to the row itself** (still one row per
   student per month), but its hour-derivation logic and status-assignment
   must be updated because both currently depend on WAR's soon-to-be-gone
   `month_period`/`week{n}_hours` columns and on `isPastDeadline()` (see
   Ripple effects).
5. **"Late" is removed entirely, system-wide — DAR, WAR, and MAR, not just
   WAR.** `SubmissionCycle::isPastDeadline()` stops being used anywhere to
   assign status. Every `status` enum (`daily_accomplishment_reports`,
   `weekly_accomplishment_reports`-successor, `monthly_accomplishment_reports`)
   drops 'Late' as a possible value, leaving: Draft, Pending, Approved,
   Returned.
6. **Assumption (flag if wrong):** `SubmissionCycle.deadline_date` stays as
   a column — informational metadata (when the meeting happens) — it just
   stops being read for lateness. Don't drop the column unless told to.
7. **Uneven week/month boundaries are moot** under the new model — there's
   no calendar-week or calendar-month grouping being enforced anymore. A
   WAR "week" is just whatever contiguous block of days the student is
   reporting, given a start date, not a fixed 1–7/8–14/etc. split.

## New WAR schema (replaces the current one)

Current `weekly_accomplishment_reports`: one row per (`student_id`,
`month_period`), with hardcoded `week1_activities..week4_activities`,
`week1_hours..week4_hours`, `week1_status..week4_status`,
`week1_comment..week4_comment`, `cycle1_id`, `cycle2_id`.

**New shape — one row per week, mirroring `daily_accomplishment_reports`
exactly:**

| column | notes |
|---|---|
| `student_id` | same as before |
| `week_start_date` | date — start of the real work-week block being reported (student-entered, transcribed from their own prepared AR); not calendar-locked |
| `activities` | JSON array of plain strings — same semantics as the current `week{n}_activities` (no per-line times, WAR carries one shared week range, per existing `data-model.md`) |
| `hours` | decimal — direct student input, same as today's `week{n}_hours` |
| `status` | Draft / Pending / Approved / Returned — no Late |
| `comment` | coordinator comment on return |
| `cycle_id` | **nullable, single FK** (not `cycle1_id`/`cycle2_id`) — null while Draft, set on submit, same `nullOnDelete` pattern as DAR |

**Submission:** mirror `DarController::submit()` exactly — student selects
any number of not-yet-submitted (`cycle_id = null`) week rows and submits
them into whichever cycle (meeting) is currently open. No hard-coded pair
count, no minimum/maximum week count enforced by the schema — "is this
block complete" is a human/paper-level judgment, not a database
constraint.

**Review:** BR-7 (independent per-section review) is preserved — it's now
naturally per-row instead of per-column, which is simpler, not a
regression.

**PDF template (`resources/views/pdf/war.blade.php`):** needs a real
rewrite either way (from four fixed week-columns to N week-rows for
whatever weeks are in this submission batch). Two things I need your plan
to explicitly decide and state, not silently assume:
- What does one generated WAR PDF now represent — one submission batch (whatever weeks came in together), or something else? The old one-PDF-per-month assumption doesn't survive this change.
- Filename convention for the WAR PDF/bundle entry, now that "the month" is no longer the natural unit (see `ReportBundleBuilder` below).

## Ripple effects — must be updated, not just WAR's own files

These are real, verified dependencies (read directly from the current
codebase), not guesses:

- **`app/Http/Controllers/Student/MarController.php` —
  `monthlyHoursFromWar()`** currently does a single lookup
  (`WeeklyAccomplishmentReport::where('student_id', ...)->whereDate('month_period', $monthPeriod)->first()`)
  and sums that one row's `week1_hours..week4_hours`. This breaks
  completely under the new schema (no `month_period` column, no single
  row). Must be rewritten to sum `hours` across all of that student's new
  WAR week-rows whose `week_start_date` falls within the target month (or
  whatever grouping your plan proposes — flag this as a decision point).
  Also drop its `isPastDeadline() ? 'Late' : 'Pending'` line (BR-6 removal).
- **`app/Services/CompletedHoursRecalculator.php`** currently loops
  `week{1..4}_status`/`week{1..4}_hours` on one WAR row per student and
  sums Approved weeks. Must be rewritten to sum `hours` across all
  `Approved` rows in the student's new WAR week-rows — this actually gets
  *simpler* under the new schema (no per-week-number loop needed, just one
  `where('status', 'Approved')->sum('hours')`). The MAR-exclusion logic
  (MAR hours are derived from WAR, so MAR is deliberately excluded from
  this sum to avoid double-counting) must be preserved — don't lose that
  guard while rewriting.
- **`app/Services/CohortAggregator.php`** — two `whereDate('month_period', ...)`
  lookups against WAR need re-deriving against the new per-week schema.
- **`app/Services/ReportBundleBuilder.php`** — looks up WAR via
  `whereDate('month_period', $monthStart)`, names the file
  `WAR-{id}-{month}.pdf`, and filters both DAR and WAR status lists on
  `whereIn('status', [..., 'Late', ...])`. Needs: (a) 'Late' dropped from
  both status filter lists, (b) a new WAR lookup/grouping strategy since
  there's no single per-month row anymore, (c) a filename convention that
  doesn't assume "one WAR = one month" (tie it to the submission
  batch/cycle instead, unless you have a better proposal — state your
  reasoning).
- **Every other file below currently references `isPastDeadline()` or the
  literal `'Late'` status** and needs the Late branch/badge removed.
  Confirm each one in your plan individually — don't summarize this as
  "and related files":
  - `app/Http/Controllers/Coordinator/DashboardController.php`
  - `app/Http/Controllers/Coordinator/DepartmentSummaryReportController.php`
  - `app/Http/Controllers/Student/DarController.php`
  - `app/Http/Controllers/Student/DashboardController.php`
  - `app/Http/Controllers/Student/WarController.php` (full rewrite anyway)
  - `app/Models/SubmissionCycle.php` (remove or repurpose `isPastDeadline()` — your call whether to delete it outright or leave it unused/deprecated; state which and why)
  - `app/Policies/DarPolicy.php`, `app/Policies/MarPolicy.php`, `app/Policies/WarPolicy.php`
  - `database/migrations/2024_01_01_000005_create_submission_cycles_table.php`
  - `database/migrations/2024_01_01_000006_create_daily_accomplishment_reports_table.php`
  - `database/migrations/2024_01_01_000011_create_monthly_accomplishment_reports_table.php`
  - `database/seeders/FullFeatureTestSeeder.php`
  - `resources/views/coordinator/dar/review.blade.php`
  - `resources/views/coordinator/mar/review.blade.php`
  - `resources/views/coordinator/reports/department-summary.blade.php`
  - `resources/views/coordinator/war/review.blade.php`
  - `resources/views/student/dar/index.blade.php`
  - `resources/views/student/dashboard.blade.php`
  - `resources/views/student/mar/show.blade.php`
  - `resources/views/student/war/show.blade.php` (full rewrite anyway)

## Also required

- Update **both** copies of `data-model.md` together, byte-identical:
  `spec/data-model.md` and
  `.opencode/skill/qreport/references/data-model.md` — per the project's
  own standing rule.
- Update `business-rules.md` — BR-5, BR-6, BR-8 all change meaning; don't
  leave stale rule text describing the old two-slot/deadline behavior.
- Rewrite/extend tests: `WarWorkflowTest` (full rewrite — schema changed),
  `DarWorkflowTest` (Late assertions removed), `CompletionAndPdfTest`
  (touches `CompletedHoursRecalculator` and the overlapping-hours case
  directly), plus a MAR test covering the new `monthlyHoursFromWar()`
  logic.
- No production data exists yet (seed/demo only, next year's cohort) —
  same as the last schema change, a clean migration + reseed is fine, no
  data-preservation migration needed.

## What I need back

A plan (not code) that: names every file it will touch (cross-check
against the Ripple effects list above and flag anything I missed), states
its answers to the two open WAR-PDF decision points, and states its
approach to the MAR/CompletedHoursRecalculator/CohortAggregator rewrites
specifically — those three are the parts most likely to get hand-waved as
"update accordingly" without actually being thought through.
