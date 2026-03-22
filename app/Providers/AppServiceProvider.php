<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Permission;
use App\Policies\DriverPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        Gate::before(function (User $user, string $ability) {
            // Keep menu visibility role-aware even for super admins.
            if (str_starts_with($ability, 'menu_')) {
                return null;
            }

            return $user->hasRole(\App\Models\Role::SUPER_ADMIN) ? true : null;
        });

        if (Schema::hasTable('permissions')) {
            Permission::query()->pluck('name')->each(function (string $permissionName) {
                Gate::define($permissionName, function (User $user) use ($permissionName) {
                    return $user->hasPermission($permissionName);
                });
            });
        }

        Gate::define('access_commuters', function (User $user) {
            return $user->hasAnyPermission(['view_commuters', 'manage_commuters']);
        });

        Gate::define('access_drivers', function (User $user) {
            return $user->hasAnyPermission(['view_drivers', 'manage_drivers']);
        });

        Gate::define('menu_org_super_admin_group', function (User $user) {
            if (!$user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return $user->hasAnyPermission([
                'view_organization_dashboard',
                'view_organizations',
                'assign_drivers_to_organization',
            ]);
        });

        Gate::define('menu_org_super_admin_dashboard', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_organization_dashboard');
        });

        Gate::define('menu_org_super_admin_organizations', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_organizations');
        });

        Gate::define('menu_org_super_admin_assignments', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('assign_drivers_to_organization');
        });

        Gate::define('menu_org_admin_group', function (User $user) {
            if (!$user->hasRole(\App\Models\Role::ADMIN) || $user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return $user->hasAnyPermission([
                'view_organization_dashboard',
                'view_organizations',
                'assign_drivers_to_organization',
            ]);
        });

        Gate::define('menu_org_admin_dashboard', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_organization_dashboard');
        });

        Gate::define('menu_org_admin_organizations', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_organizations');
        });

        Gate::define('menu_org_admin_assignments', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('assign_drivers_to_organization');
        });

        Gate::define('menu_org_role_dashboard', function (User $user) {
            if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return ($user->hasRole(\App\Models\Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && $user->hasPermission('view_organization_dashboard');
        });

        Gate::define('menu_org_role_assignments', function (User $user) {
            if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return ($user->hasRole(\App\Models\Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && $user->hasPermission('assign_drivers_to_organization');
        });

        View::composer('admin.*', function ($view) {
            $view->with('headerActiveUsers', User::where('status', User::STATUS_ACTIVE)->count())
                ->with('headerInactiveUsers', User::where('status', User::STATUS_INACTIVE)->count())
                ->with('headerSuspendedUsers', User::where('status', User::STATUS_SUSPENDED)->count());
        });
    }
}
