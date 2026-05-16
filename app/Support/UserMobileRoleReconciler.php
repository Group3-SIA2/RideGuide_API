<?php

namespace App\Support;

use App\Models\Commuter;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\OrganizationUserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures user_role rows exist when the account already has app profiles
 * but the pivot is empty, or only soft-deleted pivot rows exist.
 *
 * Role::getIdbyName() alone is not enough — an empty `roles` table returns
 * null and nothing gets attached; we firstOrCreate role rows as RoleSeeder does.
 */
final class UserMobileRoleReconciler
{
    /**
     * @var array<string, array{description: string, is_reserved: bool}>
     */
    private const ROLE_DEFAULTS = [
        Role::DRIVER => [
            'description' => 'Driver who provides ride services.',
            'is_reserved' => true,
        ],
        Role::COMMUTER => [
            'description' => 'Commuter who books rides.',
            'is_reserved' => true,
        ],
        Role::ORGANIZATION => [
            'description' => 'Transport organization manager (e.g. TODA, MODA).',
            'is_reserved' => true,
        ],
    ];

    /**
     * Attach mobile app roles implied by existing profiles. Idempotent.
     */
    public static function syncFromProfiles(User $user): void
    {
        if (Commuter::query()->where('user_id', $user->id)->exists()) {
            self::ensureUserHasMobileRole($user, Role::COMMUTER);
        }

        if (Driver::query()->where('user_id', $user->id)->exists()) {
            self::ensureUserHasMobileRole($user, Role::DRIVER);
        }

        $hasOrgContext = Organization::query()->where('owner_user_id', $user->id)->exists()
            || OrganizationUserRole::query()->where('user_id', $user->id)->exists();

        if ($hasOrgContext) {
            self::ensureUserHasMobileRole($user, Role::ORGANIZATION);
        }
    }

    public static function ensureUserHasMobileRole(User $user, string $roleName): void
    {
        $normalized = strtolower(trim($roleName));
        if (! AppRoleContext::isMobileRole($normalized)) {
            return;
        }

        $role = self::ensureRoleRecord($normalized);

        $pivot = DB::table('user_role')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->first();

        if ($pivot === null) {
            DB::table('user_role')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'role_id' => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);

            return;
        }

        if ($pivot->deleted_at !== null) {
            DB::table('user_role')->where('id', $pivot->id)->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }
    }

    private static function ensureRoleRecord(string $name): Role
    {
        $defaults = self::ROLE_DEFAULTS[$name] ?? [
            'description' => $name,
            'is_reserved' => true,
        ];

        return Role::firstOrCreate(
            ['name' => $name],
            [
                'description' => $defaults['description'],
                'is_reserved' => $defaults['is_reserved'],
            ],
        );
    }
}
