{{--
    BR-8: one document, four independently-tracked week-sections. Week 1–2
    submit as a pair into the month's first cycle, Week 3–4 into the
    second — each pair uses its own "Submit" form further down since
    they're on independent BR-7 review tracks.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:items-center">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                Weekly Accomplishment Report — {{ $war->month_period->format('F Y') }}
            </h2>
            @if ($war->overall_status !== 'Draft')
                <a href="{{ route('war.pdf', ['student' => $war->student_id, 'month' => $war->month_period->format('Y-m')]) }}"
                   target="_blank" class="text-xs text-blue-600 hover:underline py-1 shrink-0">Print / PDF</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @foreach ([1, 2, 3, 4] as $week)
            @php $status = $war->{"week{$week}_status"}; @endphp
            <div class="border rounded-md p-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-medium text-sm">Week {{ $week }}</span>
                    <span @class([
                        'px-2 py-0.5 rounded-full text-xs font-medium',
                        'bg-gray-100 text-gray-600' => $status === 'Draft',
                        'bg-yellow-100 text-yellow-800' => $status === 'Pending',
                        'bg-red-100 text-red-800' => in_array($status, ['Late', 'Returned']),
                        'bg-green-100 text-green-800' => $status === 'Approved',
                    ])>{{ $status }}</span>
                </div>

                @if (in_array($status, ['Draft', 'Returned']))
                    @if ($status === 'Returned' && $war->{"week{$week}_comment"})
                        <p class="text-xs text-red-700 mb-2">Coordinator's comment: {{ $war->{"week{$week}_comment"} }}</p>
                    @endif
                    {{--
                        One repeatable single-line input per activity line
                        (Alpine.js, loaded globally by app.js) — serializes
                        as activities[] in the single form post; no per-line
                        times, WAR carries one shared week range.
                    --}}
                    <form method="POST" action="{{ route('student.war.week.update', $war) }}" class="space-y-2"
                          x-data="{ lines: @json(old('activities', $war->{"week{$week}_activities"} ?? [''])) }">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="week" value="{{ $week }}">
                        <template x-for="(line, index) in lines" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`activities[${index}]`" x-model="lines[index]" required
                                       placeholder="Activity for Week {{ $week }}"
                                       class="flex-1 min-w-0 rounded-md border-gray-300 text-sm">
                                <button type="button" @click="lines.splice(index, 1)" x-show="lines.length > 1"
                                        class="text-red-600 hover:underline text-sm px-1 shrink-0" title="Remove line">&times;</button>
                            </div>
                        </template>
                        <button type="button" @click="lines.push('')"
                                class="px-3 py-1 border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50">
                            + Add line
                        </button>
                        <div class="flex items-center gap-3">
                            <input type="number" name="hours" step="0.01" min="0" required placeholder="Hours"
                                   value="{{ old('hours', $war->{"week{$week}_hours"}) }}"
                                   class="w-28 rounded-md border-gray-300 text-sm">
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md">
                                Save Week {{ $week }}
                            </button>
                        </div>
                    </form>
                @else
                    <ul class="text-sm text-gray-700 list-disc list-inside space-y-0.5">
                        @foreach ((array) $war->{"week{$week}_activities"} as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-gray-500 mt-1">{{ number_format($war->{"week{$week}_hours"}, 2) }}h</p>
                @endif
            </div>

            @if ($week === 2 || $week === 4)
                @php $slotFilled = $week === 2 ? $war->cycle1_id !== null : $war->cycle2_id !== null; @endphp
                @if (! $slotFilled)
                    <form method="POST" action="{{ route('student.war.submit', $war) }}" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 -mt-2 mb-2">
                        @csrf
                        <label class="text-xs text-gray-500 shrink-0">Submit Week {{ $week - 1 }}–{{ $week }} into:</label>
                        <select name="cycle_id" required class="rounded-md border-gray-300 text-xs w-full sm:w-auto min-w-0">
                            <option value="">Select a cycle…</option>
                            @foreach ($openCycles as $cycle)
                                <option value="{{ $cycle->id }}">{{ $cycle->cycle_name }} (due {{ $cycle->deadline_date->toFormattedDateString() }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-medium rounded-md text-center">Submit</button>
                    </form>
                @endif
            @endif
        @endforeach
    </div>
</x-app-layout>
