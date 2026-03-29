<?php

namespace App\Http\Requests;

use App\Rules\OrganizationOwnerEligible;
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
        $id = $this->route('id') ?? $this->route('organization');

        return [
            'name'           => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($id)],
            'organization_type_id' => [
                'required',
                'uuid',
                Rule::exists('organization_types', 'id')->whereNull('deleted_at'),
            ],
            'hq_address'     => ['nullable', 'string', 'max:500'],
            'owner_user_id'  => [
                'nullable',
                'uuid',
                new OrganizationOwnerEligible(),
            ],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
