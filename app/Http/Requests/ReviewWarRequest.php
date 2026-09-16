<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same shape as ReviewDarRequest, applied to one week-section of a WAR.
 * Comment is required when returning — mirrors workflows.md §4 step 2
 * ("Return for Revision with a required comment").
 */
class ReviewWarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week' => ['required', 'integer', 'between:1,4'],
            'decision' => ['required', 'in:approve,return'],
            'coordinator_comment' => ['required_if:decision,return', 'nullable', 'string'],
        ];
    }
}
