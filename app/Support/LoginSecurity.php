<?php

namespace App\Support;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoginSecurity
{
    public const MAX_ATTEMPTS = 4;
    public const WINDOW_SECONDS = 600;
    private const TEMPORARY_LOCK_WINDOWS = [
        900,
        3600,
    ];
    private const ESCALATION_MEMORY_SECONDS = 2592000;

    public static function rateLimitMessage(?int $seconds = null): string
    {
        if ($seconds !== null && $seconds > 0) {
            return 'Too many login attempts. Please try again in '.self::formatDuration($seconds).'.';
        }

        return 'Too many login attempts. Please try again later.';
    }

    public static function unavailableAccountMessage(): string
    {
        return 'This account is unavailable. Please contact administrator.';
    }

    public static function recordAttempt(
        Request $request,
        string $loginName,
        string $status,
        string $outcome,
        ?AdminUser $user = null,
        bool $isRateLimited = false
    ): void {
        // Keep writing to the legacy audit tables so existing admin tools remain intact,
        // while also sending structured events to the new security audit stream.
        if (Schema::hasTable('login_history')) {
            DB::table('login_history')->insert([
                'IP_Address' => $request->ip() ?? '127.0.0.1',
                'Login_Name' => $loginName,
                'Password' => '',
                'Status' => $status,
                'Date_Added' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        SecurityAudit::recordAuthAttempt(
            $request,
            $loginName,
            $status,
            $outcome,
            $user,
            $isRateLimited
        );

        if (! Schema::hasTable('admin_login_attempts')) {
            return;
        }

        DB::table('admin_login_attempts')->insert([
            'login_name' => $loginName,
            'matched_user_id' => $user?->user_id,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'request_path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
            'attempt_outcome' => $outcome,
            'status' => $status,
            'is_rate_limited' => $isRateLimited ? 1 : 0,
            'attempted_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public static function handleRateLimit(Request $request, string $loginName, string $portalLabel, ?AdminUser $user = null): bool
    {
        self::recordAttempt(
            $request,
            $loginName,
            'Rate limited '.$portalLabel.' login attempt',
            'locked',
            $user,
            true
        );

        if (! $user || (int) $user->is_active !== 1) {
            return false;
        }

        $history = self::rememberLockoutEscalation($request, $loginName, $portalLabel, $user);
        $stage = (int) ($history['count'] ?? 0);

        if ($stage <= count(self::TEMPORARY_LOCK_WINDOWS)) {
            self::applyTemporaryLock($request, $loginName, $portalLabel, $user, $stage);

            return false;
        }

        $user->update([
            'is_active' => 0,
        ]);

        self::recordAttempt(
            $request,
            $loginName,
            'Account permanently locked after repeated '.$portalLabel.' login lockouts',
            'permanent_lock',
            $user,
            true
        );

        self::sendPermanentLockAlert($request, $portalLabel, $user);

        return true;
    }

    public static function activeLockMessage(Request $request, string $loginName, string $portalLabel, ?AdminUser $user = null): ?string
    {
        if (! $user || (int) $user->is_active !== 1) {
            return null;
        }

        $lock = Cache::get(self::temporaryLockCacheKey($user));
        if (! is_array($lock)) {
            return null;
        }

        $lockedUntil = trim((string) ($lock['locked_until'] ?? ''));
        if ($lockedUntil === '') {
            Cache::forget(self::temporaryLockCacheKey($user));

            return null;
        }

        $remainingSeconds = max(0, now()->diffInSeconds($lockedUntil, false));
        if ($remainingSeconds <= 0) {
            Cache::forget(self::temporaryLockCacheKey($user));

            return null;
        }

        self::recordAttempt(
            $request,
            $loginName,
            'Temporarily blocked '.$portalLabel.' login while account lockout is active',
            'blocked',
            $user,
            true
        );

        return self::rateLimitMessage($remainingSeconds);
    }

    public static function clearSecurityState(?AdminUser $user): void
    {
        if (! $user) {
            return;
        }

        Cache::forget(self::temporaryLockCacheKey($user));
        Cache::forget(self::escalationCacheKey($user));
    }

    private static function sendPermanentLockAlert(Request $request, string $portalLabel, AdminUser $user): void
    {
        $recipient = (string) config('mail.admin_alert_address', '');

        if ($recipient === '') {
            return;
        }

        $history = Cache::get(self::escalationCacheKey($user), []);
        $ipAddresses = collect((array) ($history['ip_addresses'] ?? []))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->implode(', ');

        $subject = '['.config('app.name', 'Admin Portal').'] Account locked after repeated login attacks';
        $body = implode("\n", [
            'A '.$portalLabel.' account has been permanently locked after repeated login lockouts.',
            '',
            'Portal: '.$portalLabel,
            'User ID: '.$user->user_id,
            'User Name: '.$user->user_name,
            'Email: '.($user->user_email ?: '-'),
            'IP Address: '.($request->ip() ?? '127.0.0.1'),
            'Observed Source IPs: '.($ipAddresses !== '' ? $ipAddresses : ($request->ip() ?? '127.0.0.1')),
            'Request Path: /'.ltrim($request->path(), '/'),
            'Time: '.now()->format('Y-m-d H:i:s'),
            '',
            'Please review this account in the admin portal before reactivating it.',
        ]);

        PortalMailer::sendText($recipient, $subject, $body);
    }

    private static function rememberLockoutEscalation(Request $request, string $loginName, string $portalLabel, AdminUser $user): array
    {
        $cacheKey = self::escalationCacheKey($user);
        $history = Cache::get($cacheKey);

        if (! is_array($history)) {
            $history = [
                'count' => 0,
                'ip_addresses' => [],
            ];
        }

        $history['count'] = min(((int) ($history['count'] ?? 0)) + 1, count(self::TEMPORARY_LOCK_WINDOWS) + 1);
        $history['portal'] = $portalLabel;
        $history['last_login_name'] = $loginName;
        $history['last_attempt_at'] = now()->format('Y-m-d H:i:s');
        $history['ip_addresses'] = collect(array_merge(
            (array) ($history['ip_addresses'] ?? []),
            [$request->ip() ?? '127.0.0.1']
        ))->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->values()
            ->all();

        Cache::put($cacheKey, $history, now()->addSeconds(self::ESCALATION_MEMORY_SECONDS));

        SecurityAudit::record($request, 'auth.lockout_escalated', 'Login lockout escalated for an account.', [
            'login_name' => $loginName,
            'portal' => $portalLabel,
            'matched_user_id' => $user->user_id,
            'stage' => $history['count'],
            'ip_addresses' => $history['ip_addresses'],
        ], $history['count'] > count(self::TEMPORARY_LOCK_WINDOWS) ? 'critical' : 'warning', [
            'actor_user_id' => $user->user_id,
            'actor_login' => $loginName,
        ]);

        return $history;
    }

    private static function applyTemporaryLock(Request $request, string $loginName, string $portalLabel, AdminUser $user, int $stage): void
    {
        $seconds = self::TEMPORARY_LOCK_WINDOWS[$stage - 1] ?? self::TEMPORARY_LOCK_WINDOWS[array_key_last(self::TEMPORARY_LOCK_WINDOWS)];

        Cache::put(self::temporaryLockCacheKey($user), [
            'stage' => $stage,
            'locked_until' => now()->addSeconds($seconds)->format('Y-m-d H:i:s'),
            'seconds' => $seconds,
            'portal' => $portalLabel,
            'login_name' => $loginName,
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ], now()->addSeconds($seconds));

        self::recordAttempt(
            $request,
            $loginName,
            'Temporarily locked '.$portalLabel.' account for '.self::formatDuration($seconds).' after repeated login attempts',
            'blocked',
            $user,
            true
        );
    }

    private static function temporaryLockCacheKey(AdminUser $user): string
    {
        return 'login-security:temporary-lock:'.$user->user_id;
    }

    private static function escalationCacheKey(AdminUser $user): string
    {
        return 'login-security:escalation:'.$user->user_id;
    }

    private static function formatDuration(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        if ($minutes % 60 === 0 && $minutes >= 60) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '1 hour' : $hours.' hours';
        }

        return $minutes === 1 ? '1 minute' : $minutes.' minutes';
    }
}
