<?php

namespace App\Providers;

use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);

        View::composer('admin.*', function ($view) {
            $view->with('headerActiveUsers', User::where('status', User::STATUS_ACTIVE)->count())
                ->with('headerInactiveUsers', User::where('status', User::STATUS_INACTIVE)->count())
                ->with('headerSuspendedUsers', User::where('status', User::STATUS_SUSPENDED)->count());
        });
    }
}
