{{--
    Workflows.md §2/§3: shows Drafts (still editable, not yet linked to
    a cycle) separately from everything already submitted. The
    batch-submit form only ever lists Draft rows as checkboxes — a
    submitted row can't be resubmitted from here (BR-6/BR-7).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Daily Accomplishment Reports
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
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

        <div class="flex justify-between items-center">
            <h3 class="text-lg font-medium">Drafts</h3>
            <a href="{{ route('student.dar.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md">
                + New Entry
            </a>
        </div>

        @php $drafts = $dars->where('status', 'Draft')->where('cycle_id', null); @endphp

        @if ($drafts->isEmpty())
            <p class="text-sm text-gray-500">No drafts yet. Log today's activities with "+ New Entry".</p>
        @else
            {{--
                IMPORTANT: this form is intentionally NOT wrapped around the
                draft rows below. Each row has its own <form> for the Delete
                button, and HTML does not allow a <form> nested inside
                another <form> — the browser silently drops the inner one
                and closes the OUTER form early at the inner form's closing
                tag, which used to leave the "Submit Selected" button
                floating outside any form entirely (inert, no click
                behavior at all).

                Fix: this form only wraps the cycle dropdown + submit
                button. Each checkbox below uses the HTML5 form="..."
                attribute to associate itself with THIS form by id, even
                though it lives outside it in the DOM — so it still submits
                as part of dar_ids[] with no nesting involved.
            --}}
            <div class="space-y-4">
                <div class="border rounded-md divide-y">
                    @foreach ($drafts as $dar)
                        <label class="flex items-center gap-3 p-3 text-sm">
                            <input type="checkbox" name="dar_ids[]" value="{{ $dar->id }}" form="submit-drafts-form" class="rounded">
                            <span class="w-28 text-gray-500">{{ $dar->report_date->toFormattedDateString() }}</span>
                            <span class="flex-1 truncate">{{ $dar->activities_text }}</span>
                            <span class="w-16 text-right">{{ number_format($dar->hours_rendered, 2) }}h</span>
                            <a href="{{ route('student.dar.edit', $dar) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('student.dar.destroy', $dar) }}"
                                  onsubmit="return confirm('Delete this draft?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </label>
                    @endforeach
                </div>

                @if ($cycles->isEmpty())
                    <p class="text-sm text-amber-600">Your coordinator hasn't opened a submission cycle yet — drafts can't be submitted until one exists (BR-5).</p>
                @else
                    <form id="submit-drafts-form" method="POST" action="{{ route('student.dar.submit') }}" class="flex items-center gap-3">
                        @csrf
                        <label for="cycle_id" class="text-sm font-medium">Submit checked drafts into:</label>
                        <select name="cycle_id" id="cycle_id" class="rounded-md border-gray-300 text-sm">
                            @foreach ($cycles as $cycle)
                                <option value="{{ $cycle->id }}">
                                    {{ $cycle->cycle_name }}
                                    (deadline {{ $cycle->deadline_date->toFormattedDateString() }}{{ $cycle->isPastDeadline() ? ' — PAST DUE' : '' }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md">
                            Submit Selected
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <div>
            <h3 class="text-lg font-medium mb-3">Submitted</h3>
            @php $submitted = $dars->whereNotNull('cycle_id'); @endphp

            @if ($submitted->isEmpty())
                <p class="text-sm text-gray-500">Nothing submitted yet.</p>
            @else
                <div class="border rounded-md divide-y">
                    @foreach ($submitted as $dar)
                        <div class="flex items-center gap-3 p-3 text-sm">
                            <span class="w-28 text-gray-500">{{ $dar->report_date->toFormattedDateString() }}</span>
                            <span class="flex-1 truncate">{{ $dar->activities_text }}</span>
                            <span class="w-16 text-right">{{ number_format($dar->hours_rendered, 2) }}h</span>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-medium',
                                'bg-yellow-100 text-yellow-800' => $dar->status === 'Pending',
                                'bg-red-100 text-red-800' => in_array($dar->status, ['Late', 'Returned']),
                                'bg-green-100 text-green-800' => $dar->status === 'Approved',
                            ])>{{ $dar->status }}</span>

                            @if ($dar->status === 'Returned')
                                <a href="{{ route('student.dar.edit', $dar) }}" class="text-blue-600 hover:underline">Revise &amp; Resubmit</a>
                            @endif
                        </div>
                        @if ($dar->status === 'Returned' && $dar->coordinator_comment)
                            <p class="px-3 pb-3 text-xs text-red-700">Coordinator comment: {{ $dar->coordinator_comment }}</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>