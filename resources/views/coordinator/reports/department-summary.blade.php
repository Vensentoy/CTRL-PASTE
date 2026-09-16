{{--
    blueprint.md §9 — Department Summary Report. "Department" here means
    this coordinator's own roster (BR-11) — no super-admin tier exists
    (roles-and-permissions.md). On-screen only this session; see
    PROJECT_STATE.md for why no PDF variant was added alongside it.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Department Summary Report
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <p class="text-sm text-gray-500">
            Cohort-wide rollup for {{ $coordinator->full_name }}'s students ({{ $students->count() }} total).
        </p>

        {{-- Reused from CohortAggregator — same numbers as the dashboard
             tiles, guaranteed to agree (see
             DepartmentSummaryReportController's docblock). --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Students</p>
                <p class="text-2xl font-semibold mt-1">{{ $students->count() }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Completion Rate</p>
                <p class="text-2xl font-semibold mt-1">{{ $completionRate }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $completedCount }}/{{ $students->count() }} completed</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Ongoing</p>
                <p class="text-2xl font-semibold mt-1">{{ $ongoingCount }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Overdue (BR-6)</p>
                <p class="text-2xl font-semibold mt-1">{{ $totalOverdue }}</p>
                <p class="text-xs text-gray-400 mt-0.5">now includes never-submitted WAR/MAR</p>
            </div>
        </div>

        {{-- Submission-status breakdown, all-time, per document type. --}}
        <div class="border rounded-md overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Document Type</th>
                        <th class="px-4 py-2 text-right">Pending</th>
                        <th class="px-4 py-2 text-right">Approved</th>
                        <th class="px-4 py-2 text-right">Returned</th>
                        <th class="px-4 py-2 text-right">Late</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ([
                        'dar' => 'Daily Accomplishment Report',
                        'war' => 'Weekly Accomplishment Report (week-sections)',
                        'mar' => 'Monthly Accomplishment Report',
                    ] as $key => $label)
                        <tr>
                            <td class="px-4 py-2">{{ $label }}</td>
                            <td class="px-4 py-2 text-right">{{ $statusBreakdown[$key]['Pending'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $statusBreakdown[$key]['Approved'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $statusBreakdown[$key]['Returned'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $statusBreakdown[$key]['Late'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border rounded-md overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Student</th>
                        <th class="px-4 py-2 text-right">Completed Hrs</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Overdue</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($students as $student)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('coordinator.students.show', $student) }}" class="text-blue-600 hover:underline">
                                    {{ $student->fullName() }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-right">{{ number_format((float) $student->completed_hours, 1) }}/{{ number_format((float) $student->required_hours, 1) }}</td>
                            <td class="px-4 py-2">{{ $student->ojt_status }}</td>
                            <td class="px-4 py-2 text-right">{{ $student->overdue_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No students currently assigned to you.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
