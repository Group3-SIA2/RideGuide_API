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
            'hq_street'          => ['nullable', 'string', 'max:255', 'required_with:hq_barangay,hq_city,hq_region,hq_province,hq_postal_code'],
            'hq_barangay'        => ['nullable', 'string', 'max:255', 'required_with:hq_street,hq_city,hq_region,hq_province,hq_postal_code'],
            'hq_city'            => ['nullable', 'string', 'max:255', 'required_with:hq_street,hq_barangay,hq_region,hq_province,hq_postal_code'],
            'hq_region'          => ['nullable', 'string', 'max:255', 'required_with:hq_street,hq_barangay,hq_city,hq_province,hq_postal_code'],
            'hq_province'        => ['nullable', 'string', 'max:255', 'required_with:hq_street,hq_barangay,hq_city,hq_region,hq_postal_code'],
            'hq_postal_code'     => ['nullable', 'string', 'max:20'],
            'status'         => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
