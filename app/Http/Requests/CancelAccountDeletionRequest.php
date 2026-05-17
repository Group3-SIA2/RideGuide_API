<?php

namespace App\Http\Requests;

use App\Support\PhilippinePhone;
use Illuminate\Foundation\Http\FormRequest;

class CancelAccountDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'nullable',
                'string',
                'email:rfc,filter',
                'required_without:phone_number',
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:15',
                'required_without:email',
            ],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('email')) {
            $merge['email'] = strtolower(trim((string) $this->input('email')));
        }

        if ($this->filled('phone_number')) {
            $merge['phone_number'] = PhilippinePhone::normalize((string) $this->input('phone_number'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
