{{--
    MAR (data-model.md): one row per student per month, single status
    (unlike WAR's four week-sections) — this page is a hybrid of
    WarController::show()'s lazy-create-on-visit pattern and DAR's
    single-status edit/submit shape.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Monthly Accomplishment Report &mdash; {{ $mar->month_period->format('F Y') }}
            </h2>
            @if ($mar->status !== 'Draft')
                <a href="{{ route('mar.pdf', ['student' => $mar->student_id, 'month' => $mar->month_period->format('Y-m')]) }}"
                   target="_blank" class="text-xs text-blue-600 hover:underline">Print / PDF</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center gap-3">
            <span class="text-sm font-medium">Status:</span>
            <span @class([
                'px-2 py-0.5 rounded-full text-xs font-medium',
                'bg-gray-100 text-gray-600' => $mar->status === 'Draft',
                'bg-yellow-100 text-yellow-800' => $mar->status === 'Pending',
                'bg-red-100 text-red-800' => in_array($mar->status, ['Late', 'Returned']),
                'bg-green-100 text-green-800' => $mar->status === 'Approved',
            ])>{{ $mar->status }}</span>
        </div>

        @if ($mar->status === 'Returned' && $mar->coordinator_comment)
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">
                <strong>Coordinator comment:</strong> {{ $mar->coordinator_comment }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (in_array($mar->status, ['Draft', 'Returned']))
            <form method="POST" action="{{ route('student.mar.update', $mar) }}"
                  class="space-y-5 bg-white p-6 rounded-md shadow-sm border">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Activities (summary for the month)</label>
                    <textarea name="activities_text" rows="6" required
                              class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('activities_text', $mar->activities_text) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Monthly Total Hours</label>
                    <p class="mt-1 text-sm text-gray-900 font-medium">{{ number_format($computedHours, 2) }} hours</p>
                    <p class="text-xs text-gray-500 mt-1">
                        Auto-computed from this month's Weekly Accomplishment Report (Weeks 1&ndash;4).
                        Log or update your WAR hours to change this &mdash; it's recalculated every time
                        you save or submit the MAR.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Remarks (optional)</label>
                    <textarea name="remarks" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('remarks', $mar->remarks) }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md">
                        {{ $mar->status === 'Returned' ? 'Resubmit' : 'Save' }}
                    </button>
                </div>
            </form>

            @if ($mar->cycle_id === null)
                @if ($openCycles->isEmpty())
                    <p class="text-sm text-amber-600">Your coordinator hasn't opened a submission cycle yet &mdash; the MAR can't be submitted until one exists (BR-5).</p>
                @else
                    <form method="POST" action="{{ route('student.mar.submit', $mar) }}" class="flex items-center gap-3">
                        @csrf
                        <label for="cycle_id" class="text-sm font-medium">Submit this month's MAR into:</label>
                        <select name="cycle_id" id="cycle_id" required class="rounded-md border-gray-300 text-sm">
                            <option value="">Select a cycle&hellip;</option>
                            @foreach ($openCycles as $cycle)
                                <option value="{{ $cycle->id }}">
                                    {{ $cycle->cycle_name }}
                                    (deadline {{ $cycle->deadline_date->toFormattedDateString() }}{{ $cycle->isPastDeadline() ? ' — PAST DUE' : '' }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md">
                            Submit
                        </button>
                    </form>
                    <p class="text-xs text-gray-500">Typically submitted once the month's WAR (Week 1&ndash;4) is complete, though this isn't technically enforced.</p>
                @endif
            @endif
        @else
            <div class="bg-white p-6 rounded-md shadow-sm border space-y-3 text-sm">
                <p>{{ $mar->activities_text }}</p>
                <p class="text-gray-500">{{ number_format($mar->monthly_total_hours, 2) }} hours</p>
                @if ($mar->remarks)
                    <p class="text-gray-500">Remarks: {{ $mar->remarks }}</p>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
