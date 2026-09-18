{{--
    Workflows.md §4, applied to MAR. Unlike WAR review, each row here
    has exactly ONE status (data-model.md), so this mirrors
    coordinator/dar/review.blade.php's shape directly.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            MAR Review &mdash; {{ $cycle->cycle_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($mars->isEmpty())
            <p class="text-sm text-gray-500">No MARs submitted into this cycle yet.</p>
        @endif

        @foreach ($mars as $mar)
            <div class="border rounded-md">
                <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                    <div>
                        <span class="font-medium text-sm">{{ $mar->student->fullName() }}</span>
                        <span class="text-xs text-gray-500">&mdash; {{ $mar->month_period->format('F Y') }}</span>
                    </div>
                    <a href="{{ route('mar.pdf', ['student' => $mar->student_id, 'month' => $mar->month_period->format('Y-m')]) }}"
                       target="_blank" class="text-xs text-blue-600 hover:underline">Download PDF</a>
                </div>
                <div class="p-4 text-sm space-y-2">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span class="flex-1 min-w-0 break-words">{{ $mar->activities_text }}</span>
                        <span class="w-20 shrink-0 text-right">{{ number_format($mar->monthly_total_hours, 2) }}h</span>
                        <span @class([
                            'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                            'bg-yellow-100 text-yellow-800' => $mar->status === 'Pending',
                            'bg-red-100 text-red-800' => in_array($mar->status, ['Late', 'Returned']),
                            'bg-green-100 text-green-800' => $mar->status === 'Approved',
                        ])>{{ $mar->status }}</span>
                    </div>

                    @if (in_array($mar->status, ['Pending', 'Late']))
                        <form method="POST" action="{{ route('coordinator.mar.review.act', $mar) }}"
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
                    @elseif ($mar->status === 'Approved')
                        <p class="text-xs text-gray-400">
                            Reviewed {{ optional($mar->reviewed_at)->toFormattedDateString() }}
                        </p>
                    @elseif ($mar->status === 'Returned' && $mar->coordinator_comment)
                        <p class="text-xs text-red-700">Your comment: {{ $mar->coordinator_comment }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
