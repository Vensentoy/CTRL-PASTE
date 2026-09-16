<?php

namespace App\Http\Requests;

use App\Models\SubmissionCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Batch-submits a set of DAR Drafts into a SubmissionCycle
 * (workflows.md §3, step 3). The student picks which of their own
 * cycle_id-null drafts to submit; the coordinator's cycle they're
 * submitting into must belong to their OWN assigned coordinator (a
 * student cannot submit into some other coordinator's cycle).
 */
class SubmitDarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() && $this->user()->student !== null;
    }

    public function rules(): array
    {
        return [
            'cycle_id' => ['required', 'integer', 'exists:submission_cycles,id'],
            'dar_ids' => ['required', 'array', 'min:1'],
            'dar_ids.*' => ['integer', 'exists:daily_accomplishment_reports,id'],
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
