{{--
    BR-1 (reassignment form below), BR-11 (StudentPolicy::view() already
    enforces that this coordinator currently owns this student before
    this page ever renders), BR-12 (read-only company history), workflows.md
    §5 step 3 (Archive banner/action), Coordinator-assisted password reset,
    a Company field + completion %/remaining hours added to the summary
    block, plus a Progress Report PDF download link (blueprint.md §9 —
    Student OJT Progress Report, "viewable from the student detail page").

    THIS SESSION — added a link to the new Student Record Report
    (blueprint.md §9, item 1), next to the existing Progress Report link,
    same section, since both are "viewable from the student detail page"
    per their respective specs.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $student->fullName() }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- One-time display of a freshly-generated temp password (this
             session's resetPassword() action). Only ever present in this
             single flash — never persisted anywhere in plaintext, never
             shown again after this page reload. Styled distinctly from
             the generic status banner above so it doesn't get missed or
             mistaken for a routine confirmation message. --}}
        @if (session('newTempPassword'))
            <div class="bg-indigo-50 border border-indigo-300 text-indigo-900 rounded-md p-3 text-sm">
                <p class="font-medium mb-1">New temporary password</p>
                <p class="font-mono text-base tracking-wide">{{ session('newTempPassword') }}</p>
                <p class="text-xs text-indigo-700 mt-1">
                    Share this with the student securely (in person or over a trusted channel) &mdash;
                    it will not be shown again after you leave this page. They'll be required to set
                    their own password on next login.
                </p>
            </div>
        @endif

        {{-- THIS SESSION: Company + completion %/remaining hours added
             alongside the fields already here, and a PDF download link
             for the same data (Coordinator\ReportController::progressPdf(),
             StudentPolicy::viewReports()) — blueprint.md §9's Student OJT
             Progress Report fields (name, course, company, required/
             completed/remaining hours, completion %, status) are now all
             present on this page in one place. --}}
        @php
            $activeCompany = $student->companyAssignments->firstWhere('end_date', null);
            $requiredHours = (float) $student->required_hours;
            $completedHours = (float) $student->completed_hours;
            $remainingHours = max($requiredHours - $completedHours, 0);
            $completionPct = $requiredHours > 0 ? round(($completedHours / $requiredHours) * 100, 1) : 0.0;
        @endphp
        <div class="border rounded-md p-4">
            <div class="flex justify-between items-start mb-3">
                <p class="text-xs text-gray-500 uppercase tracking-wide">OJT Progress Report</p>
                <span class="flex gap-3">
                    <a href="{{ route('coordinator.reports.student-record', $student) }}"
                       class="text-xs text-blue-600 hover:underline">Student Record Report</a>
                    <a href="{{ route('coordinator.reports.progress.pdf.student', $student) }}"
                       class="text-xs text-blue-600 hover:underline">Download PDF</a>
                </span>
            </div>
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
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Company</p>
                    <p>{{ $activeCompany?->company_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">OJT Window</p>
                    <p>{{ $student->ojt_start_date->toFormattedDateString() }} &ndash; {{ $student->ojt_completion_date->toFormattedDateString() }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Hours</p>
                    <p>
                        {{ number_format($completedHours, 1) }} / {{ number_format($requiredHours, 1) }}
                        ({{ number_format($remainingHours, 1) }} remaining)
                        @if ($student->user->status === 'Archived')
                            <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">Archived</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Completion</p>
                    <p>{{ $completionPct }}% &mdash; {{ $student->ojt_status }}</p>
                </div>
            </div>
        </div>

        {{-- Archived banner takes priority over the Completed banner below —
             an Archived record is terminal; see StudentController::archive()
             and reassign()'s Archived guard (this session). --}}
        @if ($student->user->status === 'Archived')
            <div class="border rounded-md p-4 bg-gray-50 border-gray-300">
                <p class="text-sm font-medium mb-1">Record is Archived</p>
                <p class="text-xs text-gray-600">
                    This student's OJT record has been finalized and archived by a Coordinator.
                    Archiving is terminal in this version of the system &mdash; there is no
                    un-archive action, so this record can no longer be reopened, reassigned,
                    or edited from here. Password reset is still available below, since account
                    access is separate from OJT workflow state.
                </p>
            </div>
        @elseif ($student->ojt_status === 'Completed')
            <div class="border rounded-md p-4 bg-amber-50 border-amber-200 space-y-3">
                <div>
                    <p class="text-sm font-medium mb-1">Record is Completed (BR-10)</p>
                    <p class="text-xs text-gray-600">
                        New DAR/WAR/MAR submissions are blocked while this student's record is marked
                        Completed. Reopening does not change their completed hours &mdash; if their hours
                        still meet or exceed the required total, the next Approved report will mark it
                        Completed again automatically.
                    </p>
                </div>
                <form method="POST" action="{{ route('coordinator.students.reopen', $student) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md">
                        Reopen Record
                    </button>
                </form>

                <div class="pt-3 border-t border-amber-200">
                    <p class="text-xs text-gray-600 mb-2">
                        Once this student's OJT is fully wrapped up (workflows.md &sect;5 step 3), finalize
                        and archive their record. <strong>This cannot be undone</strong> &mdash; archiving
                        is terminal, with no un-archive path in this version of the system.
                    </p>
                    <form method="POST" action="{{ route('coordinator.students.archive', $student) }}"
                          onsubmit="return confirm('Archive {{ $student->fullName() }}\'s record? This is permanent and cannot be undone.');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-gray-700 text-white text-sm font-medium rounded-md">
                            Finalize &amp; Archive Record
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">Company Assignment History</span>
            </div>
            @if ($student->companyAssignments->isEmpty())
                <p class="p-4 text-sm text-gray-500">No company assignments recorded yet.</p>
            @else
                <div class="divide-y">
                    @foreach ($student->companyAssignments as $assignment)
                        <div class="px-4 py-3 text-sm flex justify-between items-start">
                            <div>
                                <p class="font-medium">{{ $assignment->company_name }}</p>
                                @if ($assignment->job_designation)
                                    <p class="text-xs text-gray-500">{{ $assignment->job_designation }}</p>
                                @endif
                            </div>
                            <div class="text-right text-xs text-gray-500 whitespace-nowrap ml-4">
                                {{ $assignment->start_date->toFormattedDateString() }}
                                &ndash;
                                {{ $assignment->end_date?->toFormattedDateString() ?? 'Present' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Account access (this session). Deliberately NOT gated on
             Archived status — unlike reassignment below, password reset
             is an account-access action independent of OJT workflow
             state, so it stays available even for a finalized/archived
             record (see StudentController::resetPassword()'s docblock). --}}
        <div class="border rounded-md p-4">
            <p class="text-sm font-medium mb-2">Reset Password</p>
            <p class="text-xs text-gray-500 mb-3">
                This system has no email on file for students (username-only accounts), so there is
                no self-service "forgot password" flow. Generates a new temporary password, shown to
                you once above &mdash; the student will be required to set their own password on
                next login.
            </p>
            <form method="POST" action="{{ route('coordinator.students.reset-password', $student) }}"
                  onsubmit="return confirm('Reset {{ $student->fullName() }}\'s password? A new temporary password will be generated.');">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                    Reset Password
                </button>
            </form>
        </div>

        {{-- Reassignment: blocked entirely once Archived (decided this
             session — see StudentController::reassign()'s docblock for
             the reasoning). Previously this form was left reachable
             regardless of status; that was an open question, now
             resolved. --}}
        @if ($student->user->status === 'Archived')
            <div class="border rounded-md p-4 bg-gray-50">
                <p class="text-sm font-medium mb-1 text-gray-500">Reassign Coordinator</p>
                <p class="text-xs text-gray-500">
                    Unavailable &mdash; this record is Archived. Archiving is terminal, so there is no
                    more work for a coordinator (current or new) to do on it.
                </p>
            </div>
        @else
            <div class="border rounded-md p-4">
                <p class="text-sm font-medium mb-2">Reassign Coordinator</p>
                <p class="text-xs text-gray-500 mb-3">
                    Moves this student to a different coordinator's caseload (BR-1). Their DAR/WAR/MAR
                    history and hours are untouched &mdash; only the current assignment link changes.
                    You'll lose access to this student's page once the reassignment is saved.
                </p>
                <form method="POST" action="{{ route('coordinator.students.reassign', $student) }}" class="flex items-center gap-3">
                    @csrf
                    @method('PATCH')
                    <select name="coordinator_id" required class="rounded-md border-gray-300 text-sm">
                        <option value="">Select a coordinator&hellip;</option>
                        @foreach ($coordinators as $coordinator)
                            <option value="{{ $coordinator->id }}" @selected($coordinator->id === $student->coordinator_id)>
                                {{ $coordinator->full_name }}@if ($coordinator->id === $student->coordinator_id) (current)@endif
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md">
                        Reassign
                    </button>
                </form>
                @error('coordinator_id')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>
</x-app-layout>
