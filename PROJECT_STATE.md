# LLCC OJT System — Project State

**Last updated:** 2026-08-22
**Session summary:** Built items #3 and #4 of the user's four-item plan
in one push — the Coordinator Student Roster + Reassignment feature
(BR-1) and the full MAR module (data-model.md's Monthly Accomplishment
Report). Delivered as a zip with a README covering judgment calls and a
click-test list. **Not yet confirmed applied or click-tested by the
user** — this session's uploaded "live" zip predates both features (no
`Coordinator\StudentController`, no `MonthlyAccomplishmentReport` model
anywhere in it), so everything below for items #3/#4 is "built and
delivered," not "confirmed working," until the user reports back.

This file is a complete, self-contained picture of the app as it exists
right now — it does not assume you've read any earlier version of this
file or any prior conversation. Anyone picking this up cold should be
able to work from this file alone (plus the `llcc-ojt-system` skill for
business rules/data model/workflows, and `llcc-ojt-codegen` for delivery
conventions — both are user-level skills, not bundled with this file).

## Stack (locked in)
Laravel 12, PHP 8.2.12, MySQL (`ojt_system` database), Blade (no Vue/JS
used in any view built so far, despite architecture-decisions.md
allowing for it), Laravel Breeze (auth), barryvdh/laravel-dompdf (PDF
generation — DAR, WAR, and now MAR). Dev environment is XAMPP on Windows
(`C:\xampp\htdocs\ojt-system`), no `composer install`/real PHP execution
possible in the sandbox this work is done in — every fix here is
verified by manual reasoning, hand-diffing against the user's real
uploaded zip, brace-balance and Blade-directive-balance checks, never an
actual `php -l` run (the sandbox has no `php` binary).

## Codebase inventory

| File/Module | Purpose | Status |
|---|---|---|
| `app/Http/Controllers/Controller.php` | Base controller with `AuthorizesRequests` trait | Done, unchanged this session (fixed two sessions ago). |
| DAR module (model, controller, request, policy, migration, views, PDF) | Full daily-report workflow | Done, unchanged this session. End-to-end click-through still **not yet confirmed live** — only WAR has been. |
| WAR module (model, controller, request, policy, migration, views, PDF) | Full weekly-report workflow (BR-8) | Done, confirmed working end-to-end (confirmed 2 sessions ago). Unchanged this session except serving as MAR's structural reference. |
| **MAR module (model, controller×2, requests×3, policy, migration, views×2, PDF)** | Monthly-report workflow | **Built this session — item #4.** Single-status-per-row shape mirroring DAR's review pattern, NOT WAR's four-section pattern — see "Judgment calls" below. **Not yet confirmed live.** |
| **Coordinator student roster + reassignment (BR-1)** | Item #3 of the user's plan | **Built this session.** `Coordinator\StudentController` (`index`/`show`/`reassign`), `ReassignStudentRequest`, roster + detail views. Also fills in coordinator read-access to a student's company-assignment history (flagged as missing since the Company Switch UI session). **Not yet confirmed live.** |
| `app/Policies/StudentPolicy.php` | View/update authorization for Student | Existed already with the right shape for item #3 — **confirmed already registered** in `AppServiceProvider` by reading the live zip directly, contrary to the handoff note's assumption that it still needed registering. Untouched this session. |
| `app/Providers/AppServiceProvider.php` | Policy registration | **Updated this session** — added `Gate::policy(MonthlyAccomplishmentReport::class, MarPolicy::class)`. `StudentPolicy`/`DarPolicy`/`WarPolicy` lines untouched (already correct). |
| `app/Services/CompletedHoursRecalculator.php` | BR-10 completed-hours derivation | **Updated this session** — now sums Approved MAR hours (`monthly_total_hours`) alongside the existing DAR+WAR sums, replacing the comment placeholder that was already there. Same additive-sum approach, class not restructured. |
| `app/Models/Student.php` | Student model | **Updated this session** — added `monthlyAccomplishmentReports()` relation. Nothing else changed. |
| `resources/views/coordinator/dashboard.blade.php` | Coordinator landing page | **Updated this session** — "My Students" section now links to the new roster (`coordinator.students.index`) and each student row links to their new detail page (`coordinator.students.show`). |
| `resources/views/coordinator/cycles/index.blade.php` | Cycle list | **Updated this session** — added a "MAR Review" link next to the existing "DAR Review"/"WAR Review" links. |
| `resources/views/student/dashboard.blade.php` | Student landing page | **Updated this session** — added a "This month's MAR" button to the action row, alongside the existing DAR/WAR/Info-Sheet/Company links. |
| `routes/web.php` | All routes | **Updated this session** — uncommented and implemented `coordinator.students.{index,show}`, added `coordinator.students.reassign`, added `student.mar.{show,update,submit}`, `coordinator.mar.{review,review.act}`, and the shared `mar.pdf` route. Hand-diffed clean against the user's uploaded live copy. |
| Company switch UI (BR-12) | Student self-service company-change flow | Done, confirmed present in this session's uploaded live zip (the CompanyAssignmentController the previous session delivered is there) — but **still no explicit click-test report from the user**, so still listed as unconfirmed per the last session's convention until the user actually reports back. |
| Submission Cycle creation (BR-5) | Coordinator-only cycle creation | Done, unchanged this session. |
| OJT Information Sheet | One-time student onboarding form | Done, unchanged this session. Still not click-tested per prior sessions' notes. |
| `database/database.sqlite` | Unused leftover file | Still present, harmless, never addressed, low priority. |

