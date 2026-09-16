<!DOCTYPE html>
{{--
    blueprint.md §9 — Student OJT Progress Report. NOT one of the four
    official LLCC forms in pdf-forms.md (Info Sheet/DAR/WAR/MAR), so
    there's no physically-signed layout to match field-for-field and no
    signature block — this reuses only the general PDF conventions
    already established for those forms (DejaVu Sans, the same header-
    org block, the same footer), per the user's explicit instruction
    this session.

    $students is always a collection, even for the single-student case
    (Coordinator\ReportController::progressPdf() passes collect([$student])) —
    one shared template for both the roster and single-student views, so
    there is exactly one layout to keep in sync with pdf-forms.md-style
    conventions, not two.
--}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        .header-org { text-align: center; margin-bottom: 10px; }
        .header-org h2 { margin: 0; font-size: 13px; }
        .header-org p { margin: 0; font-size: 9px; }
        .report-title { text-align: center; font-size: 11px; font-weight: bold; margin: 6px 0 14px; }
        .meta { text-align: center; font-size: 8.5px; color: #555; margin-bottom: 14px; }
        table.roster { width: 100%; border-collapse: collapse; }
        table.roster th, table.roster td {
            border: 1px solid #333; padding: 4px 5px; font-size: 8.5px; vertical-align: top;
        }
        table.roster th { background: #eee; text-align: left; }
        .col-hours, .col-pct { text-align: right; }
        .status-completed { font-weight: bold; }
        .footer { text-align: center; font-size: 7.5px; color: #555; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="header-org">
        <h2>Lapu-Lapu City College</h2>
        <p>Don B. Benedicto Rd., Gun-ob, Lapu-Lapu City, 6015 &mdash; School Code: 7174</p>
    </div>

    <div class="report-title">{{ $title }}</div>
    <div class="meta">Generated {{ now()->toFormattedDateString() }} &mdash; Coordinator: {{ $coordinator->full_name }}</div>

    <table class="roster">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Course</th>
                <th>Company</th>
                <th class="col-hours">Required Hrs</th>
                <th class="col-hours">Completed Hrs</th>
                <th class="col-hours">Remaining Hrs</th>
                <th class="col-pct">Completion %</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                @php
                    $company = $student->companyAssignments->first();
                    $required = (float) $student->required_hours;
                    $completed = (float) $student->completed_hours;
                    $remaining = max($required - $completed, 0);
                    $completionPct = $required > 0 ? round(($completed / $required) * 100, 1) : 0.0;
                @endphp
                <tr>
                    <td>{{ $student->student_id_number }}</td>
                    <td>{{ $student->fullName() }}</td>
                    <td>{{ $student->course }}@if ($student->major) &mdash; {{ $student->major }}@endif</td>
                    <td>{{ $company?->company_name ?? '—' }}</td>
                    <td class="col-hours">{{ number_format($required, 1) }}</td>
                    <td class="col-hours">{{ number_format($completed, 1) }}</td>
                    <td class="col-hours">{{ number_format($remaining, 1) }}</td>
                    <td class="col-pct">{{ $completionPct }}%</td>
                    <td class="{{ $student->ojt_status === 'Completed' ? 'status-completed' : '' }}">{{ $student->ojt_status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Website: www.llcc.edu.ph &mdash; Fb page: LLCC Public Information Office &mdash; Email: llccadmin@llcc.edu.ph
    </div>

</body>
</html>
