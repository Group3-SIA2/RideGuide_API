<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Permission;
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
        Gate::policy(Organization::class, OrganizationPolicy::class);

        Gate::before(function (User $user) {
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

        View::composer('admin.*', function ($view) {
            $view->with('headerActiveUsers', User::where('status', User::STATUS_ACTIVE)->count())
                ->with('headerInactiveUsers', User::where('status', User::STATUS_INACTIVE)->count())
                ->with('headerSuspendedUsers', User::where('status', User::STATUS_SUSPENDED)->count());
        });
    }
}
