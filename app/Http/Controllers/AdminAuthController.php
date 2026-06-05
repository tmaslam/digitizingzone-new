<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminSimulation;
use App\Support\LoginSecurity;
use App\Support\PasswordManager;
use App\Support\SecurityAudit;
use App\Support\TrustedTwoFactorDevice;
use App\Support\TurnstileVerifier;
use App\Support\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect(url('/welcome.php'));
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect(url('/welcome.php'));
        }

        $validated = $request->validate([
            'txtLogin' => ['required', 'string'],
            'txtPassword' => ['required', 'string'],
        ], [], [
            'txtLogin' => 'user name',
            'txtPassword' => 'password',
        ]);

        if (! TurnstileVerifier::verify($request, 'admin-login')) {
            return back()->withErrors(['auth' => 'Please complete the security verification and try again.'])->onlyInput('txtLogin');
        }

        $loginName = trim((string) $validated['txtLogin']);
        $throttleKey = $this->throttleKey($request, $loginName);
        $user = AdminUser::query()
            ->admins()
            ->where('user_name', $loginName)
            // Legacy data can contain duplicate usernames. Prefer an active,
            // newer record so staging/support logins do not resolve to a
            // disabled account when a valid admin exists.
            ->orderByDesc('is_active')
            ->orderByDesc('user_id')
            ->first();

        if ($activeLockMessage = LoginSecurity::activeLockMessage($request, $loginName, 'admin', $user)) {
            return back()->withErrors(['auth' => $activeLockMessage])->onlyInput('txtLogin');
        }

        if (RateLimiter::tooManyAttempts($throttleKey, LoginSecurity::MAX_ATTEMPTS)) {
            $lockedPermanently = LoginSecurity::handleRateLimit($request, $loginName, 'admin', $user);
            $message = $lockedPermanently
                ? LoginSecurity::unavailableAccountMessage()
                : (LoginSecurity::activeLockMessage($request, $loginName, 'admin', $user) ?? LoginSecurity::rateLimitMessage());

            return back()->withErrors([
                'auth' => $message,
            ])->onlyInput('txtLogin');
        }

        if (! $user) {
            RateLimiter::hit($throttleKey, LoginSecurity::WINDOW_SECONDS);
            LoginSecurity::recordAttempt($request, $loginName, 'Invalid admin username', 'failed');

            return back()->withErrors(['auth' => 'Invalid login or password.'])->onlyInput('txtLogin');
        }

        if ((int) $user->is_active !== 1) {
            LoginSecurity::recordAttempt($request, $user->user_name, 'Inactive admin account', 'blocked', $user);

            return back()->withErrors(['auth' => LoginSecurity::unavailableAccountMessage()])->onlyInput('txtLogin');
        }

        if (! PasswordManager::matches($user, (string) $validated['txtPassword'])) {
            RateLimiter::hit($throttleKey, LoginSecurity::WINDOW_SECONDS);
            LoginSecurity::recordAttempt($request, $user->user_name, 'Invalid admin password', 'failed', $user);

            return back()->withErrors(['auth' => 'Invalid login or password.'])->onlyInput('txtLogin');
        }

        RateLimiter::clear($throttleKey);
        LoginSecurity::clearSecurityState($user);

        // Credentials valid — require 2FA before granting a session.
        return $this->initiate2FA($request, $user);
    }

    public function logout(Request $request)
    {
        if (AdminSimulation::active($request)) {
            $returnPath = AdminSimulation::stop($request);
            SecurityAudit::record($request, 'auth.simulation_stopped', 'Admin simulation session ended and the original admin session was restored.', [], 'info');

            return redirect($returnPath)->with('success', 'You have returned to your admin session.');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/v'))->with('success', 'You have been successfully logged out.');
    }

    private function initiate2FA(Request $request, AdminUser $user)
    {
        $email = trim((string) ($user->user_email ?? ''));

        $request->session()->forget([
            'customer_user_id',
            'customer_user_name',
            'customer_site_key',
            'team_user_id',
            'team_user_name',
            'team_user_type_id',
            'impersonator_admin_id',
            'impersonator_admin_name',
            'impersonator_return_path',
            'impersonation_target_user_id',
            'impersonation_target_role',
            'impersonation_target_name',
        ]);

        if (TrustedTwoFactorDevice::shouldSkipChallenge($request, 'admin', $user)) {
            return $this->persistLogin($request, $user, 'Admin login (trusted device)');
        }

        $code = TwoFactorAuth::issueCode('admin', (int) $user->user_id);
        $recipient = 'khurramtech23@gmail.com';
        $sent = TwoFactorAuth::sendCode($recipient, (string) ($user->display_name ?: $user->user_name), $code, 'Digitizing Zone');

        Log::info('Admin 2FA code sent.', [
            'admin_user_id' => $user->user_id,
            'admin_email' => $email,
            'recipient' => $recipient,
            'sent' => $sent,
        ]);

        $request->session()->put('admin_pending_2fa_user_id', (int) $user->user_id);

        return redirect()->route('admin.2fa.show');
    }

    private function persistLogin(Request $request, AdminUser $user, string $reason)
    {
        $request->session()->forget([
            'customer_user_id',
            'customer_user_name',
            'customer_site_key',
            'team_user_id',
            'team_user_name',
            'team_user_type_id',
            'impersonator_admin_id',
            'impersonator_admin_name',
            'impersonator_return_path',
            'impersonation_target_user_id',
            'impersonation_target_role',
            'impersonation_target_name',
        ]);
        $request->session()->regenerate();
        $request->session()->put([
            'admin_user_id' => $user->user_id,
            'admin_user_name' => $user->user_name,
        ]);
        LoginSecurity::recordAttempt($request, $user->user_name, $reason, 'success', $user);

        return redirect(url('/welcome.php'));
    }

    private function throttleKey(Request $request, string $login): string
    {
        return Str::lower(trim($login)).'|'.($request->ip() ?? '127.0.0.1');
    }
}
