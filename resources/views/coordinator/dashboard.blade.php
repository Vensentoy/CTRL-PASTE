<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Welcome, {{ $coordinator->full_name }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Cohort-wide summary row (this session). Students/Open Cycles
             kept from before; the three "Awaiting Review" tiles now split
             DAR/WAR/MAR instead of one combined DAR-only number, plus a
             new Overdue tile (BR-6, see DashboardController::
             attachOverdueCounts() for the exact, flagged scope of what
             counts as overdue here) and a completion-rate tile. --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Students</p>
                <p class="text-2xl font-semibold mt-1">{{ $students->count() }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Open Cycles</p>
                <p class="text-2xl font-semibold mt-1">{{ $openCycles->count() }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Overdue (BR-6)</p>
                <p class="text-2xl font-semibold mt-1">{{ $totalOverdue }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Completion Rate</p>
                <p class="text-2xl font-semibold mt-1">{{ $completionRate }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $completedCount }}/{{ $students->count() }} completed</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">DAR Awaiting Review</p>
                <p class="text-2xl font-semibold mt-1">{{ $totalPendingDar }}</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">WAR Awaiting Review</p>
                <p class="text-2xl font-semibold mt-1">{{ $totalPendingWar }}</p>
                <p class="text-xs text-gray-400 mt-0.5">counted per week-section (BR-7)</p>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">MAR Awaiting Review</p>
                <p class="text-2xl font-semibold mt-1">{{ $totalPendingMar }}</p>
            </div>
        </div>

        {{-- Charts (Chart.js, vendored locally as of this session — no
             build step, consistent with this app never having used
             Vue/JS frameworks anywhere else). Two charts: cohort
             completion split, and per-student pending review counts
             (DAR/WAR/MAR stacked) for the students with the most
             outstanding review work, so a coordinator can see at a
             glance who needs attention first.

             DECIDED THIS SESSION (was an open question last session):
             the jsdelivr CDN dependency was replaced with a locally
             vendored copy (public/vendor/chartjs/chart.umd.js) because
             this app is explicitly described as LAN-only
             (llcc-ojt-system skill's opening paragraph) — a coordinator's
             browser needing outbound internet just to render two charts
             contradicted that, while everything else on this dashboard
             (and the rest of the app) works fully offline. Chart.js 4.4.4,
             same version as before, just served from this app instead of
             a CDN; no other behavior change. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="border rounded-md p-4">
                <p class="text-sm font-medium mb-3">Cohort completion status</p>
                <div style="position: relative; height: 260px;">
                    <canvas id="completionChart"
                            role="img"
                            aria-label="Doughnut chart showing {{ $completedCount }} completed and {{ $ongoingCount }} ongoing students out of {{ $students->count() }} total.">
                        Completed: {{ $completedCount }}, Ongoing: {{ $ongoingCount }}.
                    </canvas>
                </div>
            </div>
            <div class="border rounded-md p-4">
                <p class="text-sm font-medium mb-3">Pending review by student (top 8)</p>
                <div style="position: relative; height: 260px;">
                    <canvas id="pendingChart"
                            role="img"
                            aria-label="Stacked bar chart of pending DAR, WAR, and MAR counts for the students with the most outstanding reviews.">
                        See the roster table below for exact per-student counts.
                    </canvas>
                </div>
            </div>
        </div>

        {{-- THIS SESSION (dashboard discoverability fix): a cycle drops
             out of "Open Submission Cycles" the moment its deadline
             passes, even if it still has Pending DAR/WAR/MAR items sitting
             in it with no other link anywhere on this page to reach them.
             This section is deliberately separate from -- not a replacement
             for -- the one below: that one answers "what can students still
             submit into," this one answers "what still needs a decision
             from me," and a cycle can appear in both. See
             CohortAggregator::cyclesNeedingReview(). --}}
        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                <span class="font-medium text-sm">Cycles Needing Review</span>
                <a href="{{ route('coordinator.cycles.index') }}" class="text-xs text-blue-600 hover:underline">View all cycles</a>
            </div>
            @if ($cyclesNeedingReview->isEmpty())
                <p class="p-4 text-sm text-gray-500">Nothing outstanding — every submitted DAR, WAR week, and MAR across all your cycles has been reviewed.</p>
            @else
                <div class="divide-y">
                    @foreach ($cyclesNeedingReview as $cycle)
                        <div class="px-4 py-2 text-sm flex justify-between items-center gap-3">
                            <span class="truncate">
                                {{ $cycle->cycle_name }}
                                @if ($cycle->deadline_date->isPast())
                                    <span class="text-amber-600">(past deadline)</span>
                                @endif
                            </span>
                            <span class="text-gray-500 whitespace-nowrap">
                                @if ($cycle->pending_dar_count) {{ $cycle->pending_dar_count }} DAR @endif
                                @if ($cycle->pending_war_count) · {{ $cycle->pending_war_count }} WAR @endif
                                @if ($cycle->pending_mar_count) · {{ $cycle->pending_mar_count }} MAR @endif
                            </span>
                            <span class="whitespace-nowrap">
                                @if ($cycle->pending_dar_count)
                                    <a href="{{ route('coordinator.dar.review', $cycle) }}" class="text-blue-600 hover:underline text-xs">DAR</a>
                                @endif
                                @if ($cycle->pending_war_count)
                                    <a href="{{ route('coordinator.war.review', $cycle) }}" class="text-blue-600 hover:underline text-xs">WAR</a>
                                @endif
                                @if ($cycle->pending_mar_count)
                                    <a href="{{ route('coordinator.mar.review', $cycle) }}" class="text-blue-600 hover:underline text-xs">MAR</a>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                <span class="font-medium text-sm">Open Submission Cycles</span>
                <a href="{{ route('coordinator.cycles.create') }}" class="text-xs text-blue-600 hover:underline">+ New cycle</a>
            </div>
            @if ($openCycles->isEmpty())
                <p class="p-4 text-sm text-gray-500">No open cycles. Create one so students have somewhere to submit into.</p>
            @else
                <div class="divide-y">
                    @foreach ($openCycles as $cycle)
                        <div class="px-4 py-2 text-sm flex justify-between items-center">
                            <span>{{ $cycle->cycle_name }}</span>
                            <span class="text-gray-500">Due {{ $cycle->deadline_date->toFormattedDateString() }}</span>
                            <a href="{{ route('coordinator.dar.review', $cycle) }}" class="text-blue-600 hover:underline text-xs">Review</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2 flex justify-between items-center">
                <span class="font-medium text-sm">My Students</span>
                <a href="{{ route('coordinator.students.index') }}" class="text-xs text-blue-600 hover:underline">View full roster</a>
            </div>
            <div class="divide-y">
                @foreach ($students as $student)
                    <a href="{{ route('coordinator.students.show', $student) }}"
                       class="px-4 py-2 text-sm flex justify-between items-center hover:bg-gray-50 gap-3">
                        <span class="truncate">{{ $student->fullName() }} <span class="text-gray-400">({{ $student->student_id_number }})</span></span>
                        <span class="text-gray-500 whitespace-nowrap">{{ number_format($student->completed_hours, 1) }}/{{ $student->required_hours }}h</span>
                        <span class="whitespace-nowrap">
                            @if ($student->pending_dar_count > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    {{ $student->pending_dar_count }} DAR
                                </span>
                            @endif
                            @if ($student->pending_war_count > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $student->pending_war_count }} WAR
                                </span>
                            @endif
                            @if ($student->pending_mar_count > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ $student->pending_mar_count }} MAR
                                </span>
                            @endif
                            @if ($student->overdue_count > 0)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    {{ $student->overdue_count }} overdue
                                </span>
                            @endif
                            @if ($student->pending_dar_count === 0 && $student->pending_war_count === 0 && $student->pending_mar_count === 0 && $student->overdue_count === 0)
                                <span class="text-xs text-gray-400">Up to date</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- blueprint.md §9 Reports & Exports links. Kept as a plain link
             row, same visual weight as the Audit Log link below it,
             rather than a heavier card — these are occasional-use
             destinations, not daily-glance data the way the
             tiles/charts above are. THIS SESSION added the Department
             Summary Report link (item 2, blueprint.md §9 closeout). --}}
        <div class="flex justify-end gap-4">
            <a href="{{ route('coordinator.reports.progress') }}" class="text-xs text-blue-600 hover:underline">
                Progress Report
            </a>
            <a href="{{ route('coordinator.reports.department-summary') }}" class="text-xs text-blue-600 hover:underline">
                Department Summary
            </a>
            <a href="{{ route('coordinator.reports.export-csv') }}" class="text-xs text-blue-600 hover:underline">
                Export Roster (CSV)
            </a>
            <a href="{{ route('coordinator.audit-log.index') }}" class="text-xs text-blue-600 hover:underline">
                View Audit Log
            </a>
        </div>
    </div>

    {{-- Precomputed here (instead of inline inside @json() below) because
         Blade's @json() directive naively explode(',', ...)'s whatever is
         inside its parentheses. An inline array literal with several
         'key' => value, pairs has top-level commas that directive can't
         handle, and it was corrupting the compiled PHP. Building the
         collection first and handing @json() a single bare variable
         (no top-level commas) avoids that entirely. --}}
    @php
        $pendingChartData = $students->map(fn ($s) => [
            'name' => $s->fullName(),
            'dar'  => $s->pending_dar_count,
            'war'  => $s->pending_war_count,
            'mar'  => $s->pending_mar_count,
        ])->sortByDesc(fn ($s) => $s['dar'] + $s['war'] + $s['mar'])->take(8)->values();
    @endphp

    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    <script>
        // Cohort completion doughnut — two-value split, colors chosen for
        // visual distinction, not tied to any other status color meaning
        // elsewhere in this app.
        new Chart(document.getElementById('completionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Ongoing'],
                datasets: [{
                    data: [{{ $completedCount }}, {{ $ongoingCount }}],
                    backgroundColor: ['#16a34a', '#d1d5db'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });

        // Top 8 students by total pending (DAR + WAR + MAR), stacked bar.
        // Computed server-side in the @php block above, so there's exactly
        // one source of truth (the controller's per-student counts) rather
        // than a second query.
        const pendingData = @json($pendingChartData);

        new Chart(document.getElementById('pendingChart'), {
            type: 'bar',
            data: {
                labels: pendingData.map(s => s.name),
                datasets: [
                    { label: 'DAR', data: pendingData.map(s => s.dar), backgroundColor: '#eda100' },
                    { label: 'WAR', data: pendingData.map(s => s.war), backgroundColor: '#2a78d6' },
                    { label: 'MAR', data: pendingData.map(s => s.mar), backgroundColor: '#4a3aa7' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, ticks: { autoSkip: false, maxRotation: 45 } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: { legend: { position: 'bottom' } },
            },
        });
    </script>
</x-app-layout>