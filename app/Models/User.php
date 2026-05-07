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
    public const STATUS_LOCKED = 'locked';

    // Account lock reasons
    public const LOCK_REASON_FAILED_ATTEMPTS = 'failed_login_attempts';
    public const LOCK_REASON_ADMIN_INITIATED = 'admin_initiated';

    // Failed login attempt threshold
    public const FAILED_LOGIN_THRESHOLD = 3;

    // Account lock cooldown duration (in hours)
    public const ACCOUNT_LOCK_COOLDOWN_HOURS = 24;

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
        'locked_until',
        'lock_reason',
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
            'locked_until' => 'datetime',
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

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isPlainAdmin(): bool
    {
        return $this->isAdmin() && !$this->isSuperAdmin();
    }

    public function isOrganizationScoped(): bool
    {
        return ($this->hasRole(Role::ORGANIZATION) || $this->hasAnyActiveOrganizationManagement())
            && !$this->isAdmin()
            && !$this->isSuperAdmin();
    }

    /**
     * Check if the user has a specific permission through any of their roles.
     */
    public function hasPermission(string $permissionName): bool
    {
        // Super admins always have all permissions
        if ($this->isSuperAdmin()) {
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

    /**
     * Get the failed login attempts for this user.
     */
    public function loginFailAttempts(): HasMany
    {
        return $this->hasMany(LoginFailAttempt::class);
    }

    /**
     * Check if the account is currently locked.
     */
    public function isAccountLocked(): bool
    {
        if ($this->status !== self::STATUS_LOCKED) {
            return false;
        }

        // Check if lock has expired
        if ($this->locked_until && $this->locked_until->isPast()) {
            // Auto-unlock the account
            $this->update([
                'status' => self::STATUS_ACTIVE,
                'status_changed_at' => now(),
                'locked_until' => null,
                'lock_reason' => null,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Lock the user's account with a reason.
     */
    public function lockAccount(string $reason = self::LOCK_REASON_ADMIN_INITIATED): void
    {
        $this->update([
            'status' => self::STATUS_LOCKED,
            'status_changed_at' => now(),
            'locked_until' => now()->addHours(self::ACCOUNT_LOCK_COOLDOWN_HOURS),
            'lock_reason' => $reason,
        ]);
    }

    /**
     * Unlock the user's account.
     */
    public function unlockAccount(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'status_changed_at' => now(),
            'locked_until' => null,
            'lock_reason' => null,
        ]);
    }

    /**
     * Get the count of recent failed login attempts.
     */
    public function getRecentFailedAttemptCount(): int
    {
        // Count only active attempts from the last 24 hours
        return $this->loginFailAttempts()
            ->where('status', 'active')
            ->where('created_at', '>', now()->subHours(24))
            ->count();
    }

    /**
     * Check if user is an admin or super admin.
     */
    public function isAdminOrSuperAdmin(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    /**
     * Check if this user can perform account management actions.
     */
    public function canManageAccounts(): bool
    {
        // Only super admins or specific admins with permission
        return $this->hasPermission('manage_locked_accounts');
    }
}
