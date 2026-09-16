# QReport — Handoff Plan for opencode (target: Friday)

You are continuing work on an existing, mostly-built Laravel app, not
starting fresh. Read this whole file before touching any code. Do not
invent entities, fields, roles, or business logic not defined in the
`/spec` folder bundled alongside this plan — if something genuinely
seems missing from spec, flag it back to the user instead of guessing.

## 0. Load context first (do this before any plan mode / code)

1. Read every file in `/spec/`:
   - `data-model.md` — every entity/field/relationship. Never invent new ones.
   - `business-rules.md` — BR-1 through BR-14. Cite the BR-# for any
     logic you write or touch that relates to Students, Coordinators,
     DAR/WAR/MAR, or Submission Cycles.
   - `workflows.md` — the six end-to-end workflows and their edge cases.
   - `pdf-forms.md` — exact official PDF layouts (the Daily report's
     five-date batching is easy to get wrong from assumption).
   - `roles-and-permissions.md` — only Student and OJT Coordinator are
     system users. Company Supervisor, Dean, COT Adviser are NOT users —
     never build logins/dashboards for them.
   - `downstream-contract.md` — the hard must/must-not rules. Read this
     one twice. BR-14 (backend-enforced isolation) is the rule most
     likely to get quietly broken under time pressure — re-check it on
     every controller you touch, not just once.
2. Read `PROJECT_STATE.md` at the project root — it's the existing
   session-to-session progress log. Treat it as probably slightly
   stale (see Phase 1, step 1 below) but use it to understand what
   already exists and why.
