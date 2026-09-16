{{--
    Shared by DarController::create() (new $dar, no id) and edit()
    (existing draft OR a Returned entry — see DarPolicy::update()).
    hours_rendered is intentionally NOT a field here — BR-3 computes it
    server-side from time_started/time_ended on save.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $dar->exists ? ($dar->status === 'Returned' ? 'Revise Report' : 'Edit Draft') : 'New Daily Report' }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">

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

        <form method="POST"
              action="{{ $dar->exists ? route('student.dar.update', $dar) : route('student.dar.store') }}"
              class="space-y-5 bg-white p-6 rounded-md shadow-sm border">
            @csrf
            @if ($dar->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Report Date</label>
                <input type="date" name="report_date" required
                       value="{{ old('report_date', optional($dar->report_date)->toDateString()) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Time Started</label>
                    <input type="time" name="time_started" required
                           value="{{ old('time_started', $dar->time_started) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Time Ended</label>
                    <input type="time" name="time_ended" required
                           value="{{ old('time_ended', $dar->time_ended) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>
            <p class="text-xs text-gray-500">Hours rendered are calculated automatically on save (BR-3).</p>

            <div>
                <label class="block text-sm font-medium text-gray-700">Activities</label>
                <textarea name="activities_text" rows="5" required
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('activities_text', $dar->activities_text) }}</textarea>
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
</x-app-layout>
