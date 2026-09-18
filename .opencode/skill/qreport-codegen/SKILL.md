---
name: qreport-codegen
description: >
  Use this skill whenever generating, scaffolding, or updating actual
  implementation code for QReport (the LLCC OJT Tracking and Submission
  System) — migrations, models, controllers, policies, PDF templates,
  routes, etc. This skill governs HOW code is delivered and HOW progress is
  tracked across sessions and accounts — it does not contain project
  knowledge itself. Always defer to the `qreport` skill for
  roles, data model, business rules, workflows, and PDF form layouts;
  never invent anything that skill doesn't already define. Trigger this
  any time the user asks to build, generate, scaffold, add, or update
  code for this project, or asks to save/check progress on it.
---

# QReport Codegen — Delivery & Progress Protocol

This skill defines the *process* for generating code on this project, not
the project's content. Read `qreport`'s SKILL.md first for what to
build; this skill only governs how it gets delivered and tracked.

## Step 0 — Always check for existing progress first

Before generating anything, look for:
- An uploaded `PROJECT_STATE.md` — if present, read it fully before doing
  anything else. It tells you what already exists, what changed last, and
  what's next.
- An uploaded project zip — if present alongside `PROJECT_STATE.md` and the
  user wants to *edit* existing code (not just plan), inspect it to confirm
  it matches what the state file describes.

If neither is present, treat this as a brand-new, empty project.

## Step 1 — First-ever code generation (empty project)

- Generate the requested scaffold.
- Deliver it as a **zip** — no need to ask the format question this one
  time, since a first scaffold is inherently multi-file.
- In the same reply, include a short onboarding walkthrough since this is
  the user's first time handling this project's code at all:
  - Where to extract the zip
  - The resulting folder structure, briefly
  - The first commands to run to get it working locally (e.g. `composer
    install`, copy `.env.example` to `.env`, generate an app key, run
    migrations, `npm install && npm run dev`)
- Do not ask "single file or zip" on this first delivery.

## Step 2 — Every subsequent code generation

- Ask the user: **"Single file or zip?"** before generating, unless they've
  already specified in their request.
- Use their answer to decide delivery format. Prefer single file whenever
  the deliverable is naturally one file (one migration, one controller, one
  template) — don't default to zip out of convenience.
- Generate only what was asked for. Don't regenerate untouched files.
- **Every delivery, not just the first, must end with a short "what to do
  with this" note.** This is separate from the Step 1 onboarding walkthrough
  (which is a one-time, fuller setup guide) — this is a brief, per-delivery
  note covering just what changed. At minimum, state:
  - **Where it goes** — the exact path/folder the file(s) replace or add to
    in the existing project.
  - **Any commands needed to apply it** — e.g. re-run `php artisan
    migrate` after a new/changed migration, `npm run dev` after a Vue
    component change, `php artisan config:clear` after a config change.
    If nothing needs running (e.g. a Blade template that just needs the
    file dropped in), say so explicitly rather than omitting the note.
  - **Anything that depends on this change** — e.g. "this new column
    means the Student factory/seeder should be updated too" — only if
    genuinely relevant, not as padding.
  - Keep this to 2-4 lines. It is not a repeat of the full onboarding
    walkthrough — just enough that the user isn't left guessing what to
    do with what they were just handed.

## Step 3 — Saving progress

Trigger: the user says something like "save progress," "update the
progress," or is wrapping up a session.

Regenerate `PROJECT_STATE.md` (see template below) reflecting the current
state of the codebase. This is not a simple changelog — it must be
detailed enough that a **fresh Claude session with zero memory of this
conversation** can understand the codebase from this file alone.

Important distinction to hold onto: `PROJECT_STATE.md` is the **map**, not
the **territory**. It lets a new session understand and discuss the
codebase immediately. It does NOT let a new session edit the code without
also being given the actual code (the zip). Say this explicitly to the
user when handing over the state file, so they remember to bring both if
they intend to keep editing on the next account.

## `PROJECT_STATE.md` template

```markdown
# LLCC OJT System — Project State

**Last updated:** <date>
**Session summary:** <1-2 sentences on what this session accomplished>

## Stack (locked in)
Laravel 11, MySQL, Blade + Vue 3 (Vite) for interactive pieces,
Laravel Breeze (auth), barryvdh/laravel-dompdf (PDF generation).
See the `qreport` skill's architecture-decisions.md for reasoning.

## Codebase inventory
| File/Module | Purpose | Status |
|---|---|---|
| <path> | <what it does, in plain language> | done / in progress / stub |
(one row per meaningful file or logical group of files — e.g. group all
migrations as one row if they were all delivered together and are unchanged)

## Business rules implemented so far
| BR-# | Where enforced | Notes |
|---|---|---|
(only list rules that have actual code behind them yet)

## Last change (detailed)
<Explain exactly what was generated/modified this session, file by file,
and WHY — enough detail that someone with no other context could
understand the reasoning, not just the diff.>

## Next steps / open questions
- <what's not built yet, in priority order>
- <any open items from qreport's open-items.md that became
  relevant this session>
```

## Things that never trigger zip or the format question

Plain discussion, planning, answering questions about business rules,
reviewing existing code without changing it, or anything that produces no
new/changed file. Only actual code generation or update triggers Steps 1–3
above.
