<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * BR-12: Company Assignments are historized, never overwritten in place.
 * This request covers BOTH the very first assignment a student ever
 * records (no active row exists yet) and a later "switch" (an active
 * row does exist and must be closed). The controller decides which
 * case it is; this request only validates field shape plus the one
 * rule that depends on existing state: the new start_date can't be
 * before the currently active assignment's own start_date, since that
 * would create an impossible/overlapping history.
 *
 * Judgment call, not a documented rule: when closing the old row, the
 * controller sets its end_date to the new assignment's start_date
 * (same-day cutover — the old job "ends" the day the new one starts,
 * no gap day in between). data-model.md doesn't specify a gap
 * convention either way, so this is worth confirming against real LLCC
 * practice if a gap day is expected instead.
 */
class StoreCompanyAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already sits behind ['auth', 'role:student']; this
        // just confirms a student profile actually exists (BR-14).
        return $this->user()?->isStudent() && $this->user()->student !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'department_area' => ['nullable', 'string', 'max:255'],
            'job_designation' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $active = $this->user()->student->activeCompanyAssignment();

            if ($active && $this->input('start_date') < $active->start_date->toDateString()) {
                $validator->errors()->add(
                    'start_date',
                    'The new start date can\'t be before your current assignment\'s start date ('
                        . $active->start_date->toFormattedDateString() . ').'
                );
            }
        });
    }
}