## Business rules implemented so far

| BR-# | Where enforced | Notes |
|---|---|---|
| BR-1 | `Coordinator\StudentController::reassign()` + `ReassignStudentRequest` | **Built this session.** Reassignment is a plain `coordinator_id` update — history isn't a separate historized table (unlike BR-12's `company_assignments`) because `data-model.md` only requires the *current* link to change, and DAR/WAR/MAR/CompanyAssignment all key off `student_id`, never `coordinator_id`, so nothing else needs special handling. **Not yet confirmed live.** |
| BR-2 | `MonthlyAccomplishmentReport` model/migration + related controllers | **Built this session** — MAR now exists as its own table/model/workflow. `required_hours` itself (the per-student value BR-2 is actually about) was already enforced via the `students.required_hours` column from an earlier session; this session just gave MAR — one of the things that accumulates toward it — a real implementation. |
| BR-3 | `DailyAccomplishmentReport::calculateHoursRendered()` | Done, DAR only, unchanged. |
| BR-4 | `StoreDarRequest`/`UpdateDarRequest` | Done, unchanged. |
| BR-5 | `Coordinator/SubmissionCycleController` | Done, unchanged. |
| BR-6 | `SubmissionCycle::isPastDeadline()` | Done for DAR, WAR, **and now MAR** (`MarController::submit()`) — same centralized helper, never compared against `month_period`. |
| BR-7 | Per-document independent status | Done for DAR and WAR (per-week). **MAR is reviewed independently from DAR/WAR at the document level** — it has no internal sub-sections to independently track (data-model.md confirms MAR is single-status, unlike WAR), so BR-7 applies to MAR only at the "separate route/table from DAR/WAR" level, not a per-section level. |
| BR-8 | `WeeklyAccomplishmentReport` model + `WarController`/`WarReviewController` | Done, unchanged, confirmed live in an earlier session. |
| BR-9 | SoftDeletes on `DailyAccomplishmentReport` | Done, DAR only — WAR and MAR both have no destroy action at all (MAR intentionally built with no delete path this session, matching WAR's existing precedent). |
| BR-10 | `CompletedHoursRecalculator` | Done for DAR + WAR (confirmed live earlier) **+ MAR, wired this session**. The Coordinator "reopen a Completed record" action still doesn't exist for any document type. |
| BR-11 | Every query scoped through `coordinator`/`student` relations | Done throughout, including all new code this session (`StudentController`, `MarController`, `MarReviewController`, `MarPdfController`). |
| BR-12 | `CompanyAssignmentController::store()` + `StoreCompanyAssignmentRequest` | Unchanged this session. Coordinator **read access** to a student's company history — flagged as missing when this shipped — is now filled in via `Coordinator\StudentController::show()`. |
| BR-13 | `students.coordinator_id` non-nullable + `restrictOnDelete` on the coordinator FK | Schema-level guarantee unchanged. `ReassignStudentRequest` additionally validates the target coordinator actually exists (`exists:coordinators,id`) before a reassignment can null out or misdirect the link. No standalone "duplicate active assignment" check was needed — the data model only allows one `coordinator_id` value per student at all (not a list of rows), so duplication isn't structurally possible the way it would be for `company_assignments`. |
| BR-14 | Backend-enforced isolation | Held throughout — every new controller this session scopes through `request()->user()->student`/`->coordinator`, never a bare model query, and every write action still calls the relevant Policy via `$this->authorize()`. |

## Last change (detailed)

**Item #3 — Coordinator Student Roster + Reassignment, and Item #4 — MAR
Module**, delivered together as one zip:

### Item #3
- **New:** `Coordinator\StudentController` (`index()` lists via
  `$coordinator->students()`; `show()` loads a student plus their full
  `companyAssignments` history, authorized via `StudentPolicy::view()`;
  `reassign()` updates `coordinator_id` only, authorized via
  `StudentPolicy::update()`), `ReassignStudentRequest` (validates the
  target coordinator exists and differs from the current one).
- **New views:** `coordinator/students/index.blade.php`,
  `coordinator/students/show.blade.php` (includes the reassignment
  form and the read-only company-assignment history table).
- **Correction surfaced to the user, not silently applied:** the
  handoff note said `StudentPolicy` "isn't registered anywhere yet" —
  reading the actual uploaded live zip showed it already was
  (`Gate::policy(Student::class, StudentPolicy::class)` was already
  present in `AppServiceProvider`). Treated as already-done; no
  duplicate registration added.
- **Design decision, not a documented rule:** BR-1 doesn't require
  historizing coordinator assignments the way BR-12 requires for
  company assignments — reassignment is a plain column update, no new
  history table invented (per the downstream contract's "never invent
  entities not in data-model.md" rule). Flagged to the user in the
  README as worth revisiting if an audit trail of reassignments is
  wanted later — that would also need `AuditLog` (in data-model.md, but
  not built anywhere in the app yet) to actually go somewhere.

### Item #4
- **New table/model:** `monthly_accomplishment_reports` migration,
  `MonthlyAccomplishmentReport` model — `student_id`, `cycle_id`,
  `month_period`, `activities_text`, `monthly_total_hours`, `remarks`,
  `status`, `coordinator_comment`, `reviewed_by`, `reviewed_at`. Single
  status per row, matching DAR's shape, not WAR's four-week-column shape.
- **New policy:** `MarPolicy` (`view`/`update`/`submit`/`review`/
  `generatePdf`) — mirrors `DarPolicy` directly.
- **New requests:** `UpdateMarRequest` (BR-10 completion-lock check,
  same shape as `StoreDarRequest`), `SubmitMarRequest` (cycle-ownership
  check, same shape as `SubmitDarRequest`), `ReviewMarRequest`
  (required-comment-on-return, same shape as `ReviewDarRequest`).
- **New controllers:** `Student\MarController` (`show()` lazily
  creates the current month's row — pattern borrowed from
  `WarController::show()` — `update()`/`submit()` mirror DAR's
  single-status shape), `Coordinator\MarReviewController` (`show()`/
  `review()` mirror `DarReviewController` directly), `MarPdfController`
  (`forMonth()`, per student+month like `WarPdfController`, since a MAR
  row already is one month's whole document).
- **New views:** `student/mar/show.blade.php` (status badge, edit form
  while Draft/Returned, submit-into-cycle form, read-only display once
  submitted), `coordinator/mar/review.blade.php` (per-student cards,
  approve/return form — mirrors `coordinator/dar/review.blade.php`),
  `pdf/mar.blade.php` (official-format layout).
- **Wired into `CompletedHoursRecalculator`** exactly where the
  existing comment marked — added an `$approvedMarHours` sum, same
  additive pattern as DAR+WAR, class not restructured.
- **Judgment calls, all flagged to the user in the README rather than
  silently assumed:**
  1. MAR structurally mirrors DAR's single-status review shape, not
     WAR's four-section shape, despite the handoff instruction to
     "mirror WAR as closely as BR-2 allows" — `data-model.md` is
     explicit that MAR has one `status`/`coordinator_comment`/
     `reviewed_by`/`reviewed_at` per row, not four. The
     lazy-create-on-visit *pattern* (not the review shape) is still
     borrowed from WAR, since both are one-row-per-month documents.
  2. `monthly_total_hours` is treated as direct student input (same as
     WAR's `week{n}_hours`), not auto-computed from that student's WAR
     data for the month, even though `pdf-forms.md` describes the
     printed MAR as a "Summary of Weekly Accomplishment Reports" —
     `data-model.md` gives MAR its own plain field with no derivation
     rule specified.
  3. MAR submission is NOT hard-gated on that month's WAR (Week 1–4)
     being complete first — `data-model.md` establishes no dependency
     between the two entities; the UI just shows an advisory note.
  4. No delete/destroy action exists for MAR, matching WAR's existing
     precedent (WAR also has none in the running app).
  5. The MAR PDF renders `activities_text` as a single text block
     rather than the official form's per-date table, since the MAR
     entity only stores one blob per month, not per-date rows — flagged
     as worth resolving with LLCC directly, possibly by adding an
     explicit rule to `open-items.md`.
- **Full-file replacements**, hand-diffed clean against the user's
  uploaded `ojt-system-live.zip` before delivery: `routes/web.php`
  (added `coordinator.students.*`, `student.mar.*`,
  `coordinator.mar.*`, `mar.pdf` — plus uncommented/implemented the two
  previously-commented student-roster route placeholders),
  `app/Models/Student.php` (added `monthlyAccomplishmentReports()`
  only), `app/Providers/AppServiceProvider.php` (added the `MarPolicy`
  registration only), `app/Services/CompletedHoursRecalculator.php`
  (added the MAR sum only), `resources/views/coordinator/dashboard.blade.php`
  (roster links only), `resources/views/coordinator/cycles/index.blade.php`
  ("MAR Review" link only), `resources/views/student/dashboard.blade.php`
  ("This month's MAR" button only).
- **Migration required this time** (unlike the Company Switch UI
  session, which needed none) — `php artisan migrate` must be run for
  `monthly_accomplishment_reports` to exist.
- **Not yet applied or click-tested.** This session's uploaded "live"
  zip has no `Coordinator\StudentController` and no
  `MonthlyAccomplishmentReport` model anywhere in it, confirming the
  delivery predates both features. The README's click-test list is
  outstanding — see Next steps.

**Process notes reaffirmed this session** (carried forward, still
holding): every file that replaces something the user already has was
delivered as a complete file, never a snippet or partial diff — all
seven full-file replacements listed above were hand-diffed against the
user's actual live content (`ojt-system-live.zip`) before delivery to
confirm only the intended lines changed.

## Next steps / open questions

1. **Apply and click-test items #3 and #4 (just built).** Run the
   migration, drop in the new files, apply the seven full-file
   replacements, run `view:clear` + `route:clear`, then work through the
   README's 8-item click-test list — including manually creating a
   second Coordinator account first, since `DevTestSeeder` only creates
   one and reassignment needs a second coordinator to move a student to.
2. **The user's original 4-item plan is now fully built** (Info Sheet →
   Company Switch UI → Coordinator roster/reassignment → MAR), though
   only the Info Sheet and WAR have actually been click-tested live so
   far. Once #3/#4 above are confirmed, the natural next phase is
   working through the still-outstanding items below rather than new
   feature work.
3. **DAR click-through still not confirmed live** — only WAR has been.
4. **OJT Information Sheet form itself not yet click-tested** — dashboard
   link/page loads are confirmed, but no one has submitted the form yet
   to confirm the one-time lock holds on a resubmit attempt.
5. **Company Switch UI (item #2) still has no explicit click-test
   report** from the user, despite the code now visibly being present in
   the live zip — the 4-item click-test list from that session's README
   is still technically outstanding.
6. Whether register/password-reset have the login bug's email→username
   pattern — flagged since session 1, still never checked.
7. BR-10's "reopen a Completed record" coordinator action still doesn't
   exist for any document type (DAR, WAR, or now MAR).
8. **New this session:** whether `monthly_total_hours` should actually
   be auto-computed from that student's WAR hours for the month rather
   than typed in fresh — see judgment call #2 above.
9. **New this session:** the MAR PDF's activities table vs. single-blob
   mismatch (judgment call #5 above) — worth adding to
   `open-items.md` in the `llcc-ojt-system` skill if it needs to stay
   formally unresolved, or resolving directly with LLCC.
10. **New this session:** whether an audit trail of coordinator
    reassignments is wanted (would require building `AuditLog`, which
    exists in `data-model.md` but isn't implemented anywhere in the app
    yet for any action).
11. Same-day-cutover convention for company switches (from the Company
    Switch UI session) — still an unconfirmed judgment call.
12. The leftover `database/database.sqlite` file — harmless, never
    cleaned up, very low priority.
