<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminSimulation;
use App\Support\CustomerRememberLogin;
use App\Support\CustomerApprovalQueue;
use App\Support\LoginSecurity;
use App\Support\PasswordManager;
use App\Support\SecurityAudit;
use App\Support\SiteContext;
use App\Support\SignupOfferService;
use App\Support\TrustedTwoFactorDevice;
use App\Support\TurnstileVerifier;
use App\Support\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CustomerAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect('/dashboard.php');
        }

        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        $rememberedCustomer = CustomerRememberLogin::restore($request, $site);
        if ($rememberedCustomer) {
            // If the customer has 2FA enabled, restore() already wrote the session
            // but we must intercept here and go through the verification step instead.
            if ((int) ($rememberedCustomer->two_factor_enabled ?? 0) === 1) {
                if (TrustedTwoFactorDevice::shouldSkipChallenge($request, 'customer', $rememberedCustomer, $site->legacyKey)) {
                    return redirect('/dashboard.php');
                }

                $request->session()->forget(['customer_user_id', 'customer_user_name', 'customer_site_key']);
                $email = trim((string) $rememberedCustomer->user_email);
                if ($email !== '') {
                    $code = TwoFactorAuth::issueCode('customer', (int) $rememberedCustomer->user_id, $site->legacyKey);
                    TwoFactorAuth::sendCode($email, (string) ($rememberedCustomer->display_name ?: $rememberedCustomer->user_name), $code, $site->displayLabel());
                }
                $request->session()->put('customer_pending_2fa', [
                    'user_id'       => (int) $rememberedCustomer->user_id,
                    'site_key'      => $site->legacyKey,
                    'offer_pending' => false,
                    'remember_me'   => true,
                ]);
                return redirect()->route('customer.2fa.show');
            }

            return redirect('/dashboard.php');
        }

        return view('customer.auth.login', [
            'pageTitle' => 'Customer Login',
            'signupOffer' => SignupOfferService::offerSummary(SignupOfferService::activeSignupOffer($site)),
        ]);
    }

    public function login(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect('/dashboard.php');
        }

        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        $validated = $request->validate([
            'user_id' => ['required', 'string'],
            'user_psw' => ['required', 'string'],
            'remember_me' => ['nullable', 'boolean'],
        ], [], [
            'user_id' => 'email or user name',
            'user_psw' => 'password',
        ]);

        if (! TurnstileVerifier::verify($request, 'customer-login')) {
            return back()->withErrors(['auth' => 'Please complete the security verification and try again.'])->onlyInput('user_id');
        }

        $loginValue = trim((string) $validated['user_id']);
        $password = (string) $validated['user_psw'];
        $throttleKey = $this->throttleKey($request, $site->legacyKey, $loginValue);

        $customer = AdminUser::query()
            ->customers()
            ->forWebsite($site->legacyKey)
            ->where(function ($query) use ($loginValue) {
                $query->where('user_email', $loginValue)
                    ->orWhere('alternate_email', $loginValue)
                    ->orWhere('user_name', $loginValue);
            })
            ->orderByRaw(AdminUser::activeFirstOrderSql())
            ->orderByDesc('user_id')
            ->first();

        if ($activeLockMessage = LoginSecurity::activeLockMessage($request, $loginValue, 'customer', $customer)) {
            return back()->withErrors([
                'auth' => $activeLockMessage,
            ])->onlyInput('user_id');
        }

        if (RateLimiter::tooManyAttempts($throttleKey, LoginSecurity::MAX_ATTEMPTS)) {
            $lockedPermanently = LoginSecurity::handleRateLimit($request, $loginValue, 'customer', $customer);
            $message = $lockedPermanently
                ? LoginSecurity::unavailableAccountMessage()
                : (LoginSecurity::activeLockMessage($request, $loginValue, 'customer', $customer) ?? LoginSecurity::rateLimitMessage());

            return back()->withErrors([
                'auth' => $message,
            ])->onlyInput('user_id');
        }

        if (! $customer || ! PasswordManager::matches($customer, $password)) {
            RateLimiter::hit($throttleKey, LoginSecurity::WINDOW_SECONDS);
            LoginSecurity::recordAttempt($request, $loginValue, 'Invalid customer login', 'failed', $customer);

            return back()->withErrors(['auth' => 'Invalid login or password.'])->onlyInput('user_id');
        }

        $offerPaymentPending = SignupOfferService::customerShouldCompleteOfferPayment($site, $customer);

        if ((int) $customer->is_active !== 1 && ! $offerPaymentPending) {
            LoginSecurity::recordAttempt($request, $customer->user_name, 'Inactive customer account', 'blocked', $customer);

            $message = CustomerApprovalQueue::stateForCustomer($customer) === CustomerApprovalQueue::STATE_PENDING_ADMIN_APPROVAL
                ? 'This account is waiting for admin approval. Your email is already verified, and you can sign in as soon as the approval is completed.'
                : 'This account is not active yet. Please use the verification link we emailed you. If it is not in your inbox, check spam or junk, or request a new verification email.';

            return back()->withErrors([
                'auth' => $message,
            ])->onlyInput('user_id');
        }

        RateLimiter::clear($throttleKey);
        LoginSecurity::clearSecurityState($customer);

        // If the customer has 2FA enabled, pause here and send a code.
        if ((int) ($customer->two_factor_enabled ?? 0) === 1) {
            $email = trim((string) $customer->user_email);
            if ($email !== '') {
                if (TrustedTwoFactorDevice::shouldSkipChallenge($request, 'customer', $customer, $site->legacyKey)) {
                    $this->persistCustomerSession($request, $customer, $site, $offerPaymentPending, (bool) ($validated['remember_me'] ?? false));
                    LoginSecurity::recordAttempt($request, $customer->user_name, 'Customer login (trusted device)', 'success', $customer);

                    if ($offerPaymentPending) {
                        return redirect('/member-offer.php');
                    }

                    return redirect('/dashboard.php');
                }

                $code = TwoFactorAuth::issueCode('customer', (int) $customer->user_id, $site->legacyKey);
                TwoFactorAuth::sendCode(
                    $email,
                    (string) ($customer->display_name ?: $customer->user_name),
                    $code,
                    $site->displayLabel()
                );
                LoginSecurity::recordAttempt($request, $customer->user_name, 'Customer 2FA code issued', 'info', $customer);

                $request->session()->put('customer_pending_2fa', [
                    'user_id'       => (int) $customer->user_id,
                    'site_key'      => $site->legacyKey,
                    'offer_pending' => $offerPaymentPending,
                    'remember_me'   => (bool) ($validated['remember_me'] ?? false),
                ]);

                return redirect()->route('customer.2fa.show');
            }
        }

        // No 2FA (or no email on file) — complete login immediately.
        $this->persistCustomerSession($request, $customer, $site, $offerPaymentPending, (bool) ($validated['remember_me'] ?? false));
        LoginSecurity::recordAttempt($request, $customer->user_name, 'Customer login', 'success', $customer);

        if ($offerPaymentPending) {
            return redirect('/member-offer.php');
        }

        return redirect('/dashboard.php');
    }

    public function logout(Request $request)
    {
        if (AdminSimulation::active($request)) {
            $returnPath = AdminSimulation::stop($request);
            SecurityAudit::record($request, 'auth.simulation_stopped', 'Customer simulation session ended and the original admin session was restored.', [], 'info');

            return redirect($returnPath)->with('success', 'You have returned to your admin session.');
        }

        CustomerRememberLogin::clearCurrent($request);
        $request->session()->forget([
            'customer_user_id',
            'customer_user_name',
            'customer_site_key',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login.php')->with('success', 'You have been logged out successfully.');
    }

    public function persistCustomerSession(Request $request, \App\Models\AdminUser $customer, \App\Support\SiteContext $site, bool $offerPaymentPending, bool $rememberMe): void
    {
        $request->session()->forget([
            'admin_user_id',
            'admin_user_name',
            'team_user_id',
            'team_user_name',
        ]);
        $request->session()->regenerate();
        $request->session()->put([
            'customer_user_id'   => (int) $customer->user_id,
            'customer_user_name' => (string) $customer->display_name,
            'customer_site_key'  => $site->legacyKey,
        ]);

        if ($rememberMe && (int) $customer->is_active === 1 && ! $offerPaymentPending) {
            CustomerRememberLogin::issue($request, $site, $customer);
        } else {
            CustomerRememberLogin::clearCurrent($request);
        }
    }

    private function throttleKey(Request $request, string $siteKey, string $login): string
    {
        return implode('|', [
            'customer',
            Str::lower(trim($siteKey)),
            Str::lower(trim($login)),
            $request->ip() ?? '127.0.0.1',
        ]);
    }
}
