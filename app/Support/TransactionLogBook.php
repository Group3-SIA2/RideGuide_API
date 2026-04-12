<?php

namespace App\Support;

use App\Models\AdminTransactionLog;
use Illuminate\Http\Request;

class TransactionLogbook
{
    public static function write(
        Request $request,
        string $module,
        string $transactionType,
        string $status = 'success',
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        array $metadata = [],
        ?string $actorUserId = null,
        ?string $actorEmail = null
    ): void {
        $user = $request->user();

        AdminTransactionLog::create([
            'actor_user_id' => $actorUserId ?? $user?->id,
            'actor_email' => $actorEmail ?? $user?->email,
            'module' => $module,
            'transaction_type' => $transactionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => $status,
            'reason' => $reason,
            'before_data' => $before,
            'after_data' => $after,
            'metadata' => array_merge([
                'ip' => $request->ip(),
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ], $metadata),
        ]);
    }
}