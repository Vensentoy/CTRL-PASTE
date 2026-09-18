<!DOCTYPE html>
{{--
    pdf-forms.md §1: official OJT Information Sheet layout, field-for-field.
    One-time document (data-model.md) — one printout per student, no cycle
    or month parameter. College/Course/Major/Year & Section come from the
    Student row (not stored on the sheet itself — see the information
    sheets migration docblock). Photo is a blank 1x1 placeholder box and
    the attestation signature line renders blank per workflows.md §6 —
    this system never captures signature images/e-signatures.

    Branding: this form has its OWN letterhead (info-sheet-letterhead.jpg —
    three seals, serif title), distinct from the DAR/WAR/MAR banner — do
    not switch it back to pdf.partials.letterhead. Footer bar is shared
    via pdf.partials.footer; experiences table header row #C5E0B3.
--}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #111; }
        /* Content width 500pt: official tables are 10008dxa wide (measured
           from tblGrid), i.e. they span margin-to-margin at 56pt side
           insets — NOT the 72pt pgMar default. */
        .page-content { padding: 0 56pt 36pt; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        /* 11pt body text (Normal sz=22). Cells carry label+value as flowing
           text (official cells are blank fillable boxes in source; with real
           filled data, flowing text in WIDE cells is what keeps every row
           to a single line — narrow side-by-side label columns forced
           11pt values to wrap and blew the 1-page budget). */
        table.info td { padding: 0 3px; font-size: 11pt; line-height: 1.08; border: 1px solid #333; }
        .flabel { font-weight: bold; }
        table.info td.label { font-weight: bold; width: 18%; }
        .section-heading { font-size: 10px; font-weight: bold; margin: 8px 0 3px; text-transform: uppercase; }
        .photo-box { float: right; width: 72pt; height: 72pt; border: 1px solid #333; text-align: center; font-size: 8px; color: #555; padding-top: 28pt; margin-left: 8px; }
        table.experiences { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.experiences th, table.experiences td {
            border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; vertical-align: top;
        }
        table.experiences th { background: #C5E0B3; text-align: left; }
        .signatures { margin-top: 12px; }
        .sig-line { margin-top: 18px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>

{{--
    Own banner (OJT-INFO-SHEET.docx embeds a single flattened JPEG, not the
    DAR/WAR/MAR band). DomPDF needs a local path (no config/dompdf.php
    remote fetching) — if blank, fall back to base64:
    data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('images/pdf/info-sheet-letterhead.jpg'))) }}
--}}
<img src="{{ public_path('images/pdf/info-sheet-letterhead.jpg') }}" style="width:100%; display:block;">

<div class="page-content">
<div class="photo-box">1x1 Photo</div>

<p style="margin:0; font-size:11px; font-weight:bold; text-align:center;">ON-THE JOB TRAINING (OJT)</p>
<p style="margin:0 0 8px; font-size:11px; font-weight:bold; text-align:center;">Information Sheet</p>

{{--
    Section row inventory mirrors OJT-INFO-SHEET.docx tables 1: label rows
    (Surname/Given/Middle; Course/Major/Year&Section) stay multi-cell,
    long-label fields are full-width flowing rows exactly as in source
    (Student ID, College, City Address, Marital Status). Values render
    inline after their labels: source cells are blank fillable boxes, so
    with real filled data, wide flowing cells are what keep rows
    single-line (narrow label|value column pairs forced 11pt wrapping).
--}}
<div class="section-heading">A. Personal Data</div>
<table class="info">
    <tr>
        <td><span class="flabel">Surname:</span> {{ $student->surname }}</td>
        <td><span class="flabel">Given Name:</span> {{ $student->given_name }}</td>
        <td><span class="flabel">Middle Name:</span> {{ $student->middle_name ?: '—' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">Student ID Number:</span> {{ $student->student_id_number }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">College:</span> COLLEGE OF TECHNOLOGY</td>
    </tr>
    <tr>
        <td><span class="flabel">Course:</span> BS INDUSTRIAL TECHNOLOGY</td>
        <td><span class="flabel">Major:</span> {{ $student->major }}</td>
        <td><span class="flabel">Year &amp; Section:</span> {{ $student->year_section }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">City Address:</span> {{ $sheet->city_address }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">Gender:</span> {{ $sheet->gender === 'Female' ? '☑ Female ☐ Male' : '☐ Female ☑ Male' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Contact Number:</span> {{ $sheet->contact_number }}</td>
        <td colspan="2"><span class="flabel">Email Address:</span> {{ $sheet->email }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Birth Date:</span> {{ $sheet->birth_date->toFormattedDateString() }}</td>
        <td colspan="2"><span class="flabel">Birth Place:</span> {{ $sheet->birth_place }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Provincial Address:</span> {{ $sheet->provincial_address ?: '—' }}</td>
        <td colspan="2"><span class="flabel">Religion:</span> {{ $sheet->religion ?: '—' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">Marital Status:</span> {{ $sheet->marital_status ?: '—' }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="flabel">Company:</span> {{ $company?->company_name ?? '—' }}</td>
    </tr>
</table>

{{--
    Official table2 is a Father|Mother side-by-side comparison (5 rows × 2
    cols, 239/261pt) — not stacked father-then-mother blocks. Guardian is
    its own 1-column table (table3) with three full-width flowing rows.
--}}
<div class="section-heading">B. Family Data</div>
<table class="info">
    <tr>
        <td><span class="flabel">Father's Name:</span> {{ $sheet->father_name ?: '—' }}</td>
        <td><span class="flabel">Mother's Name:</span> {{ $sheet->mother_name ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Occupation:</span> {{ $sheet->father_occupation ?: '—' }}</td>
        <td><span class="flabel">Occupation:</span> {{ $sheet->mother_occupation ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Name of Company:</span> {{ $sheet->father_company ?: '—' }}</td>
        <td><span class="flabel">Name of Company:</span> {{ $sheet->mother_company ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Company Address:</span> {{ $sheet->father_company_address ?: '—' }}</td>
        <td><span class="flabel">Company Address:</span> {{ $sheet->mother_company_address ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Contact Number:</span> {{ $sheet->father_contact ?: '—' }}</td>
        <td><span class="flabel">Contact Number:</span> {{ $sheet->mother_contact ?: '—' }}</td>
    </tr>
</table>
<table class="info">
    <tr>
        <td><span class="flabel">Guardian's Name:</span> {{ $sheet->guardian_name ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Guardian's Home Address:</span> {{ $sheet->guardian_address ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Contact Number:</span> {{ $sheet->guardian_contact ?: '—' }}</td>
    </tr>
</table>

<div class="section-heading">C. Scholastic Data</div>
<table class="info">
    @foreach (['Tertiary' => 'tertiary', 'Secondary' => 'secondary', 'Primary' => 'primary'] as $label => $prefix)
        <tr>
            <td><span class="flabel">{{ $label }}:</span> {{ $sheet->{"{$prefix}_school"} ?: '—' }}</td>
            <td><span class="flabel">Address:</span> {{ $sheet->{"{$prefix}_address"} ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="flabel">Year Graduated:</span> {{ $sheet->{"{$prefix}_year_graduated"} ?: '—' }}</td>
            <td><span class="flabel">Honor/ Awards Received:</span> {{ $sheet->{"{$prefix}_honors"} ?: '—' }}</td>
        </tr>
    @endforeach
</table>

<div class="section-heading">D. Health Data</div>
<table class="info">
    <tr>
        <td><span class="flabel">Height:</span> {{ $sheet->height ? $sheet->height . ' cm' : '—' }}</td>
        <td><span class="flabel">Weight:</span> {{ $sheet->weight ? $sheet->weight . ' kg' : '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Blood Type:</span> {{ $sheet->blood_type ?: '—' }}</td>
        <td><span class="flabel">Health Problem:</span> {{ $sheet->health_problem ?: '—' }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="flabel">Vaccination Status:</span>
            @foreach (['Unvaccinated', 'First Dose', 'Second Dose', 'Booster'] as $option)
                {{ $sheet->vaccination_status === $option ? '☑' : '☐' }} {{ $option }}&nbsp;&nbsp;
            @endforeach
        </td>
    </tr>
    <tr>
        <td><span class="flabel">Type of Vaccine:</span> {{ $sheet->vaccine_type ?: '—' }}</td>
        <td><span class="flabel">Place of Vaccination:</span> {{ $sheet->vaccination_place ?: '—' }}</td>
    </tr>
    <tr>
        <td><span class="flabel">Date of Vaccination:</span> {{ optional($sheet->vaccination_date)->toFormattedDateString() ?: '—' }}</td>
        <td><span class="flabel">Health Insurance:</span> {{ $sheet->health_insurance_type === 'PhilHealth' ? '☑' : '☐' }} PhilHealth&nbsp;&nbsp;{{ $sheet->health_insurance_type === 'Private' ? '☑' : '☐' }} Private&nbsp;&nbsp;Specify: {{ $sheet->health_insurance_specify ?: '________' }}</td>
    </tr>
</table>

<div class="section-heading">E. OJT Work Experiences (for Fourth Year Students)</div>
<table class="experiences">
    <thead>
        <tr>
            <th>OJT Assignment</th>
            <th>Position</th>
            <th>Inclusive Date</th>
            <th>OJT Site Address</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($sheet->workExperiences as $exp)
            <tr>
                <td>{{ $exp->ojt_assignment }}</td>
                <td>{{ $exp->position }}</td>
                <td>{{ $exp->inclusive_start_date->toFormattedDateString() }} – {{ $exp->inclusive_end_date->toFormattedDateString() }}</td>
                <td>{{ $exp->ojt_site_address }}</td>
            </tr>
        @empty
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="signatures">
    <p style="font-size:9px; margin:0 0 4px;">I hereby affix my signature to attest the above data and statement are true and correct.</p>
    <div class="sig-line">Student-OJTee's Signature Over Printed Name</div>
    <div class="sig-date">Date</div>
    <div style="clear:both;"></div>
</div>
</div>

@include('pdf.partials.footer')

</body>
</html>
