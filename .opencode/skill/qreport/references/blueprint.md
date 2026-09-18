# Local OJT Tracking and Submission System
## Complete System Blueprint & Walkthrough
**Prepared for:** Lapu-Lapu City College (LLCC) — OJT Office
**Prepared by:** Capstone Group (6 Members)
**Document Type:** Systems Analysis & Design Blueprint

---

## 1. Executive Summary

The **Local OJT Tracking and Submission System** is a web-based, LAN-only information system that digitizes the preparation, submission, and monitoring of On-the-Job Training (OJT) documentation at LLCC. It replaces a fully manual, paper-based process in which students print and physically carry Daily, Weekly, and Monthly Accomplishment Reports to twice-monthly OJT meetings, and coordinators manually sort, review, and track them.

The system does not eliminate handwritten signatures (Company Supervisor, OJT Coordinator, COT OJT Adviser, College Dean) — these remain part of the official process. Instead, it digitizes **drafting, submission, review, approval-tracking, hour computation, and monitoring**, and generates print-ready PDFs in the official LLCC format for physical signing.

---

## 2. Problem Statement & Current Process

### 2.1 Problem
Students spend money and time printing and physically delivering OJT reports to campus twice a month. OJT Coordinators spend significant time manually organizing, checking, and tracking large volumes of paper documents across many students, with no centralized digital record of submission status or accumulated hours.

### 2.2 Current (As-Is) Process
1. Student performs OJT tasks at the assigned host company.
2. Student manually writes Daily Journal entries.
3. Student prepares Weekly Accomplishment Report.
4. Student prepares Monthly Accomplishment Report.
5. Student prints all documents.
6. Student travels to campus for the scheduled (twice-monthly) OJT meeting.
7. Student physically submits printed reports to the OJT Coordinator.
8. Coordinator manually checks, sorts, and files documents.
9. Coordinator manually tracks each student's submission status and hours using paper records.

### 2.3 Why the Current Process Fails
- Repeated printing costs for students.
- Mandatory campus travel purely to submit paperwork.
- Risk of lost, misplaced, or damaged physical documents.
- Heavy manual workload for coordinators.
- No centralized, real-time visibility into submission status or hours completed.
- Slow, error-prone progress monitoring.

