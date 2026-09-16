<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Coordinator review action on a single MAR row (workflows.md §4).
 * decision = 'approve' or 'return' — a comment is REQUIRED on return,
 * matching workflows.md's "Return for Revision with a required comment".
 * MAR has one status per row (unlike WAR's per-week decision), so unlike
 * ReviewWarRequest there's no `week` field here — same shape as
 * ReviewDarRequest.
 */
class ReviewMarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,return'],
            'coordinator_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('decision') === 'return' && ! trim((string) $this->input('coordinator_comment'))) {
                $validator->errors()->add('coordinator_comment', 'A comment is required when returning a report for revision.');
            }
        });
    }
}
