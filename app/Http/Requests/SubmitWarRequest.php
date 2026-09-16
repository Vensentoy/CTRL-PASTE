<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Submits one week-pair (Week 1–2 or Week 3–4, per BR-8) into a
 * SubmissionCycle belonging to the student's own coordinator (BR-11) —
 * same shape as SubmitDarRequest, adapted for the week-pair concept.
 *
 * BR-10 completion lock added: StoreDarRequest, UpdateMarRequest, and
 * SubmitMarRequest all already blocked a Completed student — this
 * request was the one gap where that check was missing (found while
 * building the Reopen feature). Same message/shape as the others.
 */
class SubmitWarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_id' => ['required', 'integer', 'exists:submission_cycles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $student = $this->user()?->student;

            if ($student && $student->ojt_status === 'Completed') {
                $validator->errors()->add(
                    'cycle_id',
                    'Your OJT record is marked Completed — new submissions are blocked (BR-10).'
                );
            }
        });
    }
}
