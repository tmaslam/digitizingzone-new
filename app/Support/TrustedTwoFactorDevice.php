<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrustedTwoFactorDevice
{
    public const TABLE = 'two_factor_trusted_devices';
    public const LIFETIME_DAYS = 30;

    public static function shouldSkipChallenge(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): bool
    {
        if (! Schema::hasTable(self::TABLE)) {
            Log::warning('TrustedTwoFactorDevice: table does not exist.', ['table' => self::TABLE, 'portal' => $portal]);

            return false;
        }

        [$selector, $validator] = self::cookieParts($request, $portal, $siteLegacyKey);

        if ($selector === null || $validator === null) {
            Log::info('TrustedTwoFactorDevice: no cookie present.', ['portal' => $portal, 'cookie_name' => self::cookieName($portal, $siteLegacyKey)]);

            return false;
        }

        $query = DB::table(self::TABLE)
            ->where('portal', $portal)
            ->where('user_id', (int) $user->user_id)
            ->where('selector', $selector)
            ->where('expires_at', '>=', now()->format('Y-m-d H:i:s'));

        if ($portal === 'customer') {
            $query->where('site_legacy_key', (string) ($siteLegacyKey ?? ''));
        } else {
            $query->where(function ($q) {
                $q->whereNull('site_legacy_key')->orWhere('site_legacy_key', '');
            });
        }

        $record = $query->first();

        if (! $record) {
            Log::info('TrustedTwoFactorDevice: no DB record found.', ['portal' => $portal, 'user_id' => $user->user_id, 'selector' => $selector]);
            self::forgetCookie($portal, $siteLegacyKey);

            return false;
        }

        $tokenValid = hash_equals((string) $record->token_hash, hash('sha256', $validator));
        $uaValid    = hash_equals((string) $record->user_agent_hash, self::userAgentHash($request));

        if (! $tokenValid || ! $uaValid) {
            Log::info('TrustedTwoFactorDevice: validation failed.', [
                'portal' => $portal,
                'user_id' => $user->user_id,
                'selector' => $selector,
                'token_valid' => $tokenValid,
                'ua_valid' => $uaValid,
                'stored_ua_hash' => $record->user_agent_hash,
                'request_ua_hash' => self::userAgentHash($request),
                'request_ua' => $request->userAgent(),
            ]);
            DB::table(self::TABLE)->where('id', $record->id)->delete();
            self::forgetCookie($portal, $siteLegacyKey);

            return false;
        }

        DB::table(self::TABLE)
            ->where('id', $record->id)
            ->update([
                'expires_at' => now()->addDays(self::LIFETIME_DAYS)->format('Y-m-d H:i:s'),
                'last_used_at' => now()->format('Y-m-d H:i:s'),
            ]);

        self::queueCookie($request, $portal, $siteLegacyKey, $selector, $validator);
        Log::info('TrustedTwoFactorDevice: challenge skipped.', ['portal' => $portal, 'user_id' => $user->user_id, 'selector' => $selector]);

        return true;
    }

    public static function issue(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Log::warning('TrustedTwoFactorDevice.issue: table does not exist.', ['table' => self::TABLE, 'portal' => $portal]);

            return;
        }

        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $now = now();

        // Remove any existing trusted device for this user/portal so we only
        // keep one active record per user (simpler and avoids queue churn).
        try {
            $query = DB::table(self::TABLE)
                ->where('portal', $portal)
                ->where('user_id', (int) $user->user_id);

            if ($portal === 'customer') {
                $query->where('site_legacy_key', (string) ($siteLegacyKey ?? ''));
            }

            $query->delete();
        } catch (\Throwable $e) {
            Log::error('TrustedTwoFactorDevice.issue: delete failed.', ['portal' => $portal, 'user_id' => $user->user_id, 'error' => $e->getMessage()]);
        }

        try {
            DB::table(self::TABLE)->insert([
                'portal' => $portal,
                'site_legacy_key' => $portal === 'customer' ? (string) ($siteLegacyKey ?? '') : '',
                'user_id' => (int) $user->user_id,
                'selector' => $selector,
                'token_hash' => hash('sha256', $validator),
                'user_agent_hash' => self::userAgentHash($request),
                'password_signature' => self::passwordSignature($user),
                'expires_at' => $now->copy()->addDays(self::LIFETIME_DAYS)->format('Y-m-d H:i:s'),
                'last_used_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error('TrustedTwoFactorDevice.issue: insert failed.', ['portal' => $portal, 'user_id' => $user->user_id, 'error' => $e->getMessage()]);

            return;
        }

        self::queueCookie($request, $portal, $siteLegacyKey, $selector, $validator);
        Log::info('TrustedTwoFactorDevice.issue: trusted device issued.', ['portal' => $portal, 'user_id' => $user->user_id, 'selector' => $selector, 'cookie_name' => self::cookieName($portal, $siteLegacyKey)]);
    }

    public static function revokeCurrent(Request $request, string $portal, ?string $siteLegacyKey = null): void
    {
        if (Schema::hasTable(self::TABLE)) {
            [$selector] = self::cookieParts($request, $portal, $siteLegacyKey);

            if ($selector !== null && $selector !== '') {
                $query = DB::table(self::TABLE)
                    ->where('portal', $portal)
                    ->where('selector', $selector);

                if ($portal === 'customer') {
                    $query->where('site_legacy_key', (string) ($siteLegacyKey ?? ''));
                }

                $query->delete();
            }
        }

        self::forgetCookie($portal, $siteLegacyKey);
    }

    public static function revokeForUser(string $portal, int $userId, ?string $siteLegacyKey = null): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $query = DB::table(self::TABLE)
            ->where('portal', $portal)
            ->where('user_id', $userId);

        if ($portal === 'customer') {
            $query->where('site_legacy_key', (string) ($siteLegacyKey ?? ''));
        }

        $query->delete();
    }

    public static function cookieName(string $portal, ?string $siteLegacyKey = null): string
    {
        if ($portal === 'customer') {
            $suffix = Str::of((string) ($siteLegacyKey ?? 'site'))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();

            return 'customer_trusted_2fa_'.($suffix !== '' ? $suffix : 'site');
        }

        return 'admin_trusted_2fa';
    }

    private static function queueCookie(Request $request, string $portal, ?string $siteLegacyKey, string $selector, string $validator): void
    {
        Cookie::queue(cookie(
            self::cookieName($portal, $siteLegacyKey),
            $selector.'|'.$validator,
            self::LIFETIME_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));
    }

    private static function forgetCookie(string $portal, ?string $siteLegacyKey): void
    {
        Cookie::queue(Cookie::forget(self::cookieName($portal, $siteLegacyKey), '/'));
    }

    private static function cookieParts(Request $request, string $portal, ?string $siteLegacyKey): array
    {
        $cookieValue = trim((string) $request->cookie(self::cookieName($portal, $siteLegacyKey), ''));

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return [null, null];
        }

        [$selector, $validator] = explode('|', $cookieValue, 2);

        if ($selector === '' || $validator === '') {
            return [null, null];
        }

        return [$selector, $validator];
    }

    private static function userAgentHash(Request $request): string
    {
        return hash('sha256', Str::limit((string) $request->userAgent(), 1000, ''));
    }

    private static function passwordSignature(AdminUser $user): string
    {
        return hash('sha256', implode('|', [
            trim((string) ($user->password_hash ?? '')),
            trim((string) ($user->user_password ?? '')),
            trim((string) ($user->password_migrated_at ?? '')),
        ]));
    }
}
