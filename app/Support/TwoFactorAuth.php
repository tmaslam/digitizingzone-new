<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email-based two-factor authentication.
 *
 * Reuses the existing password-reset token tables — no separate table needed:
 *   admin    → admin_password_reset_tokens    (token_type = '2fa_code')
 *   customer → customer_password_reset_tokens (token_type = '2fa_code')
 *
 * The token_type column distinguishes 2FA rows from password-reset rows so
 * the two flows never interfere with each other.
 *
 * Codes are 6-digit numeric strings stored as SHA-256 hashes (same approach
 * as the existing password-reset selector/token_hash pattern).
 * Each code is single-use and expires after CODE_TTL_MINUTES.
 * Verify attempts are capped at MAX_ATTEMPTS; on lockout the row is deleted
 * and the user must restart the login.
 */
class TwoFactorAuth
{
    public const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS     = 5;

    private const TOKEN_TYPE = '2fa_code';

    // -----------------------------------------------------------------------
    // Code lifecycle
    // -----------------------------------------------------------------------

    /**
     * Generate a 6-digit code, write it to the appropriate token table, and
     * return the plain code so the caller can email it to the user.
     * Any previous 2FA row for this user is replaced.
     *
     * @param  string|null  $siteLegacyKey  Required for the customer portal
     *                                      (customer_password_reset_tokens has a
     *                                      NOT NULL site_legacy_key column).
     */
    public static function issueCode(string $portal, int $userId, ?string $siteLegacyKey = null): string
    {
        $table = self::table($portal);
        if (! $table || ! Schema::hasTable($table)) {
            return '';
        }

        $code      = (string) str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $userCol   = self::userColumn($portal);
        $expiresAt = now()->addMinutes(self::CODE_TTL_MINUTES)->format('Y-m-d H:i:s');
        $now       = now()->format('Y-m-d H:i:s');

        // One active 2FA code per user at a time.
        DB::table($table)
            ->where($userCol, $userId)
            ->where('token_type', self::TOKEN_TYPE)
            ->delete();

        // selector is NOT NULL + UNIQUE — generate a unique value even though
        // 2FA lookup uses user_id + token_type, not the selector.
        $row = [
            $userCol     => $userId,
            'selector'   => bin2hex(random_bytes(8)),
            'token_hash' => hash('sha256', $code),
            'token_type' => self::TOKEN_TYPE,
            'attempts'   => 0,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ];

        // customer_password_reset_tokens requires site_legacy_key (NOT NULL).
        if ($portal === 'customer') {
            $row['site_legacy_key'] = (string) ($siteLegacyKey ?? '');
        }

        DB::table($table)->insert($row);

        return $code;
    }

    /**
     * Verify a submitted code.
     *
     * Returns true  — correct (row deleted).
     * Returns false — wrong  (attempt counter incremented; null if now locked).
     * Returns null  — expired, not found, or attempt limit reached (row deleted).
     */
    public static function verifyCode(string $portal, int $userId, string $code): ?bool
    {
        $table = self::table($portal);
        if (! $table || ! Schema::hasTable($table)) {
            return null;
        }

        $userCol = self::userColumn($portal);

        $row = DB::table($table)
            ->where($userCol, $userId)
            ->where('token_type', self::TOKEN_TYPE)
            ->where('expires_at', '>=', now()->format('Y-m-d H:i:s'))
            ->first();

        if (! $row) {
            return null;
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            self::invalidate($portal, $userId);
            return null;
        }

        if (! hash_equals((string) $row->token_hash, hash('sha256', trim((string) $code)))) {
            $newAttempts = (int) $row->attempts + 1;

            if ($newAttempts >= self::MAX_ATTEMPTS) {
                self::invalidate($portal, $userId);
                return null;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update(['attempts' => $newAttempts]);

            return false;
        }

        // Correct — consume immediately.
        self::invalidate($portal, $userId);
        return true;
    }

    /**
     * Delete the 2FA row for this portal + user.
     */
    public static function invalidate(string $portal, int $userId): void
    {
        $table = self::table($portal);
        if (! $table || ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where(self::userColumn($portal), $userId)
            ->where('token_type', self::TOKEN_TYPE)
            ->delete();
    }

    /**
     * Remaining verify attempts before lockout.
     */
    public static function remainingAttempts(string $portal, int $userId): int
    {
        $table = self::table($portal);
        if (! $table || ! Schema::hasTable($table)) {
            return 0;
        }

        $row = DB::table($table)
            ->where(self::userColumn($portal), $userId)
            ->where('token_type', self::TOKEN_TYPE)
            ->first();

        return $row ? max(0, self::MAX_ATTEMPTS - (int) $row->attempts) : 0;
    }

    /**
     * Purge expired 2FA rows (both tables). Safe to call from a scheduled job.
     */
    public static function purgeExpired(): void
    {
        foreach (['admin', 'customer'] as $portal) {
            $table = self::table($portal);
            if ($table && Schema::hasTable($table)) {
                DB::table($table)
                    ->where('token_type', self::TOKEN_TYPE)
                    ->where('expires_at', '<', now()->format('Y-m-d H:i:s'))
                    ->delete();
            }
        }
    }

    // -----------------------------------------------------------------------
    // Email delivery
    // -----------------------------------------------------------------------

    public static function sendCode(string $email, string $recipientName, string $code, string $siteName): bool
    {
        $subject = $siteName.' — Your verification code';

        $body = '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Verification Code</title>
</head>
<body style="margin:0;padding:24px;font-family:Arial,sans-serif;color:#17212a;background:#ffffff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
<tr><td align="center">
<table role="presentation" width="640" cellspacing="0" cellpadding="0"
       style="width:100%;max-width:640px;border:1px solid #d9dee5;border-collapse:collapse;">
    <tr>
        <td style="padding:24px 28px;border-bottom:1px solid #d9dee5;">
            <div style="font-size:24px;font-weight:700;line-height:1.2;color:#17212a;">Verification Code</div>
            <div style="margin-top:6px;font-size:13px;color:#5b6773;">'.htmlspecialchars($siteName, ENT_QUOTES).'</div>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;line-height:1.65;font-size:14px;color:#17212a;">
            <p style="margin-top:0;">Hello '.htmlspecialchars($recipientName, ENT_QUOTES).',</p>
            <p>Use the code below to complete your sign-in. It expires in '.self::CODE_TTL_MINUTES.' minutes and can only be used once.</p>
            <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:28px 0;">
            <tr>
                <td style="padding:16px 28px;background:#f4f6f8;border:1px solid #d9dee5;font-size:36px;font-weight:900;letter-spacing:8px;color:#17212a;font-family:monospace;">'.htmlspecialchars($code, ENT_QUOTES).'</td>
            </tr>
            </table>
            <p>If you did not attempt to sign in, please contact support immediately — someone may be trying to access your account.</p>
            <p style="margin-bottom:0;color:#5b6773;font-size:13px;">
                This code was sent to '.htmlspecialchars($email, ENT_QUOTES).'. Do not share it with anyone.
            </p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>';

        return PortalMailer::sendHtml($email, $subject, $body);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private static function table(string $portal): ?string
    {
        return match ($portal) {
            'admin'    => 'admin_password_reset_tokens',
            'customer' => 'customer_password_reset_tokens',
            default    => null,
        };
    }

    private static function userColumn(string $portal): string
    {
        return match ($portal) {
            'admin'    => 'admin_user_id',
            'customer' => 'customer_user_id',
            default    => 'customer_user_id',
        };
    }
}
