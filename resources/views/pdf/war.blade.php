<!DOCTYPE html>
{{--
    pdf-forms.md §3: ONE document, four WEEK sections — unlike DAR this is
    not chunked/batched, since a WAR row already IS one month's whole
    document (BR-8). Each week gets its own date-range subheading (see
    WeeklyAccomplishmentReport::weekDateRange() — computed, not stored)
    and its own "TOTAL HOURS" row, followed by one grand "TOTAL WEEKLY
    HOURS" row after all four.

    Known simplification: the physical form's per-week table has a Date
    column suggesting per-day rows within a week, but data-model.md
    stores only one aggregate activities/hours value per week for WAR
    (unlike DAR, which is genuinely per-date) — so each week renders as
    exactly one row, using the computed week date-range as
    Date Started/Date Ended.

    Weeks still in Draft print as "Not yet submitted" rather than being
    omitted, since the form's four sections are a fixed layout.

    Signature block fields are always blank per workflows.md §6, same as
    the DAR template.
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
        .week-heading { font-size: 10px; font-weight: bold; margin: 10px 0 3px; }
        table.activities { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.activities th, table.activities td {
            border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; vertical-align: top;
        }
        table.activities th { background: #eee; text-align: left; }
        .col-date { width: 10%; } .col-hours { width: 8%; }
        .col-remarks { width: 14%; } .col-activities { width: 40%; }
        .subtotal-row td { font-weight: bold; text-align: right; background: #f7f7f7; }
        .not-submitted { color: #888; font-style: italic; }
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
    <p style="margin-top:6px; font-size:11px; font-weight:bold;">WEEKLY ACCOMPLISHMENT REPORT</p>
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
        <td class="label">Date:</td><td>{{ $war->month_period->format('F Y') }}</td>
    </tr>
    <tr>
        <td class="label">Student OJT Job Designation:</td><td>{{ $company?->job_designation ?? '—' }}</td>
        <td class="label">Weekly Total Hours:</td><td>{{ number_format($totalHours, 2) }}</td>
    </tr>
</table>

@foreach ([1, 2, 3, 4] as $week)
    @php
        $status = $war->{"week{$week}_status"};
        [$rangeStart, $rangeEnd] = $war->weekDateRange($week);
    @endphp

    <div class="week-heading">
        WEEK {{ $week }} &mdash; {{ $rangeStart->toFormattedDateString() }} to {{ $rangeEnd->toFormattedDateString() }}
    </div>

    @if ($status === 'Draft')
        <table class="activities">
            <tbody>
                <tr>
                    <td class="not-submitted" colspan="6">Not yet submitted</td>
                </tr>
            </tbody>
        </table>
    @else
        <table class="activities">
            <thead>
                <tr>
                    <th class="col-activities">List of Activities Accomplished</th>
                    <th class="col-date">Date Started</th>
                    <th class="col-date">Date Ended</th>
                    <th class="col-hours">No. of Hours</th>
                    <th class="col-remarks">Remarks/Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $war->{"week{$week}_activities"} }}</td>
                    <td>{{ $rangeStart->toFormattedDateString() }}</td>
                    <td>{{ $rangeEnd->toFormattedDateString() }}</td>
                    <td>{{ number_format($war->{"week{$week}_hours"}, 2) }}</td>
                    <td>{{ $war->{"week{$week}_comment"} ?? $status }}</td>
                </tr>
                <tr class="subtotal-row">
                    <td colspan="3">TOTAL HOURS</td>
                    <td colspan="2">{{ number_format($war->{"week{$week}_hours"}, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endforeach

<div class="grand-total">TOTAL WEEKLY HOURS: {{ number_format($totalHours, 2) }}</div>

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
