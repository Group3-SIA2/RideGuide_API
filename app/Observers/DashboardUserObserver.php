<?php

namespace App\Observers;

use App\Models\User;
use App\Support\DashboardCache;

class DashboardUserObserver
{
    public function saved(User $user): void
    {
        DashboardCache::forgetUserDashboards($user->id);
        DashboardCache::forgetAdminDashboards();
    }

    public function deleted(User $user): void
    {
        DashboardCache::forgetUserDashboards($user->id);
        DashboardCache::forgetAdminDashboards();
    }

    public function restored(User $user): void
    {
        DashboardCache::forgetUserDashboards($user->id);
        DashboardCache::forgetAdminDashboards();
    }
}