### 2.4 Scope Statement
- **Client:** OJT Office of Lapu-Lapu City College (LLCC).
- **In scope:** Student report drafting/submission, Coordinator review/approval, hours tracking, PDF generation matching official LLCC forms, monitoring dashboards, reports/exports.
- **Out of scope (current version):** Company Supervisor system access, Dean/Adviser system access, digital/e-signatures, email/SMS notifications, native mobile app, registrar integration.
- **Deployment:** LAN-only (school's local network); not internet-accessible.

---

## 3. Users & Roles

| Role | Description | Account Creation | Access Scope |
|---|---|---|---|
| **Student** | OJT trainee across any LLCC course/program | Created by OJT Coordinator | Own records only |
| **OJT Coordinator** | Manages an assigned set of students (by department/program) | Created by another Coordinator/initial setup | Only their own assigned students' records |

**Key role rules:**
- All Coordinators share **equal permission levels** — no head coordinator/super-admin tier.
- A student has **exactly one** assigned Coordinator at a time; reassignment updates this without altering history.
- Students cannot self-register; no email verification step exists (accounts are provisioned directly from official student records).
- Company Supervisors, College Dean, and COT OJT Adviser are **not system users** in the current version — their involvement remains on the printed, physically signed document.

> **Open item for LLCC confirmation:** Whether "COT OJT Adviser" (named on the Monthly Report) is the same person as the "OJT Coordinator" or a distinct role. Documented as a placeholder signatory field on generated PDFs pending clarification.

---

## 4. Core Concepts & Terminology

| Term | Definition |
|---|---|
| **Submission Cycle** | A coordinator-defined period (name, coverage start/end dates, deadline) representing one of the twice-monthly meetings. Students submit reports *into* a cycle. |
| **Daily Accomplishment Report (DAR)** | A single day's logged activity, time in/out, computed hours, and remarks. |
| **Weekly Accomplishment Report (WAR)** | One document per month containing four week-sections (Week 1–4). Sections are filled in progressively — Week 1–2 in Cycle 1, Week 3–4 in Cycle 2 of the same month. |
| **Monthly Accomplishment Report (MAR)** | One document per month, summarizing the month's activities; submitted once fully covered. |
| **OJT Information Sheet** | One-time student profile record (personal, family, scholastic, health, and OJT-assignment data), digitized in this system. |
| **Company Assignment** | A historized record of which company a student is/was deployed to, with date ranges — supports multiple assignments over time. |

---

## 5. Functional Requirements (Features)

### 5.1 Student Features
- Log in with coordinator-issued credentials; forced password change on first login.
- Complete the one-time **OJT Information Sheet**.
- View/manage own **Company Assignment(s)**.
- Create/edit **Daily Accomplishment Report** drafts continuously (not tied to a cycle until submitted).
- Complete the shared **Weekly Accomplishment Report** document, section by section, per active cycle.
- Complete the **Monthly Accomplishment Report** once its coverage period is reached.
- Submit all applicable reports into the **current open Submission Cycle**.
- View personal dashboard: required vs. completed hours, completion %, per-report statuses (Pending / Approved / Returned for Revision / Late).
- View coordinator comments on returned reports; edit and resubmit.
- Download/print the official-format PDF of any of their reports (with blank signature fields).
- Receive in-system notifications/banners (returned reports, upcoming deadlines).

### 5.2 OJT Coordinator Features
- Create/manage Student and Coordinator accounts (including password resets).
- Create/manage **Submission Cycles** (name, coverage dates, deadline).
- Set/edit each student's **required total OJT hours** (varies by program).
- Manage each student's assigned Coordinator (reassignment), Company Assignment history, and account status (Active / Inactive / Completed / Archived).
- Review submitted reports **per document type, per cycle** — Approve or Return for Revision (with comments) independently for DAR, WAR, and MAR.
- View real-time dashboard: total students, pending/approved/returned/late counts, per-student progress.
- Generate/export: Student OJT Progress Report, Submission Monitoring Report, Student Record Report, Department Summary Report (own students only).
- Export data to Excel/CSV.
- View audit log entries relevant to their scope.

### 5.3 System-Level Features
- Auto-computation of hours rendered (Time Ended − Time Started) and roll-up to weekly/monthly/overall totals.
- Auto-flagging of "Late" status based on Submission Cycle deadlines (not on the activity date itself).
- Auto status change to **Completed** when required hours are met (submissions further restricted unless coordinator reopens the record).
- Soft-delete only — no hard deletion of submitted reports.
- Session timeout on inactivity.
- Backend-enforced role/data isolation (students cannot access another student's data via direct request manipulation, not just hidden UI).
- PDF generation matching official LLCC layouts (Daily, Weekly, Monthly forms) with blank signature blocks.

---

## 6. Complete Workflow

### 6.1 Onboarding
1. Coordinator creates a Student account → assigns Coordinator, program/course, required OJT hours, OJT start date, initial Company Assignment.
2. System issues a temporary password.
3. Student logs in, is forced to change password, then completes the **OJT Information Sheet** (one-time).

### 6.2 Daily Logging (Continuous)
1. Student creates a DAR draft for a given date (must not be future-dated, before OJT start, or after OJT completion).
2. Student enters Time Started / Time Ended → system computes hours automatically.
3. Draft is visible only to the student until submitted.

### 6.3 Submission Cycle Lifecycle
1. Coordinator creates a Submission Cycle (e.g., "Cycle 1 – June 2026", coverage June 1–15, deadline June 16).
2. All students under that coordinator submit into this shared cycle.
3. Student batch-submits their accumulated DAR drafts for the coverage period, completes/submits the relevant WAR week-sections (Week 1–2 for Cycle 1, Week 3–4 for Cycle 2), and — if the month's coverage is complete — submits the MAR.
4. Each document type transitions independently to **Pending**.
5. If the deadline passes without submission, the cycle is marked **Late/Missed** for that student; the student may still submit those reports in the *next* cycle, carrying the Late flag permanently on that record.

### 6.4 Coordinator Review
1. Coordinator opens the cycle and reviews submissions **per document, per student** (not as one all-or-nothing batch).
2. For each document: **Approve**, or **Return for Revision** with a required comment.
3. Returned documents go back to the student for edits; on resubmission, status returns to **Pending** for re-review.
4. Coordinators cannot directly edit student-submitted content — only annotate/decide.

### 6.5 Completion
1. System continuously accumulates approved-hours totals against the student's required hours.
2. When required hours are met, student status auto-changes to **Completed**; further new submissions are blocked unless the coordinator manually reopens the record.
3. Coordinator finalizes/archives the student's OJT record.

### 6.6 PDF Generation & Physical Signing
1. At any point, student or coordinator can generate an official-format PDF of a DAR/WAR/MAR.
2. PDF includes all entered data plus **blank signature blocks** (Company Supervisor, OJT Coordinator/COT Adviser, College Dean) for offline handwritten signing, per existing LLCC process.

---

## 7. Business Rules

| # | Rule |
|---|---|
| BR-1 | A student has exactly one active Coordinator at a time. Reassignment does not alter historical records. |
| BR-2 | Required OJT hours are set per student by the Coordinator (no single global value — varies by program). |
| BR-3 | Hours Rendered = Time Ended − Time Started; never manually entered by the student. |
| BR-4 | A DAR date cannot be in the future, before the student's OJT start date, or after their OJT completion date. |
| BR-5 | Submission Cycles are created manually by a Coordinator (name, coverage dates, deadline) — no fixed system calendar. |
| BR-6 | "Late" is determined by whether a report was submitted before its cycle's deadline — not by the activity date. |
| BR-7 | DAR, WAR, and MAR are reviewed and statused **independently** within a cycle. |
| BR-8 | The WAR is one document per month with four week-sections; sections are completed progressively across the month's two cycles. |
| BR-9 | Submitted reports are never hard-deleted — soft delete only, retained for audit. |
| BR-10 | Once a student reaches required hours, status becomes Completed and new submissions are blocked unless the Coordinator reopens the record. |
| BR-11 | A Coordinator can only view/manage students currently assigned to them. |
| BR-12 | Company Assignments are historized (multiple records per student over time), never overwritten. |
| BR-13 | A student must always have at least one valid assigned Coordinator; the system must prevent orphaned or duplicate-conflicting assignments. |
| BR-14 | All role/data-access restrictions are enforced at the backend/API/database level, not merely hidden in the UI. |

---

## 8. Data Model (Entities & Relationships)

### 8.1 Entity List

**User (base)**
- user_id (PK), role (Student/Coordinator), username, password_hash, status (Active/Inactive/Completed/Archived), created_at, last_login_at

**Student** (extends User)
- student_id (PK, FK→User), coordinator_id (FK→Coordinator), student_id_number, surname, given_name, middle_name, course, major, year_section, ojt_start_date, ojt_completion_date, required_hours, completed_hours (derived/cached), ojt_status (Ongoing/Completed)

**Coordinator** (extends User)
- coordinator_id (PK, FK→User), full_name, department_area

**OJTInformationSheet**
- info_sheet_id (PK), student_id (FK), personal_data fields (city_address, gender, contact_number, email, birth_date, birth_place, provincial_address, religion, marital_status), family_data fields (father/mother name, occupation, company, address, contact; guardian name, address, contact), scholastic_data (tertiary/secondary/primary: school, address, year_graduated, honors), health_data (height, weight, blood_type, health_problem, vaccination_status, vaccine_type, vaccination_place, vaccination_date, health_insurance), ojt_work_experience (assignment, position, inclusive_dates, site_address) — repeatable for 4th-year students, signed_date

**CompanyAssignment**
- assignment_id (PK), student_id (FK), company_name, department_area, job_designation, mobile_number, start_date, end_date (nullable = current)

**SubmissionCycle**
- cycle_id (PK), coordinator_id (FK), cycle_name, coverage_start_date, coverage_end_date, deadline_date, created_at

**DailyAccomplishmentReport (DAR)**
- dar_id (PK), student_id (FK), cycle_id (FK, nullable until submitted), report_date, activities_text, time_started, time_ended, hours_rendered (computed), remarks_student, status (Draft/Pending/Approved/Returned/Late), coordinator_comment, reviewed_by, reviewed_at, is_deleted (soft delete)

**WeeklyAccomplishmentReport (WAR)**
- war_id (PK), student_id (FK), month_period (e.g., "June 2026"), week1_status, week1_activities, week1_hours, week2_status, week2_activities, week2_hours, week3_status, week3_activities, week3_hours, week4_status, week4_activities, week4_hours, cycle1_id (FK), cycle2_id (FK), overall_status, is_deleted

**MonthlyAccomplishmentReport (MAR)**
- mar_id (PK), student_id (FK), month_period, activities_text, monthly_total_hours, remarks, status, cycle_id (FK), coordinator_comment, reviewed_by, reviewed_at, is_deleted

**AuditLog**
- log_id (PK), user_id (FK), action_type (Login/Submit/Approve/Return/Update/AccountChange), action_details, timestamp

### 8.2 Key Relationships
- One **Coordinator** → many **Students**
- One **Student** → many **CompanyAssignments** (historized)
- One **Coordinator** → many **SubmissionCycles**
- One **Student** → many **DAR**, one **WAR** per month, many **MAR** (one per month)
- One **SubmissionCycle** → many **DAR/WAR-section/MAR** submissions across students
- One **Student** → one **OJTInformationSheet**
- One **User** → many **AuditLog** entries

---

## 9. Reports & Exports

| Report | Contents | Scope |
|---|---|---|
| **Student OJT Progress Report** | Name, course, company, required/completed/remaining hours, completion %, status | Per student or all under coordinator |
| **Submission Monitoring Report** | Name, cycle, submitted/pending/approved/returned/late counts | Per cycle, per coordinator's students |
| **Student Record Report** | Full profile, company history, report history, total hours, approval history | Per student |
| **Department Summary Report** | # OJT students, active, completed, missing/late — **coordinator's own students only** | Per coordinator |
| **Excel/CSV Export** | Student info, hours, submission status | On-demand, coordinator-triggered |

---

## 10. Security & Privacy (RA 10173 Alignment)

- **Access control:** Students see only their own data; Coordinators see only their assigned students. Enforced at backend/API/DB level, not just UI.
- **Sensitive data handling:** OJT Information Sheet (health, family, birth data) is restricted to the owning student and their assigned coordinator only.
- **Authentication:** Coordinator-issued temporary password; forced change on first login; hashed password storage; minimum length + letter/number complexity enforced.
- **No self-service password recovery** — LAN-only, no email verification; Coordinator manually resets.
- **Session timeout** on inactivity (shared/public campus machines).
- **Audit logging** of logins, submissions, approvals/returns, and account/data changes (actor, action, timestamp).
- **Retention:** No auto-deletion. Completed records are archived, not erased. Any deletion requires authorized manual action and is soft-deleted (recoverable) for auditability.
- **Account lifecycle:** Active / Inactive / Completed / Archived statuses for lifecycle management (withdrawal, graduation, etc.).

---

## 11. Edge Cases Handled

| Scenario | Handling |
|---|---|
| Student reassigned to a new Coordinator | History/reports/hours remain intact; only the coordinator link updates. |
| Student changes host company mid-OJT | New CompanyAssignment record created; old one closed with an end date — full history preserved. |
| Student misses a Submission Cycle entirely | Can submit in the next cycle; original cycle permanently flagged Late/Missed. |
| Coordinator needs to "delete" a report | Soft delete only — hidden from normal views, retained in DB for audit. |
| Student exceeds required hours | Status becomes Completed; further submissions blocked unless coordinator reopens. |
| Invalid/missing coordinator assignment | System validation prevents saving a student with zero or conflicting coordinator assignments. |
| Weekly Report spans two cycles | Same WAR document is progressively completed — Week 1–2 in Cycle 1, Week 3–4 in Cycle 2; system tracks section-level completion/review status. |
| Report returned for revision | Only that specific document is affected; other documents in the same cycle keep their own independent status. |

---

## 12. Technical Architecture (Recommended)

Given the **LAN-only, single local-server deployment**, and typical capstone team constraints, the recommended stack prioritizes ease of local hosting, low maintenance overhead, and strong documentation/community support:

| Layer | Recommendation | Rationale |
|---|---|---|
| **Frontend** | HTML/CSS/JavaScript (or a lightweight framework like Vue/React if the team is comfortable) — must be **responsive** for mobile browser support | Works well on desktop and phones over campus WiFi; no build complexity required for a LAN app |
| **Backend** | PHP (native or Laravel) **or** Node.js/Express | Both are well-documented, easy to self-host on a single on-campus machine, and commonly taught/supported in PH capstone programs |
| **Database** | MySQL / MariaDB | Reliable, free, pairs naturally with PHP/Laravel stacks, easy local backup |
| **PDF Generation** | Server-side library (e.g., DomPDF/mPDF for PHP, or Puppeteer/PDFKit for Node) rendering the official LLCC template layouts | Needed to reproduce exact form structure with blank signature blocks |
| **Hosting** | Single on-campus PC/server running XAMPP/LAMP (or Node runtime) on the school LAN | Matches confirmed LAN-only, single-server deployment; exact hardware/OS to be confirmed with LLCC |
| **Authentication** | Server-side sessions with hashed passwords (e.g., bcrypt) | Standard, secure, no external dependency (no email-based flows needed) |

> **Open item for LLCC/adviser confirmation:** Final hardware/OS for the hosting server.

---

## 13. Future Improvements (Phase 2 — Out of Current Scope)

- **Company Supervisor Portal** — direct online verification of accomplishments, evaluations, and hour confirmation.
- **Email/SMS Notifications** — deadline reminders, revision/approval alerts (requires internet connectivity, currently out of scope for LAN-only deployment).
- **Native Mobile Application** — dedicated Android/iOS app (current version is a responsive web app only).
- **Analytics & Early-Warning Monitoring** — trend analysis to flag students at risk of delayed completion.
- **Digital/E-Signature Integration** — replacing handwritten signatures for Supervisor, Coordinator, and Dean.
- **Registrar System Integration** — automatic enrollment/program verification instead of manual account creation.

---

## 14. Open Items Requiring LLCC / Adviser Confirmation

1. Whether "COT OJT Adviser" (Monthly Report signatory) is the same role as "OJT Coordinator" or a distinct role.
2. Exact required OJT hours per program (only BSIT's 1,440–1,800 range is currently confirmed).
3. Whether students who exceed required hours may continue logging entries, or are hard-blocked.
4. Precise intended use of the "Remarks/Status" column — student self-notes vs. coordinator review annotation (may be both, needs official confirmation).
5. Whether OJT meeting/cycle dates follow any recurring pattern or are always manually set per instance.
6. Final server hardware/OS for on-campus LAN hosting.

---

## 15. Summary

This blueprint defines a two-role (Student, Coordinator), LAN-only, responsive web system built around a **Submission Cycle** model that mirrors LLCC's actual twice-monthly meeting process. It digitizes the three official accomplishment reports and the one-time Information Sheet, automates hour computation and completion tracking, enforces backend-level data isolation and auditability per RA 10173 expectations, and reproduces official LLCC PDF layouts for the physical signatures that remain part of the school's process. All assumptions made where LLCC's exact policy is still unconfirmed are flagged in Section 14 for validation before final development.
