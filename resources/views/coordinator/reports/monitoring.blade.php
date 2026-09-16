{{--
    blueprint.md §9 — Submission Monitoring Report. "Submitted" is a
    derived total (Pending+Approved+Returned+Late), not a sixth status —
    see Coordinator\ReportController::monitoring()'s docblock for why
    that's always accurate for the rows this query can even see.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Submission Monitoring &mdash; {{ $cycle->cycle_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <p class="text-sm text-gray-500">
            Coverage {{ $cycle->coverage_start_date->toFormattedDateString() }}
            &ndash; {{ $cycle->coverage_end_date->toFormattedDateString() }}
            &middot; Deadline {{ $cycle->deadline_date->toFormattedDateString() }}
        </p>

        <div class="border rounded-md overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-2">Student</th>
                        <th class="px-4 py-2 text-right">Submitted</th>
                        <th class="px-4 py-2 text-right">Pending</th>
                        <th class="px-4 py-2 text-right">Approved</th>
                        <th class="px-4 py-2 text-right">Returned</th>
                        <th class="px-4 py-2 text-right">Late</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($students as $student)
                        @php $row = $counts[$student->id]; @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('coordinator.students.show', $student) }}" class="text-blue-600 hover:underline">
                                    {{ $student->fullName() }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-right">{{ $row['submitted'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $row['pending'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $row['approved'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $row['returned'] }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($row['late'] > 0)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $row['late'] }}</span>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No students currently assigned to you.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400">
            Counts combine DAR, WAR week-sections, and MAR submitted into this specific cycle (BR-6/BR-7/BR-8).
            "Submitted" is Pending + Approved + Returned + Late combined, not a separate status of its own.
        </p>
    </div>
</x-app-layout>