3. Stack is locked in: Laravel 12, PHP 8.2, MySQL, Blade (+ Vue only
   where already used — don't introduce Vue elsewhere), Laravel Breeze,
   barryvdh/laravel-dompdf. Don't change stack decisions.

## 1. Phase 0 — Ground truth & cleanup (do first, ~30–60 min)

1. **Verify `PROJECT_STATE.md` against actual code.** It currently
   describes the MAR module and `Coordinator\StudentController` as
   "built but not yet confirmed applied" — but both already exist in
   the live codebase. Update the state file's status lines to match
   reality before planning anything else, so you're not re-verifying
   things that are already confirmed present.
2. **Resolve the DB connection question.** Run:
   ```
   php artisan db:show
   ```
   Confirm the app is actually running on MySQL (the locked-in stack
   choice), not silently falling back to SQLite. There's a leftover
   SQLite file (`ojt_system`, no extension, sitting at the project
   root — NOT in `database/`) with real data in it from some earlier
   point. Once you confirm MySQL is the live connection, delete this
   stray file — it's not part of the app.
3. **Delete all `.bak.*` files** scattered through the repo
   (`app/Http/Controllers/Coordinator/DashboardController.php.bak.*`,
   `resources/views/coordinator/dashboard.blade.php.bak.*`,
   `resources/views/coordinator/students/show.blade.php.bak.*`,
   `routes/web.php.bak.*`). These are stale manual backups, not
   version control — confirm the live (non-`.bak`) version of each is
   the one currently wired into routes before deleting, then delete.
4. Run `composer install`, `npm install`, `php artisan migrate`,
   confirm the app boots at all (`php artisan serve` or via XAMPP) with
   no fatal errors before going further.

## 2. Phase 1 — QR code login shortcut (small, isolated, do early as a warm-up)

Per `workflows.md`'s Onboarding section: this is explicitly NOT an
auth mechanism. It just needs to open the login page. Scope is
intentionally tiny — don't over-build it.

1. Install `simplesoftwareio/simple-qrcode` via Composer (works fully
   offline/LAN — no external API calls, which matters since this
   server has no internet access per the architecture constraints).
2. On the login page, generate the QR code encoding the **current
   request's root URL dynamically** — `request()->root()` or
   equivalent — never a hardcoded value from `.env`'s `APP_URL`. The
   LAN IP can change between sessions/hotspots; hardcoding it means the
   QR silently breaks without any visible error.
3. Add a short caption: "Scan to open this page." No other logic
   attaches to this — it doesn't touch auth, roles, or any model.

## 3. Phase 2 — Click-test every workflow (the bulk of the remaining work)

Work through `workflows.md`'s six workflows in order. For each, actually
click through it as both a Student and a Coordinator account (create a
second Coordinator account manually first — `DevTestSeeder` only makes
one, and reassignment testing needs two). Fix bugs as you find them,
citing the BR-# affected in your commit/change notes.

**Priority order — start with what's never been click-tested at all:**

1. **OJT Information Sheet** (Onboarding) — submit it once, then try to
   submit again. Confirm the one-time lock actually holds on a
   resubmit attempt (never confirmed working per PROJECT_STATE.md).
2. **DAR** (Daily Logging) — create a draft, enter time in/out, confirm
   hours auto-compute (BR-3), confirm it stays invisible to the
   coordinator until submitted.
3. **Company Switch UI** (BR-12 edge case) — code exists, never
   click-tested. Confirm old assignment gets an end date, new one
   opens correctly, history stays intact.
4. **Coordinator Student Roster + Reassignment** (BR-1) — just
   confirmed present in code but never click-tested. Reassign a
   student to your second test coordinator, confirm history/hours
   stay intact and only the coordinator link changes.
5. **MAR module** — never click-tested. Submit a MAR, review it as
   coordinator (approve / return for revision), confirm BR-6 deadline
   handling and BR-7 independent-status behavior.
6. **Submission Cycle lifecycle end-to-end** — create a cycle, batch-
   submit DAR drafts + WAR sections + MAR for the coverage period,
   confirm each transitions to Pending independently (BR-7), confirm a
   missed deadline flags Late/Missed permanently (BR-6) while still
   allowing next-cycle submission.
7. **Coordinator Review** — Approve and Return-for-Revision on each
   document type, confirm returned docs go back to Pending correctly
   on resubmission, confirm coordinators never directly edit
   student-submitted content.
8. **Completion** (BR-10) — push a test student's approved hours past
   `required_hours`, confirm status auto-changes to Completed and
   further submissions get blocked.
9. **PDF Generation** — generate DAR, WAR, and MAR PDFs, cross-check
   against `pdf-forms.md` exactly (especially the Daily report's
   five-date-per-printout batching — flagged as an easy thing to get
   wrong).
10. **Register / password-reset** — check the suspected email-vs-
    username pattern bug flagged since early sessions but never
    actually verified.

For each bug found: fix it, note which BR (if any) it relates to, and
keep a running list — you'll need this for the final `PROJECT_STATE.md`
rewrite in Phase 4.

## 4. Phase 3 — Build the two known gaps

1. **BR-10 "reopen a Completed record" coordinator action.** Currently
   doesn't exist for any document type. A Coordinator needs a way to
   manually reopen a student's Completed status so new submissions
   aren't permanently blocked. Scope: a single action on the
   Coordinator's student view, backend-authorized via the existing
   Student policy pattern, that flips status back and allows new
   submissions again. Don't build anything beyond what BR-10 actually
   requires.
2. **AuditLog wiring.** The `AuditLog` model, an `AuditLogger` service,
   and `Coordinator\AuditLogController` already exist per the codebase,
   but per `PROJECT_STATE.md` they're not actually called from any real
   action yet. Wire `AuditLogger` into at minimum: Coordinator
   Approve/Return-for-Revision actions, and Coordinator reassignment
   (Phase 2, item 4) — these are the actions most likely to matter for
   an audit trail. Don't invent additional audit-logged actions beyond
   what's asked here unless something in `data-model.md` specifically
   requires it.

## 5. Phase 4 — Final pass

1. Full click-through of all six workflows one more time, back to
   back, after all fixes and the two new features land.
2. Regenerate `PROJECT_STATE.md` in full, following the template
   convention already established in the existing file — mark every
   workflow's actual confirmed-live status honestly (don't mark
   something "confirmed" unless it was actually click-tested this
   pass).
3. Confirm the `.bak` files and stray SQLite file from Phase 0 are
   actually gone, not just ignored.
4. Re-audit BR-14 one final time: every controller touching Student/
   Coordinator-scoped data goes through the relevant Policy via
   `$this->authorize()`, never a bare/unscoped query — do a project-wide
   grep for model queries that skip this pattern before calling it done.

## Guardrails throughout (from `downstream-contract.md`)

- Never invent entities/fields not in `data-model.md` — propose back to
  the user if something genuinely seems missing.
- Treat derived values (`hours_rendered`, `completed_hours`,
  `overall_status`, and similar) as always computed, never accepted as
  direct input from a student or coordinator.
- Match PDF layouts exactly — no restructuring the official forms.
- Flag anything genuinely ambiguous rather than silently deciding it
  yourself, especially anything touching the still-open judgment calls
  already logged in `PROJECT_STATE.md` (MAR hours auto-computed vs.
  typed input; MAR PDF's single-blob vs. per-date table).
