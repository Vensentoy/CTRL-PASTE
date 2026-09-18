<!DOCTYPE html>
{{--
    pdf-forms.md §3: ONE document, four WEEK sections — unlike DAR this is
    not chunked/batched, since a WAR row already IS one month's whole
    document (BR-8). Each week gets its own date-range subheading (see
    WeeklyAccomplishmentReport::weekDateRange() — computed, not stored)
    and its own "TOTAL HOURS" row, followed by one grand "TOTAL WEEKLY
    HOURS" row after all four.

    Multi-line itemization (real-form ground truth): each week renders one
    row per activity line. The gold Week cell, Date Started, Date Ended,
    No. of Hours, and Remarks/Status appear once, on the first line's row,
    spanning all of that week's lines — only the activity text repeats.
    Date ranges stay computed (weekDateRange()), not stored.

    Weeks still in Draft print as "Not yet submitted" rather than being
    omitted, since the form's four sections are a fixed layout.

    Signature block fields are always blank per workflows.md §6, same as
    the DAR template.

    Branding (official docx ground truth): letterhead banner + footer bar
    come from pdf.partials.letterhead / pdf.partials.footer; table header
    row #C5E0B3, week column #FFD966, per-week subtotal #D9D9D9, grand
    total band #BFBFBF. The gold Week cell is the table's first column
    (matching the official form's merged week column); its content is the
    computed week date-range, not stored data.
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
        table.activities { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.activities th, table.activities td {
            border: 1px solid #333; padding: 3px 4px; font-size: 8.5px; vertical-align: top;
        }
        table.activities th { background: #C5E0B3; text-align: left; }
        .col-week { width: 16%; background: #FFD966; font-weight: bold; }
        .col-date { width: 10%; } .col-hours { width: 8%; }
        .col-remarks { width: 14%; } .col-activities { width: 40%; }
        .subtotal-row td { font-weight: bold; text-align: right; background: #D9D9D9; }
        .not-submitted { color: #888; font-style: italic; }
        .grand-total { text-align: right; font-weight: bold; font-size: 11px; margin: 8px 0 16px; background: #BFBFBF; padding: 4px 6px; }
        .signatures { margin-top: 24px; }
        .sig-line { margin-top: 26px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>

@include('pdf.partials.letterhead')

<div class="page-content">
<p style="margin:0 0 8px; font-size:11px; font-weight:bold; text-align:center;">WEEKLY ACCOMPLISHMENT REPORT</p>

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

    @if ($status === 'Draft')
        <table class="activities">
            <thead>
                <tr>
                    <th class="col-week">Week</th>
                    <th class="col-activities">List of Activities Accomplished</th>
                    <th class="col-date">Date Started</th>
                    <th class="col-date">Date Ended</th>
                    <th class="col-hours">No. of Hours</th>
                    <th class="col-remarks">Remarks/Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="col-week">WEEK {{ $week }} &mdash; {{ $rangeStart->toFormattedDateString() }} to {{ $rangeEnd->toFormattedDateString() }}</td>
                    <td class="not-submitted" colspan="5">Not yet submitted</td>
                </tr>
            </tbody>
        </table>
    @else
        @php
            $lines = (array) $war->{"week{$week}_activities"};
            $lineCount = max(count($lines), 1);
        @endphp
        <table class="activities">
            <thead>
                <tr>
                    <th class="col-week">Week</th>
                    <th class="col-activities">List of Activities Accomplished</th>
                    <th class="col-date">Date Started</th>
                    <th class="col-date">Date Ended</th>
                    <th class="col-hours">No. of Hours</th>
                    <th class="col-remarks">Remarks/Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        @if ($loop->first)
                            <td class="col-week" rowspan="{{ $lineCount }}">WEEK {{ $week }} &mdash; {{ $rangeStart->toFormattedDateString() }} to {{ $rangeEnd->toFormattedDateString() }}</td>
                        @endif
                        <td>{{ $line }}</td>
                        @if ($loop->first)
                            <td rowspan="{{ $lineCount }}">{{ $rangeStart->toFormattedDateString() }}</td>
                            <td rowspan="{{ $lineCount }}">{{ $rangeEnd->toFormattedDateString() }}</td>
                            <td rowspan="{{ $lineCount }}">{{ number_format($war->{"week{$week}_hours"}, 2) }}</td>
                            <td rowspan="{{ $lineCount }}">{{ $war->{"week{$week}_comment"} ?? $status }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td class="col-week">WEEK {{ $week }} &mdash; {{ $rangeStart->toFormattedDateString() }} to {{ $rangeEnd->toFormattedDateString() }}</td>
                        <td></td>
                        <td>{{ $rangeStart->toFormattedDateString() }}</td>
                        <td>{{ $rangeEnd->toFormattedDateString() }}</td>
                        <td>{{ number_format($war->{"week{$week}_hours"}, 2) }}</td>
                        <td>{{ $war->{"week{$week}_comment"} ?? $status }}</td>
                    </tr>
                @endforelse
                <tr class="subtotal-row">
                    <td colspan="4">TOTAL HOURS</td>
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
</div>

@include('pdf.partials.footer')

</body>
</html>
