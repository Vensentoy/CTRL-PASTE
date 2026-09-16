# LLCC OJT System — Delivery: Item #3 (Coordinator Roster + Reassignment) + Item #4 (MAR Module)

Built together in one push per your request. Everything below is **built,
not yet confirmed live** — this session's sandbox has no `php` binary, so
every file was verified by manual reasoning, hand-diffing against your
uploaded `ojt-system-live.zip`, and brace/Blade-directive-balance checks
only, same limitation noted in the last PROJECT_STATE.md.

## Where this goes

Drop every file in this zip into your project at the same relative path
(e.g. `app/Http/Controllers/Coordinator/StudentController.php` goes to
`C:\xampp\htdocs\ojt-system\app\Http\Controllers\Coordinator\StudentController.php`).
Files that already exist in your project (`routes/web.php`,
`app/Models/Student.php`, `app/Providers/AppServiceProvider.php`,
`app/Services/CompletedHoursRecalculator.php`,
`resources/views/coordinator/dashboard.blade.php`,
`resources/views/coordinator/cycles/index.blade.php`,
`resources/views/student/dashboard.blade.php`) are **full-file
replacements** — overwrite them, don't merge by hand. Every other file
listed below is new.

## Commands to run after applying

```
php artisan migrate
php artisan view:clear
php artisan route:clear
```

The migration is required this time (unlike last session's Company
Switch UI delivery) — it creates the new `monthly_accomplishment_reports`
table.

---

## Item #3 — Coordinator Student Roster + Reassignment (BR-1)

**New files:**
- `app/Http/Controllers/Coordinator/StudentController.php` — `index()`
  (roster via `Coordinator::students()`), `show()` (detail + full
  company-assignment history, read-only), `reassign()` (changes
  `coordinator_id` only).
- `app/Http/Requests/ReassignStudentRequest.php` — validates the target
  coordinator exists and differs from the current one.
- `resources/views/coordinator/students/index.blade.php`,
  `resources/views/coordinator/students/show.blade.php`.

**Correction to the handoff note:** `StudentPolicy` was **already
registered** in `AppServiceProvider` before this session — I confirmed
this by reading your uploaded `ojt-system-live.zip` directly (the
`Gate::policy(Student::class, StudentPolicy::class)` line was already
there). The handoff assumed it still needed registering; it didn't.
`AppServiceProvider.php` in this delivery only adds the new `MarPolicy`
line for item #4 — the `StudentPolicy` line is untouched.

**How reassignment works (BR-1):** `students.coordinator_id` is a plain
"current assignment" column, not a historized list of rows the way
`company_assignments` is for BR-12. Reassigning a student is just
updating that one column — every DAR/WAR/MAR/CompanyAssignment row keys
off `student_id`, never `coordinator_id`, so history and hours are
untouched automatically, with no extra code needed to "preserve" them.
I deliberately did **not** build a `coordinator_assignments` history
table (mirroring how `company_assignments` historizes company changes)
because `data-model.md` doesn't define one — BR-1 only requires that
history isn't altered when the current link changes, which the existing
schema already guarantees by construction. If you want an audit trail of
*who was reassigned when*, that would need a new entity added to the
`llcc-ojt-system` skill first (the downstream contract says not to
invent one silently), and there's also no `AuditLog` table actually
built anywhere in the app yet despite it being in `data-model.md` — this
delivery doesn't add one, consistent with every other review action
(DAR/WAR approve/return) also not writing an audit entry today.

**Judgment call:** the reassignment dropdown lists *every* coordinator
in the system (all coordinators are equal rank per
`roles-and-permissions.md`, so this isn't a scope leak). After a
successful reassignment, the acting coordinator is redirected to the
roster list, not back to the student's page — `StudentPolicy::view()`
would immediately deny them access to a student they no longer own, so
sending them back to that page would just 403.

---

## Item #4 — MAR Module (BR-2 in your numbering)

**The main judgment call, flagged as instructed rather than silently
resolved:** your handoff said "mirror the WAR pattern as closely as BR-2
allows." Reading `data-model.md`, MAR is **not** shaped like WAR. It's
one row per student per month with a **single** `status` /
`coordinator_comment` / `reviewed_by` / `reviewed_at` trail — the exact
same review shape as DAR, not WAR's four independently-reviewed
week-sections. So this delivery is a deliberate **hybrid**:

- **Creation pattern** borrowed from WAR: `MarController::show()` lazily
  creates the current month's row on first visit
  (`firstOrCreate`), same as `WarController::show()` — because MAR, like
  WAR, is naturally one row per month, not many rows like DAR.
- **Review/status shape** borrowed from DAR: one `status` field, one
  `cycle_id`, reviewed as a single decision per row —
  `MarReviewController`/`MarPolicy`/`ReviewMarRequest` all mirror
  `DarReviewController`/`DarPolicy`/`ReviewDarRequest` directly, *not*
  `WarReviewController`'s per-week `week` parameter.

I did **not** literally clone WAR's four-section columns onto MAR,
since `data-model.md` explicitly gives MAR just `activities_text`,
`monthly_total_hours`, and `remarks` — a single free-text
document, not four. Flagging this clearly since it's a direct
deviation from "mirror WAR" as literally instructed.

**Second judgment call — `monthly_total_hours` is direct student
input**, not auto-computed from that student's WAR hours for the month.
`pdf-forms.md`'s official layout describes the MAR body as a "Summary of
Weekly Accomplishment Reports," which *could* suggest deriving it from
WAR data — but `data-model.md` lists `monthly_total_hours` as MAR's own
plain field with no derivation rule, the same treatment WAR's own
`week{n}_hours` columns get (WAR also has no DAR-style BR-3 derivation).
Built as direct input for now. Worth confirming against LLCC's actual
paper process — if MAR is meant to auto-total from WAR, that's a small
follow-up change to `MarController::show()`/`update()`.

**Third judgment call — no hard gate on WAR completion.** Workflows.md
describes MAR submission as happening "if the month's coverage is now
complete" (implying it naturally follows the WAR's Week 3–4 submission).
I did **not** build a technical gate requiring `WeeklyAccomplishmentReport`
Week 1–4 to all be Approved/submitted before a MAR can be submitted —
`data-model.md` establishes no foreign-key or dependency between the two
entities, and inventing one risks blocking a legitimate submission LLCC's
real process might actually allow independently. The MAR submit page just
shows an advisory note instead of a hard block.

**Fourth judgment call — no delete/destroy action for MAR**, matching
WAR's existing precedent (WAR also has no destroy route in the live app,
noted as a standing gap in the last PROJECT_STATE.md). Since MAR rows are
lazily created and edited in place (like WAR), not created fresh per
entry (like DAR), there was nothing to delete.

