<?php

namespace App\Support;

class LegacyDate
{
    public static function normalize(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return $value;
    }

    public static function display(mixed $value, string $fallback = '-'): string
    {
        return self::normalize($value) ?: $fallback;
    }
}
