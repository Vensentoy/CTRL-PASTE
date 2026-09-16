<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Workflows.md §1 step 4: "Student completes the OJT Information
 * Sheet — one time, ever." There is no Draft/edit workflow for this
 * document in data-model.md (unlike DAR/WAR/MAR, it has no status
 * field) — this request is a single, final create.
 *
 * Required vs. optional below follows a plain-sense reading of the
 * form (core identity fields required; family/scholastic/health detail
 * fields left optional since data-model.md and pdf-forms.md don't
 * mark them either way) — not a documented rule, so this is a
 * judgment call worth double-checking against actual LLCC intake
 * practice before treating it as final.
 */
class StoreInformationSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already sits behind ['auth', 'role:student']; this
        // just confirms the student profile exists AND that no sheet
        // has been created yet — the one-time constraint (data-model.md)
        // enforced here in addition to the DB unique constraint, so the
        // user gets a real validation error instead of a raw SQL one.
        return $this->user()?->isStudent()
            && $this->user()->student !== null
            && $this->user()->student->informationSheet === null;
    }

    public function rules(): array
    {
        return [
            // --- A. Personal Data ---------------------------------
            'city_address' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Female,Male'],
            'contact_number' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:255'],
            'provincial_address' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],

            // --- B. Family Data ------------------------------------
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'father_company' => ['nullable', 'string', 'max:255'],
            'father_company_address' => ['nullable', 'string', 'max:255'],
            'father_contact' => ['nullable', 'string', 'max:50'],

            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'mother_company' => ['nullable', 'string', 'max:255'],
            'mother_company_address' => ['nullable', 'string', 'max:255'],
            'mother_contact' => ['nullable', 'string', 'max:50'],

            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_address' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:50'],

            // --- C. Scholastic Data ---------------------------------
            'tertiary_school' => ['nullable', 'string', 'max:255'],
            'tertiary_address' => ['nullable', 'string', 'max:255'],
            'tertiary_year_graduated' => ['nullable', 'string', 'max:10'],
            'tertiary_honors' => ['nullable', 'string', 'max:255'],

            'secondary_school' => ['nullable', 'string', 'max:255'],
            'secondary_address' => ['nullable', 'string', 'max:255'],
            'secondary_year_graduated' => ['nullable', 'string', 'max:10'],
            'secondary_honors' => ['nullable', 'string', 'max:255'],

            'primary_school' => ['nullable', 'string', 'max:255'],
            'primary_address' => ['nullable', 'string', 'max:255'],
            'primary_year_graduated' => ['nullable', 'string', 'max:10'],
            'primary_honors' => ['nullable', 'string', 'max:255'],

            // --- D. Health Data --------------------------------------
            'height' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'health_problem' => ['nullable', 'string', 'max:500'],
            'vaccination_status' => ['nullable', 'in:Unvaccinated,First Dose,Second Dose,Booster'],
            'vaccine_type' => ['nullable', 'string', 'max:100'],
            'vaccination_place' => ['nullable', 'string', 'max:255'],
            'vaccination_date' => ['nullable', 'date', 'before_or_equal:today'],
            'health_insurance_type' => ['nullable', 'in:PhilHealth,Private'],
            'health_insurance_specify' => ['nullable', 'string', 'max:255'],

            // --- E. OJT Work Experiences (repeatable, optional) -------
            'work_experiences' => ['nullable', 'array'],
            'work_experiences.*.ojt_assignment' => ['required_with:work_experiences', 'string', 'max:255'],
            'work_experiences.*.position' => ['required_with:work_experiences', 'string', 'max:255'],
            'work_experiences.*.inclusive_start_date' => ['required_with:work_experiences', 'date'],
            'work_experiences.*.inclusive_end_date' => ['required_with:work_experiences', 'date', 'after_or_equal:work_experiences.*.inclusive_start_date'],
            'work_experiences.*.ojt_site_address' => ['required_with:work_experiences', 'string', 'max:255'],

            // --- Closing attestation ----------------------------------
            'signed_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->user()->student->informationSheet !== null) {
                $validator->errors()->add(
                    'city_address',
                    'Your OJT Information Sheet has already been submitted and cannot be resubmitted (one-time only).'
                );
            }
        });
    }
}
