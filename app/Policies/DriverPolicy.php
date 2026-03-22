<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

class DriverPolicy
{
    /**
     * Admins can access all drivers; organization managers can access driver area.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user)
            || $user->hasRole(Role::ORGANIZATION)
            || $user->hasAnyActiveOrganizationManagement();
    }

    /**
     * Organization managers can only view drivers under organizations they own.
     */
    public function view(User $user, Driver $driver): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $driver->organization_id) {
            return false;
        }

        return (
            $user->hasRole(Role::ORGANIZATION)
            && $driver->organization()->where('owner_user_id', $user->id)->exists()
        ) || $user->isOrganizationManagerFor($driver->organization_id);
    }

    public function assignToOwnedOrganization(User $user, Driver $driver, Organization $organization): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $isOwnerByRole = $user->hasRole(Role::ORGANIZATION) && $organization->owner_user_id === $user->id;
        $isOrganizationManager = $user->isOrganizationManagerFor($organization->id);

        if (! $isOwnerByRole && ! $isOrganizationManager) {
            return false;
        }

        return $driver->organization_id === null || $driver->organization_id === $organization->id;
    }

    public function unassignFromOwnedOrganization(User $user, Driver $driver, Organization $organization): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return (
            ($user->hasRole(Role::ORGANIZATION) && $organization->owner_user_id === $user->id)
            || $user->isOrganizationManagerFor($organization->id)
        )
            && $driver->organization_id === $organization->id;
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN);
    }
}
