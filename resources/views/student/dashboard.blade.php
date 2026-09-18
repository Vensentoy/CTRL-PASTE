<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Welcome, {{ $student->given_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{--
            BR-10: once ojt_status flips to Completed, new submissions are
            blocked everywhere else in the app (Dar/War/MarController) —
            this banner exists purely to explain WHY, since a student
            hitting a blocked submit button with no context is confusing.
            Reopening is a Coordinator-only action (StudentController
            ::reopen()) — there is nothing for the student to click here.
        --}}
        @if ($student->ojt_status === 'Completed')
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-md p-4 text-sm">
                <p class="font-semibold">Your OJT is marked Completed 🎉</p>
                <p class="mt-1">
                    You've reached {{ number_format($student->completed_hours, 1) }} of your required
                    {{ number_format($student->required_hours, 1) }} hours. New DAR, WAR, and MAR
                    submissions are locked. If you need to submit additional reports, ask your
                    Coordinator to reopen your record.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Hours Progress</p>
                <p class="text-2xl font-semibold mt-1">
                    {{ number_format($student->completed_hours, 1) }} / {{ $student->required_hours }}
                </p>
                <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: {{ $hoursPercent }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($student->ojt_status === 'Completed')
                        Completed
                    @else
                        {{ number_format($hoursRemaining, 1) }} hours remaining
                    @endif
                </p>
            </div>

            <a href="{{ route('student.company.index') }}" class="border rounded-md p-4 block hover:bg-gray-50">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Company</p>
                @if ($activeCompany)
                    <p class="text-sm font-medium mt-1">{{ $activeCompany->company_name }}</p>
                    <p class="text-xs text-gray-500">{{ $activeCompany->job_designation }}</p>
                @else
                    <p class="text-sm text-gray-400 mt-1">No active assignment — tap to add</p>
                @endif
            </a>

            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Next Deadline</p>
                @if ($activeCycle)
                    <p class="text-sm font-medium mt-1">{{ $activeCycle->cycle_name }}</p>
                    <p class="text-xs text-gray-500">Due {{ $activeCycle->deadline_date->toFormattedDateString() }}</p>
                @else
                    <p class="text-sm text-gray-400 mt-1">No open cycle yet</p>
                @endif
            </div>
        </div>

        @if (! $student->informationSheet)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-3 text-sm flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center">
                <span>You haven't completed your OJT Information Sheet yet — this is a one-time onboarding step.</span>
                <a href="{{ route('student.information-sheet.create') }}" class="font-medium underline shrink-0">Fill it out</a>
            </div>
        @endif

        {{--
            BR-7: DAR/WAR/MAR are shown independently here — one badge
            per document type, never a combined status — matching how
            they're reviewed independently by the Coordinator. BR-6:
            "Missed" is a label computed only in the controller
            (activeCycleDocumentStatus()); it is never a stored value,
            same principle as "Late" itself — deadline-based, not
            activity-date-based.
        --}}
        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">This Cycle's Documents</span>
                @if ($activeCycle)
                    <span class="text-xs text-gray-500 ml-2">{{ $activeCycle->cycle_name }} — due {{ $activeCycle->deadline_date->toFormattedDateString() }}</span>
                @endif
            </div>
            <div class="p-4">
                @if (! $activeCycle)
                    <p class="text-sm text-gray-400">No open cycle yet — nothing to submit into right now.</p>
                @else
                    @php
                        $statusBadgeColors = [
                            'Draft' => 'bg-gray-100 text-gray-600',
                            'Pending' => 'bg-blue-100 text-blue-800',
                            'Late' => 'bg-orange-100 text-orange-800',
                            'Returned' => 'bg-red-100 text-red-800',
                            'Approved' => 'bg-green-100 text-green-800',
                            'Missed' => 'bg-red-100 text-red-900',
                        ];
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach (['dar' => 'Daily (DAR)', 'war' => 'Weekly (WAR)', 'mar' => 'Monthly (MAR)'] as $key => $label)
                            @php $docStatus = $cycleDocStatus[$key]; @endphp
                            <div class="flex items-center justify-between border rounded-md px-3 py-2">
                                <span class="text-sm">{{ $label }}</span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusBadgeColors[$docStatus] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $docStatus }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                <span class="font-medium text-sm">Daily Accomplishment Reports</span>
                <a href="{{ route('student.dar.index') }}" class="text-xs text-blue-600 hover:underline">View all</a>
            </div>
            <div class="p-4 grid grid-cols-3 sm:grid-cols-5 gap-4 text-sm">
                @foreach (['Draft', 'Pending', 'Late', 'Returned', 'Approved'] as $status)
                    <div>
                        <p class="text-xs text-gray-500">{{ $status }}</p>
                        <p class="text-lg font-semibold">{{ $darCounts[$status] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>
            @if ($recentDars->isNotEmpty())
                <div class="divide-y border-t">
                    @foreach ($recentDars as $dar)
                        <div class="px-4 py-2 text-sm flex justify-between">
                            <span>{{ $dar->report_date->toFormattedDateString() }}</span>
                            <span class="text-gray-500">{{ $dar->status }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ route('student.dar.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md text-center">Log a new DAR</a>
            <a href="{{ route('student.war.show') }}"
               class="px-4 py-2 border text-sm font-medium rounded-md text-center">This month's WAR</a>
            <a href="{{ route('student.mar.show') }}"
               class="px-4 py-2 border text-sm font-medium rounded-md text-center">This month's MAR</a>
            <a href="{{ route('student.information-sheet.show') }}"
               class="px-4 py-2 border text-sm font-medium rounded-md text-center">OJT Information Sheet</a>
            <a href="{{ route('student.company.index') }}"
               class="px-4 py-2 border text-sm font-medium rounded-md text-center">Company Assignment</a>
        </div>
    </div>
</x-app-layout>
