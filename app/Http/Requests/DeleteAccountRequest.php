<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required'         => 'Password is required to delete your account.',
            'password.current_password' => 'The provided password is incorrect.',
        ];
    }
}