<!DOCTYPE html>
{{--
    pdf-forms.md §4: the official layout describes the body as a
    two-column Date | List of Activities table spanning the whole
    month, labeled "Summary of Weekly Accomplishment Reports (Week
    1–4)". Known simplification, same category as pdf/war.blade.php's
    own documented note: the MonthlyAccomplishmentReport entity in
    data-model.md stores ONE free-text activities_text field per month,
    not a per-date list — there's no per-date data on the MAR row
    itself to populate that table with. The table shell below is
    structural/branding only: a single body row whose gold Date cell
    carries the month label (already on the row) and whose right cell
    holds the activities_text blob — no per-date content is fabricated.
    Do not derive per-date rows from DAR here, since data-model.md
    doesn't establish a relationship between MAR and DAR beyond both
    belonging to the same student.

    Branding (official docx ground truth): letterhead banner + footer bar
    come from pdf.partials.letterhead / pdf.partials.footer; table header
    row #C5E0B3, date column #FFD966, Remarks/Status band #C5E0B3. MAR has
    no subtotal rows (single continuous table), so the two grays from the
    brand system don't apply here.

    Signature block fields are always blank per workflows.md §6, same
    as the DAR/WAR templates.
--}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        .page-content { padding: 20px 30px 40px; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.info td { padding: 2px 4px; font-size: 9px; }
        table.info td.label { font-weight: bold; width: 18%; }
        .summary-heading { font-size: 10px; font-weight: bold; margin: 10px 0 3px; }
        table.activities { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.activities th, table.activities td {
            border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; vertical-align: top;
        }
        table.activities th { background: #C5E0B3; text-align: left; }
        .col-date { width: 14%; background: #FFD966; font-weight: bold; }
        .activities-cell { white-space: pre-wrap; min-height: 120px; }
        .remarks-heading { font-size: 10px; font-weight: bold; margin: 10px 0 0; background: #C5E0B3; border: 1px solid #333; border-bottom: none; padding: 3px 4px; }
        .remarks-block { border: 1px solid #333; padding: 8px; font-size: 9px; min-height: 40px; }
        .grand-total { text-align: right; font-weight: bold; font-size: 11px; margin: 8px 0 16px; }
        .signatures { margin-top: 24px; }
        .sig-line { margin-top: 26px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>

@include('pdf.partials.letterhead')

<div class="page-content">
<p style="margin:0 0 8px; font-size:11px; font-weight:bold; text-align:center;">MONTHLY ACCOMPLISHMENT REPORT</p>

<table class="info">
    <tr>
        <td class="label">Student's Name:</td><td>{{ $student->fullName() }}</td>
        <td class="label">Course:</td><td>BS IN INDUSTRIAL TECHNOLOGY</td>
    </tr>
    <tr>
        <td class="label">Year &amp; Section:</td><td>{{ $student->year_section }}</td>
        <td class="label">Major:</td><td>{{ $student->major }}</td>
    </tr>
    <tr>
        <td class="label">Company's Name:</td><td>{{ $company?->company_name ?? '—' }}</td>
        <td class="label">Mobile Number:</td><td>{{ $company?->mobile_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Department Area:</td><td>{{ $company?->department_area ?? '—' }}</td>
        <td class="label">Month:</td><td>{{ $mar->month_period->format('F Y') }}</td>
    </tr>
    <tr>
        <td class="label">Student OJT Job Designation:</td><td>{{ $company?->job_designation ?? '—' }}</td>
        <td class="label">Monthly Total Hours:</td><td>{{ number_format($mar->monthly_total_hours, 2) }}</td>
    </tr>
</table>

<div class="summary-heading">Note: Summary of Weekly Accomplishment Reports (Week 1&ndash;4)</div>
<table class="activities">
    <thead>
        <tr>
            <th class="col-date">Date</th>
            <th>List of Activities Accomplished</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-date">{{ $mar->month_period->format('F Y') }}</td>
            <td class="activities-cell">{{ $mar->activities_text }}</td>
        </tr>
    </tbody>
</table>

<div class="remarks-heading">Remarks/Status</div>
<div class="remarks-block">{{ $mar->remarks ?? '—' }}</div>

<div class="grand-total">MONTHLY TOTAL HOURS: {{ number_format($mar->monthly_total_hours, 2) }}</div>

<div class="signatures">
    <div class="sig-line">Company Supervisor/Manager/OJT Coordinator's Signature over Printed Name</div>
    <div class="sig-date">Date</div>
    <div style="clear:both;"></div>

    <div class="sig-line">COT OJT Adviser's Signature over Printed Name</div>
    <div class="sig-date">Date</div>
    <div style="clear:both;"></div>

    <div class="sig-line">College Dean's Signature over Printed Name (Approved by)</div>
    <div class="sig-date">Date</div>
    <div style="clear:both;"></div>
</div>
</div>

@include('pdf.partials.footer')

</body>
</html>
