# LLCC OJT System — Project State

**Last updated:** 2026-09-16
**Session summary:** Phases 1–3 of `OPENCODE_HANDOFF_PLAN.md` delivered
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
`:memory:` for the test suite** (`phpunit.xml`), Blade (no Vue/JS used
in any view), Laravel Breeze (auth), barryvdh/laravel-dompdf (DAR/WAR/
MAR PDFs), simplesoftwareio/simple-qrcode (login-page QR). The DB is
**already seeded** (`FullFeatureTestSeeder`: coordinators `coord.reyes`
/ `coord.cruz` + 7 students, password `password`) — do **not** reseed.

## Truth about the test suite (read first)

- `php artisan test tests/Feature/Workflows` → **60 passed, 0 failed**
  (235 assertions). This is the workflow click-test suite.
- `php artisan test` (full suite) → **63 passed, 22 failed**, and the
  22 failures are **all intentional or superseded** — they live in the
  untouched Breeze scaffold under `tests/Feature/Auth/*` and fail
  because (a) `App\Models\User::factory()` was removed (users are
  seeded, not factory-generated — the scaffold tests call it) and (b)
  `/register` and `/forgot-password` are **deliberately 404** per
  `roles-and-permissions.md` (only seeded Student + OJT Coordinator
  accounts exist; no self-service registration/password reset). None of
  the workflow failures are in our code. Do not "fix" these without the
  user asking — they're the perpetual, known-red set.

## Codebase inventory

| File/Module | Purpose | Status |
|---|---|---|
| DAR module (model, controller, request, policy, migration, views, PDF) | Daily-report workflow | **Confirmed live via feature tests this pass** — draft → hours derived server-side (BR-3) → invisible until submitted → batch submit into open cycle → coordinator approve/return → completion lock. |
| WAR module (model, controller, request, policy, migration, views, PDF) | Weekly-report workflow (BR-8) | Now **confirmed live via feature tests** (was the only previously-click-tested module): lazy month creation, week 1–2 / 3–4 submit pairs (BR-5/BR-6 slots), single-week approve/return (BR-7), submit lock. |
| MAR module (model, controllers, requests, policy, migration, views, PDF) | Monthly-report workflow | Now **confirmed live via feature tests**: lazy creation, `monthly_total_hours` **derived server-side from that month's WAR week hours** (8+9+7+10 → 34 in the test — resolves the old open question), submit→Pending→approve (+hours)/return-with-comment→resubmit. |
| `Coordinator\StudentController` (index/show/reassign/reopen/archive/resetPassword) + `StudentPolicy` | Roster, BR-1 reassignment, BR-10 reopen, archive, password reset | Now **confirmed live via feature tests** — own-students-only roster, non-owner 403, reassign swaps ownership preserving report history (BR-1), same-coordinator rejected, archived-blocked, reopen unblocks Completed, archive terminal, reset forces password change on next login. |
| Company switch UI (BR-12) | Student self-service company change | Now **confirmed live via feature tests**: first assignment active, switch closes old (`end_date` = new start) and inserts new, no overlapping starts, coordinator sees history. |
| `app/Services/CompletedHoursRecalculator.php` | BR-10 hours accumulation + auto-Completed | Sums Approved DAR + WAR week + MAR hours; flips `ojt_status` to Completed at `required_hours`. **Confirmed live via feature tests.** |
| `app/Services/AuditLogger.php`, `app/Models/AuditLog.php`, `Coordinator\AuditLogController.php`, `dashboard` audit-log view | Audit trail (data-model.md's `audit_logs`) | **Wired and confirmed live this pass.** Called from: Login, DAR/WAR/MAR submit, DAR/WAR/MAR review (approve + return), company assignment, reassign, reopen, archive, reset-password. `action_type` enum = the fixed six (Login/Submit/Approve/Return/Update/AccountChange). Viewer route `coordinator.audit-log.index` gated to coordinators, with `action_type` filter. |
| QR login shortcut | Onboarding convenience (not an auth mechanism) | Phase 1 — QR on the login page encodes `request()->root()` dynamically, caption "Scan to open this page." |
| `tests/Feature/Workflows/` (8 test classes + abstract `WorkflowTestCase`) | HTTP click-test suite | Phase 2 — 60 tests, green. sqlite `:memory:` + `RefreshDatabase`; helpers build unique seeded-like students (unique `S...` student_id_number) and cycles on the fly. |
| Stray root SQLite + `.bak.*` files | Phase 0 cleanup | Confirmed deleted (Phase 0 commit); verified still gone this pass. |
| `routes/web.php` | All routes | Live, uncommented, all covered by the test suite. Login is username-based (`/login`), `/register` and `/forgot-password` intentionally absent. |

## Business rules implemented so far

| BR-# | Where enforced | Status this pass |
|---|---|---|
| BR-1 | `Coordinator\StudentController::reassign()` + `ReassignStudentRequest` (target must exist ≠ current) | **Confirmed by tests** — history/hours preserved, ownership swapped, audit `AccountChange` row written. |
| BR-2 | `students.required_hours` + `CompletedHoursRecalculator` | Pre-existing; exercised by completion tests (9h ≥ 8h → Completed). |
| BR-3 | `DailyAccomplishmentReport::calculateHoursRendered()` | **Confirmed by test** (08:00–12:30 → 4.5h, derived server-side). |
| BR-4 | `StoreDarRequest`/`UpdateDarRequest` | **Confirmed by tests** — future / outside-window `report_date` and `time_ended` ≤ `time_started` rejected. |
| BR-5 | `Coordinator/SubmissionCycleController`; two submit slots | **Confirmed by tests** — DAR batch submit and WAR pair 1–2/3–4 into `cycle1_id`/`cycle2_id`; WAR 3rd submit rejected. |
| BR-6 | `SubmissionCycle::isPastDeadline()` (centralized) | **Confirmed by tests** — late flag flips only on cycle deadline, across DAR/WAR/MAR. |
| BR-7 | Per-document independent status | **Confirmed by tests** — DAR/WAR/MAR transition independently; WAR supports single-week (branch) review; MAR is single-status per row by spec. |
| BR-8 | WAR module | Covered end-to-end (see inventory). |
| BR-9 | SoftDeletes on DAR | **Confirmed by test** — draft delete is a soft delete; WAR/MAR intentionally have no delete path. |
| BR-10 | `CompletedHoursRecalculator` + `StudentPolicy::reopen()` + `Coordinator\StudentController::reopen()` | **Now fully wired AND confirmed by tests** — auto-Completed at `required_hours`, new submissions locked with friendly errors, coordinator reopen flips back to Ongoing and unblocks (audit `Update`). |
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

## Next steps / open questions

1. **Decision for the user:** the 22 red Breeze `tests/Feature/Auth/*`
   tests (no `User::factory()`, intentionally-404 `/register` and
   `/forgot-password`). Proposal: delete them (they test behaviors the
   spec forbids) or rewrite them against the seeded/login flow. Left
   untouched because deleting test scaffold isn't in the phase plan.
2. **MAR PDF rendering** (carried judgment call): `activities_text` is
   still a single blob rendered as one text block, not the official
   form's per-date table — `data-model.md` gives MAR no per-date rows.
   Resolution belongs to LLCC or a new `open-items.md` rule.
3. **Same-day-cutover convention for company switches** (carried):
   whether a switch with `start_date` = old `end_date` is acceptable.
   Still an unconfirmed judgment call.
4. Full-suite run command now `php artisan test`
   (63 pass / 22 known-red); warm dev DB untouched — no reseed needed.