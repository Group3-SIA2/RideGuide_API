<?php

namespace App\Http\Requests;

use App\Rules\OrganizationOwnerEligible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'organization_type_id' => [
                'required_without:organization_type',
                'uuid',
                Rule::exists('organization_types', 'id')->whereNull('deleted_at'),
            ],
            'organization_type' => [
                'required_without:organization_type_id',
                'string',
                'max:100',
                Rule::exists('organization_types', 'name')->whereNull('deleted_at'),
            ],
            'hq_address'     => ['nullable', 'string', 'max:500'],
            'owner_user_id'  => [
                'nullable',
                'uuid',
                new OrganizationOwnerEligible(),
            ],
            'hq_street'          => ['nullable', 'string', 'max:255'],
            'hq_barangay'        => ['nullable', 'string', 'max:255'],
            'hq_subdivision'     => ['nullable', 'string', 'max:255'],
            'hq_floor_unit_room' => ['nullable', 'string', 'max:255'],
            'hq_lat'             => ['nullable', 'string', 'max:50'],
            'hq_lng'             => ['nullable', 'string', 'max:50'],
        ];
    }
}
