<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    private const DEFAULT_TTL_SECONDS = 300;

    public static function key(string $userId, string $activeRole): string
    {
        return sprintf('dashboard:%s:%s', $userId, strtolower($activeRole));
    }

    public static function ttlSeconds(): int
    {
        $configured = (int) config('cache.dashboard_ttl_seconds', self::DEFAULT_TTL_SECONDS);

        return $configured > 0 ? $configured : self::DEFAULT_TTL_SECONDS;
    }

    public static function forgetUserDashboards(string $userId): void
    {
        foreach (['admin', 'driver', 'commuter'] as $role) {
            Cache::forget(self::key($userId, $role));
        }
    }
}
