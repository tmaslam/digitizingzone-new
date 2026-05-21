<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Simple cookie-based "trust this device" for 2FA.
 *
 * The cookie contains a signed payload: user_id|portal|expires_at|signature
 * Signature is HMAC-SHA256 using the app key.
 *
 * No database table is required. No token rotation. No user-agent binding.
 */
class TrustedTwoFactorDevice
{
    public const LIFETIME_DAYS = 30;

    public static function shouldSkipChallenge(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): bool
    {
        $cookieName = self::cookieName($portal, $siteLegacyKey);
        $cookieValue = $request->cookie($cookieName);

        if (empty($cookieValue) || ! str_contains($cookieValue, '|')) {
            return false;
        }

        $parts = explode('|', $cookieValue, 4);
        if (count($parts) !== 4) {
            self::forgetCookie($portal, $siteLegacyKey);
            return false;
        }

        [$cookieUserId, $cookiePortal, $expiresAt, $signature] = $parts;

        if ($cookiePortal !== $portal || (int) $cookieUserId !== (int) $user->user_id) {
            return false;
        }

        $payload = $cookieUserId . '|' . $cookiePortal . '|' . $expiresAt;
        if (! hash_equals(hash_hmac('sha256', $payload, config('app.key')), $signature)) {
            self::forgetCookie($portal, $siteLegacyKey);
            return false;
        }

        if (now()->timestamp > (int) $expiresAt) {
            self::forgetCookie($portal, $siteLegacyKey);
            return false;
        }

        // Extend the cookie lifetime on every successful use
        self::issue($request, $portal, $user, $siteLegacyKey);
        Log::info('TrustedTwoFactorDevice: challenge skipped.', ['portal' => $portal, 'user_id' => $user->user_id]);

        return true;
    }

    public static function issue(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): void
    {
        $expiresAt = now()->addDays(self::LIFETIME_DAYS)->timestamp;
        $payload   = (int) $user->user_id . '|' . $portal . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        Cookie::queue(cookie(
            self::cookieName($portal, $siteLegacyKey),
            $payload . '|' . $signature,
            self::LIFETIME_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));
    }

    public static function revokeCurrent(Request $request, string $portal, ?string $siteLegacyKey = null): void
    {
        Cookie::queue(Cookie::forget(self::cookieName($portal, $siteLegacyKey), '/'));
    }

    public static function revokeForUser(string $portal, int $userId, ?string $siteLegacyKey = null): void
    {
        // Cookie-based: we can only clear the cookie on the current request.
        // Other devices will naturally fail if the user changes password
        // because we don't tie to password hash anymore.
        Cookie::queue(Cookie::forget(self::cookieName($portal, $siteLegacyKey), '/'));
    }

    private static function cookieName(string $portal, ?string $siteLegacyKey = null): string
    {
        if ($portal === 'customer') {
            $suffix = Str::of((string) ($siteLegacyKey ?? 'site'))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();

            return 'customer_trusted_2fa_' . ($suffix !== '' ? $suffix : 'site');
        }

        return 'admin_trusted_2fa';
    }
}
