---
name: qreport
description: >
  Authoritative knowledge base for QReport, Group 8's capstone name for
  the LLCC OJT Tracking and Submission System — a LAN-only capstone
  project digitizing Daily/Weekly/Monthly Accomplishment Reports
  (DAR/WAR/MAR), the OJT Information Sheet, Company Assignments, and
  Submission Cycles for Students and OJT Coordinators. Always consult
  this skill before doing ANY work on this project — designing a
  feature, generating code, building UI, writing documentation, or
  answering a question about how the system should behave. Contains the
  roles, permission model, full data model, the 14 business rules,
  complete workflows, and the exact official PDF form layouts. No code —
  pure system knowledge any other skill or session must stay consistent
  with. Trigger on QReport, LLCC, OJT tracking, Students/Coordinators in
  this context, DAR/WAR/MAR, Submission Cycles, or the OJT Information
  Sheet, even if the user doesn't name the skill directly.
---

# QReport (LLCC OJT Tracking and Submission System) — Super Skill

This is the single source of truth for the system's concept, rules, and
official document formats. It contains **no implementation code on
purpose** — code is generated fresh each time by a separate, disposable
code-generation skill that defers to this one. That keeps this skill
stable and reusable even if the tech stack ever changes.

## What this system is, in one paragraph

A web-based, LAN-only system that digitizes OJT report preparation,
submission, review, and monitoring at LLCC, replacing a fully manual
paper process. It does not remove handwritten signatures — it digitizes
everything up to the point of physical signing, then generates print-ready
PDFs matching LLCC's official forms exactly.

## How to use this skill

1. **Read this file first** for orientation, then jump into whichever
   reference file matches the task at hand (table below).
2. **Before generating or reviewing any code**, check `business-rules.md`
   and cite the specific BR-# being satisfied.
3. **Before touching anything PDF/print-related**, read `pdf-forms.md` in
   full — the official layouts have real quirks (e.g. the Daily report
   batches five dates per printout) that are easy to get wrong from
   assumption alone.
4. **If something seems ambiguous or unconfirmed**, check `open-items.md`
   before guessing — several genuinely open questions exist and should be
   surfaced to the user, not silently resolved.
5. **Any other skill or session working on this project must follow**
   `downstream-contract.md` — it defines the hard "must / must not" rules
   for staying consistent with everything in this skill.

## Reference index

| File | Contents |
|---|---|
| `references/blueprint.md` | The original full blueprint document, kept intact as raw source material. |
| `references/data-model.md` | Every entity, its fields, and relationships, in prose/tables — no schema code. |
| `references/business-rules.md` | The 14 business rules (BR-1–BR-14), each as an authoritative "must always be true" statement. |
| `references/workflows.md` | The six end-to-end workflows (onboarding through PDF/signing) plus edge cases. |
| `references/pdf-forms.md` | Exact official PDF form layouts for the Info Sheet, DAR, WAR, and MAR — ground truth extracted from LLCC's own templates. |
| `references/roles-and-permissions.md` | The Student/Coordinator model, who is explicitly NOT a system user, and the backend-enforced isolation principle (BR-14). |
| `references/architecture-decisions.md` | Why Laravel + MySQL + Vue + DomPDF was chosen, with tradeoffs against alternatives — reasoning only. |
| `references/open-items.md` | Genuinely unresolved questions that need LLCC/adviser confirmation — don't assume answers here. |
| `references/downstream-contract.md` | The rules any other skill, session, or piece of work on this project must follow. |
| `assets/original-forms/*.docx` | The four original official LLCC forms (Info Sheet, Daily, Weekly, Monthly), bundled as pixel-level ground truth. `pdf-forms.md` is the readable summary; open these directly when actually building PDF-generation templates, since exact spacing/column widths/merged cells only live here. |

## The two roles, briefly

- **Student** — own records only, cannot self-register, coordinator-issued
  credentials.
- **OJT Coordinator** — manages only their own assigned students; all
  coordinators are equal rank, no super-admin tier.

Company Supervisors, the College Dean, and the COT OJT Adviser are **not**
system users in this version — see `roles-and-permissions.md`.

## The one rule that overrides everything else

**BR-14 — backend-enforced isolation.** Every access restriction in this
system must be enforced at the backend/API/database level, never only
hidden in the UI. This is the constraint most likely to get quietly
violated under deadline pressure, so it's worth re-checking on every new
piece of work, not just assumed from the design.
