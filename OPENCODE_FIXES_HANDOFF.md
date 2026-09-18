# QReport — Fix Handoff for opencode (3 verified issues, no scope creep)

You are patching an existing, mostly-built app — not building anything new.
These three issues were found by static code review against `/spec/` and
`PROJECT_STATE.md`, cross-referencing file contents directly (not by
running the suite). Verify each with the test suite before/after your
change. Do not touch anything outside what's listed here.

Read `/spec/business-rules.md` and `/spec/downstream-contract.md` first if
you haven't already this session — cite the relevant BR-# in your commit/
comment for each fix, per project convention.

---

## Fix 1 — BR-10 double-counts WAR hours inside MAR (real correctness bug)

**Where:** `app/Services/CompletedHoursRecalculator.php` and
`app/Http/Controllers/Student/MarController.php::monthlyHoursFromWar()`.

**The bug:** `MarController::monthlyHoursFromWar()` computes each MAR row's
`monthly_total_hours` as the sum of that same month's WAR
`week1_hours..week4_hours`. But `CompletedHoursRecalculator::recalculate()`
separately sums `approvedWarHours` (from Approved WAR weeks) **and**
`approvedMarHours` (from Approved MAR rows, which already contain those
same WAR hours) and adds both into `completed_hours`. For any month where
both the WAR weeks and that month's MAR get Approved, the hours are
counted twice — a student can hit `ojt_status = Completed` at roughly half
their real required hours. This directly undermines BR-2/BR-10.

**Fix (pick one, don't do both):**
- **Option A (recommended):** In `CompletedHoursRecalculator`, drop
  `approvedMarHours` from the sum entirely — WAR hours already carry the
  weight once approved, and MAR's total is derived from WAR, not
  independent data. Rationale in the code comment: MAR's contribution
  to completion is already represented via `approvedWarHours`.
- **Option B:** Keep MAR in the sum, but exclude WAR weeks whose
  `month_period` matches a month that has an Approved MAR row for that
  student. More complex, no real benefit over Option A given MAR's
  number is 100% derived from WAR — don't do this unless Option A
  breaks an existing test for a reason that turns out to matter.

**After the fix:** confirm/update `tests/Feature/Workflows/CompletionAndPdfTest.php`
to include a case where the *same month's* WAR weeks and MAR are both
approved, and assert `completed_hours` reflects the hours once, not twice.
If no such overlapping case exists yet, that's why this bug wasn't caught
— add one.

---

## Fix 2 — QR gate is bypassed by default in the exact environment you'll demo in

**Where:** `app/Http/Middleware/EnsureQrAccess.php`, `config/qr.php`,
`.env.example`.

**The bug:** `EnsureQrAccess` bypasses the whole gate whenever
`app()->environment('local')` — and `.env.example` ships `APP_ENV=local`.
Following the project's own onboarding steps (`cp .env.example .env`)
means the QR requirement ("only by scanning the QR can they access the
website") is **silently disabled** unless someone remembers to also set
an undocumented `QR_GATE_ENFORCE=true`. That flag appears nowhere in
`.env.example`, `README.md`, or either handoff plan. The 8 passing
`QrGateTest.php` tests don't catch this because they run under
`environment('testing')`, a separate bypass branch — they're testing a
code path that's off by default in the actual local/demo environment.

**Fix:** flip the default so the gate is **enforced unless explicitly
bypassed**, not bypassed unless explicitly enforced:
1. In `config/qr.php`, remove the `local`-environment bypass branch from
   `EnsureQrAccess` entirely, or gate it behind an explicit
   `QR_GATE_BYPASS_LOCAL=true` env var that defaults to `false`.
2. Keep the `environment('testing')` bypass as-is (tests still need it) —
   just make sure it's the *only* automatic bypass.
3. Add `QR_GATE_BYPASS_LOCAL=false` (commented, with a one-line
   explanation) to `.env.example` so a future person sees the knob
   exists instead of discovering it by reading middleware source.
4. Re-run the full suite — if any non-QR-gate test starts failing
   because it hits `/login` directly without going through
   `qr.enter` first, that test was silently relying on the local
   bypass and needs a `withSession(['qr_verified_at' => ..., 'qr_verified_expires_at' => ...])`
   (or equivalent helper) added, not a reversion of this fix.

---

## Fix 3 — No PDF for the OJT Information Sheet (missing form, not a bug)

**Where:** `spec/pdf-forms.md` §1 describes this as a printed, one-time
signed document (photo placeholder, personal/family/scholastic/health
data, closing attestation signature line) — same category as DAR/WAR/MAR.
There is currently no `resources/views/pdf/information-sheet.blade.php`,
no controller, and no route for it.

**Before building anything:** this is a scope question, not obviously a
bug — confirm with the user whether a physically-signed Info Sheet is
needed for the current milestone before spending time on it. If yes:

1. New `InfoSheetPdfController` mirroring `DarPdfController`'s shape
   (ownership check via the student's own info sheet, `Pdf::loadView`,
   `->stream()`).
2. New `resources/views/pdf/information-sheet.blade.php` following
   `pdf-forms.md` §1 field-for-field — reuse the header/footer block and
   CSS pattern already established in `pdf/dar.blade.php` for visual
   consistency with the other three forms.
3. Route + link from wherever the student's Info Sheet is currently
   viewed (`student/information-sheet/*` views).
4. One feature test asserting `200` + `application/pdf`, matching the
   existing PDF tests in `tests/Feature/Workflows/CompletionAndPdfTest.php`.

Do **not** invent a photo-upload feature to fill the "1x1 photo
placeholder" unless the user asks — render it as a blank placeholder box,
consistent with how signature lines render blank per `workflows.md` §6.

---

## When done

Run `php artisan test` (full suite) and paste the result back. Regenerate
`PROJECT_STATE.md` per the codegen skill's template, with a "Last change"
section listing exactly which of these three fixes were applied, which
were skipped (and why), and current pass/fail counts.
