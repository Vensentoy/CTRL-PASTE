{{--
    Workflows.md §1 step 4 — filled out ONCE, ever. No edit/resubmit
    path exists for this document, so this form has no $sheet->exists
    branching like dar/form.blade.php does.

    Section E (OJT Work Experiences) is a fixed 3 repeatable rows
    rather than a JS "add row" control — kept consistent with how the
    rest of this project avoids Vue/JS for form interactions (DAR/WAR
    forms are plain Blade). Any row left completely blank is silently
    skipped by the controller. Bump the loop count below if 3 rows
    isn't enough in practice.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            OJT Information Sheet
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-3 text-sm mb-6">
            This form can only be submitted once. Please review everything before saving.
        </div>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.information-sheet.store') }}" class="space-y-8">
            @csrf

            {{-- A. Personal Data --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-4">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">A. Personal Data</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">City Address</label>
                        <input type="text" name="city_address" required value="{{ old('city_address') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provincial Address</label>
                        <input type="text" name="provincial_address" value="{{ old('provincial_address') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Gender</label>
                        <select name="gender" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select</option>
                            <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                            <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                        <input type="text" name="contact_number" required value="{{ old('contact_number') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" name="email" required value="{{ old('email') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Birth Date</label>
                        <input type="date" name="birth_date" required value="{{ old('birth_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Birth Place</label>
                        <input type="text" name="birth_place" required value="{{ old('birth_place') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Religion</label>
                        <input type="text" name="religion" value="{{ old('religion') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Marital Status</label>
                        <input type="text" name="marital_status" value="{{ old('marital_status') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>
            </div>

            {{-- B. Family Data --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-6">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">B. Family Data</h3>

                @foreach (['father' => "Father's", 'mother' => "Mother's"] as $prefix => $label)
                    <div class="space-y-3">
                        <p class="text-xs font-medium text-gray-500 uppercase">{{ $label }} Information</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <input type="text" name="{{ $prefix }}_name" placeholder="Name" value="{{ old("{$prefix}_name") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_occupation" placeholder="Occupation" value="{{ old("{$prefix}_occupation") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_company" placeholder="Company" value="{{ old("{$prefix}_company") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_company_address" placeholder="Company Address" value="{{ old("{$prefix}_company_address") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_contact" placeholder="Contact Number" value="{{ old("{$prefix}_contact") }}"
                                   class="rounded-md border-gray-300 text-sm sm:col-span-2">
                        </div>
                    </div>
                @endforeach

                <div class="space-y-3">
                    <p class="text-xs font-medium text-gray-500 uppercase">Guardian's Information</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input type="text" name="guardian_name" placeholder="Name" value="{{ old('guardian_name') }}"
                               class="rounded-md border-gray-300 text-sm">
                        <input type="text" name="guardian_contact" placeholder="Contact Number" value="{{ old('guardian_contact') }}"
                               class="rounded-md border-gray-300 text-sm">
                        <input type="text" name="guardian_address" placeholder="Home Address" value="{{ old('guardian_address') }}"
                               class="rounded-md border-gray-300 text-sm sm:col-span-2">
                    </div>
                </div>
            </div>

            {{-- C. Scholastic Data --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-6">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">C. Scholastic Data</h3>

                @foreach (['tertiary' => 'Tertiary', 'secondary' => 'Secondary', 'primary' => 'Primary'] as $prefix => $label)
                    <div class="space-y-3">
                        <p class="text-xs font-medium text-gray-500 uppercase">{{ $label }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <input type="text" name="{{ $prefix }}_school" placeholder="School" value="{{ old("{$prefix}_school") }}"
                                   class="rounded-md border-gray-300 text-sm sm:col-span-2">
                            <input type="text" name="{{ $prefix }}_address" placeholder="Address" value="{{ old("{$prefix}_address") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_year_graduated" placeholder="Year Graduated" value="{{ old("{$prefix}_year_graduated") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="{{ $prefix }}_honors" placeholder="Honors/Awards" value="{{ old("{$prefix}_honors") }}"
                                   class="rounded-md border-gray-300 text-sm sm:col-span-2">
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- D. Health Data --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-4">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">D. Health Data</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                        <input type="number" step="0.01" name="height" value="{{ old('height') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                        <input type="number" step="0.01" name="weight" value="{{ old('weight') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Blood Type</label>
                        <input type="text" name="blood_type" value="{{ old('blood_type') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Health Problem</label>
                        <input type="text" name="health_problem" value="{{ old('health_problem') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vaccination Status</label>
                        <select name="vaccination_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select</option>
                            @foreach (['Unvaccinated', 'First Dose', 'Second Dose', 'Booster'] as $status)
                                <option value="{{ $status }}" @selected(old('vaccination_status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vaccine Type</label>
                        <input type="text" name="vaccine_type" value="{{ old('vaccine_type') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Place of Vaccination</label>
                        <input type="text" name="vaccination_place" value="{{ old('vaccination_place') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date of Vaccination</label>
                        <input type="date" name="vaccination_date" value="{{ old('vaccination_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Health Insurance</label>
                        <select name="health_insurance_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select</option>
                            <option value="PhilHealth" @selected(old('health_insurance_type') === 'PhilHealth')>PhilHealth</option>
                            <option value="Private" @selected(old('health_insurance_type') === 'Private')>Private</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Specify</label>
                        <input type="text" name="health_insurance_specify" value="{{ old('health_insurance_specify') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>
            </div>

            {{-- E. OJT Work Experiences --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-6">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">
                    E. OJT Work Experiences <span class="text-gray-400 normal-case">(4th Year Students — leave blank if none)</span>
                </h3>

                @for ($i = 0; $i < 3; $i++)
                    <div class="space-y-3 border-t pt-4 first:border-t-0 first:pt-0">
                        <p class="text-xs font-medium text-gray-500 uppercase">Entry {{ $i + 1 }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <input type="text" name="work_experiences[{{ $i }}][ojt_assignment]" placeholder="OJT Assignment"
                                   value="{{ old("work_experiences.$i.ojt_assignment") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="work_experiences[{{ $i }}][position]" placeholder="Position"
                                   value="{{ old("work_experiences.$i.position") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="date" name="work_experiences[{{ $i }}][inclusive_start_date]"
                                   value="{{ old("work_experiences.$i.inclusive_start_date") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="date" name="work_experiences[{{ $i }}][inclusive_end_date]"
                                   value="{{ old("work_experiences.$i.inclusive_end_date") }}"
                                   class="rounded-md border-gray-300 text-sm">
                            <input type="text" name="work_experiences[{{ $i }}][ojt_site_address]" placeholder="OJT Site Address"
                                   value="{{ old("work_experiences.$i.ojt_site_address") }}"
                                   class="rounded-md border-gray-300 text-sm sm:col-span-2">
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Attestation --}}
            <div class="bg-white p-4 sm:p-6 rounded-md shadow-sm border space-y-4">
                <h3 class="font-semibold text-sm text-gray-900 uppercase tracking-wide">Attestation</h3>
                <p class="text-xs text-gray-500">
                    Your signature will be collected physically on the printed copy. This date confirms when you completed this form.
                </p>
                <div class="w-48">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="signed_date" required value="{{ old('signed_date', now()->toDateString()) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('student.dashboard') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md">
                    Submit (one-time only)
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
