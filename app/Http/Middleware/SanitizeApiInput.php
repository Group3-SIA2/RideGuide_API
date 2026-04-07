<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeApiInput
{
    private const PASSWORD_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->sanitizeValue($request->all()));

        return $next($request);
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $clean[$nestedKey] = $this->sanitizeValue($nestedValue, is_string($nestedKey) ? $nestedKey : null);
            }

            return $clean;
        }

        if (! is_string($value) || ($key && in_array($key, self::PASSWORD_KEYS, true))) {
            return $value;
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
        $value = trim($value);
        $value = strip_tags($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
