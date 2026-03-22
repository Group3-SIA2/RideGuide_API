<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'google_id',
        'facebook_id',
        'phone_number',
        'password',
        'status',
        'status_reason',
        'status_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAccountActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->trashed();
    }

    /**
     * Get the roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->using(UserRole::class)
            ->withTimestamps();
    }

    /**
     * Get the OTPs for the user.
     */
    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user has a specific permission through any of their roles.
     */
    public function hasPermission(string $permissionName): bool
    {
        // Super admins always have all permissions
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($q) use ($permissionName) {
                $q->where('name', $permissionName);
            })
            ->exists()
            || $this->organizationUserRoles()
                ->where('status', 'active')
                ->whereHas('role.permissions', function ($q) use ($permissionName) {
                    $q->where('name', $permissionName);
                })
                ->exists();
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all permissions for the user (via their roles).
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        $globalRoleIds = $this->roles()->pluck('roles.id');
        $organizationRoleIds = $this->organizationUserRoles()
            ->where('status', 'active')
            ->pluck('role_id');

        return Permission::whereHas('roles', function ($q) use ($globalRoleIds, $organizationRoleIds) {
            $q->whereIn('roles.id', $globalRoleIds)
                ->orWhereIn('roles.id', $organizationRoleIds);
        })->get();
    }

    /**
     * Check if the user's email is verified.
     */
    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Check if the user's phone number is verified.
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    // Seeder purpose lungs
    public static function getIdByFirstName(string $firstName): ?string
    {
        $user = self::where('first_name', $firstName)->first();
        return $user ? $user->id : null;
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function commuter(): HasOne
    {
        return $this->hasOne(Commuter::class);
    }

    public function organizationUserRoles(): HasMany
    {
        return $this->hasMany(OrganizationUserRole::class, 'user_id');
    }

    public function managedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user_role', 'user_id', 'organization_id')
            ->withTimestamps()
            ->withPivot(['id', 'role_id', 'status', 'invited_by_user_id', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->wherePivot('status', 'active');
    }

    public function isOrganizationManagerFor(string $organizationId): bool
    {
        return $this->organizationUserRoles()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->exists();
    }

    public function hasAnyActiveOrganizationManagement(): bool
    {
        return $this->organizationUserRoles()
            ->where('status', 'active')
            ->exists();
    }
}
