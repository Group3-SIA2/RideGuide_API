<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    const SUPER_ADMIN  = 'super_admin';
    const ADMIN        = 'admin';
    const DRIVER       = 'driver';
    const COMMUTER     = 'commuter';
    const ORGANIZATION = 'organization';

    public const RESERVED_NAMES = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::DRIVER,
        self::COMMUTER,
        self::ORGANIZATION,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_reserved',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_reserved' => 'boolean',
        ];
    }

    /**
     * Get the users that belong to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->using(UserRole::class)
            ->withTimestamps();
    }

    /**
     * Get the permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    public function organizationUserRoles(): HasMany
    {
        return $this->hasMany(OrganizationUserRole::class, 'role_id');
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    // Seeder purpose lungs
    public static function getIdbyName(string $name): ?string
    {
        $role = self::where('name', $name)->first();
        return $role ? $role->id : null;
    }

    public static function isReservedName(string $name): bool
    {
        return in_array($name, self::RESERVED_NAMES, true);
    }

    public function isReserved(): bool
    {
        return (bool) ($this->is_reserved ?? false) || self::isReservedName((string) $this->name);
    }
}
