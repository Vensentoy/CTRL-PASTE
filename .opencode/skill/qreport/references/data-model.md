# Data Model — LLCC OJT System

Conceptual model only. No schema syntax, no ORM code — this describes what
must be true about the data, not how any particular framework stores it.
Downstream code-generation skills translate this into migrations/models.

## Entities

### User (base identity)
Every account is a User first. A User is either a Student or a Coordinator —
never both, never neither.

| Field | Meaning |
|---|---|
| role | Student or Coordinator |
| username | login identifier (not email — no email verification flow exists) |
| password | coordinator-issued temp password initially; forced change on first login |
| status | Active / Inactive / Completed / Archived |
| last_login_at | for session/audit purposes |

### Student (extends User)
| Field | Meaning |
|---|---|
| coordinator_id | the ONE currently-assigned coordinator (BR-1) |
| student_id_number, surname, given_name, middle_name | identity |
| course, major, year_section | academic info |
| ojt_start_date, ojt_completion_date | bounds all DAR dates (BR-4) |
| required_hours | set per student by coordinator, varies by program (BR-2) |
| completed_hours | derived/cached total of APPROVED hours — never user-entered |
| ojt_status | Ongoing / Completed (BR-10) |

### Coordinator (extends User)
| Field | Meaning |
|---|---|
| full_name, department_area | identity. All coordinators are equal rank — no super-admin tier. |

### OJTInformationSheet
**One-time only** — one row per student, ever created once, not resubmitted
per cycle like DAR/WAR/MAR. Confirmed explicitly by the project owner.

Sections, matching the official form exactly:
- **A. Personal Data**: college, city address, gender, contact number, email,
  birth date/place, provincial address, religion, marital status
- **B. Family Data**: father's + mother's name/occupation/company/company
  address/contact; guardian's name/address/contact
- **C. Scholastic Data**: tertiary/secondary/primary — school, address, year
  graduated, honors (three parallel blocks)
- **D. Health Data**: height, weight, blood type, health problem, vaccination
  status (Unvaccinated/First Dose/Second Dose/Booster), vaccine type, place,
  date, health insurance (PhilHealth/Private + specify)
- **E. OJT Work Experiences** (4th-year students, repeatable list): OJT
  assignment, position, inclusive dates, OJT site address
- Signed date (student attestation)

### CompanyAssignment
Historized — a student can have many over time, never overwritten in place.
| Field | Meaning |
|---|---|
| company_name, department_area, job_designation, mobile_number | — |
| start_date, end_date | end_date null = current/active assignment |

To "change companies," close the old record (set end_date) and create a new
one. Never edit company_name on an existing row to represent a switch (BR-12).

### SubmissionCycle
A coordinator-defined period representing one of the twice-monthly meetings.
| Field | Meaning |
|---|---|
| coordinator_id | who created/owns this cycle |
| cycle_name | e.g. "Cycle 1 – June 2026" |
| coverage_start_date, coverage_end_date | the period being reported on |
| deadline_date | the line that determines Late status (BR-6) |

No fixed system calendar — created manually each time (BR-5).

### DailyAccomplishmentReport (DAR)
One row per calendar day of logged activity.
| Field | Meaning |
|---|---|
| student_id | owner |
| cycle_id | null while still a draft; set on submission |
| report_date | must obey BR-4 (not future, not before OJT start, not after completion) |
| activities | JSON array of {activity, time_started, time_ended} — multiple itemized entries per date (min 1, max 20, enforced in the form request) |
| hours_rendered | ALWAYS derived = Σ(entry time_ended − time_started) (BR-3), recomputed server-side by the model whenever `activities` is written; never accepted as direct input |
| remarks_student | student's own note |
| status | Draft / Pending / Approved / Returned / Late |
| coordinator_comment, reviewed_by, reviewed_at | review trail |
| soft-deleted, never hard-deleted (BR-9) |

### WeeklyAccomplishmentReport (WAR)
**One document per month**, containing four week-sections (Week 1–4), filled
in progressively across the month's two cycles: Week 1–2 in Cycle 1, Week
3–4 in Cycle 2 (BR-8).
| Field | Meaning |
|---|---|
| student_id, month_period | identity (unique per student per month) |
| week{1..4}_activities | JSON array of plain strings — multiple activity lines per week (no per-line times; one shared week range) |
| week{1..4}_hours, week{1..4}_status, week{1..4}_comment | each week reviewed independently (BR-7) |
| cycle1_id, cycle2_id | which two cycles this month's weeks were submitted through |
| overall_status | derived rollup for dashboards only — not an independent source of truth |

### MonthlyAccomplishmentReport (MAR)
One row per student per month, submitted once the month's coverage is
complete.
| Field | Meaning |
|---|---|
| student_id, month_period | identity (unique per student per month) |
| activities_text, monthly_total_hours, remarks | content |
| status, cycle_id, coordinator_comment, reviewed_by, reviewed_at | review trail, same pattern as DAR |

### AuditLog
Append-only. Never edited, never deleted.
| Field | Meaning |
|---|---|
| user_id | actor (nullable — some system events may not have one) |
| action_type | Login / Submit / Approve / Return / Update / AccountChange |
| action_details | free text |
| timestamp | when |

## Key Relationships

- One Coordinator → many Students
- One Student → many CompanyAssignments (historized)
- One Coordinator → many SubmissionCycles
- One Student → many DAR, one WAR per month, many MAR (one per month)
- One SubmissionCycle → many DAR / WAR-section / MAR submissions across students
- One Student → exactly one OJTInformationSheet (ever)
- One User → many AuditLog entries

## Non-obvious modeling notes

- **Student and Coordinator both "extend" User.** Any implementation needs a
  clean way to represent this (e.g. shared-primary-key profile tables, or a
  single-table-with-nullable-columns approach) — the important thing is that
  role determines exactly one active profile, never both.
- **hours_rendered, completed_hours, overall_status are all derived values.**
  No code path should ever accept these as direct user input. They exist to
  make read paths fast, not as sources of truth in their own right.
- **The WAR is one row per student per month, not per week.** Resist the
  temptation to model it as four separate weekly rows — the official form
  treats it as a single document with four sections, and BR-8 depends on that.
