<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Company Assignment
        </h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="border rounded-md p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Current Assignment</p>
            @if ($activeCompany)
                <p class="text-lg font-semibold mt-1">{{ $activeCompany->company_name }}</p>
                <p class="text-sm text-gray-600">{{ $activeCompany->job_designation }}</p>
                @if ($activeCompany->department_area)
                    <p class="text-xs text-gray-500">{{ $activeCompany->department_area }}</p>
                @endif
                @if ($activeCompany->mobile_number)
                    <p class="text-xs text-gray-500">{{ $activeCompany->mobile_number }}</p>
                @endif
                <p class="text-xs text-gray-500 mt-1">
                    Since {{ $activeCompany->start_date->toFormattedDateString() }}
                </p>
            @else
                <p class="text-sm text-gray-400 mt-1">No active company assignment on file.</p>
            @endif

            <a href="{{ route('student.company.create') }}"
               class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                {{ $activeCompany ? 'Record a company change' : 'Record my company' }}
            </a>
        </div>

        <div class="border rounded-md">
            <div class="bg-gray-50 px-4 py-2">
                <span class="font-medium text-sm">History</span>
            </div>

            @if ($history->isEmpty())
                <p class="p-4 text-sm text-gray-500">No company assignments recorded yet.</p>
            @else
                <div class="divide-y">
                    @foreach ($history as $assignment)
                        <div class="px-4 py-3 text-sm flex justify-between items-start">
                            <div>
                                <p class="font-medium">{{ $assignment->company_name }}</p>
                                @if ($assignment->job_designation)
                                    <p class="text-xs text-gray-500">{{ $assignment->job_designation }}</p>
                                @endif
                            </div>
                            <div class="text-right text-xs text-gray-500 whitespace-nowrap ml-4">
                                {{ $assignment->start_date->toFormattedDateString() }}
                                –
                                {{ $assignment->end_date?->toFormattedDateString() ?? 'Present' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
