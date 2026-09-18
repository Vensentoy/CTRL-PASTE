{{--
    BR-8/BR-7: a WAR row appears here if EITHER its cycle1_id or cycle2_id
    matches this cycle — only the relevant week-pair for this specific
    cycle is shown per row, via WeeklyAccomplishmentReport::weekPairForCycle().
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            WAR Review — {{ $cycle->cycle_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($wars->isEmpty())
            <p class="text-sm text-gray-500">No WAR week-sections submitted into this cycle yet.</p>
        @endif

        @foreach ($wars as $war)
            @php $weeks = $war->weekPairForCycle($cycle->id) ?? []; @endphp
            <div class="border rounded-md">
                <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                    <div>
                        <span class="font-medium text-sm">{{ $war->student->fullName() }}</span>
                        <span class="text-xs text-gray-500">— {{ $war->month_period->format('F Y') }}</span>
                    </div>
                    <a href="{{ route('war.pdf', ['student' => $war->student_id, 'month' => $war->month_period->format('Y-m')]) }}"
                       target="_blank" class="text-xs text-blue-600 hover:underline">Print / PDF</a>
                </div>
                <div class="divide-y">
                    @foreach ($weeks as $week)
                        @php $status = $war->{"week{$week}_status"}; @endphp
                        <div class="p-4 text-sm">
                            <div class="flex flex-wrap items-start gap-x-3 gap-y-2 mb-2">
                                <span class="w-16 shrink-0 text-gray-500">Week {{ $week }}</span>
                                <ul class="flex-1 min-w-0 break-words list-disc list-inside space-y-0.5">
                                    @foreach ((array) $war->{"week{$week}_activities"} as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                                <span class="w-16 shrink-0 text-right">{{ number_format($war->{"week{$week}_hours"}, 2) }}h</span>
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                                    'bg-yellow-100 text-yellow-800' => $status === 'Pending',
                                    'bg-red-100 text-red-800' => in_array($status, ['Late', 'Returned']),
                                    'bg-green-100 text-green-800' => $status === 'Approved',
                                ])>{{ $status }}</span>
                            </div>

                            @if (in_array($status, ['Pending', 'Late']))
                                <form method="POST" action="{{ route('coordinator.war.review.act', $war) }}"
                                      class="flex flex-col sm:flex-row sm:items-start gap-3 mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="week" value="{{ $week }}">
                                    <textarea name="coordinator_comment" rows="1" placeholder="Comment (required to return)"
                                              class="flex-1 min-w-0 rounded-md border-gray-300 text-xs"></textarea>
                                    <div class="flex gap-2 shrink-0">
                                    <button type="submit" name="decision" value="approve"
                                            class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md">Approve</button>
                                    <button type="submit" name="decision" value="return"
                                            class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md">Return</button>
                                    </div>
                                </form>
                            @elseif ($status === 'Returned' && $war->{"week{$week}_comment"})
                                <p class="text-xs text-red-700">Your comment: {{ $war->{"week{$week}_comment"} }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
