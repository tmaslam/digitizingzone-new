<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CustomerPublicRateLimit
{
    public static function tooManyAttempts(Request $request, string $scope, string $siteKey, string $identity, int $maxAttempts, int $decaySeconds): bool
    {
        $key = self::key($request, $scope, $siteKey, $identity);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, $decaySeconds);

        return false;
    }

    public static function clear(Request $request, string $scope, string $siteKey, string $identity): void
    {
        RateLimiter::clear(self::key($request, $scope, $siteKey, $identity));
    }

    private static function key(Request $request, string $scope, string $siteKey, string $identity): string
    {
        return implode('|', [
            'customer-public',
            strtolower(trim($scope)),
            strtolower(trim($siteKey)),
            strtolower(trim($identity)),
            $request->ip() ?? '127.0.0.1',
        ]);
    }
}
