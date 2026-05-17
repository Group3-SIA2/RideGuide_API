<?php

namespace App\Support;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;

class AccountLoginStatus
{
    public static function blockedLoginResponse(User $user): ?JsonResponse
    {
        if ($user->isAccountActive()) {
            return null;
        }

        if ($user->hasPendingDeletion()) {
            return response()->json([
                'success' => false,
                'code' => 'account_deletion_pending',
                'message' => 'Your account is scheduled for deletion. Sign in with your password on the restore screen to cancel deletion before the grace period ends.',
                'data' => app(AccountDeletionService::class)->deletionMeta($user),
            ], 403);
        }

        return response()->json([
            'success' => false,
            'message' => 'Your account is not active.',
        ], 403);
    }
}
