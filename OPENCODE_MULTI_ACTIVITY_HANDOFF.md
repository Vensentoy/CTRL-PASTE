# QReport — DAR/WAR Multi-Activity Itemization (Real Feature, Not a Template Fix)

## Why this exists

Every fix so far in this project has been display-layer only (colors,
letterhead, page size, footer position) — zero schema or workflow
impact. This one is different: it's a genuine data-model gap, found by
comparing the system's output against **real, actual filled-out
DAR/WAR/MAR forms** a real student (Lester S. Tapao, Peba Tandem
Manufacturing Inc.) submitted on paper — not the blank official
templates used for every previous round. See
`lester-reference-examples/` (the three real docx files) — treat these
as ground truth for what "done" looks like, the same way
`spec/pdf-forms.md` was ground truth for colors.

**Confirmed with the user:** no real production data exists yet
(current DB is seed/demo data only), and this system targets **next
year's** OJT cohort, not any currently-in-progress student. So this is
a clean schema change — no backward-compatible migration of existing
DAR/WAR records is needed. Reset/reseed freely.

## The actual gap

**DAR** currently stores one activity per date: one `activities_text`,
one `time_started`, one `time_ended`, one computed `hours` value, per
DAR row (= per date). The real form has **multiple itemized activities
per date**, each with its own time-in/time-out and computed duration,
under one shared date and one shared Remarks/Status. Example from
Lester's 6/1/26: four separate activities (disassembling computers →
7:30–10:30, 3hrs; reassembling → 10:30–12:00, 1hr30m; touring the
server room → 12:30–3:30, 3hrs; checking the server room → 3:30–5:00,
1hr30m), summing to that date's `TOTAL HOURS: 9hrs`.

