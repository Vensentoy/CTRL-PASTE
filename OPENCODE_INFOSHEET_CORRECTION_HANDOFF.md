# QReport — Info Sheet PDF Correction Handoff for opencode

## Context — this one needs more than a recolor

The earlier PDF branding handoff told you to reuse the DAR/WAR/MAR
letterhead/footer partials for the Info Sheet "for consistency," on the
assumption that all four LLCC documents share one letterhead. That
assumption was wrong — verified directly against `OJT-INFO-SHEET.docx`,
which has its **own distinct letterhead** (three seals, serif title
font) that's different from the DAR/WAR/MAR banner. Beyond that, several
fields and a whole attestation line are structurally different from what
got built. This handoff corrects both.

## 1. Letterhead — use a different asset entirely

`OJT-INFO-SHEET.docx` embeds its own single flattened banner image
(logo cluster + wave + title block, all baked into one JPEG) — not the
`letterhead-band.png` used elsewhere. It's provided as
`public/images/pdf/info-sheet-letterhead.jpg` in the attached zip.

- Do **not** `@include('pdf.partials.letterhead')` in
  `information-sheet.blade.php` anymore. Replace it with a direct
  `<img src="{{ public_path('images/pdf/info-sheet-letterhead.jpg') }}" style="width:100%; display:block;">`
  (same `public_path()`-first, base64-fallback pattern as the other
  letterhead, in case DomPDF renders it blank).
- The footer partial (`pdf.partials.footer`, the blue/gold CSS bars) is
  correct as-is and shared across all four forms — keep that one.
- Apply the same page-margin fix from the branding-placement issue
  (`@page { margin: 0; } body { margin: 0; }`, real content in a padded
  `.page-content` wrapper) here too, so this banner also bleeds to the
  true page edge instead of sitting inset.

## 2. Title block

Official form shows two centered bold lines directly under the banner:
```
ON-THE JOB TRAINING (OJT)
Information Sheet
```
Current template renders a single line, "OJT INFORMATION SHEET" — wrong
wording and wrong line count. Match the two-line version exactly
(second line is title case, not all-caps).

## 3. Photo box position

Official form positions the "1x1 Photo" box in the header area, roughly
level with the title block (top-right of the page, near the top, not
further down). Current template renders it lower, roughly level with
"A. PERSONAL DATA" instead. Move it up to sit beside/near the title
block, matching the official position.

## 4. Field layout — real bordered grid, not plain text lines

The official form renders every field inside an actual bordered table
cell (a fillable-box look), section by section (A–E). The current
template renders `Label: value` as plain text lines with no borders at
all. Wrap each section (A. Personal Data, B. Family Data, C. Scholastic
Data, D. Health Data) in a bordered `<table>` with cell borders, matching
the boxed-grid look of the source docx — not a cosmetic nice-to-have,
this is the actual visual structure of the official document.

## 5. Section-by-section field corrections

Cross-checked directly against `OJT-INFO-SHEET.docx` — these are
content/label mismatches, not styling:

**A. Personal Data**
- Official groups `Course: <value> | Major: | Year & Section:` together
  in one row, with `College: <value>` as its own separate row above it.
  Current template pairs `College + Course` on one row and
  `Major + Year & Section` on another — wrong grouping. Match the
  official grouping.

**B. Family Data**
- "Company:" → should read **"Name of Company:"** (both father's and
  mother's rows).
- Guardian block is **three separate full-width rows** in the official
  form: `Guardian's Name:`, `Guardian's Home Address:`, `Contact
  Number:` — each its own row. Current template compresses this into
  two lines (`Guardian's Name: — / Contact: —` then `Home Address: —`),
  dropping the "Guardian's" qualifier from the address label and
  misordering it. Rebuild as three distinct rows, correctly labeled.

**C. Scholastic Data**
- "Tertiary School:" → **"Tertiary:"**
- "Secondary School:" → **"Secondary:"**
- "Primary School:" → **"Primary:"**
- "Honors-Awards:" → **"Honor/ Awards Received:"**
  (Apply this label correction to all three school rows.)

**D. Health Data**
- "Health Insurance:" currently prints the plain value (e.g.
  "PhilHealth"). Official form has this as a **checkbox pair**:
  `☐ PhilHealth    ☐ Private Specify: ________`. Render as checkboxes
  with the applicable one checked based on the stored value, matching
  the same checkbox pattern already used correctly for Gender and
  Vaccination Status elsewhere in this same template.

## 6. Missing attestation line

Directly above the signature line, the official form has this sentence
(currently absent entirely from the template):
```
I hereby affix my signature to attest the above data and statement are true and correct.
```
Add it as its own line/paragraph immediately before the
"Student-OJTee's Signature Over Printed Name" line.

## What's already correct — don't touch

- Section E (OJT Work Experiences table) — green header, structure, and
  columns already match the official form correctly.
- The blank/unchecked box style already used for Gender and Vaccination
  Status checkboxes is the right pattern — just extend it to Health
  Insurance per #5 above.
- Footer partial and its colors — unchanged, already correct.

## Verify

1. `php artisan test` — no logic changed, should stay at the current
   pass count.
2. Render one real Info Sheet PDF against seeded data and compare
   side-by-side against `OJT-INFO-SHEET.docx` — check the letterhead,
   title wording, photo box position, all five corrected labels, the
   grid borders, and the attestation sentence.
3. Update `PROJECT_STATE.md` noting the Info Sheet now uses its own
   letterhead asset (`info-sheet-letterhead.jpg`, not
   `letterhead-band.png`) — flag both as load-bearing so neither gets
   deleted as "unused" later.
