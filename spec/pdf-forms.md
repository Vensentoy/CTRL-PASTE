# Official PDF Form Layouts — Ground Truth

Extracted directly from LLCC's official DOCX templates. Any PDF-generation
work must reproduce these layouts field-for-field — this is the actual
document that gets physically signed, so structure matters as much as data.

**This file is the fast-reference summary.** The original source files are
bundled at `assets/original-forms/` (OJT-INFO-SHEET.docx, OJT-DAILY-SHEET.docx,
OJT-WEEKLY.docx, OJT-MONTHLY.docx). When actually building the PDF-generation
templates, open the original docx files too — this summary captures every
field and the structural quirks, but exact spacing, column widths, merged
cells, and visual layout are only fully preserved in the originals. Treat
this file as "what fields exist and how they're grouped," and the docx
files as "exactly how it should look on paper."

Common header on all three report forms (Daily/Weekly/Monthly):
> Don B. Benedicto Rd., Gun-ob, Lapu-Lapu City, 6015
> School Code: 7174 — Lapu-Lapu City College

Common footer on all four forms:
> Website: www.llcc.edu.ph — Fb page: LLCC Public Information Office —
> Email: llccadmin@llcc.edu.ph

---

## 1. OJT Information Sheet

One-time document (see data-model.md — never resubmitted). Includes a 1x1
photo placeholder at the top.

**A. Personal Data** — Surname / Given Name / Middle Name; Student ID
Number; College (fixed: "COLLEGE OF TECHNOLOGY"); Course (fixed: "BS
INDUSTRIAL TECHNOLOGY") / Major / Year & Section; City Address; Gender
(Female/Male checkboxes); Contact Number / Email Address; Birth Date /
Birth Place; Provincial Address / Religion; Marital Status.

**B. Family Data** — Father's Name/Occupation/Company/Company
Address/Contact, mirrored for Mother's; Guardian's Name/Home
Address/Contact.

**C. Scholastic Data** — three parallel blocks (Tertiary, Secondary,
Primary), each with School / Address / Year Graduated / Honors-Awards.

**D. Health Data** — Height / Weight; Blood Type / Health Problem;
Vaccination Status (Unvaccinated / First Dose / Second Dose / Booster —
checkbox style); Type of Vaccine / Place of Vaccination / Date of
Vaccination; Health Insurance (PhilHealth / Private + "Specify" blank).

**E. OJT Work Experiences** (for Fourth Year Students) — repeatable table:
OJT Assignment | Position | Inclusive Date | OJT Site Address.

Closing attestation line + signature: "Student-OJTee's Signature Over
Printed Name" + Date.

---

## 2. Daily Accomplishment Report (DAR)

**⚠️ Important structural note:** the printed Daily form is not "one date
per document." The actual template batches **five consecutive dates per
printout** (observed sample: 6/1/26 through 6/5/26 — a work week), each
date getting its own mini-table of activity rows and its own per-date
"TOTAL HOURS" subtotal, followed by one grand "TOTAL DAILY HOURS" for the
whole printout.

The underlying data model still stores **one DAR row per calendar date**
(see data-model.md) — the PDF-generation layer is what groups multiple
DAR rows by date range into this batched five-day layout. Don't conflate
the storage model with the print layout.

Header block: Student's Name / Course (fixed: "BS IN INDUSTRIAL
TECHNOLOGY"); Year & Section / Major; Company's Name / Mobile Number;
Department Area / Date; Student OJT Job Designation / Daily Total Hours.

Per-date table columns: Date | List of Activities Accomplished | Time
Started | Time Ended | No. of Hours | Remarks/Status. Each date section
ends with a "TOTAL HOURS" row; the whole printout ends with "TOTAL DAILY
HOURS."

Signature block (see "Common signature block" below).

---

## 3. Weekly Accomplishment Report (WAR)

One WAR document, four WEEK sections (Week 1–4), matching the WAR entity's
one-row-per-month, four-week-columns model.

Header block: Student's Name / Course (fixed); Year & Section / Major;
Company's Name / Mobile Number; Department Area / Date; Student OJT Job
Designation / Weekly Total Hours.

Table columns (repeated per week section): Date | List of Activities
Accomplished | Date Started | Date Ended | No. of Hours | Remarks/Status.
Each WEEK block carries its own date-range subheading and ends with a
"TOTAL HOURS" row. After all four weeks: one "TOTAL WEEKLY HOURS" row.

**Note:** the source template sample had a stray duplicate "WEEK 1"
section after Week 4 — treat that as a template artifact/typo, not a real
fifth section. The data model has exactly four weeks (BR-8).

Signature block (see below).

---

## 4. Monthly Accomplishment Report (MAR)

Header block: same pattern as WAR/DAR, but "Date" field becomes "Month",
and "Weekly Total Hours" becomes "Monthly Total Hours".

Body: "Note: Summary of Weekly Accomplishment Reports (Week 1–4)" — a
two-column table, Date | List of Activities Accomplished, spanning the
full month (no per-week grouping here — this is the monthly rollup).
Followed by a "Remarks/Status" free-text block.

Signature block (see below).

---

## Common signature block (DAR, WAR, MAR)

All three report forms end with the same three-signatory pattern — this
directly bears on the blueprint's open item about role distinctness:

1. **Reviewed by:** "Company Supervisor/Manager/OJT Coordinator's Signature
   over Printed Name" — Date
2. **"COT OJT Adviser's Signature over Printed Name"** — Date (a separate
   line from #1 — the two are visually distinct signatories on the form)
3. **Approved by:** "College Dean's Signature over Printed Name" — Date

All signature fields render **blank** in system-generated PDFs (see
workflows.md §6) — these remain handwritten, physical signatures. The
system never captures or stores signature images/e-signatures in this
version.

## Open question this raises (carry into open-items.md)

The form's own layout lists "Company Supervisor/Manager/OJT Coordinator"
on one line and "COT OJT Adviser" on a separate line — suggesting they may
be distinct roles/people, not the same person under two names. This
supports treating them as separate placeholder fields on the generated PDF
rather than assuming they're identical, but final confirmation from LLCC
is still needed (per blueprint §14, item 1).
