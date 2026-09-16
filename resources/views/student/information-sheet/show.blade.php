{{--
    Read-only by design — this document has no edit path (data-model.md:
    one-time only, no status field). If a correction is ever needed,
    that's a Coordinator-side or manual DB action, not a student-facing
    form; not built here since nothing in the skill describes that flow.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            OJT Information Sheet
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-gray-50 border text-gray-600 rounded-md p-3 text-sm">
            Submitted {{ $sheet->signed_date->toFormattedDateString() }}. This form is one-time only and cannot be edited.
        </div>

        <div class="bg-white p-6 rounded-md shadow-sm border space-y-2">
            <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide mb-2">A. Personal Data</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div><dt class="text-gray-500">City Address</dt><dd>{{ $sheet->city_address }}</dd></div>
                <div><dt class="text-gray-500">Provincial Address</dt><dd>{{ $sheet->provincial_address ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Gender</dt><dd>{{ $sheet->gender }}</dd></div>
                <div><dt class="text-gray-500">Contact Number</dt><dd>{{ $sheet->contact_number }}</dd></div>
                <div><dt class="text-gray-500">Email</dt><dd>{{ $sheet->email }}</dd></div>
                <div><dt class="text-gray-500">Birth Date / Place</dt><dd>{{ $sheet->birth_date->toFormattedDateString() }}, {{ $sheet->birth_place }}</dd></div>
                <div><dt class="text-gray-500">Religion</dt><dd>{{ $sheet->religion ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Marital Status</dt><dd>{{ $sheet->marital_status ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white p-6 rounded-md shadow-sm border space-y-4">
            <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">B. Family Data</h3>
            @foreach (['father' => "Father's", 'mother' => "Mother's"] as $prefix => $label)
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">{{ $label }} Information</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                        <div><dt class="text-gray-500">Name</dt><dd>{{ $sheet->{"{$prefix}_name"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Occupation</dt><dd>{{ $sheet->{"{$prefix}_occupation"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Company</dt><dd>{{ $sheet->{"{$prefix}_company"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Company Address</dt><dd>{{ $sheet->{"{$prefix}_company_address"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Contact</dt><dd>{{ $sheet->{"{$prefix}_contact"} ?: '—' }}</dd></div>
                    </dl>
                </div>
            @endforeach
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase mb-1">Guardian's Information</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                    <div><dt class="text-gray-500">Name</dt><dd>{{ $sheet->guardian_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Contact</dt><dd>{{ $sheet->guardian_contact ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500">Address</dt><dd>{{ $sheet->guardian_address ?: '—' }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="bg-white p-6 rounded-md shadow-sm border space-y-4">
            <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">C. Scholastic Data</h3>
            @foreach (['tertiary' => 'Tertiary', 'secondary' => 'Secondary', 'primary' => 'Primary'] as $prefix => $label)
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">{{ $label }}</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                        <div><dt class="text-gray-500">School</dt><dd>{{ $sheet->{"{$prefix}_school"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Address</dt><dd>{{ $sheet->{"{$prefix}_address"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Year Graduated</dt><dd>{{ $sheet->{"{$prefix}_year_graduated"} ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">Honors</dt><dd>{{ $sheet->{"{$prefix}_honors"} ?: '—' }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="bg-white p-6 rounded-md shadow-sm border">
            <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide mb-2">D. Health Data</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div><dt class="text-gray-500">Height / Weight</dt><dd>{{ $sheet->height ?: '—' }} cm / {{ $sheet->weight ?: '—' }} kg</dd></div>
                <div><dt class="text-gray-500">Blood Type</dt><dd>{{ $sheet->blood_type ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Health Problem</dt><dd>{{ $sheet->health_problem ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Vaccination Status</dt><dd>{{ $sheet->vaccination_status ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Vaccine Type / Place</dt><dd>{{ $sheet->vaccine_type ?: '—' }} / {{ $sheet->vaccination_place ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Vaccination Date</dt><dd>{{ optional($sheet->vaccination_date)->toFormattedDateString() ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Health Insurance</dt><dd>{{ $sheet->health_insurance_type ?: '—' }} {{ $sheet->health_insurance_specify ? "({$sheet->health_insurance_specify})" : '' }}</dd></div>
            </dl>
        </div>

        @if ($sheet->workExperiences->isNotEmpty())
            <div class="bg-white p-6 rounded-md shadow-sm border">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide mb-2">E. OJT Work Experiences</h3>
                <div class="divide-y">
                    @foreach ($sheet->workExperiences as $exp)
                        <div class="py-2 text-sm">
                            <p class="font-medium">{{ $exp->ojt_assignment }} — {{ $exp->position }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $exp->inclusive_start_date->toFormattedDateString() }} – {{ $exp->inclusive_end_date->toFormattedDateString() }}
                                · {{ $exp->ojt_site_address }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('student.dashboard') }}" class="px-4 py-2 text-sm text-gray-600">Back to Dashboard</a>
        </div>
    </div>
</x-app-layout>
