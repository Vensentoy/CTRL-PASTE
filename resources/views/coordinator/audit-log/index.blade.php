{{--
    BR-11/BR-14: $logs is already scoped server-side in
    AuditLogController::index() — this view never re-derives or trusts
    any user_id/coordinator filtering itself, it only renders what it's
    handed. See that controller's docblock for the exact scoping rule
    and the flagged reassignment edge case.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Audit Log
        </h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <p class="text-sm text-gray-500">
            Your own actions, plus submissions and events logged by students
            currently assigned to you. Append-only — nothing here can be
            edited or removed.
        </p>

        {{-- Action-type filter --}}
        <form method="GET" action="{{ route('coordinator.audit-log.index') }}" class="flex items-center gap-2">
            <label for="action_type" class="text-sm text-gray-600">Filter:</label>
            <select name="action_type" id="action_type"
                    class="rounded-md border-gray-300 text-sm"
                    onchange="this.form.submit()">
                <option value="">All actions</option>
                @foreach ($actionTypes as $type)
                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @if ($selectedType)
                <a href="{{ route('coordinator.audit-log.index') }}" class="text-xs text-blue-600 hover:underline">Clear</a>
            @endif
        </form>

        <div class="border rounded-md divide-y">
            @forelse ($logs as $log)
                @php
                    // No stored actor name on AuditLog itself (data-model.md
                    // keys it off user_id only) — resolved here at render
                    // time from whichever profile the underlying User has.
                    // 'System' covers the nullable user_id case
                    // (data-model.md: "some system events may not have one").
                    $actorName = $log->user?->student?->fullName()
                        ?? $log->user?->coordinator?->full_name
                        ?? 'System';

                    $badgeColors = [
                        'Login' => 'bg-gray-100 text-gray-700',
                        'Submit' => 'bg-blue-100 text-blue-800',
                        'Approve' => 'bg-green-100 text-green-800',
                        'Return' => 'bg-red-100 text-red-800',
                        'Update' => 'bg-yellow-100 text-yellow-800',
                        'AccountChange' => 'bg-purple-100 text-purple-800',
                    ];
                    $badgeClass = $badgeColors[$log->action_type] ?? 'bg-gray-100 text-gray-700';
                @endphp
                <div class="p-4 text-sm flex flex-wrap justify-between items-start gap-x-4 gap-y-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $log->action_type }}
                            </span>
                            <span class="font-medium break-words">{{ $actorName }}</span>
                        </div>
                        @if ($log->action_details)
                            <p class="text-xs text-gray-600 mt-1 break-words">{{ $log->action_details }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-gray-500 whitespace-nowrap shrink-0">
                        {{ $log->created_at->format('M j, Y g:i A') }}
                    </span>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">
                    No audit entries yet{{ $selectedType ? " for {$selectedType}" : '' }}.
                </p>
            @endforelse
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
