<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerRememberLogin
{
    public const COOKIE_NAME = 'customer_remember_login';
    public const TABLE = 'customer_remember_tokens';
    private const LIFETIME_DAYS = 30;

    public static function issue(Request $request, SiteContext $site, AdminUser $customer): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $now = now();
        $expiresAt = $now->copy()->addDays(self::LIFETIME_DAYS);

        DB::table(self::TABLE)
            ->where('site_legacy_key', $site->legacyKey)
            ->where('customer_user_id', $customer->user_id)
            ->delete();

        DB::table(self::TABLE)->insert([
            'site_id' => $site->id,
            'site_legacy_key' => $site->legacyKey,
            'customer_user_id' => $customer->user_id,
            'selector' => $selector,
            'token_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'last_used_at' => $now->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        Cookie::queue(cookie(
            self::COOKIE_NAME,
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

    public static function restore(Request $request, SiteContext $site): ?AdminUser
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $cookieValue = trim((string) $request->cookie(self::COOKIE_NAME, ''));

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return null;
        }

        [$selector, $validator] = explode('|', $cookieValue, 2);

        if ($selector === '' || $validator === '') {
            self::forget();

            return null;
        }

        $record = DB::table(self::TABLE)
            ->where('site_legacy_key', $site->legacyKey)
            ->where('selector', $selector)
            ->where('expires_at', '>=', now()->format('Y-m-d H:i:s'))
            ->first();

        if (! $record || ! hash_equals((string) $record->token_hash, hash('sha256', $validator))) {
            if ($record) {
                DB::table(self::TABLE)->where('selector', $selector)->delete();
            }

            self::forget();

            return null;
        }

        $customer = AdminUser::query()
            ->customers()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $record->customer_user_id)
            ->first();

        if (! $customer) {
            DB::table(self::TABLE)->where('selector', $selector)->delete();
            self::forget();

            return null;
        }

        $newValidator = bin2hex(random_bytes(32));

        DB::table(self::TABLE)
            ->where('selector', $selector)
            ->update([
                'token_hash' => hash('sha256', $newValidator),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS)->format('Y-m-d H:i:s'),
                'last_used_at' => now()->format('Y-m-d H:i:s'),
            ]);

        Cookie::queue(cookie(
            self::COOKIE_NAME,
            $selector.'|'.$newValidator,
            self::LIFETIME_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));

        $request->session()->regenerate();
        $request->session()->put([
            'customer_user_id' => (int) $customer->user_id,
            'customer_user_name' => (string) $customer->display_name,
            'customer_site_key' => $site->legacyKey,
        ]);

        LoginSecurity::recordAttempt($request, $customer->user_name, 'Customer remember-me login', 'success', $customer);

        return $customer;
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME, '/'));
    }

    public static function clearCurrent(Request $request): void
    {
        if (Schema::hasTable(self::TABLE)) {
            $cookieValue = trim((string) $request->cookie(self::COOKIE_NAME, ''));

            if ($cookieValue !== '' && str_contains($cookieValue, '|')) {
                [$selector] = explode('|', $cookieValue, 2);

                if ($selector !== '') {
                    DB::table(self::TABLE)->where('selector', $selector)->delete();
                }
            }
        }

        self::forget();
    }
}
