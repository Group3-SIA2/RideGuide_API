<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class InputValidation
{
    public static function nameRequiredRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[\p{L}\s]+$/u'];
    }

    public static function nameNullableRules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s]+$/u'];
    }

    public static function passwordRequiredRules(): array
    {
        return ['required', 'string', Password::min(8)->mixedCase()->symbols()];
    }

    public static function passwordConfirmedRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->symbols()];
    }

    public static function safeSearchRules(int $max = 120): array
    {
        return ['nullable', 'string', "max:{$max}", 'regex:/^[\p{L}\p{N}\s@._,\-]+$/u'];
    }

    public static function safeStringRules(bool $required = false, int $max = 255): array
    {
        $prefix = $required ? ['required'] : ['nullable'];

        return [...$prefix, 'string', "max:{$max}"];
    }
}
