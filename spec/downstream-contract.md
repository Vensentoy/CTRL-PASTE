# Contract for Downstream Work

This file defines the rules any *other* skill, session, or piece of work on
the LLCC OJT System must follow to stay consistent with this super skill.
This includes (but isn't limited to) a future code-generation skill, a
frontend/design skill, or an ordinary chat session doing ad-hoc work.

## Must

- **Never invent entities, fields, or relationships** not present in
  `data-model.md`. If something new is genuinely needed, propose it back to
  the user as an addition to this skill first, rather than quietly adding
  it in generated code.
- **Cite the specific BR-# a piece of logic satisfies** when generating or
  reviewing code that touches Students, Coordinators, DAR/WAR/MAR, or
  Submission Cycles (see `business-rules.md`).
- **Preserve the two-role isolation model** (`roles-and-permissions.md`) in
  every design decision — no shortcuts that expose one student's data to
  another, or one coordinator's students to a different coordinator.
- **Match the official PDF form layouts exactly** (`pdf-forms.md`) for any
  work involving report generation, printing, or form fields — including
  the Daily report's five-date batching behavior, which is easy to miss.
- **Treat derived values as derived** — hours_rendered, completed_hours,
  overall_status, and similar fields are never accepted as direct input
  from a student or coordinator in any implementation.
- **Flag unresolved items** (`open-items.md`) rather than silently
  assuming an answer, especially the COT OJT Adviser question and the
  post-completion submission-blocking behavior.

## Must not

- Must not treat the OJT Information Sheet as a recurring/per-cycle
  submission — it is one-time only, confirmed explicitly by the project
  owner.
- Must not design system accounts, logins, or dashboards for Company
  Supervisor, College Dean, or COT OJT Adviser — they are not system users
  in this version (see `roles-and-permissions.md`).
- Must not assume a change of stack invalidates this skill. The conceptual
  model (roles, data, rules, workflows, forms) holds regardless of whether
  the implementation is Laravel, something else, or changes later —
  `architecture-decisions.md` is the one file that would need updating.

## Recommended family of downstream skills

These are proposed, not yet built. Each should be a thin, single-purpose
skill that defers to this one for anything conceptual:

- **Code generation** — scaffolds/updates actual implementation code,
  delivered as a single file or zip depending on what's being generated,
  with a progress file (`PROJECT_STATE.md`) maintained separately for
  cross-session/cross-account continuity.
- **Frontend/UI** — handled by the already-imported `frontend-design`
  skill directly; this skill's data model and workflows supply what it
  needs without a dedicated project-specific frontend skill.