**Fifth judgment call — PDF layout simplification.** `pdf-forms.md`
describes the MAR body as a *per-date* two-column table (Date |
Activities). The MAR entity only stores one `activities_text` blob per
month, not per-date rows — so `resources/views/pdf/mar.blade.php`
renders that blob as a single text block instead of a table, the same
category of simplification `pdf/war.blade.php` already documents for
WAR's own per-week table. I did **not** silently derive a per-date
breakdown from that student's DAR rows for the month to fill the
official table shape, since nothing in `data-model.md` establishes that
MAR's PDF should pull from DAR data. Flagging this as worth resolving
with LLCC directly (add it to `open-items.md` if it needs to stay
unresolved) rather than guessing.

**New files:**
- `database/migrations/2024_01_01_000011_create_monthly_accomplishment_reports_table.php`
- `app/Models/MonthlyAccomplishmentReport.php`
- `app/Policies/MarPolicy.php`
- `app/Http/Requests/UpdateMarRequest.php`,
  `app/Http/Requests/SubmitMarRequest.php`,
  `app/Http/Requests/ReviewMarRequest.php`
- `app/Http/Controllers/Student/MarController.php` — `show()` (lazy
  create), `update()` (save content, resubmit-on-Return), `submit()`
  (into a cycle).
- `app/Http/Controllers/Coordinator/MarReviewController.php` — `show()`
  (per-cycle list), `review()` (approve/return one row).
- `app/Http/Controllers/MarPdfController.php` — `forMonth()`.
- `resources/views/student/mar/show.blade.php`,
  `resources/views/coordinator/mar/review.blade.php`,
  `resources/views/pdf/mar.blade.php`.

**BR-10 wiring, as instructed:** `CompletedHoursRecalculator` now sums
Approved MAR hours (`monthly_total_hours`) alongside the existing DAR
and WAR sums, replacing the comment placeholder that was already there.
Same additive-sum approach the class already used for DAR+WAR — I didn't
restructure the class, just extended the existing pattern.

**BR-6/BR-7 check, as instructed:** BR-6 (deadline-based lateness) is
enforced identically to DAR/WAR via `SubmissionCycle::isPastDeadline()`
in `MarController::submit()`. BR-7 (independent per-document review)
applies at the "MAR is reviewed separately from DAR/WAR" level — it does
**not** need BR-7's *per-section* handling the way WAR does, because
`data-model.md` confirms MAR has no sub-sections to independently track.

---

## Click-test list

1. **Coordinator roster:** log in as the seeded coordinator
   (`coord.reyes` / `password`), visit `/coordinator/students` — the
   seeded students should appear, sorted by surname.
2. **Student detail:** click into a student — confirm hours, OJT window,
   and (if you'd already tested the Company Switch UI) their company
   history render correctly.
3. **Reassignment:** `DevTestSeeder` only creates **one** coordinator, so
   reassignment has nothing to move a student *to* yet. You'll need to
   manually create a second `Coordinator`+`User` row (via Tinker or a
   quick manual DB insert) to actually test this end-to-end. Once you
   have two: reassign a student, confirm (a) they disappear from the old
   coordinator's roster, (b) they appear on the new coordinator's roster,
   (c) their DAR/WAR history is unaffected, (d) attempting to reassign to
   the *same* coordinator they're already on is rejected.
4. **MAR — student side:** log in as a seeded student, visit
   `/student/mar` — a Draft row for the current month should appear.
   Fill in Activities + Monthly Total Hours, save, confirm it persists.
5. **MAR — submit:** with an open cycle available, submit the MAR;
   confirm status becomes Pending (or Late if past the cycle deadline).
6. **MAR — coordinator review:** as the coordinator, open
   `/coordinator/cycles`, click the new "MAR Review" link, Approve or
   Return the submission; confirm `completed_hours` updates on Approve
   (check the student's dashboard or coordinator roster row).
7. **MAR — resubmit after Return:** as the student, confirm a Returned
   MAR is editable again and resubmitting moves it back to Pending.
8. **MAR PDF:** once a MAR has moved past Draft, confirm the "Print /
   PDF" link on `/student/mar` (and the "Download PDF" link on the
   coordinator review page) both render without error.

## Anything that depends on this change

- `php artisan migrate` is required (new `monthly_accomplishment_reports`
  table) — this is the first migration since the Company Switch UI
  session, which needed none.
- No changes to `DevTestSeeder` were made — see click-test item #3 above
  for why you'll need a second coordinator to fully exercise
  reassignment.
