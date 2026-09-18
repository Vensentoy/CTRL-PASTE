{{--
    Shared by DarController::create() (new $dar, no id) and edit()
    (existing draft OR a Returned entry — see DarPolicy::update()).
    hours_rendered is intentionally NOT a field here — BR-3 computes it
    server-side from the activities array on save (model mutator).

    Activities are a repeatable row group (one row per itemized activity,
    each with its own time pair) via Alpine.js — loaded globally by
    app.js, no new dependency. Rows serialize as
    activities[i][activity|time_started|time_ended] in the single form
    post; no new backend endpoints. Server validation (1–20 entries) is
    authoritative; the client-side row cap mirrors it for UX only.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $dar->exists ? ($dar->status === 'Returned' ? 'Revise Report' : 'Edit Draft') : 'New Daily Report' }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

        @if ($dar->status === 'Returned' && $dar->coordinator_comment)
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm mb-6">
                <strong>Coordinator comment:</strong> {{ $dar->coordinator_comment }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // Seeding Alpine's initial rows server-side: flashed old input
            // wins after a validation failure, otherwise the saved entries,
            // otherwise one blank row. Built in @php (not inline @json())
            // because Blade's directive parser truncates nested-array
            // expressions like this one.
            $initialEntries = old('activities', $dar->activities ?? [['activity' => '', 'time_started' => '', 'time_ended' => '']]);
        @endphp

        <form method="POST"
              action="{{ $dar->exists ? route('student.dar.update', $dar) : route('student.dar.store') }}"
              class="space-y-5 bg-white p-6 rounded-md shadow-sm border"
              x-data="darActivitiesForm()">
            @csrf
            @if ($dar->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Report Date</label>
                <input type="date" name="report_date" required
                       value="{{ old('report_date', optional($dar->report_date)->toDateString()) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">Activities</label>
                    <span class="text-xs text-gray-500" x-text="entries.length + ' / 20'"></span>
                </div>

                <template x-for="(entry, index) in entries" :key="index">
                    <div class="border rounded-md p-3 space-y-2 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500" x-text="'Activity ' + (index + 1)"></span>
                            <button type="button" @click="removeEntry(index)" x-show="entries.length > 1"
                                    class="text-xs text-red-600 hover:underline">Remove</button>
                        </div>
                        <textarea :name="`activities[${index}][activity]`" x-model="entry.activity" rows="2" required maxlength="1000"
                                  placeholder="What did you accomplish?"
                                  class="block w-full rounded-md border-gray-300 text-sm"></textarea>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Time Started</label>
                                <input type="time" :name="`activities[${index}][time_started]`" x-model="entry.time_started" required
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Time Ended</label>
                                <input type="time" :name="`activities[${index}][time_ended]`" x-model="entry.time_ended" required
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addEntry()" x-show="entries.length < 20"
                        class="px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                    + Add another activity
                </button>
                <p class="text-xs text-gray-500">Hours rendered are calculated automatically on save (BR-3).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Remarks (optional)</label>
                <textarea name="remarks_student" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('remarks_student', $dar->remarks_student) }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('student.dar.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md">
                    {{ $dar->status === 'Returned' ? 'Resubmit' : 'Save Draft' }}
                </button>
            </div>
        </form>
    </div>

    <script>
        function darActivitiesForm() {
            return {
                entries: @json($initialEntries),
                addEntry() {
                    if (this.entries.length < 20) {
                        this.entries.push({activity: '', time_started: '', time_ended: ''});
                    }
                },
                removeEntry(index) {
                    if (this.entries.length > 1) {
                        this.entries.splice(index, 1);
                    }
                },
            };
        }
    </script>
</x-app-layout>
