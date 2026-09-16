<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * BR-5: Submission Cycles are created manually by a Coordinator — this
 * request is the only place a cycle gets created anywhere in the app
 * (besides DevTestSeeder, which is explicitly dev-only). coordinator_id
 * is never taken from the request body; the controller always sets it
 * from the authenticated user, so a coordinator can never create a cycle
 * "for" another coordinator (BR-11/BR-14).
 */
class StoreSubmissionCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'role:coordinator' middleware (EnsureRole) already
        // enforces this is a coordinator (BR-14 layer 1). No per-resource
        // ownership check is needed here since a cycle doesn't exist yet
        // to own — ownership is fixed at creation time in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_name' => ['required', 'string', 'max:255'],
            'coverage_start_date' => ['required', 'date'],
            'coverage_end_date' => ['required', 'date', 'after_or_equal:coverage_start_date'],
            'deadline_date' => ['required', 'date'],
        ];
    }

    /**
     * BR-6 depends entirely on deadline_date being a meaningful line in
     * time relative to the coverage period — a deadline set before the
     * coverage period even ends would make every on-time submission
     * impossible by construction. This isn't stated as a literal BR-#
     * clause, but it's a direct structural consequence of BR-6 as written
     * ("whether a report is Late depends only on whether it was submitted
     * before its Submission Cycle's deadline") — a deadline that can never
     * be met isn't a deadline, so it's rejected here rather than silently
     * accepted and producing confusing behavior later.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $coverageEnd = $this->input('coverage_end_date');
            $deadline = $this->input('deadline_date');

            if ($coverageEnd && $deadline && $deadline < $coverageEnd) {
                $validator->errors()->add(
                    'deadline_date',
                    'The deadline must be on or after the coverage end date.'
                );
            }
        });
    }
}
