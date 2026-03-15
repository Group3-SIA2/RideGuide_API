<?php

namespace App\Rules;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OrganizationOwnerEligible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        $ownerUser = User::withTrashed()->find($value);

        if (!$ownerUser) {
            $fail('The selected owner user is invalid.');
            return;
        }

        if ($ownerUser->trashed()) {
            $fail('The selected owner user is inactive.');
            return;
        }

        if ($ownerUser->status !== User::STATUS_ACTIVE) {
            $fail('The selected owner user must be active.');
            return;
        }

        $hasAllowedRole = $ownerUser->roles()
            ->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION])
            ->exists();

        if (!$hasAllowedRole) {
            $fail('The selected owner must have an admin, super_admin, or organization role.');
        }
    }
}
