<?php

namespace App\Observers;

use App\Models\Commuter;
use App\Support\DashboardCache;

class DashboardCommuterObserver
{
    public function saved(Commuter $commuter): void
    {
        DashboardCache::forgetUserDashboards($commuter->user_id);
        DashboardCache::forgetAdminDashboards();
    }

    public function deleted(Commuter $commuter): void
    {
        DashboardCache::forgetUserDashboards($commuter->user_id);
        DashboardCache::forgetAdminDashboards();
    }

    public function restored(Commuter $commuter): void
    {
        DashboardCache::forgetUserDashboards($commuter->user_id);
        DashboardCache::forgetAdminDashboards();
    }
}
