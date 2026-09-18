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

    Branding (official docx ground truth): letterhead banner + footer bar
    come from pdf.partials.letterhead / pdf.partials.footer; table header
    row #C5E0B3, date column #FFD966, per-date subtotal #D9D9D9, grand
    total band #BFBFBF.
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
        .col-date { width: 9%; } .col-time { width: 9%; } .col-hours { width: 8%; }
        .col-remarks { width: 12%; } .col-activities { width: 40%; }
        .date-cell { background: #FFD966; }
        .subtotal-row td { font-weight: bold; text-align: right; background: #D9D9D9; }
        .grand-total { text-align: right; font-weight: bold; font-size: 11px; margin: 8px 0 16px; background: #BFBFBF; padding: 4px 6px; }
        .printout ~ .printout { page-break-before: always; }
        .signatures { margin-top: 24px; }
        .sig-line { margin-top: 26px; border-top: 1px solid #333; width: 70%; padding-top: 2px; font-size: 8.5px; }
        .sig-date { float: right; width: 25%; border-top: 1px solid #333; margin-top: -14px; padding-top: 2px; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>

@foreach ($batches as $batch)
    @php $batchTotal = $grouper->batchTotalHours($batch); @endphp
    <div class="printout">
        @include('pdf.partials.letterhead')

        <div class="page-content">
        <p style="margin:0 0 8px; font-size:11px; font-weight:bold; text-align:center;">DAILY ACCOMPLISHMENT REPORT</p>

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
                <td class="label">Date:</td>
                <td>{{ $batch->first()->report_date->toFormattedDateString() }} &ndash; {{ $batch->last()->report_date->toFormattedDateString() }}</td>
            </tr>
            <tr>
                <td class="label">Student OJT Job Designation:</td><td>{{ $company?->job_designation ?? '—' }}</td>
                <td class="label">Daily Total Hours:</td><td>{{ number_format($batchTotal, 2) }}</td>
            </tr>
        </table>

        @foreach ($batch as $dar)
            @php
                // Multi-activity itemization (real-form ground truth): one
                // row per activity entry. The Date and Remarks/Status cells
                // appear once, on the first row, spanning all of this
                // date's entries; Time Started/Ended/Hours stay per-row.
                $entries = $dar->activities ?? [];
                $entryHours = $dar->activityEntryHours();
                $rowCount = max(count($entries), 1);
            @endphp
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
                    @forelse ($entries as $entry)
                        <tr>
                            @if ($loop->first)
                                <td class="date-cell" rowspan="{{ $rowCount }}">{{ $dar->report_date->toFormattedDateString() }}</td>
                            @endif
                            <td>{{ $entry['activity'] ?? '' }}</td>
                            <td>{{ isset($entry['time_started']) ? \Carbon\Carbon::parse($entry['time_started'])->format('g:i') : '' }}</td>
                            <td>{{ isset($entry['time_ended']) ? \Carbon\Carbon::parse($entry['time_ended'])->format('g:i') : '' }}</td>
                            <td>{{ number_format($entryHours[$loop->index] ?? 0, 2) }}</td>
                            @if ($loop->first)
                                <td rowspan="{{ $rowCount }}">{{ $dar->remarks_student }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td class="date-cell">{{ $dar->report_date->toFormattedDateString() }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>0.00</td>
                            <td>{{ $dar->remarks_student }}</td>
                        </tr>
                    @endforelse
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
        </div>

    </div>
@endforeach

{{-- Single occurrence: position:fixed repeats this on every batch page. --}}
@include('pdf.partials.footer')

</body>
</html>
