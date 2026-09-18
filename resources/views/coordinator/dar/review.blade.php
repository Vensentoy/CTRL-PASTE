{{--
    Workflows.md §4: one cycle at a time, reviewed per document, per
    student — the "Approve" / "Return" buttons below each act on exactly
    ONE DailyAccomplishmentReport row (BR-7), never the whole list.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Review — {{ $cycle->cycle_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <p class="text-sm text-gray-500">
            Coverage {{ $cycle->coverage_start_date->toFormattedDateString() }}
            – {{ $cycle->coverage_end_date->toFormattedDateString() }} ·
            Deadline {{ $cycle->deadline_date->toFormattedDateString() }}
        </p>

        @if ($dars->isEmpty())
            <p class="text-sm text-gray-500">No reports submitted into this cycle yet.</p>
        @endif

        @foreach ($dars->groupBy('student_id') as $studentDars)
            @php $student = $studentDars->first()->student; @endphp
            <div class="border rounded-md">
                <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                    <span class="font-medium text-sm">{{ $student->fullName() }}</span>
                    <a href="{{ route('dar.pdf', ['cycle' => $cycle->id, 'student' => $student->id]) }}"
                       class="text-xs text-blue-600 hover:underline">Download PDF</a>
                </div>
                <div class="divide-y">
                    @foreach ($studentDars as $dar)
                        <div class="p-4 text-sm">
                            <div class="flex flex-wrap items-start gap-x-3 gap-y-2 mb-2">
                                <span class="w-28 shrink-0 text-gray-500">{{ $dar->report_date->toFormattedDateString() }}</span>
                                <ul class="flex-1 min-w-0 break-words list-disc list-inside space-y-0.5">
                                    @foreach ($dar->activities ?? [] as $entry)
                                        <li>{{ $entry['activity'] ?? '—' }}
                                            <span class="text-gray-500">({{ $entry['time_started'] ?? '?' }}–{{ $entry['time_ended'] ?? '?' }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <span class="w-16 shrink-0 text-right">{{ number_format($dar->hours_rendered, 2) }}h</span>
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                                    'bg-yellow-100 text-yellow-800' => $dar->status === 'Pending',
                                    'bg-red-100 text-red-800' => in_array($dar->status, ['Late', 'Returned']),
                                    'bg-green-100 text-green-800' => $dar->status === 'Approved',
                                ])>{{ $dar->status }}</span>
                            </div>

                            @if (in_array($dar->status, ['Pending', 'Late']))
                                <form method="POST" action="{{ route('coordinator.dar.review.act', $dar) }}"
                                      class="flex flex-col sm:flex-row sm:items-start gap-3 mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="coordinator_comment" rows="1" placeholder="Comment (required to return)"
                                              class="flex-1 min-w-0 rounded-md border-gray-300 text-xs"></textarea>
                                    <div class="flex gap-2 shrink-0">
                                    <button type="submit" name="decision" value="approve"
                                            class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md">Approve</button>
                                    <button type="submit" name="decision" value="return"
                                            class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md">Return</button>
                                    </div>
                                </form>
                            @elseif ($dar->status === 'Approved')
                                <p class="text-xs text-gray-400">
                                    Reviewed {{ optional($dar->reviewed_at)->toFormattedDateString() }}
                                </p>
                            @elseif ($dar->status === 'Returned' && $dar->coordinator_comment)
                                <p class="text-xs text-red-700">Your comment: {{ $dar->coordinator_comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
