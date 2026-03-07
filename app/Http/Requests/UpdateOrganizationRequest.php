<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by OrganizationPolicy in the controller.
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'           => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($id)],
            'type'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
