<?php

namespace App\Observers;

use App\Models\Driver;
use App\Support\DashboardCache;

class DashboardDriverObserver
{
    public function saved(Driver $driver): void
    {
        DashboardCache::forgetUserDashboards($driver->user_id);
        DashboardCache::forgetAdminDashboards();
    }

    public function deleted(Driver $driver): void
    {
        DashboardCache::forgetUserDashboards($driver->user_id);
        DashboardCache::forgetAdminDashboards();
    }

    public function restored(Driver $driver): void
    {
        DashboardCache::forgetUserDashboards($driver->user_id);
        DashboardCache::forgetAdminDashboards();
    }
}
