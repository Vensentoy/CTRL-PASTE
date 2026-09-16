<!DOCTYPE html>
{{--
    pdf-forms.md §2: five consecutive dates per printout, each date with
    its own activity table + per-date TOTAL HOURS row, then one grand
    TOTAL DAILY HOURS per batch. $batches is a Collection of up to-5-row
    chunks from DarPdfGrouper — one <div class="printout"> per batch,
    each starting a fresh page except the very first.

    Signature block fields are always blank per workflows.md §6 — this
    system never captures e-signatures. "COT OJT Adviser" is kept as a
    separate line from "Company Supervisor/Manager/OJT Coordinator" per
    pdf-forms.md's open question — do not collapse them into one line.
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
        table.activities { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.activities th, table.activities td {
            border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; vertical-align: top;
        }
        table.activities th { background: #eee; text-align: left; }
        .col-date { width: 9%; } .col-time { width: 9%; } .col-hours { width: 8%; }
        .col-remarks { width: 12%; } .col-activities { width: 40%; }
        .subtotal-row td { font-weight: bold; text-align: right; background: #f7f7f7; }
        .grand-total { text-align: right; font-weight: bold; font-size: 11px; margin: 8px 0 16px; }
        .printout { page-break-after: always; }
        .printout:last-child { page-break-after: avoid; }
        .signatures { margin-top: 24px; }
        .sig-line { margin-top: 26px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
        .footer { text-align: center; font-size: 7.5px; color: #555; margin-top: 20px; }
    </style>
</head>
<body>

@foreach ($batches as $batch)
    @php $batchTotal = $grouper->batchTotalHours($batch); @endphp
    <div class="printout">
        <div class="header-org">
            <h2>Lapu-Lapu City College</h2>
            <p>Don B. Benedicto Rd., Gun-ob, Lapu-Lapu City, 6015 &mdash; School Code: 7174</p>
            <p style="margin-top:6px; font-size:11px; font-weight:bold;">DAILY ACCOMPLISHMENT REPORT</p>
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
                <td class="label">Company's Name:</td><td>{{ $activeCompany?->company_name ?? '—' }}</td>
                <td class="label">Mobile Number:</td><td>{{ $activeCompany?->mobile_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Department Area:</td><td>{{ $activeCompany?->department_area ?? '—' }}</td>
                <td class="label">Date:</td>
                <td>{{ $batch->first()->report_date->toFormattedDateString() }} &ndash; {{ $batch->last()->report_date->toFormattedDateString() }}</td>
            </tr>
            <tr>
                <td class="label">Student OJT Job Designation:</td><td>{{ $activeCompany?->job_designation ?? '—' }}</td>
                <td class="label">Daily Total Hours:</td><td>{{ number_format($batchTotal, 2) }}</td>
            </tr>
        </table>

        @foreach ($batch as $dar)
            <table class="activities">
                <thead>
                    <tr>
                        <th class="col-date">Date</th>
                        <th class="col-activities">List of Activities Accomplished</th>
                        <th class="col-time">Time Started</th>
                        <th class="col-time">Time Ended</th>
                        <th class="col-hours">No. of Hours</th>
                        <th class="col-remarks">Remarks/Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $dar->report_date->toFormattedDateString() }}</td>
                        <td>{{ $dar->activities_text }}</td>
                        <td>{{ $dar->time_started }}</td>
                        <td>{{ $dar->time_ended }}</td>
                        <td>{{ number_format($dar->hours_rendered, 2) }}</td>
                        <td>{{ $dar->remarks_student }}</td>
                    </tr>
                    <tr class="subtotal-row">
                        <td colspan="5">TOTAL HOURS</td>
                        <td>{{ number_format($dar->hours_rendered, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <div class="grand-total">TOTAL DAILY HOURS: {{ number_format($batchTotal, 2) }}</div>

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
    </div>
@endforeach

</body>
</html>
