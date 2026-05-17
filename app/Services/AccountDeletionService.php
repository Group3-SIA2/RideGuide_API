<?php

namespace App\Services;

use App\Models\User;
use App\Support\DashboardCache;
use Illuminate\Support\Carbon;

class AccountDeletionService
{
    public const GRACE_DAYS = 30;

    public function requestDeletion(User $user): User
    {
        $requestedAt = now();
        $scheduledAt = $requestedAt->copy()->addDays(self::GRACE_DAYS);

        $user->forceFill([
            'status' => User::STATUS_INACTIVE,
            'status_reason' => User::STATUS_REASON_USER_DELETION,
            'status_changed_at' => $requestedAt,
            'deletion_requested_at' => $requestedAt,
            'deletion_scheduled_at' => $scheduledAt,
        ])->save();

        $user->tokens()->delete();
        DashboardCache::forgetUserDashboards($user->id);

        return $user->fresh();
    }

    public function cancelDeletion(User $user): User
    {
        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'status_reason' => null,
            'status_changed_at' => now(),
            'deletion_requested_at' => null,
            'deletion_scheduled_at' => null,
        ])->save();

        DashboardCache::forgetUserDashboards($user->id);

        return $user->fresh();
    }

    public function purgeExpiredDeletions(): int
    {
        $count = 0;

        User::query()
            ->where('status', User::STATUS_INACTIVE)
            ->where('status_reason', User::STATUS_REASON_USER_DELETION)
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->each(function (User $user) use (&$count) {
                $user->tokens()->delete();
                $user->forceDelete();
                $count++;
            });

        return $count;
    }

    public function deletionMeta(User $user): ?array
    {
        if (!$user->hasPendingDeletion()) {
            return null;
        }

        $scheduledAt = $user->deletion_scheduled_at
            ? Carbon::parse($user->deletion_scheduled_at)
            : null;

        $daysRemaining = $scheduledAt
            ? max(0, (int) now()->diffInDays($scheduledAt, false))
            : null;

        return [
            'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
            'deletion_scheduled_at' => $scheduledAt?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'grace_days' => self::GRACE_DAYS,
        ];
    }
}
