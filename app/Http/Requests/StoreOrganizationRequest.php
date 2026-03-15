<?php

namespace App\Http\Requests;

use App\Rules\OrganizationOwnerEligible;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by OrganizationPolicy in the controller.
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'type'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'hq_address'     => ['nullable', 'string', 'max:500'],
            'owner_user_id'  => [
                'nullable',
                'uuid',
                new OrganizationOwnerEligible(),
            ],
        ];
    }
}
