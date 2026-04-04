<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\Commuter;
use App\Models\LicenseId;
use App\Models\LicenseImage;
use App\Models\Organization;
use App\Models\Permission;
use App\Observers\DashboardCommuterObserver;
use App\Observers\DashboardDriverObserver;
use App\Observers\DashboardLicenseObserver;
use App\Observers\DashboardOrganizationObserver;
use App\Observers\DashboardUserObserver;
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
        User::observe(DashboardUserObserver::class);
        Driver::observe(DashboardDriverObserver::class);
        Commuter::observe(DashboardCommuterObserver::class);
        Organization::observe(DashboardOrganizationObserver::class);
        LicenseId::observe(DashboardLicenseObserver::class);
        LicenseImage::observe(DashboardLicenseObserver::class);

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
                'view_organization_assignments',
                'manage_organization_types',
                'view_fare_rates',
                'manage_fare_rates',
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
                && $user->hasAnyPermission([
                    'view_organization_assignments',
                    'assign_drivers_to_organization',
                    'unassign_drivers_from_organization',
                    'assign_organization_terminals',
                    'create_organization_terminals',
                    'delete_organization_terminals',
                    'manage_organization_terminals',
                ]);
        });

        Gate::define('menu_org_super_admin_fares', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_fare_rates', 'manage_fare_rates']);
        });

        Gate::define('menu_org_super_admin_types', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_organization_types');
        });

        Gate::define('menu_org_admin_group', function (User $user) {
            if (!$user->hasRole(\App\Models\Role::ADMIN) || $user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return $user->hasAnyPermission([
                'view_organization_dashboard',
                'view_organizations',
                'assign_drivers_to_organization',
                'view_organization_assignments',
                'manage_organization_types',
                'view_fare_rates',
                'manage_fare_rates',
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
                && $user->hasAnyPermission([
                    'view_organization_assignments',
                    'assign_drivers_to_organization',
                    'unassign_drivers_from_organization',
                    'assign_organization_terminals',
                    'create_organization_terminals',
                    'delete_organization_terminals',
                    'manage_organization_terminals',
                ]);
        });

        Gate::define('menu_org_admin_fares', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_fare_rates', 'manage_fare_rates']);
        });

        Gate::define('menu_org_admin_types', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_organization_types');
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
                && $user->hasAnyPermission([
                    'view_organization_assignments',
                    'assign_drivers_to_organization',
                    'unassign_drivers_from_organization',
                    'assign_organization_terminals',
                    'create_organization_terminals',
                    'delete_organization_terminals',
                    'manage_organization_terminals',
                ]);
        });

        Gate::define('menu_org_role_fares', function (User $user) {
            if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
                return false;
            }

            return ($user->hasRole(\App\Models\Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && $user->hasAnyPermission(['view_fare_rates', 'manage_fare_rates']);
        });

        Gate::define('menu_super_admin_users', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_users');
        });

        Gate::define('menu_admin_users', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_users');
        });

        Gate::define('menu_super_admin_commuters', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_commuters', 'manage_commuters']);
        });

        Gate::define('menu_admin_commuters', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_commuters', 'manage_commuters']);
        });

        Gate::define('menu_super_admin_drivers', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_drivers', 'manage_drivers']);
        });

        Gate::define('menu_admin_drivers', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasAnyPermission(['view_drivers', 'manage_drivers']);
        });

        Gate::define('menu_super_admin_user_management', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_users');
        });

        Gate::define('menu_admin_user_management', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_users');
        });

        Gate::define('menu_super_admin_user_authorization', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_authorization');
        });

        Gate::define('menu_admin_user_authorization', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('manage_authorization');
        });

        Gate::define('menu_super_admin_profile', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN);
        });

        Gate::define('menu_admin_profile', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN);
        });

        Gate::define('menu_org_profile', function (User $user) {
            return ($user->hasRole(\App\Models\Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && !$user->hasRole(\App\Models\Role::ADMIN);
        });

        Gate::define('menu_super_admin_backups', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_backups');
        });

        Gate::define('menu_admin_backups', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_backups');
        });

        Gate::define('menu_super_admin_transactions', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_transactions');
        });

        Gate::define('menu_admin_transactions', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && $user->hasPermission('view_transactions');
        });

        Gate::define('menu_super_admin_logout', function (User $user) {
            return $user->hasRole(\App\Models\Role::SUPER_ADMIN);
        });

        Gate::define('menu_admin_logout', function (User $user) {
            return $user->hasRole(\App\Models\Role::ADMIN)
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN);
        });

        Gate::define('menu_org_logout', function (User $user) {
            return ($user->hasRole(\App\Models\Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && !$user->hasRole(\App\Models\Role::SUPER_ADMIN)
                && !$user->hasRole(\App\Models\Role::ADMIN);
        });

        View::composer('admin.*', function ($view) {
            $view->with('headerActiveUsers', User::where('status', User::STATUS_ACTIVE)->count())
                ->with('headerInactiveUsers', User::where('status', User::STATUS_INACTIVE)->count())
                ->with('headerSuspendedUsers', User::where('status', User::STATUS_SUSPENDED)->count());
        });
    }
}
