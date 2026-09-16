<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Saves content for exactly one week-section of a WAR (BR-7: each week is
 * independent, so this never touches the other three). `week` identifies
 * which of the four sections is being written and is validated against
 * WarPolicy::updateWeek() in the controller, not here — this request only
 * validates the shape of the data, not who's allowed to write it.
 *
 * BR-10 completion lock added — same gap found in SubmitWarRequest: a
 * Completed student could still edit week content even though DAR/MAR
 * already block equivalent edits. Blocked here at the content-save step,
 * not just at submit, matching StoreDarRequest's/UpdateMarRequest's
 * "block edits, not just submission" behavior.
 */
class UpdateWarWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week' => ['required', 'integer', 'between:1,4'],
            'activities' => ['required', 'string'],
            // Direct numeric input — data-model.md gives WAR no
            // time_started/time_ended fields the way DAR has, so unlike
            // hours_rendered (BR-3) there is no server-side derivation
            // to enforce here, just a sane bound.
            'hours' => ['required', 'numeric', 'min:0', 'max:168'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $student = $this->user()?->student;

            if ($student && $student->ojt_status === 'Completed') {
                $validator->errors()->add(
                    'activities',
                    'Your OJT record is marked Completed. Ask your Coordinator to reopen it before editing the WAR (BR-10).'
                );
            }
        });
    }
}
