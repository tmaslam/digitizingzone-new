<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminSimulation;
use App\Support\LoginSecurity;
use App\Support\PasswordManager;
use App\Support\SecurityAudit;
use App\Support\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TeamAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('team_user_id')) {
            return redirect('/team/welcome.php');
        }

        return view('team.auth.login');
    }

    public function login(Request $request)
    {
        if ($request->session()->has('team_user_id')) {
            return redirect('/team/welcome.php');
        }

        $validated = $request->validate([
            'txtLogin' => ['required', 'string'],
            'txtPassword' => ['required', 'string'],
        ], [], [
            'txtLogin' => 'user name',
            'txtPassword' => 'password',
        ]);

        if (! TurnstileVerifier::verify($request, 'team-login')) {
            return back()->withErrors(['auth' => 'Please complete the security verification and try again.'])->onlyInput('txtLogin');
        }

        $loginName = trim((string) $validated['txtLogin']);
        $throttleKey = $this->throttleKey($request, $loginName);
        $user = AdminUser::query()
            ->teamPortalUsers()
            ->active()
            ->where('user_name', $loginName)
            ->first();

        if ($activeLockMessage = LoginSecurity::activeLockMessage($request, $loginName, 'team', $user)) {
            return back()->withErrors(['auth' => $activeLockMessage])->onlyInput('txtLogin');
        }

        if (RateLimiter::tooManyAttempts($throttleKey, LoginSecurity::MAX_ATTEMPTS)) {
            $lockedPermanently = LoginSecurity::handleRateLimit($request, $loginName, 'team', $user);
            $message = $lockedPermanently
                ? LoginSecurity::unavailableAccountMessage()
                : (LoginSecurity::activeLockMessage($request, $loginName, 'team', $user) ?? LoginSecurity::rateLimitMessage());

            return back()->withErrors([
                'auth' => $message,
            ])->onlyInput('txtLogin');
        }

        if (! $user || (int) $user->is_active !== 1) {
            if (! $user) {
                RateLimiter::hit($throttleKey, LoginSecurity::WINDOW_SECONDS);
                LoginSecurity::recordAttempt($request, $loginName, 'Invalid team username', 'failed');

                return back()->withErrors(['auth' => 'Invalid login or password.'])->onlyInput('txtLogin');
            }

            LoginSecurity::recordAttempt($request, $user->user_name, 'Inactive team account', 'blocked', $user);

            return back()->withErrors(['auth' => LoginSecurity::unavailableAccountMessage()])->onlyInput('txtLogin');
        }

        if (! PasswordManager::matches($user, (string) $validated['txtPassword'])) {
            RateLimiter::hit($throttleKey, LoginSecurity::WINDOW_SECONDS);
            LoginSecurity::recordAttempt($request, $loginName, 'Invalid team login or password', 'failed', $user);

            return back()->withErrors(['auth' => 'Invalid login or password.'])->onlyInput('txtLogin');
        }

        RateLimiter::clear($throttleKey);
        LoginSecurity::clearSecurityState($user);

        $request->session()->forget([
            'admin_user_id',
            'admin_user_name',
        ]);
        $request->session()->regenerate();
        $request->session()->put([
            'team_user_id' => $user->user_id,
            'team_user_name' => $user->user_name,
            'team_user_type_id' => (int) $user->usre_type_id,
        ]);

        LoginSecurity::recordAttempt($request, $user->user_name, $user->is_supervisor ? 'Supervisor portal login' : 'Team portal login', 'success', $user);

        return redirect('/team/welcome.php');
    }

    public function logout(Request $request)
    {
        if (AdminSimulation::active($request)) {
            $returnPath = AdminSimulation::stop($request);
            SecurityAudit::record($request, 'auth.simulation_stopped', 'Team simulation session ended and the original admin session was restored.', [], 'info');

            return redirect($returnPath)->with('success', 'You have returned to your admin session.');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have been successfully logged out.');
    }

    private function throttleKey(Request $request, string $login): string
    {
        return 'team|'.Str::lower(trim($login)).'|'.($request->ip() ?? '127.0.0.1');
    }
}
