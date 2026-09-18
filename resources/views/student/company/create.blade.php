<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $activeCompany ? 'Record a Company Change' : 'Record My Company' }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if ($activeCompany)
            <div class="bg-gray-50 border rounded-md p-3 text-sm text-gray-600">
                You're currently assigned to <strong>{{ $activeCompany->company_name }}</strong>
                (since {{ $activeCompany->start_date->toFormattedDateString() }}).
                Submitting below closes that assignment and starts a new one —
                your full history is always kept, nothing is overwritten.
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.company.store') }}" class="border rounded-md p-4 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Department / Area</label>
                <input type="text" name="department_area" value="{{ old('department_area') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Job Designation</label>
                <input type="text" name="job_designation" value="{{ old('job_designation') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Company Mobile Number</label>
                <input type="text" name="mobile_number" value="{{ old('mobile_number') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ $activeCompany ? 'New Assignment Start Date' : 'Start Date' }}
                </label>
                <input type="date" name="start_date" value="{{ old('start_date') }}"
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                @if ($activeCompany)
                    <p class="text-xs text-gray-500 mt-1">
                        Your current assignment will be closed as of this date.
                    </p>
                @endif
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                    Submit
                </button>
                <a href="{{ route('student.company.index') }}"
                   class="px-4 py-2 border text-sm font-medium rounded-md">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
