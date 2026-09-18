# QReport — PDF Branding Fix Handoff for opencode

## What's wrong

`pdf-forms.md` (and the Blade templates built from it) only captured the
**fields** of the official DAR/WAR/MAR forms, never their **visual
identity**. The actual official documents (verified directly from the
source `.docx` files — see Ground truth below) are fully branded:

- LLCC crest logo + a diagonal blue/gold "wave" ribbon banner across the
  top of every page
- A colored table: green header row, gold/yellow merged date column,
  gray subtotal bands
- A blue-over-gold horizontal bar footer

Current `resources/views/pdf/*.blade.php` render as plain black-and-white
tables with no logo, no color, no banner. This handoff fixes that —
**visual/CSS work only, no business logic changes.**

## Ground truth (extracted directly from the official docx, not eyeballed)

Colors, pulled from `w:fill` / `srgbClr` in the actual document XML:

| Element | Hex |
|---|---|
| Ribbon banner (header + footer bar) | `#00339A` (deep blue), `#FFC000` (gold) |
| Table header row (e.g. "Date / List of Activities...") | `#C5E0B3` (light green) |
| Date/Week/Month column (merged cell) | `#FFD966` (gold) |
| Per-date/per-week "TOTAL HOURS" subtotal row | `#D9D9D9` (light gray) |
| Grand total row ("TOTAL DAILY/WEEKLY HOURS") | `#BFBFBF` (darker gray) |

Confirmed identical across `OJT_DAILY_SHEET.docx`, `OJT_WEEKLY.docx`, and
`OJT_MONTHLY.docx` — this is one consistent brand system, not per-form
variation. MAR has no subtotal rows (single continuous table), so the two
grays don't apply there — it uses the green header + gold date column +
a green "Remarks/Status:" band at the bottom instead.

## Assets provided (`pdf-brand-assets.zip`)

- `images/pdf/llcc-logo.png` — the crest, extracted directly from the
  docx's embedded media (`word/media/image1.png`), clean and
  transparent. Vector-source quality, not a screenshot.
- `images/pdf/letterhead-band.png` — the full top banner (wave graphic +
  logo + "Lapu-Lapu City College" + address + school code), rendered at
  300 DPI print resolution. This is a deliberate simplification: the wave
  is a `custGeom` freeform vector shape in the original, which DomPDF
  cannot reliably reproduce from CSS/SVG. Since the banner text never
  varies by student, form, or date, baking the whole band into one static
  image is the safe, pixel-accurate choice — not a corner cut.

Drop both files into `public/images/pdf/` in the Laravel project.

**Footer is NOT an image** — it's simple enough to do as plain CSS (two
flat-colored bars, no diagonal), which keeps the footer text
(`Website:`/`Fb page:`/`Email:`) crisp and selectable rather than
rasterized. See markup below.

## Implementation plan

### 1. Extract a shared header/footer partial (do this first)

All four PDF views (`dar.blade.php`, `war.blade.php`, `mar.blade.php`,
`information-sheet.blade.php`) currently duplicate the same
`.header-org` markup and the same plain-text footer line. Pull both into
partials so the brand fix lands once, not four times:

- `resources/views/pdf/partials/letterhead.blade.php`:
  ```blade
  <img src="{{ public_path('images/pdf/letterhead-band.png') }}" style="width:100%; display:block;">
  ```
  (Use `public_path()`, not a URL — DomPDF needs a local filesystem path
  or a base64 data URI, not an HTTP request, unless remote fetching is
  explicitly enabled in `config/dompdf.php`. If `public_path()` results in
  a blank image when you test it, switch to embedding as base64:
  `data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/pdf/letterhead-band.png'))) }}`
  — try the simple path first, only fall back to base64 if needed.)

- `resources/views/pdf/partials/footer.blade.php`:
  ```blade
  <div style="margin-top:auto;">
      <div style="height:6px; background:#00339A;"></div>
      <div style="height:10px; background:#FFC000;"></div>
      <div style="text-align:center; font-size:7.5px; color:#555; padding:4px 0;">
          Website: www.llcc.edu.ph &mdash; Fb page: LLCC Public Information Office &mdash; Email: llccadmin@llcc.edu.ph
      </div>
  </div>
  ```
  This replaces the current plain `.footer` div in all four templates.

- Replace each template's existing `<div class="header-org">...</div>`
  block with `@include('pdf.partials.letterhead')`, and each `.footer`
  div with `@include('pdf.partials.footer')`.

### 2. Recolor the tables (per template)

In `dar.blade.php`, `war.blade.php`, `mar.blade.php`:
- `table.activities th` (or equivalent header row) → `background: #C5E0B3;`
  (currently `#eee`)
- The merged date/week/month column → `background: #FFD966;` (currently
  unstyled/white) — this is the `<td>` that spans the activity rows for
  one date (DAR), one week (WAR), or the whole month (MAR)
- DAR/WAR only — the per-date/per-week `.subtotal-row` → `background:
  #D9D9D9;` (currently `#f7f7f7`)
- DAR/WAR only — the grand-total row/div ("TOTAL DAILY HOURS" /
  "TOTAL WEEKLY HOURS") → `background: #BFBFBF;` where it's rendered as
  a table row; if it's currently a plain `<div class="grand-total">`
  with no background (check `dar.blade.php:33`), give it one to match
  — a light gray band behind the bold total text, full width
- MAR only — the "Remarks/Status:" band at the bottom of the table →
  `background: #C5E0B3;` (same green as the header, per the official
  form)

### 3. Info Sheet PDF

`information-sheet.blade.php` should get the same letterhead/footer
partials for consistency (all four are LLCC letterhead documents), even
though the source docx for the Info Sheet wasn't part of this specific
verification pass — reuse the same partials rather than inventing a
different treatment for it.

### 4. Verify visually, don't just trust green tests

The existing `CompletionAndPdfTest`/workflow tests only assert `200` +
`application/pdf` — they will stay green regardless of whether this
looks right. After making these changes:
```
php artisan test tests/Feature/Workflows/CompletionAndPdfTest.php
```
should still pass (no logic changed), but that's necessary, not
sufficient. Actually render one real PDF (hit `/dar/pdf/cycle/{cycle}/student/{student}`
in a browser against the live seeded DB) and visually compare it side by
side with the official `.docx` before calling this done.

## What NOT to do

- Don't touch `CompletedHoursRecalculator`, any Policy, any Request
  validation, or any route — this handoff is templates/CSS/assets only.
- Don't try to reproduce the diagonal wave banner as hand-coded CSS or
  inline SVG — use the provided image. Time spent fighting DomPDF's
  shape rendering is time not spent on the actual visual fix.
- Don't invent different brand colors for the Info Sheet PDF — reuse
  the exact hex values above.

## When done

Update `PROJECT_STATE.md`'s codebase inventory row for the PDF modules
to note the branding pass, and mention the two new asset files in the
"what to do with this" note so nobody deletes them as unused.
