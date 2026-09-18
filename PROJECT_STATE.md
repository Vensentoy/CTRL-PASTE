# LLCC OJT System — Project State

**Last updated:** 2026-09-18
**Session summary:** Built the full-set report bundle — one ZIP with every
submitted DAR/WAR/MAR PDF per student, both roles (user chose ZIP +
both-roles). Single-download controllers now delegate PDF assembly to the
new `ReportBundleBuilder`; zero-render-change proven by normalized
byte-diffs. Full suite green (105 → 109 tests, 420 assertions).
**Previous session summary (2026-09-18):** Implemented `OPENCODE_MULTI_ACTIVITY_HANDOFF.md` —
DAR/WAR now store multiple itemized activities per date/week as JSON
columns (real schema change, verified against Lester's filled-out forms).
Full suite green (100 → 105 tests, 401 assertions).
**Previous session summary (2026-09-16):** Applied all three `OPENCODE_FIXES_HANDOFF.md` fixes —
BR-10 WAR/MAR double-count removed (Option A), QR gate enforced by
default, and the missing OJT Information Sheet PDF built. Full suite
green (97 passed).
Coordinator-only Generate QR button (2-min single-use signed token)
gates `GET/POST /login` via `EnsureQrAccess` middleware; login form no
longer shows always-on QR. Suite fully green (93 passed).
against the live codebase. Phase 1: QR-code login shortcut (encodes
`request()->root()` so it survives LAN IP changes — never a hardcoded
URL). Phase 2: the full click-test pass was executed as **HTTP feature
tests** (`tests/Feature/Workflows/`, 60 tests, all passing) — the
practical substitute for manual browser clicking in this environment —
covering all six `workflows.md` workflows for both roles, plus BR-14
isolation and the register/password-reset question. Phase 3: both known
gaps turned out to be **already built and wired** — verified rather than
built: the BR-10 "reopen a Completed record" coordinator action and the
`AuditLogger` wiring across DAR/WAR/MAR submit + review, reassign,
reopen, archive, reset-password, company switch, and login. Bugs found
and fixed this pass, all with BR-# citations: the login screen's
email-vs-username pattern (fixed), `month_period` exact-match lookups
made portable with `whereDate` across 6 call sites (would silently miss
rows depending on DB datetime storage), and the info-sheet one-time
guard switched from a stale-able Eloquent cached relation to a fresh DB
`exists()` check (a second submit previously risked a raw SQL
500 instead of a clean 403/validation error).

This file is a complete, self-contained picture of the app as it exists
right now — it does not assume you've read any earlier version of this
file or any prior conversation. Anyone picking this up cold should be
able to work from this file alone (plus the `llcc-ojt-system` skill for
business rules/data model/workflows, and `llcc-ojt-codegen` for delivery
conventions — both are user-level skills, not bundled with this file).

## Stack (locked in)
Laravel 12, PHP 8.2.12 (XAMPP — `C:\xampp\htdocs\ojt-system`,
`php artisan test` and `php artisan` commands actually run in this
environment), MySQL (`ojt_system` database) for the live app, **sqlite
`:memory:` for the test suite** (`phpunit.xml`), Blade + Alpine.js
(repeatable DAR/WAR activity rows only) for interactive pieces,
Laravel Breeze (auth), barryvdh/laravel-dompdf (DAR/WAR/
MAR PDFs), simplesoftwareio/simple-qrcode (login-page QR). The DB was
**reseeded 2026-09-18** (`FullFeatureTestSeeder`: coordinators
`coord.reyes` / `coord.cruz` + 7 students, password `password`) with
multi-entry DAR/WAR demo data — required by the JSON-column schema
change (old rows couldn't migrate; handoff authorized reset/reseed,
demo data only).

## Truth about the test suite (read first)

- `php artisan test tests/Feature/Workflows` → **84 passed, 0 failed**.
  This is the workflow click-test suite.
- `php artisan test` (full suite) → **109 passed, 0 failed**
  (420 assertions) — **fully green since 2026-09-18** (was 100 green on
  2026-09-16; +5 multi-activity tests then +4 bundle tests this day, zero
  regressions).
  The former 22
  Breeze reds under `tests/Feature/Auth/*` were fixed: `User::factory()`
  → `User::create()` + `Hash::make` (no factory exists — users are
  seeded), `email` → `username` login, `/register` and
  `/forgot-password` now correctly assert 404 per
  `roles-and-permissions.md`, `ConfirmablePasswordController` fixed
  from `email` to `username`, and `ProfileUpdateRequest` aligned to
  `username` (Breeze's `name`/`email` don't exist on users table).
  QR gate at `tests/Feature/Workflows/QrGateTest.php:13` enforces
  coordinator-only generation, 2-min single-use, friendly block, local
  bypass (`APP_ENV=testing` bypass via `config/qr.php:7`).

## Codebase inventory

| File/Module | Purpose | Status |
|---|---|---|
| DAR module (model, controller, request, policy, migration, views, PDF) | Daily-report workflow | **Confirmed live via feature tests this pass** — draft → hours derived server-side (BR-3) → invisible until submitted → batch submit into open cycle → coordinator approve/return → completion lock. **2026-09-18: multi-activity itemization** — one date holds 1–20 `{activity, time_started, time_ended}` entries in a JSON column; `hours_rendered` = entry sum via model mutator; Alpine repeatable rows on the form; PDF renders one row per entry with rowspanned Date/Remarks. |
| WAR module (model, controller, request, policy, migration, views, PDF) | Weekly-report workflow (BR-8) | Now **confirmed live via feature tests** (was the only previously-click-tested module): lazy month creation, week 1–2 / 3–4 submit pairs (BR-5/BR-6 slots), single-week approve/return (BR-7), submit lock. **2026-09-18: multi-line itemization** — each `week{n}_activities` is a JSON array of plain-text lines; Alpine repeatable inputs on the show view; PDF renders one row per line with all other week cells rowspanned. |
| MAR module (model, controllers, requests, policy, migration, views, PDF) | Monthly-report workflow | Now **confirmed live via feature tests**: lazy creation, `monthly_total_hours` **derived server-side from that month's WAR week hours** (8+9+7+10 → 34 in the test — resolves the old open question), submit→Pending→approve (+hours)/return-with-comment→resubmit. |
| `Coordinator\StudentController` (index/show/reassign/reopen/archive/resetPassword) + `StudentPolicy` | Roster, BR-1 reassignment, BR-10 reopen, archive, password reset | Now **confirmed live via feature tests** — own-students-only roster, non-owner 403, reassign swaps ownership preserving report history (BR-1), same-coordinator rejected, archived-blocked, reopen unblocks Completed, archive terminal, reset forces password change on next login. |
| Company switch UI (BR-12) | Student self-service company change | Now **confirmed live via feature tests**: first assignment active, switch closes old (`end_date` = new start) and inserts new, no overlapping starts, coordinator sees history. |
| `app/Services/CompletedHoursRecalculator.php` | BR-10 hours accumulation + auto-Completed | Sums Approved DAR + WAR-week hours ONLY — MAR rows deliberately excluded (BR-2/BR-10 fix 2026-09-16: MAR's total is 100% derived from WAR weeks via `monthlyHoursFromWar()`, so counting both double-counted); flips `ojt_status` to Completed at `required_hours`. **Confirmed live via feature tests, incl. overlapping WAR+MAR-Approved case.** |
| `app/Services/AuditLogger.php`, `app/Models/AuditLog.php`, `Coordinator\AuditLogController.php`, `dashboard` audit-log view | Audit trail (data-model.md's `audit_logs`) | **Wired and confirmed live this pass.** Called from: Login, DAR/WAR/MAR submit, DAR/WAR/MAR review (approve + return), company assignment, reassign, reopen, archive, reset-password. `action_type` enum = the fixed six (Login/Submit/Approve/Return/Update/AccountChange). Viewer route `coordinator.audit-log.index` gated to coordinators, with `action_type` filter. |
| QR login shortcut → QR-gated access | Gate: coordinator-only Generate QR → 2-min single-use signed token at `GET /qr/enter` sets session flag checked by `EnsureQrAccess` before `GET/POST /login` | **Enforced by default in ALL environments since 2026-09-16** (fixes-handoff Fix 2): the old `local`-environment auto-bypass is gone — only `testing` bypasses automatically. Explicit opt-out via `QR_GATE_BYPASS_LOCAL=true` (`config/qr.php:12`, documented in `.env.example`). Login `auth/login.blade.php:8` no longer always-on, `auth/qr-required.blade.php:1` friendly block, `coordinator/dashboard.blade.php:22` Generate button. |
| `tests/Feature/Workflows/` (9 test classes + abstract `WorkflowTestCase`) | HTTP click-test suite | Phase 2 — 60 tests, green. sqlite `:memory:` + `RefreshDatabase`; helpers build unique seeded-like students (unique `S...` student_id_number) and cycles on the fly. Plus `QrGateTest.php:13` — 8 tests for gate. |
| PDF branding pass (`pdf/partials/letterhead.blade.php`, `pdf/partials/footer.blade.php`, recolored DAR/WAR/MAR/Info-Sheet templates, `public/images/pdf/` assets) | Official LLCC visual identity on all four printouts (branding handoff 2026-09-16) | Letterhead banner image + blue-over-gold CSS footer bar shared via partials (DAR includes per 5-day batch page). Table colors from official docx: header `#C5E0B3`, date/week column `#FFD966`, DAR/WAR subtotal `#D9D9D9`, grand-total band `#BFBFBF`. WAR gained a real gold Week first-column (heading div removed, colspan-adjusted). MAR activities now a real Date \| Activities table (gold cell = month label only, no fabricated per-date rows) + green Remarks band. Templates/CSS only — no logic, policy, or route touched. Render-verified: all four PDFs generate with the banner image embedded (1 image object each, ~1.2MB at 300 DPI). **Full-bleed follow-up 2026-09-16:** `@page{margin:0}` + `body{margin:0}` in all four templates, content wrapped in `.page-content{padding:20px 30px}` with letterhead/footer as direct full-width children (DAR: per `.printout` batch page) — verified at the PDF-operator level: banner placement matrix `595.28 0 0 H 0.00 top` = full A4 width from x=0 to the top corner on all four forms. **Do not delete `public/images/pdf/letterhead-band.png` / `llcc-logo.png` / `public/images/pdf/info-sheet-letterhead.jpg` — ALL THREE are load-bearing PDF assets (the Info Sheet uses its own JPG banner, not the shared PNG).** |
| Info Sheet PDF (`InfoSheetPdfController`, `InformationSheetPolicy`, `pdf/information-sheet.blade.php`, `info-sheet.pdf` route) | Official-format OJT Information Sheet printout (pdf-forms.md §1) | **Built 2026-09-16** (fixes-handoff Fix 3): per-student PDF, photo = blank 1×1 box, signatures blank, ownership via policy (student own / coordinator own-students). Print link on `student/information-sheet/show`. Tested (200 + pdf + 403 matrix). |
| Stray root SQLite + `.bak.*` files | Phase 0 cleanup | Confirmed deleted (Phase 0 commit); verified still gone this pass. |
| `routes/web.php` | All routes | Live, uncommented, all covered by the test suite. Login is username-based (`/login`), `/register` and `/forgot-password` intentionally absent. |

## Business rules implemented so far

| BR-# | Where enforced | Status this pass |
|---|---|---|
| BR-1 | `Coordinator\StudentController::reassign()` + `ReassignStudentRequest` (target must exist ≠ current) | **Confirmed by tests** — history/hours preserved, ownership swapped, audit `AccountChange` row written. |
| BR-2 | `students.required_hours` + `CompletedHoursRecalculator` | Pre-existing; exercised by completion tests (9h ≥ 8h → Completed). |
| BR-3 | `DailyAccomplishmentReport::setActivitiesAttribute()` mutator (+ `hoursForActivities()`) | **Confirmed by tests** — single 08:00–12:30 → 4.5h AND Lester-shaped 4-entry date → 9.0h, always derived server-side, never from input. Status-only saves never recompute (mutator fires only on `activities` writes). |
| BR-4 | `StoreDarRequest`/`UpdateDarRequest` | **Confirmed by tests** — future / outside-window `report_date` and per-entry `time_ended` ≤ `time_started` (`activities.{i}.time_ended`) rejected; 21-entry post rejected on `activities` (max:20), 20 accepted. |
| BR-5 | `Coordinator/SubmissionCycleController`; two submit slots | **Confirmed by tests** — DAR batch submit and WAR pair 1–2/3–4 into `cycle1_id`/`cycle2_id`; WAR 3rd submit rejected. |
| BR-6 | `SubmissionCycle::isPastDeadline()` (centralized) | **Confirmed by tests** — late flag flips only on cycle deadline, across DAR/WAR/MAR. |
| BR-7 | Per-document independent status | **Confirmed by tests** — DAR/WAR/MAR transition independently; WAR supports single-week (branch) review; MAR is single-status per row by spec. |
| BR-8 | WAR module | Covered end-to-end (see inventory). |
| BR-9 | SoftDeletes on DAR | **Confirmed by test** — draft delete is a soft delete; WAR/MAR intentionally have no delete path. |
| BR-10 | `CompletedHoursRecalculator` + `StudentPolicy::reopen()` + `Coordinator\StudentController::reopen()` | **Now fully wired AND confirmed by tests** — auto-Completed at `required_hours`, new submissions locked with friendly errors, coordinator reopen flips back to Ongoing and unblocks (audit `Update`). **2026-09-16 fix:** MAR rows excluded from the sum (derived-from-WAR, not independent) + overlapping-approval test pins single-counting. |
| BR-11 | All queries scoped through `coordinator`/`student` relations | **Confirmed by tests** — cross-coordinator review/edit/view/submit all 403; own-coordinator-cycle-only submit. |
| BR-12 | `CompanyAssignmentController::store()` + `StoreCompanyAssignmentRequest` | **Confirmed by tests** — switch closes old + opens new, no overlap, coordinator history view. |
| BR-13 | `students.coordinator_id` non-nullable + `restrictOnDelete` | Schema-level; reassign request also validates target exists. |
| BR-14 | Backend isolation via policies on every scoped write | **Confirmed by tests** — 403 matrix for wrong-coordinator review (DAR/WAR/MAR+PDF), other-student DAR edit, cross-role gate, guest redirect; re-audited this pass. |

## Last change (detailed) — Phases 1–3

### Phase 1 — QR login shortcut
Composer dep `simplesoftwareio/simple-qrcode`; the login view renders the
QR encoding `request()->root()` (never `APP_URL`/hardcoded) with caption
"Scan to open this page." No auth/role/model coupling. Commit `ac9fa36`.

### Phase 2 — HTTP click-test pass (test `tests/Feature/Workflows`)
60 feature tests across 8 classes + abstract `WorkflowTestCase`
(`RefreshDatabase`, sqlite `:memory:`; helpers `makeCoordinator`,
`makeStudent` with unique `S...` student_id_number, `makeCycle`,
`makeDar`). Coverage maps to the handoff plan's priority list:

1. **Info Sheet** — show→create redirect; store once; second submit
   blocked; create→show after exists; other-student isolation.
2. **DAR** — derived hours (BR-3), date/time validation (BR-4), draft
   invisible to coordinator until submitted, batch submit → Pending +
   audit `Submit`, late (BR-6), cross-coordinator cycle submit error
   (BR-11), other-student edit 403, edit-after-submit 403, soft-delete
   (BR-9), completed-student lock (BR-10).
3. **Company Switch** — first active row; switch closes old + inserts
   new; overlapping start rejected; coordinator history (BR-12).
4. **Coordinator roster + reassignment** — own-students only;
   non-owner 403; reassign preserves history + swaps ownership + audit
   `AccountChange`; same-coordinator rejected; archived blocked (BR-1).
5. **MAR** — lazy create; `monthly_total_hours` **derived from the
   month's WAR hours** (resolves the open "typed vs. computed" question
   in favor of derived); submit → Pending; approve +hours; return
   requires comment; return→edit→Pending; completed lock.
6. **Cycle lifecycle + review** — approve/return for DAR, WAR
   (single-week branch), MAR; return-requires-comment; returned→
   resubmit→Pending; draft-not-reviewable; audit `Approve`/`Return`.
7. **Completion + reopen + archive + reset** — Completed at threshold,
   locked, reopen unblocks, archive terminal-and-completed-only,
   coordinator password reset forces change on next login.
8. **PDFs** — DAR/WAR/MAR stream `200` + `application/pdf`;
   cross-coordinator PDF 403.
9. **Register/password-reset** — both 404 (intentional); guest redirects
   to `/login`; role gate 403s both ways. The email-vs-username login
   suspicion is resolved: **login is username-based and works**
   (`LoginRequest` validates `username`).

**Bugs found & fixed (with evidence):**
- **LoginRequest** already validated `username` (fixed in an earlier
  session); locked in by test so it can't regress.
- **`where('month_period', ...)` → `whereDate('month_period', ...)`** in
  6 sites: `MarPdfController`, `WarPdfController`,
  `Student\MarController`, `Student\DashboardController` (×2),
  `Services/CohortAggregator` (×2). Exact date-string equality on a
  datetime column silently misses rows depending on DB storage (sqlite
  stores `Y-m-d H:i:s`); `whereDate` is portable across the sqlite test
  DB and the live MySQL. Test assertions were aligned to query the model
  and compare `format('Y-m-d')`.
- **`StoreInformationSheetRequest`** (`authorize()` + `withValidator()`)
  used the cached `informationSheet` relation to enforce the one-time
  rule; after a successful first store the in-memory relation could
  still read null and the DB unique constraint would surface as a raw
  500 on a resubmit. Now both guards do a **fresh
  `OjtInformationSheet::where('student_id', ...)->exists()`**, so a
  resubmit is a clean 403 (authorize) / validation error — the DB
  unique constraint stays the backstop.

### Phase 3 — verified, not built (both gaps already closed)
The handoff plan listed two gaps, but the codebase already had both:
1. **BR-10 reopen** — `Coordinator\StudentController::reopen()` +
   `StudentPolicy::reopen()` (current-coordinator-only), flips
   Completed → Ongoing, blocks Archived, logs audit `Update`. Tested.
2. **AuditLog wiring** — `AuditLogger::log()` called from every intended
   action (see inventory). Tested (`Submit`/`Approve`/`Return`/
   `AccountChange` rows asserted; viewer gated + filterable).

No new architecture invented; both items were confirmed present and
covered rather than duplicated.

## Last change (detailed) — `OPENCODE_FIXES_HANDOFF.md` (2026-09-16)

All three fixes applied (user confirmed: Fix 1 Option A, Fix 3 build
now). Nothing skipped. Each cites its BR-# in-code per project
convention.

### Fix 1 — BR-10 double-count (BR-2/BR-10, Option A)
`app/Services/CompletedHoursRecalculator.php` no longer sums Approved
MAR rows: `completed_hours = Approved DAR + Approved WAR weeks` only.
MAR's `monthly_total_hours` is 100% derived from the same month's WAR
weeks (`MarController::monthlyHoursFromWar()`), so the old three-way
sum counted those hours twice and flipped students to Completed at
~half their real requirement. `Student.php`'s derived-field comment
updated to match. Tests: new overlapping case in
`CompletionAndPdfTest::test_overlapping_war_and_mar_approvals_count_hours_once`
(all 4 WAR weeks + same-month MAR approved → 34h counted once, still
Ongoing vs 40h required); `MarWorkflowTest`'s old
`test_coordinator_can_approve_the_mar_and_hours_are_added` (which pinned
the buggy behavior) rewritten as
`test_coordinator_approving_the_mar_does_not_double_count_war_hours`
(MAR Approved alone → 0h; hours arrive only via Approved WAR weeks).

### Fix 2 — QR gate enforced by default
`EnsureQrAccess.php` lost its `environment('local')` auto-bypass (and
the undocumented `QR_GATE_ENFORCE`/`QR_GATE_BYPASS` flags): the gate is
now enforced in every environment, with the `testing` bypass as the
only automatic one. Explicit local opt-out via new
`config/qr.php:bypass_local` → `QR_GATE_BYPASS_LOCAL` (defaults false),
documented with a commented line in `.env.example`. No existing test
relied on the local bypass (all run under `testing`), so no test
helpers needed changing — `QrGateTest` + `LoginAndOnboardingTest`
(16 tests) still green unmodified.

### Fix 3 — OJT Information Sheet PDF (built, per user confirmation)
New `InfoSheetPdfController::show()` (mirrors `MarPdfController`
shape: find student → `firstOrFail` sheet + work experiences →
`generatePdf` authorize → `Pdf::loadView` → `stream()`), new
`InformationSheetPolicy` (view/generatePdf = owning student or owning
coordinator, BR-11/BR-14; registered in `AppServiceProvider`), new
`pdf/information-sheet.blade.php` covering `pdf-forms.md` §1
field-for-field with `dar.blade.php`'s header/footer CSS (1×1 photo =
blank box, signatures blank, no photo-upload invented), route
`GET /info-sheet/pdf/student/{student}` (`info-sheet.pdf`), Print/PDF
link on the student's info-sheet show view. Test:
`CompletionAndPdfTest::test_info_sheet_pdf_streams_and_scopes_by_ownership`
(200 + `application/pdf` for owner + owning coordinator, 403 for
non-owner).

## Last change (detailed) — DAR/WAR multi-activity itemization (2026-09-18)

Per `OPENCODE_MULTI_ACTIVITY_HANDOFF.md` (max:20 cap added to the handoff
doc itself this session — it was a prior-session decision missing from the
doc): DAR/WAR store multiple itemized activities instead of one, verified
against the real filled-out forms in `lester-reference-examples/`
(Lester S. Tapao — 4 entries/9h on 6/1/26; 8 lines in WAR Week 1). MAR
verified untouched (migration, model, template all unmodified).

**Design (deliberate, not an oversight): JSON columns, not a child
relational table.** Same itemized data, identical correct PDF output,
fewer files to keep in sync (migration + model + request + form + PDF +
tests). This is real itemized storage, not a display-only parsing trick.
WAR keeps its four per-week columns (`week{n}_activities` TEXT → JSON
array of strings) rather than the handoff's literal single `activities`
column — one WAR row is one month with four independent week-sections
(BR-7/BR-8), confirmed with the user before building.

- **Migration** `2024_01_01_000014_convert_dar_war_activities_to_json.php`:
  DAR drops `activities_text`/`time_started`/`time_ended`, adds NOT NULL
  `activities` JSON; WAR re-adds each `week{n}_activities` as nullable
  JSON. Clean schema change — no production data (demo only), dev DB
  reset + reseeded via `migrate:fresh` + `FullFeatureTestSeeder`.
- **Models:** DAR fillable/cast updated; `calculateHoursRendered()`
  replaced by `setActivitiesAttribute()` mutator (recomputes
  `hours_rendered` = Σ entry durations exactly on `activities` writes)
  + `hoursForActivities()` / `activityEntryHours()` / `minutesBetween()`
  helpers + `activitiesSummary()` for list rows. WAR adds the four
  `array` casts; hours logic untouched.
- **Requests:** `StoreDarRequest`/`UpdateDarRequest` validate
  `activities` 1–20 items with per-item `activity`/`time_started`/
  `time_ended` (`after:activities.*.time_started`);
  `UpdateWarWeekRequest` validates `activities` 1+ plain strings (no WAR
  cap — matches the old unbounded blob). BR-4/BR-10 `withValidator`
  blocks unchanged.
- **Controllers:** `Student\DarController` store/update no longer compute
  hours (mutator owns it). `WarController::updateWeek` passes the array
  straight through — plus a **drive-by latent-bug fix**: `show()`'s
  `firstOrCreate` exact-match on `month_period` 500s on a second visit
  after any save (date cast re-serializes with time; sqlite misses) —
  now `whereDate` + create-if-missing, matching the project's
  established portability pattern. `MarController::show` has the same
  latent twin and is deliberately left alone (MAR-freeze this session).
- **Views:** DAR form = Alpine repeatable `{activity, time pair}` rows
  (prefill from `old()`/model, add/remove, client cap mirrors server);
  WAR show = Alpine repeatable single-line inputs per week; DAR index =
  `activitiesSummary()`; coordinator DAR review = full entry list with
  times; coordinator WAR review = line list.
- **PDFs:** `pdf/dar` loops entries per date (Date + Remarks rowspanned,
  times/hours per-row, TOTAL HOURS subtotal unchanged, times printed
  `g:i` like the real form's "7:30"); `pdf/war` loops lines per week
  (Week + dates + hours + remarks all rowspanned). **Drive-by bug fix:**
  `pdf/dar` read `$activeCompany` but the controller passes `$company` —
  company header always printed "—"; fixed (all four occurrences).
- **Seeder:** all DAR fixtures rewritten as 2–3-entry days (Pedro stays
  exactly 8h/day × 5 = 40h → Completed; Liza's Late keeps remarks); all
  WAR fixtures rewritten as 2–3-line weeks.
- **Tests (100 → 105, all green, 401 assertions):** every DAR/WAR fixture
  adapted to the array shape (same test names — no behavior regressed);
  5 genuinely new tests: 21st-entry rejection + 20-accept boundary
  (`DarWorkflowTest`), DAR create/edit form-render with activity rows
  (`DarWorkflowTest` — this one caught a real Blade `@json`
  truncation bug on nested arrays, fixed via `@php` + variable),
  Lester-shaped 4-entry → 9.0h sum (`DarWorkflowTest`), multi-line WAR
  save + form render + `rowspan="4"` blade structure (`WarWorkflowTest`),
  20-entry DAR PDF render + 6-date 5+1 batching + `rowspan="20"`
  (`CompletionAndPdfTest`). Bytes can't assert "not visually awkward"
  for a 20-row DomPDF table — final eyeball vs the Lester DOCX stays
  manual (see next steps).
- **Docs:** `spec/data-model.md` and
  `.opencode/skill/qreport/references/data-model.md` updated with the
  identical edit (hash-verified byte-identical after) — DAR `activities`
  JSON, WAR per-week JSON arrays, `hours_rendered` = Σ rule.

## Last change (detailed) — full-set report bundle ZIP (2026-09-18)

User asked whether any one-action full-set download exists (it didn't —
only per-report PDFs + summary listings) and chose: (a) ZIP of individual
PDFs, (b) triggerable by both roles.

- **New `app/Services/ReportBundleBuilder.php`** — single source of truth
  for PDF assembly: `darCycleDocument()` / `warMonthDocument()` /
  `marMonthDocument()` (each preserving its controller's original
  filters, abort ordering, Folio paper, filename), `renderDocument()`,
  enumerators (`darCycles()` / `warMonths()` / `marMonths()`), a
  `documentsFor()` generator (one rendered PDF alive at a time), and
  `build()` writing each PDF to a temp file + `ZipArchive::addFile()`
  (disk-to-disk at `close()` — peak memory stays flat at any bundle
  size; per-doc temps unlinked right after close; duplicate member names
  suffixed, e.g. after a BR-1 reassignment yields same-named cycles).
  `DarPdfController` / `WarPdfController` / `MarPdfController` now
  delegate assembly and keep only HTTP concerns (scoping, authorize,
  `stream()` response — byte-identical behavior).
- **New `StudentReportBundleController::download()`** (`GET
  /reports/bundle/student/{student}`, `reports.bundle`, shared role
  group) — authorizes once via `StudentPolicy::viewReports` (own student
  / own students; provably equivalent to per-document `generatePdf`, so
  no new policy method), streams `Reports-{id}.zip` with
  `deleteFileAfterSend()`. Empty bundle → 404. No audit row (downloads
  don't log anywhere — consistent). Links on student DAR index +
  coordinator student-show.
- **Parity proof (the paranoid part):** pre-refactor baselines rendered
  to `storage/app/sample-pdfs/baseline-{dar-20item,war-multiline,mar}.pdf`,
  then normalized byte-diffed (strip CreationDate/ModDate/ID) post-refactor
  — DAR and MAR identical; WAR's first baseline was INVALID (rendered from
  an in-memory `create()` model missing week3/4_status, which the template
  reads as non-Draft → 2 extra empty tables; production-unreachable since
  controllers always re-query and columns default to Draft) — regenerated
  controller-faithfully, then identical too. Same-process old-vs-new renders
  also identical for DAR and WAR. MediaBox 612×936 confirmed on all files;
  footer/template/DomPDF untouched so byte-identity transitively covers
  rowspan + footer-pin. Keepers: the three `baseline-*.pdf` files.
- **Tests (+4, all in `CompletionAndPdfTest`):** own-student + owning
  coordinator 200 + zip headers, ZIP member census (1×DAR/WAR/MAR,
  non-empty `.pdf`s) via direct builder call, per-doc temp cleanup
  assertion, cross-coordinator + other-student 403s, empty → 404.
- Follow-up already noted in item 12 (MAR `firstOrCreate` twin) is
  unaffected — builder uses `whereDate`+`firstOrFail`, never
  `firstOrCreate`.

## Next steps / open questions

1. **Breeze 22 red — resolved 2026-09-16:** rewritten to assert the
   intentional 404s for `/register` and `/forgot-password`, use
   `username` login, and create users without `User::factory()`.
   `ConfirmablePasswordController` and `ProfileUpdateRequest` were
   corrected alongside the tests. Full suite is now green.
2. **MAR PDF rendering — resolved 2026-09-16 as accepted simplification:**
   `activities_text` remains a single blob (see `resources/views/pdf/mar.blade.php:3`).
   `pdf-forms.md:111` describes a two-column Date | Activities table
   labeled "Summary of Weekly Accomplishment Reports (Week 1–4)", but
   `data-model.md:108` gives MAR only one free-text field per month with
   no per-date rows. Per `downstream-contract.md` (never invent entities
   not in data-model), the PDF keeps the single-block rendering with the
   correct heading and `monthly_total_hours` — no DAR-derived per-date
   breakdown is synthesized. Flagged in-code as intentional; re-open
   only if LLCC provides a new per-date MAR spec.
3. **Same-day-cutover for company switches — resolved 2026-09-16 as accepted:**
   `app/Http/Controllers/Student/CompanyAssignmentController.php:73`
   closes the old row with `end_date = new start_date` (no gap day) and
   `app/Http/Requests/StoreCompanyAssignmentRequest.php:50` validates
   `start_date >= active.start_date`. Covered by
   `tests/Feature/Workflows/CompanySwitchTest.php:62` (same-day) and
   `:85` (predated rejected). Per `business-rules.md:BR-12` historized
   history is preserved; `workflows.md` and `data-model.md` specify no
   gap convention, so same-day is kept unless LLCC requests a gap day.
4. Warm dev DB untouched — no reseed needed. Full-suite command is
     `php artisan test` (99 passed, 0 failed, 343 assertions). No further
     phases pending unless LLCC changes the two accepted simplifications
     above.
5. **QR bootstrap deadlock — fixed 2026-09-16:** `GET /login` without a QR
   session returned the formless 403 block page, so coordinators could
   never reach the login form needed to generate the first QR (the POST
   bypass existed but was unreachable from a browser). `EnsureQrAccess`
   now always passes `GET /login` through; the form renders with an amber
   "coordinators log in directly / students need a QR scan" notice, and
   `POST` enforcement is unchanged (students without session still 403).
   Pinned by rewritten `QrGateTest` case (GET 200 + form, student POST
   403). The form was never the gate — the POST check + QR session is.
6. **QR-expired page respects login state — fixed 2026-09-16 (view-only):**
   `auth/qr-expired.blade.php` now branches on `@auth`/`@guest` — guests
   see the original "Back to login" link; logged-in users see "You're
   still logged in as {username} — the expired QR changed nothing." with
   a Continue to dashboard button. No controller/middleware/token change.
   Pinned by two new `QrGateTest` cases. Follow-up: removed the redundant
   secondary "Back to login" link from the logged-in branch (the `guest`
   guard sends authenticated `/login` hits to the dashboard anyway, so both
   links landed in the same place) + `assertDontSee` pin.
7. **QR roaming setup — switched 2026-09-16 (option b):** `.env` no longer
   pins `QR_HOST` (removed) and `APP_URL` is back to `localhost:8000`, so
   generated QRs resolve the host live — request host when the dashboard is
   opened via the LAN URL, else LAN auto-detection. Coordinator dashboard
   now shows "This QR points to {host}" under each generated QR (parsed
   from the endpoint's own `signed_url`, no backend change), making a
   wrong-adapter guess visible before anyone scans. Pinned by new
   `QrGateTest` null-host generation case. Habit: open the dashboard via
   `http://<today's-PC-IP>:8000` (via `ipconfig`) so no guessing happens at
   all. Full suite: 100 passed, 351 assertions.
8. **Mobile responsive pass — 2026-09-16 (Tailwind classes only, no logic):**
   base `px-4` added to all 22 page containers (content touched viewport
   edges under 640px); student DAR index rows wrap with `min-w-0` truncate
   + larger Edit/Delete tap targets and a stacking submit bar; dashboard
   counts are `grid-cols-3 sm:grid-cols-5`, CTAs stack full-width;
   info-sheet form grids all `grid-cols-1 sm:grid-cols-*` (`sm:col-span-2`
   on spanning children); WAR/MAR headers + submit rows stack; all 5
   student-record tables wrapped in `overflow-x-auto`; coordinator review
   rows/forms wrap with `break-words`; cycles list stacks its 4 links; QR
   image `max-w-full h-auto`. `npm run build` recompiled (new classes
   confirmed in bundle). No new tests — class-only, existing suite guards.
   Full suite: 100 passed, 351 assertions. Manual 360px checklist still
   owed: student dashboard → DAR index → DAR form, one coordinator review
   screen, one report page (no page-level h-scroll, ≥44px targets).
9. **Info Sheet PDF correction — 2026-09-16 (template-only, per correction
   handoff):** the earlier branding pass wrongly reused the DAR/WAR/MAR
   banner — the Info Sheet has its own letterhead (`info-sheet-letterhead
   .jpg`, direct `<img>`, footer partial still shared). Two-line title
   (`ON-THE JOB TRAINING (OJT)` / `Information Sheet`), photo box moved up
   beside the title, sections A–D converted to bordered fillable-box grid,
   A regrouped (College own row; Course|Major|Year&Section one 6-col row),
   B `Name of Company:` + guardian as 3 labeled rows, C short labels
   (`Tertiary:` etc., `Honor/ Awards Received:`), D insurance as ☑/☐ pair
   with Specify, attestation sentence added above the (still blank)
   signature line. Section E, footer, margins untouched. Verified:
   suite 100 green; re-rendered PDF embeds the JPG (2639×372) at full
   bleed (`595.28 x=0.000`, top corner); 16-point HTML content check all
   OK. Side-by-side vs `OJT-INFO-SHEET.docx` still owed (human eyeball).
10. **Folio paper + Info Sheet 1-page fix — 2026-09-16:** user challenged
    the A4 assumption — `pgSz 12240×18720` extracted in-memory from ALL
    FOUR source docx proves **Folio 8.5×13" (612×936pt)** everywhere, so
    all four PDF controllers now `setPaper([0,0,612,936])` (report PDFs
    stay A4 — no official form). DAR/WAR/MAR insets already ≈ official
    14.4/21.6pt, unchanged. Info Sheet overflow root-caused by bisect
    (A+B alone filled the page) + PDF text-position dump: narrow
    label|value columns forced 11pt wrapping (~215pt spill). Fix from
    measured source geometry: content width 468→**500pt** (official
    tblGrid width — tables span the 72pt pgMar at 56pt insets), sections
    rebuilt as flowing label+value cells (B is officially a Father|Mother
    comparison table, guardian its own 1-col table — both confirmed
    cell-by-cell), 6-col experiment dropped. Verified at operator level:
    all four MediaBox `612×936`, banners `scaleX=612 x=0` top corner,
    Info Sheet back to **1 page**, others still 1. Suite 100 green.
11. **Fixed footer pin — 2026-09-16:** footer partial root was
    `margin-top:auto` with NO flex parent anywhere in PDF views (no-op —
    footer floated after content). Now `position:fixed;bottom:0;left:0;
    right:0` (DomPDF repeats fixed elements on every page from a single
    occurrence). DAR include moved out of the per-batch loop (per-copy
    fixed footers would stack); `.printout` break rule changed
    `after:always+last-avoid` → `~ .printout{break-before:always}` because
    the moved footer broke the `:last-child` guard (caused a blank 2nd
    page — caught by the page-count check). Content bottom clearance for
    the ~34px bar: DAR/WAR/MAR `20px 30px 40px`, Info Sheet `0 56pt 36pt`
    (slight pgMar-bottom deviation, forced — alternative is overlap).
    Verified at operator level: blue/gold bar rects at y≈14–22 on EVERY
    page incl. a 1-DAR short page (previously floated mid-page) and both
    pages of a forced 2-batch DAR. Suite 100 green.
12. **Multi-activity follow-ups (2026-09-18, manual):** (a) eyeball a
    rendered DAR + WAR PDF against `lester-reference-examples/` —
    rowspan placement and the 20-row table's page flow can't be asserted
    from bytes (DomPDF rowspan is partial-support); (b)
    `MarController::show` still uses `firstOrCreate` on `month_period`
    (same latent sqlite double-visit 500 fixed on the WAR side this
    session) — fix if MAR ever gets a save-then-revisit test;
    (c) mobile 360px checklist from item 8 still owed.