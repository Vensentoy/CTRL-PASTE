# Open Items Requiring LLCC / Adviser Confirmation

Carried from the original blueprint, plus one new observation from the
official form layouts. Treat these as unresolved — don't silently assume
an answer in code or design; flag it back to the project owner instead.

1. **COT OJT Adviser vs. OJT Coordinator** — whether the "COT OJT Adviser"
   named as a signatory on the Weekly/Monthly/Daily reports is the same
   person as the in-system "OJT Coordinator" role, or a distinct person
   entirely. The official form layouts list them as visually separate
   signature lines (see pdf-forms.md), which leans toward "distinct," but
   this needs official confirmation before being treated as settled.
2. **Exact required OJT hours per program** — only BSIT's 1,440–1,800 range
   is currently confirmed. Other programs' requirements are unknown.
3. **Behavior once a student exceeds required hours** — may they continue
   logging entries for record-keeping, or are they hard-blocked entirely?
   (Current default assumption per BR-10: submissions blocked unless a
   Coordinator reopens the record — but "blocked" specifics need
   confirming.)
4. **Intended use of the "Remarks/Status" column** on the report forms —
   student self-notes, coordinator review annotation, or potentially both.
5. **Whether OJT meeting/cycle dates follow any recurring pattern**, or are
   always manually set per instance by a Coordinator (current assumption:
   always manual, per BR-5).
6. **Final server hardware/OS** for the on-campus LAN hosting deployment.
   A hotspot-from-the-server-PC approach has been proposed (see
   architecture-decisions.md) — still unconfirmed whether that's the
   intended final deployment or a defense-day demo convenience only.

## How to use this file

When a downstream skill or session hits one of these ambiguities, it should
surface the question to the user rather than picking a silent default —
especially for #1 and #3, since they affect either the data model (separate
adviser role?) or a hard business rule (BR-10's blocking behavior).
