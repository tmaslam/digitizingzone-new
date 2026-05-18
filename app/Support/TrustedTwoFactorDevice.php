<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrustedTwoFactorDevice
{
    public const TABLE = 'two_factor_trusted_devices';
    public const LIFETIME_DAYS = 30;

    public static function shouldSkipChallenge(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): bool
    {
        if (! Schema::hasTable(self::TABLE)) {
            return false;
        }

        [$selector, $validator] = self::cookieParts($request, $portal, $siteLegacyKey);

        if ($selector === null || $validator === null) {
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
            $query->whereNull('site_legacy_key');
        }

        $record = $query->first();

        if (! $record) {
            self::forgetCookie($portal, $siteLegacyKey);

            return false;
        }

        $valid = hash_equals((string) $record->token_hash, hash('sha256', $validator))
            && hash_equals((string) $record->user_agent_hash, self::userAgentHash($request))
            && hash_equals((string) $record->password_signature, self::passwordSignature($user));

        if (! $valid) {
            DB::table(self::TABLE)->where('id', $record->id)->delete();
            self::forgetCookie($portal, $siteLegacyKey);

            return false;
        }

        $newValidator = bin2hex(random_bytes(32));

        DB::table(self::TABLE)
            ->where('id', $record->id)
            ->update([
                'token_hash' => hash('sha256', $newValidator),
                'user_agent_hash' => self::userAgentHash($request),
                'password_signature' => self::passwordSignature($user),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS)->format('Y-m-d H:i:s'),
                'last_used_at' => now()->format('Y-m-d H:i:s'),
            ]);

        self::queueCookie($request, $portal, $siteLegacyKey, $selector, $newValidator);

        return true;
    }

    public static function issue(Request $request, string $portal, AdminUser $user, ?string $siteLegacyKey = null): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        self::revokeCurrent($request, $portal, $siteLegacyKey);

        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $now = now();

        DB::table(self::TABLE)->insert([
            'portal' => $portal,
            'site_legacy_key' => $portal === 'customer' ? (string) ($siteLegacyKey ?? '') : null,
            'user_id' => (int) $user->user_id,
            'selector' => $selector,
            'token_hash' => hash('sha256', $validator),
            'user_agent_hash' => self::userAgentHash($request),
            'password_signature' => self::passwordSignature($user),
            'expires_at' => $now->copy()->addDays(self::LIFETIME_DAYS)->format('Y-m-d H:i:s'),
            'last_used_at' => $now->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        self::queueCookie($request, $portal, $siteLegacyKey, $selector, $validator);
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
