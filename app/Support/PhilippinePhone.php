<?php

namespace App\Support;

class PhilippinePhone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s\-()]/', '', $phone);

        if (str_starts_with($phone, '09')) {
            return '+63'.substr($phone, 1);
        }

        if (str_starts_with($phone, '639')) {
            return '+'.$phone;
        }

        return $phone;
    }
}
