# Business Rules — LLCC OJT System

These 14 rules are the authoritative checklist. Any code, UI flow, or design
decision for this system must satisfy every rule relevant to it. Each rule
is stated as "what must always be true" — deliberately implementation-agnostic,
so it applies whether the eventual code is Laravel, Node, Django, or anything
else.

| # | Rule | What must always be true |
|---|---|---|
| BR-1 | Single active coordinator | A student has exactly one active Coordinator at any moment. Reassigning a student to a new coordinator must never alter or delete that student's historical records — only the current assignment link changes. |
| BR-2 | Per-student required hours | Required OJT hours are set individually per student by their Coordinator. There is no single global value — different programs require different totals (e.g. BSIT confirmed at 1,440–1,800 range). |
| BR-3 | Hours are always computed | Hours Rendered = Time Ended − Time Started. This value must never be a field the student (or any client) can set directly — it is derived server-side every time. |
| BR-4 | DAR date bounds | A Daily Accomplishment Report's date can never be in the future, before the student's OJT start date, or after their OJT completion date. |
| BR-5 | Manual cycle creation | Submission Cycles (name, coverage dates, deadline) are created manually by a Coordinator. There is no fixed/automatic system calendar generating them. |
| BR-6 | Lateness is deadline-based | Whether a report is "Late" depends only on whether it was submitted before its Submission Cycle's deadline — never on the activity/report date itself. A report logged for a date long past can still be on-time if submitted before the cycle deadline. |
| BR-7 | Independent document review | DAR, WAR, and MAR are reviewed and statused independently within a cycle — approving one must never auto-approve or block review of the others. |
| BR-8 | WAR's monthly/four-week structure | The Weekly Accomplishment Report is one document per month containing four week-sections. Sections fill in progressively: Week 1–2 during the month's first cycle, Week 3–4 during its second cycle. |
| BR-9 | Soft delete only | Submitted reports are never hard-deleted. "Deleting" a report must always be a soft, recoverable operation, retained for audit purposes. |
| BR-10 | Completion lock | Once a student's approved hours meet or exceed their required hours, their status becomes Completed and new submissions are blocked — unless a Coordinator manually reopens the record. |
| BR-11 | Coordinator scope | A Coordinator can only view or manage students currently assigned to them. This must hold even if they know another student's ID or try to access it directly — see BR-14. |
| BR-12 | Historized company assignments | Company Assignments are never overwritten to reflect a change of company. Changing companies means closing the old assignment record (set end date) and creating a new one — full history is always preserved. |
| BR-13 | No orphaned students | A student must always have at least one valid assigned Coordinator. The system must actively prevent saving a student with zero coordinators or with conflicting/duplicate active assignments. |
| BR-14 | Backend-enforced isolation | All role- and ownership-based access restrictions must be enforced at the backend/API/database level — never as UI-only hiding. A student manipulating a request directly must still be structurally unable to reach another student's data, and likewise for a coordinator reaching students outside their assignment. |

## How downstream skills should use this file

- Any generated code that touches Students, Coordinators, DAR/WAR/MAR, or
  Submission Cycles should be checked against this table before being
  considered done.
- When reviewing or generating code, cite the specific BR-# a piece of logic
  is satisfying (e.g. "this query scopes by coordinator_id — BR-11") so the
  connection back to this document stays traceable.
- If a proposed feature would require breaking one of these rules, that's a
  signal to flag it back to the project owner rather than silently
  implementing an exception.
