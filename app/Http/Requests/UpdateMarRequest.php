<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Saves MAR content (activities_text / remarks).
 *
 * `monthly_total_hours` is deliberately absent from the rule set as of
 * this session — resolving the judgment call flagged in
 * PROJECT_STATE.md's open items: pdf-forms.md describes the printed MAR
 * as a "Summary of Weekly Accomplishment Reports," so this is now
 * treated the same way BR-3 treats DAR's hours_rendered — never
 * accepted as direct input, always derived server-side. See
 * MarController::update() for the WAR-sum computation.
 *
 * Authorization (ownership + must still be Draft or Returned) lives in
 * MarPolicy::update() via $this->authorize() in the controller — this
 * class only covers field-level validation, plus the BR-10 completion
 * lock check (same shape as StoreDarRequest).
 */
class UpdateMarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() && $this->user()->student !== null;
    }

    public function rules(): array
    {
        return [
            'activities_text' => ['required', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $student = $this->user()->student;

            if ($student->ojt_status === 'Completed') {
                $validator->errors()->add(
                    'activities_text',
                    'Your OJT record is marked Completed. Ask your Coordinator to reopen it before editing the MAR (BR-10).'
                );
            }
        });
    }
}
