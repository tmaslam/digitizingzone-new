<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\LoginSecurity;
use App\Support\PasswordManager;
use App\Support\PortalMailer;
use App\Support\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class AdminPasswordController extends Controller
{
    private const TABLE = 'admin_password_reset_tokens';

    public function showForgot()
    {
        $this->cleanupExpiredTokens();

        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $this->cleanupExpiredTokens();

        $request->validate([
            'identity' => ['required', 'string', 'max:255'],
        ], [], [
            'identity' => 'username or email',
        ]);

        if (! TurnstileVerifier::verify($request, 'admin-password-reset')) {
            return back()->withErrors(['reset' => 'Please complete the security verification and try again.'])->withInput();
        }

        $genericMessage = 'If that account exists, a password reset link has been sent to the associated email address.';

        if (! Schema::hasTable(self::TABLE)) {
            return back()->with('success', $genericMessage);
        }

        $identity = trim((string) $request->input('identity'));

        // Rate limit per IP AND per identity so rotating IPs cannot enumerate accounts.
        $throttleKey = 'admin-pw-reset|'.($request->ip() ?? '127.0.0.1').'|'.strtolower($identity);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['reset' => 'Too many reset requests. Please try again later.']);
        }
        RateLimiter::hit($throttleKey, 900);

        $admin = AdminUser::query()
            ->whereIn('usre_type_id', [AdminUser::TYPE_ADMIN, AdminUser::TYPE_SUPERVISOR])
            ->where(function ($query) use ($identity) {
                $query->where('user_name', $identity)
                    ->orWhere('user_email', $identity);
            })
            ->orderByDesc('user_id')
            ->first();

        if ($admin && trim((string) $admin->user_email) !== '') {
            $selector = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(32));
            $expiresAt = now()->addMinutes(60);

            DB::table(self::TABLE)
                ->where('admin_user_id', $admin->user_id)
                ->delete();

            DB::table(self::TABLE)->insert([
                'admin_user_id' => $admin->user_id,
                'selector' => $selector,
                'token_hash' => hash('sha256', $validator),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $resetUrl = url('/v/reset-password?selector='.$selector.'&token='.$validator);
            $name = trim((string) ($admin->display_name ?: $admin->user_name));
            $email = (string) $admin->user_email;
            $expiry = $expiresAt->format('F j, Y g:i A');

            $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#19232e;padding:32px;">'
                .'<p>Hi '.$name.',</p>'
                .'<p>A password reset was requested for your admin account. Click the link below to set a new password. This link expires at '.$expiry.'.</p>'
                .'<p><a href="'.$resetUrl.'" style="display:inline-block;padding:12px 20px;background:#0f5f66;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Reset Password</a></p>'
                .'<p>If you did not request this, you can safely ignore this email.</p>'
                .'</body></html>';

            PortalMailer::sendHtml($email, '1Dollar Admin — Password Reset', $body);
        }

        return redirect(url('/v'))->with('success', $genericMessage);
    }

    public function showReset(Request $request)
    {
        $this->cleanupExpiredTokens();

        $selector = trim((string) $request->query('selector', ''));
        $token = trim((string) $request->query('token', ''));

        return view('admin.auth.reset-password', [
            'selector' => $selector,
            'token' => $token,
            'valid' => $this->validTokenPair($selector, $token),
        ]);
    }

    public function doReset(Request $request)
    {
        $this->cleanupExpiredTokens();

        $validated = $request->validate([
            'selector' => ['required', 'string'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);

        $tokenRow = $this->tokenRecord((string) $validated['selector'], (string) $validated['token']);
        if (! $tokenRow) {
            return back()->withErrors(['reset' => 'This password reset link is invalid or has expired.']);
        }

        $admin = AdminUser::query()
            ->whereIn('usre_type_id', [AdminUser::TYPE_ADMIN, AdminUser::TYPE_SUPERVISOR])
            ->where('user_id', $tokenRow->admin_user_id)
            ->first();

        if (! $admin) {
            return back()->withErrors(['reset' => 'This password reset link is no longer valid.']);
        }

        $admin->forceFill(array_merge(
            PasswordManager::payload((string) $validated['password']),
            ['is_active' => 1]
        ))->save();
        LoginSecurity::clearSecurityState($admin);

        DB::table(self::TABLE)
            ->where('admin_user_id', $admin->user_id)
            ->delete();

        return redirect(url('/v'))->with('success', 'Your password has been reset. Please sign in with your new password.');
    }

    private function tokenRecord(string $selector, string $validator): ?object
    {
        if (! Schema::hasTable(self::TABLE) || $selector === '' || $validator === '') {
            return null;
        }

        $record = DB::table(self::TABLE)
            ->where('selector', $selector)
            ->where('expires_at', '>=', now()->format('Y-m-d H:i:s'))
            ->first();

        if (! $record) {
            return null;
        }

        return hash_equals((string) $record->token_hash, hash('sha256', $validator)) ? $record : null;
    }

    private function validTokenPair(string $selector, string $validator): bool
    {
        return $this->tokenRecord($selector, $validator) !== null;
    }

    private function cleanupExpiredTokens(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)
            ->where('expires_at', '<', now()->format('Y-m-d H:i:s'))
            ->delete();
    }
}
