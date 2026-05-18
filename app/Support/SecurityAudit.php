<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\SecurityAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SecurityAudit
{
    private static ?bool $tableAvailable = null;

    public static function refreshTableAvailability(): void
    {
        self::$tableAvailable = null;
    }

    public static function record(
        ?Request $request,
        string $eventType,
        string $message,
        array $details = [],
        string $severity = 'warning',
        array $overrides = []
    ): void {
        // Every noteworthy security event should go through one path so the app
        // keeps a consistent paper trail in both Laravel logs and the optional DB table.
        $severity = self::normalizeSeverity($severity);
        $payload = self::payload($request, $eventType, $message, $details, $severity, $overrides);

        self::writeLog($severity, $payload);
        self::writeDatabase($payload);
    }

    public static function recordAuthAttempt(
        Request $request,
        string $loginName,
        string $status,
        string $outcome,
        ?AdminUser $user = null,
        bool $isRateLimited = false
    ): void {
        $eventType = match ($outcome) {
            'success' => 'auth.login_succeeded',
            'blocked' => 'auth.login_blocked',
            'locked' => 'auth.login_rate_limited',
            'permanent_lock' => 'auth.account_locked',
            default => 'auth.login_failed',
        };

        $severity = match ($outcome) {
            'success' => 'info',
            'permanent_lock' => 'critical',
            'blocked', 'locked' => 'warning',
            default => 'notice',
        };

        self::record($request, $eventType, $status, [
            'login_name' => $loginName,
            'outcome' => $outcome,
            'matched_user_id' => $user?->user_id,
            'matched_role' => $user?->role_label,
            'is_rate_limited' => $isRateLimited,
        ], $severity, [
            'actor_user_id' => $user?->user_id,
            'actor_login' => $loginName,
        ]);
    }

    public static function recordUnauthorizedAccess(
        Request $request,
        string $message,
        array $details = [],
        string $severity = 'warning'
    ): void {
        self::record($request, 'auth.unauthorized_access', $message, $details, $severity);
    }

    public static function recordFileAccessDenied(
        Request $request,
        string $message,
        array $details = []
    ): void {
        self::record($request, 'files.access_denied', $message, $details, 'warning');
    }

    public static function recordUploadRejected(
        Request $request,
        string $profile,
        string $message,
        array $details = []
    ): void {
        self::record($request, 'files.upload_rejected', $message, array_merge($details, [
            'upload_profile' => $profile,
        ]), 'warning');
    }

    public static function recordBotVerificationFailure(
        Request $request,
        string $context,
        string $message,
        array $details = [],
        string $severity = 'warning'
    ): void {
        self::record($request, 'bot.turnstile_failed', $message, array_merge($details, [
            'turnstile_context' => $context,
        ]), $severity);
    }

    private static function payload(
        ?Request $request,
        string $eventType,
        string $message,
        array $details,
        string $severity,
        array $overrides
    ): array {
        $actor = self::resolveActor($request);
        $siteKey = $overrides['site_legacy_key'] ?? self::resolveSiteKey($request);
        $portal = $overrides['portal'] ?? self::resolvePortal($request);
        $path = $request ? '/'.ltrim($request->path(), '/') : null;

        return [
            'event_type' => Str::limit(trim($eventType), 80, ''),
            'severity' => $severity,
            'portal' => Str::limit((string) $portal, 30, ''),
            'site_legacy_key' => $siteKey !== '' ? Str::limit($siteKey, 100, '') : null,
            'actor_user_id' => $overrides['actor_user_id'] ?? $actor['user_id'],
            'actor_login' => Str::limit((string) ($overrides['actor_login'] ?? $actor['login']), 150, ''),
            'ip_address' => Str::limit((string) ($request?->ip() ?? '127.0.0.1'), 45, ''),
            'user_agent' => Str::limit((string) ($request?->userAgent() ?? ''), 255, ''),
            'request_path' => Str::limit((string) ($overrides['request_path'] ?? $path ?? ''), 255, ''),
            'request_method' => Str::upper(Str::limit((string) ($request?->method() ?? ($overrides['request_method'] ?? 'CLI')), 10, '')),
            'message' => Str::limit(trim($message), 255, ''),
            'details_json' => self::cleanDetails($details),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private static function resolveActor(?Request $request): array
    {
        if (! $request) {
            return ['user_id' => null, 'login' => null];
        }

        $admin = $request->attributes->get('adminUser');
        if ($admin instanceof AdminUser) {
            return ['user_id' => (int) $admin->user_id, 'login' => (string) $admin->user_name];
        }

        $team = $request->attributes->get('teamUser');
        if ($team instanceof AdminUser) {
            return ['user_id' => (int) $team->user_id, 'login' => (string) $team->user_name];
        }

        $customer = $request->attributes->get('customerUser');
        if ($customer instanceof AdminUser) {
            return ['user_id' => (int) $customer->user_id, 'login' => (string) ($customer->user_email ?: $customer->user_name)];
        }

        return [
            'user_id' => (int) ($request->session()->get('admin_user_id')
                ?: $request->session()->get('team_user_id')
                ?: $request->session()->get('customer_user_id')
                ?: 0) ?: null,
            'login' => (string) ($request->session()->get('admin_user_name')
                ?: $request->session()->get('team_user_name')
                ?: $request->session()->get('customer_user_name')
                ?: ''),
        ];
    }

    private static function resolveSiteKey(?Request $request): string
    {
        if (! $request) {
            return '';
        }

        $site = $request->attributes->get('siteContext');
        if ($site instanceof SiteContext) {
            return $site->legacyKey;
        }

        return trim((string) $request->session()->get('customer_site_key', ''));
    }

    private static function resolvePortal(?Request $request): string
    {
        if (! $request) {
            return 'system';
        }

        $path = '/'.ltrim($request->path(), '/');

        return match (true) {
            str_starts_with($path, '/v'),
            str_starts_with($path, '/admin') => 'admin',
            str_starts_with($path, '/team') => 'team',
            self::resolveSiteKey($request) !== '' => 'customer',
            default => 'public',
        };
    }

    private static function normalizeSeverity(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' => strtolower(trim($severity)),
            default => 'warning',
        };
    }

    private static function cleanDetails(array $details): array
    {
        $json = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return ['serialization_error' => true];
        }

        if (strlen($json) <= 6000) {
            return $details;
        }

        return [
            'truncated' => true,
            'preview' => Str::limit($json, 5900, '...'),
        ];
    }

    private static function writeLog(string $severity, array $payload): void
    {
        match ($severity) {
            'debug' => Log::debug('Security audit event.', $payload),
            'info' => Log::info('Security audit event.', $payload),
            'notice' => Log::notice('Security audit event.', $payload),
            'warning' => Log::warning('Security audit event.', $payload),
            'critical' => Log::critical('Security audit event.', $payload),
            'alert' => Log::alert('Security audit event.', $payload),
            'emergency' => Log::emergency('Security audit event.', $payload),
            default => Log::error('Security audit event.', $payload),
        };
    }

    private static function writeDatabase(array $payload): void
    {
        try {
            if (! self::tableAvailable()) {
                return;
            }

            SecurityAuditEvent::query()->create($payload);
        } catch (\Throwable $exception) {
            Log::error('Security audit database write failed.', [
                'event_type' => $payload['event_type'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function tableAvailable(): bool
    {
        if (self::$tableAvailable !== null) {
            return self::$tableAvailable;
        }

        try {
            self::$tableAvailable = Schema::hasTable('security_audit_events');
        } catch (\Throwable) {
            self::$tableAvailable = false;
        }

        return self::$tableAvailable;
    }
}
