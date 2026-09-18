# Workflows — LLCC OJT System

Six workflows, covering the full lifecycle from account creation to physical
signing. Described as sequences of events/state changes — not UI steps or
code, so any implementation (web app, different framework, etc.) can follow
the same logic.

## 1. Onboarding
1. Coordinator creates a Student account → assigns Coordinator, program/course,
   required OJT hours, OJT start date, and an initial Company Assignment.
2. System issues a temporary password.
3. Student logs in, is forced to change password.
4. Student completes the OJT Information Sheet — one time, ever.

**QR code (confirmed scope):** A QR code posted/displayed on campus encodes
the server's LAN URL only — scanning it just opens the login page in the
device's browser, the same page reached by typing the IP manually. It is
**not** an authentication mechanism and does **not** log attendance, time-in/
out, or any DAR/WAR/MAR data on its own — the student still logs in with
credentials as in steps 1–3 above. This was an explicit decision to keep the
QR feature low-risk: it doesn't touch the data model, roles, or auth flow at
all.

## 2. Daily Logging (continuous, cycle-independent)
1. Student creates a DAR draft for a given date (must satisfy BR-4).
2. Student enters Time Started / Time Ended → hours computed automatically (BR-3).
3. Draft is visible only to the student until submitted — not yet linked to
   any Submission Cycle.

## 3. Submission Cycle Lifecycle
1. Coordinator creates a Submission Cycle (name, coverage dates, deadline) — BR-5.
2. All students under that coordinator submit into this shared cycle.
3. Student batch-submits accumulated DAR drafts for the coverage period,
   completes/submits the relevant WAR week-sections (Week 1–2 for the
   month's first cycle, Week 3–4 for its second — BR-8), and — if the
   month's coverage is now complete — submits the MAR.
4. Each document type (DAR / WAR-section / MAR) transitions independently to
   **Pending** (BR-7).
5. If the deadline passes without submission, that cycle is marked
   Late/Missed for that student. They may still submit in the *next* cycle,
   but the Late flag is permanent on that record (BR-6).

## 4. Coordinator Review
1. Coordinator opens the cycle and reviews submissions per document, per
   student — never as one all-or-nothing batch.
2. For each document: **Approve**, or **Return for Revision** with a
   required comment.
3. Returned documents go back to the student for edits; on resubmission,
   status returns to Pending for re-review.
4. Coordinators annotate/decide only — they never directly edit
   student-submitted content.

## 5. Completion
1. Approved-hours totals continuously accumulate against the student's
   required hours.
2. When required hours are met, status auto-changes to Completed; further
   new submissions are blocked unless the Coordinator manually reopens the
   record (BR-10).
3. Coordinator finalizes/archives the student's OJT record.

## 6. PDF Generation & Physical Signing
1. At any point, student or coordinator can generate an official-format PDF
   of a DAR/WAR/MAR — see `pdf-forms.md` for exact layout requirements.
2. The PDF includes all entered data plus blank signature blocks (Company
   Supervisor, OJT Coordinator/COT Adviser, College Dean) for offline
   handwritten signing, per LLCC's existing process. E-signatures are
   explicitly out of scope for this version.

## Edge cases (apply across all workflows above)

| Scenario | Handling |
|---|---|
| Student reassigned to a new Coordinator | History/reports/hours stay intact; only the coordinator link updates (BR-1). |
| Student changes host company mid-OJT | New CompanyAssignment record created; old one closed with an end date (BR-12). |
| Student misses a Submission Cycle entirely | Can submit in the next cycle; original cycle stays permanently flagged Late/Missed (BR-6). |
| Coordinator "deletes" a report | Soft delete only — hidden from normal views, retained for audit (BR-9). |
| Student exceeds required hours | Status becomes Completed; further submissions blocked unless reopened (BR-10). |
| Invalid/missing coordinator assignment | Must be prevented at save time — a student can never end up with zero or conflicting coordinators (BR-13). |
| Weekly Report spans two cycles | Same WAR document progressively completed — system tracks section-level completion/review status independently (BR-8, BR-7). |
| Report returned for revision | Only that specific document is affected; other documents in the same cycle keep their own independent status (BR-7). |
