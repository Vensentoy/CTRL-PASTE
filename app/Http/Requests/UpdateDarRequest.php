<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edits an existing DAR Draft. Authorization (ownership + must still be
 * an unsubmitted Draft) is handled by DarPolicy::update() via
 * $this->authorize('update', $dar) in the controller — this class only
 * covers field-level validation, same BR-4/BR-10 shape as StoreDarRequest.
 *
 * BR-10 completion lock added this session — this was the one gap found
 * during last session's click-testing audit: StoreDarRequest already had
 * this check (a Completed student can't create a new DAR draft), and
 * UpdateWarWeekRequest/UpdateMarRequest already had the equivalent check
 * for their own edit paths, but this file — the DAR *edit* path — didn't.
 * Without it, a Returned DAR could still be resubmitted after the
 * student's ojt_status flipped to Completed via a different document's
 * approval in between. Copied verbatim from StoreDarRequest's version of
 * this same check: same error message wording, same field ('report_date'),
 * same position at the top of withValidator() with an early return so the
 * date-bounds checks below don't also fire on a Completed record.
 */
class UpdateDarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() && $this->user()->student !== null;
    }

    public function rules(): array
    {
        return [
            'report_date' => ['required', 'date'],
            // Same multi-activity shape as StoreDarRequest (1–20 entries;
            // per-item failures key as activities.{i}.{field}).
            'activities' => ['required', 'array', 'min:1', 'max:20'],
            'activities.*.activity' => ['required', 'string', 'max:1000'],
            'activities.*.time_started' => ['required', 'date_format:H:i'],
            'activities.*.time_ended' => ['required', 'date_format:H:i', 'after:activities.*.time_started'],
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
                    'Your OJT record is marked Completed. Ask your Coordinator to reopen it before editing entries (BR-10).'
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
