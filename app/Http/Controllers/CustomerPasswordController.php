<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\CustomerPublicRateLimit;
use App\Support\PasswordManager;
use App\Support\SiteContext;
use App\Support\SystemEmailTemplates;
use App\Support\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerPasswordController extends Controller
{
    private const TABLE = 'customer_password_reset_tokens';

    public function showForgot(Request $request)
    {
        $this->cleanupExpiredTokens();

        return view('customer.auth.forgot-password', [
            'pageTitle' => 'Forgot Password',
        ]);
    }

    public function sendResetLink(Request $request)
    {
        $this->cleanupExpiredTokens();

        $validated = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
        ], [], [
            'identity' => 'email or user name',
        ]);

        if (! TurnstileVerifier::verify($request, 'customer-password-reset')) {
            return back()->withErrors(['reset' => 'Please complete the security verification and try again.'])->withInput();
        }

        $genericMessage = 'If that account exists for this website, a password reset link has been sent.';

        if (! Schema::hasTable(self::TABLE)) {
            return back()->with('success', $genericMessage);
        }

        $site = $this->site($request);
        $identity = trim((string) $validated['identity']);

        if (CustomerPublicRateLimit::tooManyAttempts($request, 'password-reset', $site->legacyKey, $identity, 5, 900)) {
            return back()->withErrors(['reset' => 'Too many reset requests. Please try again later.']);
        }

        $customer = AdminUser::query()
            ->customers()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where(function ($query) use ($identity) {
                $query->where('user_email', $identity)
                    ->orWhere('alternate_email', $identity)
                    ->orWhere('user_name', $identity);
            })
            ->orderByRaw(AdminUser::activeFirstOrderSql())
            ->orderByDesc('user_id')
            ->first();

        if ($customer) {
            $selector = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(32));
            $expiresAt = now()->addMinutes(60);

            DB::table(self::TABLE)
                ->where('customer_user_id', $customer->user_id)
                ->where('site_legacy_key', $site->legacyKey)
                ->delete();

            DB::table(self::TABLE)->insert([
                'site_id' => $site->id,
                'site_legacy_key' => $site->legacyKey,
                'customer_user_id' => $customer->user_id,
                'selector' => $selector,
                'token_hash' => hash('sha256', $validator),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $resetUrl = url('/reset-password.php?selector='.$selector.'&token='.$validator);
            if (! SystemEmailTemplates::send(
                (string) $customer->user_email,
                'customer_password_reset',
                $site,
                [
                    'customer_name' => trim((string) ($customer->display_name ?: $customer->user_name)),
                    'customer_email' => (string) $customer->user_email,
                    'reset_url' => $resetUrl,
                    'expires_at' => $expiresAt->format('F j, Y g:i A'),
                ],
                fn () => [
                    'subject' => $site->brandName.' password reset',
                    'body' => view('customer.emails.password-reset', [
                        'customer' => $customer,
                        'siteContext' => $site,
                        'resetUrl' => $resetUrl,
                        'expiresAt' => $expiresAt,
                    ])->render(),
                ]
            )) {
                return back()->withErrors(['reset' => 'We could not send the password reset email right now. Please try again shortly or contact support.'])->withInput();
            }
        }

        return redirect('/login.php')->with('success', $genericMessage);
    }

    public function showReset(Request $request)
    {
        $this->cleanupExpiredTokens();
        $site = $this->site($request);

        return view('customer.auth.reset-password', [
            'pageTitle' => 'Reset Password',
            'selector' => trim((string) $request->query('selector', '')),
            'token' => trim((string) $request->query('token', '')),
            'valid' => $this->validTokenPair(
                trim((string) $request->query('selector', '')),
                trim((string) $request->query('token', '')),
                $site
            ),
        ]);
    }

    public function reset(Request $request)
    {
        $this->cleanupExpiredTokens();

        $validated = $request->validate([
            'selector' => ['required', 'string'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);

        $site = $this->site($request);
        $tokenRow = $this->tokenRecord((string) $validated['selector'], (string) $validated['token'], $site);
        if (! $tokenRow) {
            return back()->withErrors(['reset' => 'This password reset link is invalid or has expired.']);
        }

        $customer = AdminUser::query()
            ->customers()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $tokenRow->customer_user_id)
            ->first();

        if (! $customer) {
            return back()->withErrors(['reset' => 'This password reset link is no longer valid for this site.']);
        }

        $customer->forceFill(array_merge(
            PasswordManager::payload((string) $validated['password']),
            ['exist_customer' => '1']
        ))->save();

        DB::table(self::TABLE)
            ->where('customer_user_id', $customer->user_id)
            ->where('site_legacy_key', $site->legacyKey)
            ->delete();

        return redirect('/login.php')->with('success', 'Your password has been reset successfully. Please sign in.');
    }

    private function tokenRecord(string $selector, string $validator, SiteContext $site): ?object
    {
        if (! Schema::hasTable(self::TABLE) || $selector === '' || $validator === '') {
            return null;
        }

        $record = DB::table(self::TABLE)
            ->where('site_legacy_key', $site->legacyKey)
            ->where('selector', $selector)
            ->where('expires_at', '>=', now()->format('Y-m-d H:i:s'))
            ->first();

        if (! $record) {
            return null;
        }

        return hash_equals((string) $record->token_hash, hash('sha256', $validator)) ? $record : null;
    }

    private function validTokenPair(string $selector, string $validator, SiteContext $site): bool
    {
        return $this->tokenRecord($selector, $validator, $site) !== null;
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

    private function site(Request $request): SiteContext
    {
        return $request->attributes->get('siteContext');
    }
}
