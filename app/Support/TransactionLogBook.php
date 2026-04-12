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

        $combinedMetadata = array_merge([
            'ip' => self::maskIp($request->ip()),
            'route' => optional($request->route())->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => self::maskUserAgent($request->userAgent()),
        ], $metadata);

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
            'metadata' => self::sanitizeMetadata($combinedMetadata),
        ]);
    }

    private static function sanitizeMetadata(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = self::sanitizeMetadata($itemValue, (string) $itemKey);
            }

            return $sanitized;
        }

        if (is_string($value) && str_contains($value, '@')) {
            return self::maskEmail($value);
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        $sensitive = [
            'password',
            'passcode',
            'otp',
            'token',
            'secret',
            'authorization',
            'cookie',
            'session',
            'credential',
            'private_key',
        ];

        foreach ($sensitive as $term) {
            if (str_contains($key, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function maskIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = 'x';
                return implode('.', $parts);
            }
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $last = count($parts) - 1;
            $parts[$last] = 'xxxx';
            return implode(':', $parts);
        }

        return '[redacted_ip]';
    }

    private static function maskUserAgent(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        return strlen($ua) > 120 ? substr($ua, 0, 120) . '...' : $ua;
    }

    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '[redacted]';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = strlen($local) <= 2
            ? str_repeat('*', strlen($local))
            : substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));

        return $maskedLocal . '@' . $domain;
    }
}