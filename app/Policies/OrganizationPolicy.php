<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Admins see any org; others can only view active ones.
     */
    public function view(User $user, Organization $organization): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $organization->status === 'active';
    }

    /**
     * Admins and organization-role users can create organizations.
     */
    public function create(User $user): bool
    {
        return $this->isAdmin($user)
            || $user->hasPermission('create_organizations')
            || $user->hasRole(Role::ORGANIZATION);
    }

    /**
     * Admins can update any org; an org-role user can only update their own.
     */
    public function update(User $user, Organization $organization): bool
    {
        if ($this->isAdmin($user) || $user->hasPermission('edit_organizations')) {
            return true;
        }

        return ($user->hasRole(Role::ORGANIZATION) && $organization->owner_user_id === $user->id)
            || $user->isOrganizationManagerFor($organization->id);
    }

    /**
     * Admins can delete any org; an org-role user can only delete their own.
     */
    public function delete(User $user, Organization $organization): bool
    {
        if ($this->isAdmin($user) || $user->hasPermission('delete_organizations')) {
            return true;
        }

        return ($user->hasRole(Role::ORGANIZATION) && $organization->owner_user_id === $user->id)
            || $user->isOrganizationManagerFor($organization->id);
    }

    /**
     * Only admins can restore soft-deleted organizations.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('delete_organizations');
    }

    public function assignOwner(User $user, ?User $ownerUser): bool
    {
        if (!$this->isAdmin($user) && !$user->hasPermission('edit_organizations')) {
            return false;
        }

        if (!$ownerUser) {
            return true;
        }

        if ($ownerUser->trashed()) {
            return false;
        }

        if ($ownerUser->status !== User::STATUS_ACTIVE) {
            return false;
        }

        $ownerHasAllowedRole = $ownerUser->roles()
            ->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION])
            ->exists()
            || $ownerUser->hasAnyPermission([
                'view_organizations',
                'create_organizations',
                'edit_organizations',
            ]);

        if (!$ownerHasAllowedRole) {
            return false;
        }

        return true;
    }

    private function isAdmin(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
