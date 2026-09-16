<?php

namespace App\Http\Requests;

use App\Models\SubmissionCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Submits the current MAR row into a SubmissionCycle belonging to the
 * student's own coordinator (BR-11) — same shape as SubmitDarRequest,
 * adapted for MAR's single cycle_id (unlike WAR's two cycle slots, a
 * MAR row is submitted exactly once).
 */
class SubmitMarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() && $this->user()->student !== null;
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
            $student = $this->user()->student;

            if ($student->ojt_status === 'Completed') {
                $validator->errors()->add(
                    'cycle_id',
                    'Your OJT record is marked Completed — new submissions are blocked (BR-10).'
                );

                return;
            }

            $cycleId = $this->integer('cycle_id');
            if (! $cycleId) {
                return;
            }

            $cycle = SubmissionCycle::find($cycleId);

            // The cycle must belong to this student's OWN coordinator —
            // otherwise a student could submit into an unrelated
            // coordinator's cycle by guessing an ID (BR-11/BR-14).
            if ($cycle && $cycle->coordinator_id !== $student->coordinator_id) {
                $validator->errors()->add('cycle_id', 'That submission cycle does not belong to your coordinator.');
            }
        });
    }
}
