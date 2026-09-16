<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Breeze scaffold validated `name`/`email`, but this system has
        // neither column — users has `username` only (data-model.md).
        // Keep the endpoint alive (profile.edit view still renders) but
        // don't require email/name; validate username if supplied.
        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
