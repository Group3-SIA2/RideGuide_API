<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\Organization;
use App\Support\DashboardCache;

class DashboardOrganizationObserver
{
    public function saved(Organization $organization): void
    {
        Driver::query()
            ->where('organization_id', $organization->id)
            ->pluck('user_id')
            ->unique()
            ->each(fn (string $userId) => DashboardCache::forgetUserDashboards($userId));

        DashboardCache::forgetAdminDashboards();
    }

    public function deleted(Organization $organization): void
    {
        $this->saved($organization);
    }

    public function restored(Organization $organization): void
    {
        $this->saved($organization);
    }
}
