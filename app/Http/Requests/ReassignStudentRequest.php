<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * BR-1: reassigns a student's CURRENT coordinator link only — historical
 * DAR/WAR/MAR/CompanyAssignment rows all key off student_id, never
 * coordinator_id, so nothing else needs to change here; history is
 * preserved automatically just by leaving those tables alone.
 *
 * Authorization (only the student's CURRENT owning coordinator may
 * reassign them) is handled by StudentPolicy::update() via
 * $this->authorize() in the controller — this class only validates that
 * the target coordinator exists and is actually different from the
 * current one.
 */
class ReassignStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        return [
            'coordinator_id' => ['required', 'integer', 'exists:coordinators,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $student = $this->route('student');

            if ($student && $this->integer('coordinator_id') === $student->coordinator_id) {
                $validator->errors()->add('coordinator_id', 'This student is already assigned to that coordinator.');
            }
        });
    }
}