**WAR** has the same shape of gap, one level up: multiple activity
*lines* per week (no individual times — just activity text, one per
line), with **one shared** Date Started / Date Ended / No. of Hours /
Remarks-Status for the whole week, merged across all that week's
activity lines. Example: Lester's Week 1 lists 8 distinct activity
lines (one per task performed across that week's 5 days), with
`6/1/26 | 6/5/26 | 45hrs | COMPLETED` appearing once, vertically
centered against all 8 rows.

**MAR needs no changes** — already verified correct against the real
example (single narrative paragraph(s), "COMPLETED" in the Remarks
band, matches what's already built).

## Chosen approach: JSON column, not a new relational table

A fully "correct" implementation would be a child table
(`dar_activity_entries` with a `dar_id` foreign key, etc.). That's more
moving parts than fits cleanly in one focused session across migration
+ model + relationship + request + form + PDF + tests all staying in
sync. Given the deadline, use a **JSON column** instead — it stores the
same itemized data, produces the identical correct PDF output (which is
the actual deliverable that matters), and touches far fewer files. This
is a legitimate engineering trade-off, not a shortcut that produces
wrong output — call this out explicitly in `PROJECT_STATE.md` when
done, so a future session doesn't mistake it for an oversight.

## Schema changes

**`daily_accomplishment_reports` table:**
- Drop: `activities_text`, `time_started`, `time_ended`
- Add: `activities` (JSON) — array of
  `{"activity": string, "time_started": "HH:MM", "time_ended": "HH:MM"}`
- Keep `hours` (numeric) as a **stored, computed** column — recompute
  it as the sum of each entry's duration whenever `activities` is set
  (model mutator or saving-event observer), so
  `CompletedHoursRecalculator` and every other consumer of `hours`
  keeps working unchanged. Don't make callers compute this themselves.
- `remarks_status` (or whatever the current single status field is
  named) stays exactly as-is — it's already per-date, matching the
  real form's per-date merged status.

**`weekly_accomplishment_reports` table:**
- Drop: `activities_text`
- Add: `activities` (JSON) — array of plain strings (no per-item
  times — WAR only has one shared time range for the whole week)
- Keep `week1_hours`..`week4_hours` (or whatever the current per-week
  hours columns are named) exactly as entered/computed today — this
  itemization doesn't change how weekly hours are determined, only how
  the activity list is stored and displayed. Do **not** try to
  auto-derive WAR hours from summing anything DAR-side; that's a
  separate concern already handled correctly by existing BR logic.

## Model changes

- `DailyAccomplishmentReport`: `activities` cast to `array`. Add a
  mutator/observer that recalculates `hours` = sum of each entry's
  `time_ended - time_started` whenever `activities` is set. Validate at
  least one entry exists.
- `WeeklyAccomplishmentReport`: `activities` cast to `array`. No hours
  recalculation needed here (hours stay independently entered/approved
  as today).

## Request validation

- DAR store/update request: `activities` required array, **min 1,
  max 20 items** (cap decided in a previous session — enforced here
  so a date can't grow an unbounded activity list);
  each item requires `activity` (string), `time_started` and
  `time_ended` (valid times, end after start).
- WAR store/update request: `activities` required array, min 1 item;
  each item requires a non-empty string.

## Student-facing forms

Both DAR and WAR submission forms need a repeatable row group instead
of a single textarea/time-pair:
- DAR: repeatable {activity text, time started, time ended} row, with
  "Add another activity" / remove-row controls. Use Alpine.js (already
  likely available via the Breeze/Tailwind stack — check before adding
  a new dependency) for the add/remove interaction; no new backend
  endpoints needed, submit the whole array as one form post.
- WAR: repeatable single-line activity text input, same add/remove
  pattern, no time fields.

## PDF template changes

**`dar.blade.php`:** for each date, loop over that date's `activities`
array. First activity row carries the Date cell (`rowspan` = count of
that date's activities) and the Remarks/Status cell (same rowspan);
every activity row after the first omits those two `<td>`s entirely
(they're covered by the rowspan). Time Started/Time Ended/Hours stay
**per-row** (each activity has its own). The existing full-width
`TOTAL HOURS` subtotal row stays exactly as-is structurally — sum of
the array entries' hours, not a change to that row's shape.

**`war.blade.php`:** same rowspan pattern for the gold Week cell and
the green Remarks/Status cell (rowspan = count of that week's
activities). Difference from DAR: Date Started / Date Ended / No. of
Hours are **merged once per week** (also `rowspan`), not per-activity
row — only the activity text column repeats per row. The existing
`TOTAL HOURS` row stays as-is.

**`mar.blade.php`:** no changes.

## Seeders / factories

Update whatever seeds demo DAR/WAR data to generate realistic
multi-entry arrays (2–5 activities per date/week) instead of one flat
string — this is a good opportunity to make demo data actually
resemble real usage, which will also make manual QA against the Lester
reference easier.

## Tests

Be upfront in your plan about this: every existing feature test that
creates a DAR/WAR record via the old flat fields will break and need
updating to the new array shape. This is the largest test-touch of any
round so far — budget for it, don't treat it as a surprise partway
through. `CompletedHoursRecalculator`'s own logic shouldn't need to
change (it still just sums a `hours` column), but the tests that set up
its fixtures will.

## Verify

1. `php artisan test` — expect a real, possibly-large change in which
   specific tests exist (not just pass count), since DAR/WAR fixtures
   are being restructured. Report the actual before/after test names
   changed, not just a pass/fail number.
2. Render a DAR and a WAR PDF using data shaped like Lester's real
   example (multiple activities per date, multiple lines per week) and
   compare directly against `lester-reference-examples/` — rowspan
   placement, per-date/per-week TOTAL HOURS sums, and Remarks/Status
   merge are the things most likely to be subtly wrong on first pass.
3. Confirm MAR is untouched — no changes to its migration, model, or
   template.
4. Update `PROJECT_STATE.md`: note the JSON-column design choice and
   why (see rationale above), and that this is real itemized storage,
   not a display-only parsing trick.
