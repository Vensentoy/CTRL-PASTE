<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Creates a DAR Draft (workflows.md §2 — Daily Logging).
 *
 * - hours_rendered is deliberately absent from the rule set — it is
 *   never accepted as input (BR-3). The controller computes it via
 *   DailyAccomplishmentReport::calculateHoursRendered() after this
 *   request validates.
 * - report_date bounds (BR-4: not future, not before ojt_start_date,
 *   not after ojt_completion_date) can't be expressed as static Laravel
 *   rules since two of the three bounds depend on the logged-in
 *   student's own row — handled in withValidator() below instead.
 * - BR-10: a student whose ojt_status is already Completed is blocked
 *   from creating new drafts unless a Coordinator reopens the record.
 */
class StoreDarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already sits behind ['auth', 'role:student']; this just
        // confirms the student profile itself exists.
        return $this->user()?->isStudent() && $this->user()->student !== null;
    }

    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
            'activities_text' => ['required', 'string', 'max:5000'],
            'time_started' => ['required', 'date_format:H:i'],
            'time_ended' => ['required', 'date_format:H:i', 'after:time_started'],
            'remarks_student' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $student = $this->user()->student;

            if ($student->ojt_status === 'Completed') {
                $validator->errors()->add(
                    'report_date',
                    'Your OJT record is marked Completed. Ask your Coordinator to reopen it before logging new entries (BR-10).'
                );

                return;
            }

            $reportDate = $this->date('report_date');
            if (! $reportDate) {
                return;
            }

            if ($reportDate->isFuture()) {
                $validator->errors()->add('report_date', 'The report date cannot be in the future (BR-4).');
            }

            if ($student->ojt_start_date && $reportDate->lt($student->ojt_start_date)) {
                $validator->errors()->add('report_date', 'The report date cannot be before your OJT start date (BR-4).');
            }

            if ($student->ojt_completion_date && $reportDate->gt($student->ojt_completion_date)) {
                $validator->errors()->add('report_date', 'The report date cannot be after your OJT completion date (BR-4).');
            }
        });
    }
}
