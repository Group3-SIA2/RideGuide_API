<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

class AppRoleContext
{
    /**
     * Roles supported by Flutter mobile app contexts only.
     *
     * @return list<string>
     */
    public static function mobileRoles(): array
    {
        return [
            Role::DRIVER,
            Role::COMMUTER,
            Role::ORGANIZATION,
        ];
    }

    public static function isMobileRole(string $role): bool
    {
        return in_array(strtolower($role), self::mobileRoles(), true);
    }

    /**
     * @return list<string>
     */
    public static function assignedMobileRoles(User $user): array
    {
        return $user->roles()
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name))
            ->filter(fn (string $name) => self::isMobileRole($name))
            ->values()
            ->all();
    }

    public static function isValidActiveRoleForUser(User $user, ?string $role): bool
    {
        if (!is_string($role) || trim($role) === '') {
            return false;
        }

        return in_array(strtolower($role), self::assignedMobileRoles($user), true);
    }
}

