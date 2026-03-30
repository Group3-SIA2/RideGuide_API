<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\LicenseId;
use App\Models\LicenseImage;
use App\Support\DashboardCache;

class DashboardLicenseObserver
{
    public function saved($model): void
    {
        $this->invalidateForLicenseModel($model);
    }

    public function deleted($model): void
    {
        $this->invalidateForLicenseModel($model);
    }

    public function restored($model): void
    {
        $this->invalidateForLicenseModel($model);
    }

    private function invalidateForLicenseModel($model): void
    {
        if ($model instanceof LicenseId) {
            $licenseIds = [$model->id];
        } elseif ($model instanceof LicenseImage) {
            $licenseIds = LicenseId::query()
                ->where('image_id', $model->id)
                ->pluck('id')
                ->all();
        } else {
            return;
        }

        if (empty($licenseIds)) {
            DashboardCache::forgetAdminDashboards();

            return;
        }

        Driver::query()
            ->whereIn('driver_license_id', $licenseIds)
            ->pluck('user_id')
            ->unique()
            ->each(fn (string $userId) => DashboardCache::forgetUserDashboards($userId));

        DashboardCache::forgetAdminDashboards();
    }
}
