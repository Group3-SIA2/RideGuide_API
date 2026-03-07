<?php

namespace App\Policies;

use App\Models\Organization;
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
        return $this->isAdmin($user) || $user->role->name === 'organization';
    }

    /**
     * Admins can update any org; an org-role user can only update their own.
     */
    public function update(User $user, Organization $organization): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->role->name === 'organization'
            && $organization->owner_user_id === $user->id;
    }

    /**
     * Admins can delete any org; an org-role user can only delete their own.
     */
    public function delete(User $user, Organization $organization): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->role->name === 'organization'
            && $organization->owner_user_id === $user->id;
    }

    /**
     * Only admins can restore soft-deleted organizations.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role->name, ['admin', 'super_admin']);
    }
}
