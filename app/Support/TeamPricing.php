<?php

namespace App\Support;

class TeamPricing
{
    public static function isValidHours(string $hours): bool
    {
        $parsed = self::parseHours($hours);

        return $parsed !== null && ($parsed[0] > 0 || $parsed[1] > 0);
    }

    public static function normalizeHours(string $hours): ?string
    {
        $parsed = self::parseHours($hours);

        if ($parsed === null) {
            return null;
        }

        return sprintf('%d:%02d', $parsed[0], $parsed[1]);
    }

    public static function composeHours(?string $hours, ?string $minutes): ?string
    {
        $hours = trim((string) $hours);
        $minutes = trim((string) $minutes);

        if ($hours === '' && $minutes === '') {
            return null;
        }

        if (($hours !== '' && preg_match('/^\d+$/', $hours) !== 1)
            || ($minutes !== '' && preg_match('/^\d+$/', $minutes) !== 1)) {
            return null;
        }

        $normalized = sprintf('%d:%02d', (int) ($hours === '' ? '0' : $hours), (int) ($minutes === '' ? '0' : $minutes));

        return self::normalizeHours($normalized);
    }

    private static function timeParts(string $totalHours): array
    {
        return self::parseHours($totalHours) ?? [0, 0];
    }

    private static function parseHours(string $totalHours): ?array
    {
        $totalHours = trim($totalHours);

        if ($totalHours === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $totalHours) === 1) {
            return [(int) $totalHours, 0];
        }

        if (preg_match('/^(\d+):(\d{1,2})$/', $totalHours, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($minutes < 0 || $minutes > 59) {
            return null;
        }

        return [$hours, $minutes];
    }
}
