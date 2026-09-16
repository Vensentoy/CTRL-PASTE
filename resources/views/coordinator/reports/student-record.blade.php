{{--
    blueprint.md §9 — Student Record Report. Same field/section shape as
    the PDF (pdf/student-record.blade.php) so the on-screen and
    downloaded versions never drift, same discipline as the existing
    Progress Report pair.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Student Record Report &mdash; {{ $student->fullName() }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between items-center">
            <a href="{{ route('coordinator.students.show', $student) }}" class="text-sm text-blue-600 hover:underline">
                &larr; Back to student
            </a>
            <a href="{{ route('coordinator.reports.student-record.pdf', $student) }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                Download PDF
            </a>
        </div>

        {{-- Profile summary --}}
        <div class="border rounded-md p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide mb-3">Profile</p>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Student ID</p>
                    <p>{{ $student->student_id_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Course</p>
                    <p>{{ $student->course }}@if ($student->major) &mdash; {{ $student->major }}@endif</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Year &amp; Section</p>
                    <p>{{ $student->year_section }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">OJT Window</p>
                    <p>{{ $student->ojt_start_date->toFormattedDateString() }} &ndash; {{ $student->ojt_completion_date->toFormattedDateString() }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Hours</p>
                    <p>{{ number_format((float) $student->completed_hours, 1) }} / {{ number_format((float) $student->required_hours, 1) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $student->ojt_status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $student->ojt_status }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Company assignment history (BR-12) --}}
        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Company Assignment History</span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Company</th>
                        <th class="px-4 py-2">Designation</th>
                        <th class="px-4 py-2">Start</th>
                        <th class="px-4 py-2">End</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($student->companyAssignments as $assignment)
                        <tr>
                            <td class="px-4 py-2">{{ $assignment->company_name }}</td>
                            <td class="px-4 py-2">{{ $assignment->job_designation }}</td>
                            <td class="px-4 py-2">{{ $assignment->start_date->toFormattedDateString() }}</td>
                            <td class="px-4 py-2">{{ $assignment->end_date?->toFormattedDateString() ?? 'Active' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No company assignments on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Report history: DAR/WAR/MAR submission records --}}
        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Daily Accomplishment Reports</span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2 text-right">Hours</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($student->dailyAccomplishmentReports as $dar)
                        <tr>
                            <td class="px-4 py-2">{{ $dar->report_date->toFormattedDateString() }}</td>
                            <td class="px-4 py-2 text-right">{{ $dar->hours_rendered !== null ? number_format((float) $dar->hours_rendered, 2) : '—' }}</td>
                            <td class="px-4 py-2">{{ $dar->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">No DAR entries on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Weekly Accomplishment Reports</span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Month</th>
                        <th class="px-4 py-2">Week 1</th>
                        <th class="px-4 py-2">Week 2</th>
                        <th class="px-4 py-2">Week 3</th>
                        <th class="px-4 py-2">Week 4</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($student->weeklyAccomplishmentReports as $war)
                        <tr>
                            <td class="px-4 py-2">{{ $war->month_period->format('F Y') }}</td>
                            <td class="px-4 py-2">{{ $war->week1_status }}</td>
                            <td class="px-4 py-2">{{ $war->week2_status }}</td>
                            <td class="px-4 py-2">{{ $war->week3_status }}</td>
                            <td class="px-4 py-2">{{ $war->week4_status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No WAR entries on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Monthly Accomplishment Reports</span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Month</th>
                        <th class="px-4 py-2 text-right">Total Hours</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($student->monthlyAccomplishmentReports as $mar)
                        <tr>
                            <td class="px-4 py-2">{{ $mar->month_period->format('F Y') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format((float) $mar->monthly_total_hours, 2) }}</td>
                            <td class="px-4 py-2">{{ $mar->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">No MAR entries on record.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Approval history — pulled from AuditLog, see
             StudentRecordReportController::approvalHistory()'s docblock
             for exactly how this is matched (no direct FK on AuditLog
             to a student or document, flagged there and in
             PROJECT_STATE.md). --}}
        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Approval History</span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Action</th>
                        <th class="px-4 py-2">By</th>
                        <th class="px-4 py-2">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($approvalHistory as $log)
                        @php
                            // Same actor-name resolution as
                            // coordinator/audit-log/index.blade.php — no
                            // stored actor name on AuditLog itself.
                            $actorName = $log->user?->student?->fullName()
                                ?? $log->user?->coordinator?->full_name
                                ?? 'System';
                        @endphp
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at->toDayDateTimeString() }}</td>
                            <td class="px-4 py-2">{{ $log->action_type }}</td>
                            <td class="px-4 py-2">{{ $actorName }}</td>
                            <td class="px-4 py-2">{{ $log->action_details }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No recorded activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
