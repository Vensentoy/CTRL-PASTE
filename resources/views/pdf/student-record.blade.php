<!DOCTYPE html>
{{--
    blueprint.md §9 — Student Record Report. Same PDF conventions as
    pdf/progress-report.blade.php (not one of the four official signed
    forms in pdf-forms.md, so no signature block/exact layout to match) —
    DejaVu Sans, the same header-org block, the same footer.
--}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
        .header-org { text-align: center; margin-bottom: 10px; }
        .header-org h2 { margin: 0; font-size: 13px; }
        .header-org p { margin: 0; font-size: 9px; }
        .report-title { text-align: center; font-size: 11px; font-weight: bold; margin: 6px 0 14px; }
        .meta { text-align: center; font-size: 8.5px; color: #555; margin-bottom: 14px; }
        .section-title { font-size: 10px; font-weight: bold; margin: 14px 0 4px; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table th, table td { border: 1px solid #333; padding: 3px 5px; font-size: 8.5px; vertical-align: top; }
        table th { background: #eee; text-align: left; }
        .col-right { text-align: right; }
        .profile-table td { border: none; padding: 2px 5px; }
        .profile-table td.label { color: #555; width: 130px; }
        .footer { text-align: center; font-size: 7.5px; color: #555; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="header-org">
        <h2>Lapu-Lapu City College</h2>
        <p>Don B. Benedicto Rd., Gun-ob, Lapu-Lapu City, 6015 &mdash; School Code: 7174</p>
    </div>

    <div class="report-title">{{ $title }}</div>
    <div class="meta">Generated {{ now()->toFormattedDateString() }}</div>

    <div class="section-title">Profile</div>
    <table class="profile-table">
        <tr>
            <td class="label">Student ID</td><td>{{ $student->student_id_number }}</td>
            <td class="label">Course</td><td>{{ $student->course }}@if ($student->major) &mdash; {{ $student->major }}@endif</td>
        </tr>
        <tr>
            <td class="label">Year &amp; Section</td><td>{{ $student->year_section }}</td>
            <td class="label">OJT Window</td><td>{{ $student->ojt_start_date->toFormattedDateString() }} &ndash; {{ $student->ojt_completion_date->toFormattedDateString() }}</td>
        </tr>
        <tr>
            <td class="label">Hours</td><td>{{ number_format((float) $student->completed_hours, 1) }} / {{ number_format((float) $student->required_hours, 1) }}</td>
            <td class="label">Status</td><td>{{ $student->ojt_status }}</td>
        </tr>
    </table>

    <div class="section-title">Company Assignment History</div>
    <table>
        <thead><tr><th>Company</th><th>Designation</th><th>Start</th><th>End</th></tr></thead>
        <tbody>
            @forelse ($student->companyAssignments as $assignment)
                <tr>
                    <td>{{ $assignment->company_name }}</td>
                    <td>{{ $assignment->job_designation }}</td>
                    <td>{{ $assignment->start_date->toFormattedDateString() }}</td>
                    <td>{{ $assignment->end_date?->toFormattedDateString() ?? 'Active' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No company assignments on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Daily Accomplishment Reports</div>
    <table>
        <thead><tr><th>Date</th><th class="col-right">Hours</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($student->dailyAccomplishmentReports as $dar)
                <tr>
                    <td>{{ $dar->report_date->toFormattedDateString() }}</td>
                    <td class="col-right">{{ $dar->hours_rendered !== null ? number_format((float) $dar->hours_rendered, 2) : '—' }}</td>
                    <td>{{ $dar->status }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No DAR entries on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Weekly Accomplishment Reports</div>
    <table>
        <thead><tr><th>Month</th><th>Week 1</th><th>Week 2</th><th>Week 3</th><th>Week 4</th></tr></thead>
        <tbody>
            @forelse ($student->weeklyAccomplishmentReports as $war)
                <tr>
                    <td>{{ $war->month_period->format('F Y') }}</td>
                    <td>{{ $war->week1_status }}</td>
                    <td>{{ $war->week2_status }}</td>
                    <td>{{ $war->week3_status }}</td>
                    <td>{{ $war->week4_status }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No WAR entries on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Monthly Accomplishment Reports</div>
    <table>
        <thead><tr><th>Month</th><th class="col-right">Total Hours</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($student->monthlyAccomplishmentReports as $mar)
                <tr>
                    <td>{{ $mar->month_period->format('F Y') }}</td>
                    <td class="col-right">{{ number_format((float) $mar->monthly_total_hours, 2) }}</td>
                    <td>{{ $mar->status }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No MAR entries on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Approval History</div>
    <table>
        <thead><tr><th>When</th><th>Action</th><th>By</th><th>Details</th></tr></thead>
        <tbody>
            @forelse ($approvalHistory as $log)
                @php
                    $actorName = $log->user?->student?->fullName()
                        ?? $log->user?->coordinator?->full_name
                        ?? 'System';
                @endphp
                <tr>
                    <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $log->action_type }}</td>
                    <td>{{ $actorName }}</td>
                    <td>{{ $log->action_details }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No recorded activity yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Website: www.llcc.edu.ph &mdash; Fb page: LLCC Public Information Office &mdash; Email: llccadmin@llcc.edu.ph
    </div>

</body>
</html>
