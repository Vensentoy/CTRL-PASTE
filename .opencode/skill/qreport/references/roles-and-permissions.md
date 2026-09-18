# Roles & Permission Model

## The two roles

**Student**
- Own records only. Never able to see or act on another student's data.
- Cannot self-register — accounts are provisioned by a Coordinator directly
  from official student records; there is no public sign-up or email
  verification flow.

**OJT Coordinator**
- Can only view/manage students **currently assigned to them** (BR-11).
- All Coordinators are equal rank — there is no head-coordinator or
  super-admin tier in this version. Any Coordinator can create/manage
  Student and Coordinator accounts, but data access is still scoped to
  their own assigned students.

## Roles that are explicitly NOT system users (v1)

Company Supervisors, the College Dean, and the COT OJT Adviser are **not**
system accounts. Their involvement stays on the printed, physically signed
document (see pdf-forms.md). Do not design login flows, dashboards, or
permissions for these roles in the current scope — they belong to Phase 2
(see open-items.md) if ever added.

## The core isolation principle (BR-14)

Every access restriction described above must be enforced **at the
backend/API/database level** — never only by hiding UI elements. A
Student manipulating a request directly (changing an ID in a URL, editing
a hidden form field, calling an API endpoint by hand) must still be
structurally incapable of reaching another student's data. The same
applies to a Coordinator attempting to reach a student outside their
assignment.

This is the single most important constraint any downstream implementation
must satisfy — it should be checked on every new endpoint, query, or
data-fetching function, not just assumed from the UI design.

## Session & auth notes (conceptual, not implementation)

- Coordinator-issued temporary password on account creation; forced change
  on first login.
- No self-service password recovery — LAN-only deployment, no email
  verification is available. A Coordinator must manually reset a forgotten
  password.
- Session timeout on inactivity, since this runs on shared/public campus
  machines.
- Every login, submission, approval/return, and account/data change should
  produce an audit trail entry (actor, action, timestamp) — see
  data-model.md's AuditLog entity.
