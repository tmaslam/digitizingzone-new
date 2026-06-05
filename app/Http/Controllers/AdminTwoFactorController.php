<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\LoginSecurity;
use App\Support\TrustedTwoFactorDevice;
use App\Support\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminTwoFactorController extends Controller
{
    /**
     * Show the 2FA code entry page.
     */
    public function show(Request $request)
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect(url('/welcome.php'));
        }

        $pendingId = $request->session()->get('admin_pending_2fa_user_id');
        if (! $pendingId) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    /**
     * Verify the submitted code and complete the login.
     */
    public function verify(Request $request)
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect(url('/welcome.php'));
        }

        $pendingId = $request->session()->get('admin_pending_2fa_user_id');
        if (! $pendingId) {
            return redirect()->route('admin.login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'trust_device' => ['nullable', 'boolean'],
        ], [], [
            'code' => 'verification code',
        ]);

        $userId = (int) $pendingId;
        $result = TwoFactorAuth::verifyCode('admin', $userId, (string) $validated['code']);

        if ($result === null) {
            // Expired or too many attempts.
            $request->session()->forget('admin_pending_2fa_user_id');

            return redirect()->route('admin.login')
                ->withErrors(['auth' => 'The verification code has expired or too many incorrect attempts were made. Please sign in again.'])
                ->onlyInput('txtLogin');
        }

        if ($result === false) {
            $remaining = TwoFactorAuth::remainingAttempts('admin', $userId);

            return back()->withErrors(['code' => 'Incorrect verification code. '.$remaining.' attempt'.($remaining === 1 ? '' : 's').' remaining.']);
        }

        // Code correct — persist the session.
        $user = AdminUser::query()->admins()->find($userId);
        if (! $user) {
            $request->session()->forget('admin_pending_2fa_user_id');

            return redirect()->route('admin.login')
                ->withErrors(['auth' => 'Account not found. Please sign in again.']);
        }

        if ((bool) ($validated['trust_device'] ?? false)) {
            TrustedTwoFactorDevice::issue($request, 'admin', $user);
        } else {
            TrustedTwoFactorDevice::revokeCurrent($request, 'admin');
        }

        $request->session()->forget('admin_pending_2fa_user_id');
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
            'admin_user_id'   => $user->user_id,
            'admin_user_name' => $user->user_name,
        ]);
        LoginSecurity::recordAttempt($request, $user->user_name, 'Admin login (2FA verified)', 'success', $user);

        return redirect(url('/welcome.php'));
    }

    /**
     * Resend a fresh verification code to the admin's registered email.
     */
    public function resend(Request $request)
    {
        $pendingId = $request->session()->get('admin_pending_2fa_user_id');
        if (! $pendingId) {
            return redirect()->route('admin.login');
        }

        $user = AdminUser::query()->admins()->find((int) $pendingId);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        $email = trim((string) ($user->user_email ?? ''));
        $recipient = 'khurramtech23@gmail.com';
        $code = TwoFactorAuth::issueCode('admin', (int) $user->user_id);
        $sent = TwoFactorAuth::sendCode($recipient, (string) ($user->display_name ?: $user->user_name), $code, 'Digitizing Zone');

        Log::info('Admin 2FA code resent.', [
            'admin_user_id' => $user->user_id,
            'admin_email' => $email,
            'recipient' => $recipient,
            'sent' => $sent,
        ]);

        return redirect()->route('admin.2fa.show')
            ->with('success', 'A new verification code has been sent to your registered email address.');
    }
}
