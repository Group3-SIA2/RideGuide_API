<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? auth()->id();

        return [
            'first_name'     => ['sometimes', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'last_name'      => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already taken by another account.',
            'first_name.max'     => 'First name must not exceed 255 characters.',
            'middle_name.max'    => 'Middle name must not exceed 255 characters.',
            'last_name.max'      => 'Last name must not exceed 255 characters.',
        ];
    }
}