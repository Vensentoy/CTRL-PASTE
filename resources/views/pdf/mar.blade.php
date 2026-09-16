<!DOCTYPE html>
{{--
    pdf-forms.md §4: the official layout describes the body as a
    two-column Date | List of Activities table spanning the whole
    month, labeled "Summary of Weekly Accomplishment Reports (Week
    1–4)". Known simplification, same category as pdf/war.blade.php's
    own documented note: the MonthlyAccomplishmentReport entity in
    data-model.md stores ONE free-text activities_text field per month,
    not a per-date list — there's no per-date data on the MAR row
    itself to populate that table with. Rendered here as a single text
    block instead. Flagging this as an open question rather than
    silently deriving a per-date breakdown from that student's DAR rows
    for the month, since data-model.md doesn't establish a relationship
    between MAR and DAR beyond both belonging to the same student.

    Signature block fields are always blank per workflows.md §6, same
    as the DAR/WAR templates.
--}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        .header-org { text-align: center; margin-bottom: 10px; }
        .header-org h2 { margin: 0; font-size: 13px; }
        .header-org p { margin: 0; font-size: 9px; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.info td { padding: 2px 4px; font-size: 9px; }
        table.info td.label { font-weight: bold; width: 18%; }
        .summary-heading { font-size: 10px; font-weight: bold; margin: 10px 0 3px; }
        .activities-block { border: 1px solid #333; padding: 8px; font-size: 9px; min-height: 120px; white-space: pre-wrap; }
        .remarks-block { border: 1px solid #333; padding: 8px; font-size: 9px; margin-top: 8px; min-height: 40px; }
        .grand-total { text-align: right; font-weight: bold; font-size: 11px; margin: 8px 0 16px; }
        .signatures { margin-top: 24px; }
        .sig-line { margin-top: 26px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
        .footer { text-align: center; font-size: 7.5px; color: #555; margin-top: 20px; }
    </style>
</head>
<body>

<div class="header-org">
    <h2>Lapu-Lapu City College</h2>
    <p>Don B. Benedicto Rd., Gun-ob, Lapu-Lapu City, 6015 &mdash; School Code: 7174</p>
    <p style="margin-top:6px; font-size:11px; font-weight:bold;">MONTHLY ACCOMPLISHMENT REPORT</p>
</div>

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
<div class="activities-block">{{ $mar->activities_text }}</div>

<div class="summary-heading">Remarks/Status</div>
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

<div class="footer">
    Website: www.llcc.edu.ph &mdash; Fb page: LLCC Public Information Office &mdash; Email: llccadmin@llcc.edu.ph
</div>

</body>
</html>
