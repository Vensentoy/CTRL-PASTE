{{--
    blueprint.md §9 — Student OJT Progress Report, roster view. Same
    fields as the PDF (pdf/progress-report.blade.php) so the on-screen
    and downloaded versions never drift — this view computes them the
    same way inline per row, rather than importing PHP into the PDF's
    Blade file or vice versa.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Student OJT Progress Report
        </h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between items-center">
            <p class="text-sm text-gray-500">
                {{ $coordinator->full_name }}'s students &mdash; required/completed/remaining hours and completion status.
            </p>
            <a href="{{ route('coordinator.reports.progress.pdf') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                Download PDF
            </a>
        </div>

        <div class="border rounded-md overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Student</th>
                        <th class="px-4 py-2">Course</th>
                        <th class="px-4 py-2">Company</th>
                        <th class="px-4 py-2 text-right">Required</th>
                        <th class="px-4 py-2 text-right">Completed</th>
                        <th class="px-4 py-2 text-right">Remaining</th>
                        <th class="px-4 py-2 text-right">Completion %</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($students as $student)
                        @php
                            $company = $student->companyAssignments->first();
                            $required = (float) $student->required_hours;
                            $completed = (float) $student->completed_hours;
                            $remaining = max($required - $completed, 0);
                            $completionPct = $required > 0 ? round(($completed / $required) * 100, 1) : 0.0;
                        @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('coordinator.students.show', $student) }}" class="text-blue-600 hover:underline">
                                    {{ $student->fullName() }}
                                </a>
                                <p class="text-xs text-gray-500">{{ $student->student_id_number }}</p>
                            </td>
                            <td class="px-4 py-2">{{ $student->course }}@if ($student->major) &mdash; {{ $student->major }}@endif</td>
                            <td class="px-4 py-2">{{ $company?->company_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($required, 1) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($completed, 1) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($remaining, 1) }}</td>
                            <td class="px-4 py-2 text-right">{{ $completionPct }}%</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $student->ojt_status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $student->ojt_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">No students currently assigned to you.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
