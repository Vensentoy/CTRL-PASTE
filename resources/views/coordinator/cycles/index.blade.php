{{--
    BR-5: this list plus the create form below it are the ONLY place in
    the running app that produces a SubmissionCycle — DevTestSeeder is
    dev-only and explicitly not a precedent for production behavior.
    THIS SESSION: added a "Monitoring Report" link per cycle, alongside
    the existing per-document review links (blueprint.md §9 — Submission
    Monitoring Report).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Submission Cycles
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('coordinator.cycles.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">+ New Cycle</a>
        </div>

        <div class="border rounded-md divide-y">
            @forelse ($cycles as $cycle)
                <div class="p-4 text-sm flex justify-between items-center">
                    <div>
                        <p class="font-medium">{{ $cycle->cycle_name }}</p>
                        <p class="text-xs text-gray-500">
                            Coverage {{ $cycle->coverage_start_date->toFormattedDateString() }}
                            – {{ $cycle->coverage_end_date->toFormattedDateString() }}
                            · Deadline {{ $cycle->deadline_date->toFormattedDateString() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-gray-500">{{ $cycle->daily_accomplishment_reports_count }} DAR(s)</span>
                        <a href="{{ route('coordinator.dar.review', $cycle) }}"
                           class="text-blue-600 hover:underline text-xs">DAR Review</a>
                        <a href="{{ route('coordinator.war.review', $cycle) }}"
                           class="text-blue-600 hover:underline text-xs">WAR Review</a>
                        <a href="{{ route('coordinator.mar.review', $cycle) }}"
                           class="text-blue-600 hover:underline text-xs">MAR Review</a>
                        <a href="{{ route('coordinator.reports.monitoring', $cycle) }}"
                           class="text-blue-600 hover:underline text-xs">Monitoring Report</a>
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">No cycles created yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
