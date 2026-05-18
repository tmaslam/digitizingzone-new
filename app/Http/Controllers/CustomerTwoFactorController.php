<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\LoginSecurity;
use App\Support\SiteContext;
use App\Support\SignupOfferService;
use App\Support\TrustedTwoFactorDevice;
use App\Support\TwoFactorAuth;
use Illuminate\Http\Request;

class CustomerTwoFactorController extends Controller
{
    /**
     * Show the 2FA code entry page.
     */
    public function show(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect('/dashboard.php');
        }

        $pending = $request->session()->get('customer_pending_2fa');
        if (! $pending) {
            return redirect('/login.php');
        }

        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        // Only show the offer panel when the customer still needs to pay.
        // If they have already completed the $1 payment, suppress it.
        $offerPending = (bool) ($pending['offer_pending'] ?? false);

        return view('customer.auth.two-factor', [
            'signupOffer' => $offerPending
                ? SignupOfferService::offerSummary(SignupOfferService::activeSignupOffer($site))
                : null,
        ]);
    }

    /**
     * Verify the submitted code and complete the customer login.
     */
    public function verify(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect('/dashboard.php');
        }

        $pending = $request->session()->get('customer_pending_2fa');
        if (! $pending) {
            return redirect('/login.php');
        }

        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'trust_device' => ['nullable', 'boolean'],
        ], [], [
            'code' => 'verification code',
        ]);

        $userId = (int) $pending['user_id'];
        $result = TwoFactorAuth::verifyCode('customer', $userId, (string) $validated['code']);

        if ($result === null) {
            $request->session()->forget('customer_pending_2fa');

            return redirect('/login.php')
                ->withErrors(['auth' => 'The verification code has expired or too many incorrect attempts were made. Please sign in again.'])
                ->onlyInput('user_id');
        }

        if ($result === false) {
            $remaining = TwoFactorAuth::remainingAttempts('customer', $userId);

            return back()->withErrors(['code' => 'Incorrect verification code. '.$remaining.' attempt'.($remaining === 1 ? '' : 's').' remaining.']);
        }

        // Code correct — load the customer and complete login.
        $customer = AdminUser::query()
            ->customers()
            ->forWebsite($site->legacyKey)
            ->find($userId);

        if (! $customer) {
            $request->session()->forget('customer_pending_2fa');

            return redirect('/login.php')
                ->withErrors(['auth' => 'Account not found. Please sign in again.']);
        }

        $offerPaymentPending = (bool) $pending['offer_pending'];
        $rememberMe          = (bool) $pending['remember_me'];

        if ((bool) ($validated['trust_device'] ?? false)) {
            TrustedTwoFactorDevice::issue($request, 'customer', $customer, $site->legacyKey);
        } else {
            TrustedTwoFactorDevice::revokeCurrent($request, 'customer', $site->legacyKey);
        }

        $request->session()->forget('customer_pending_2fa');

        /** @var CustomerAuthController $authController */
        $authController = app(CustomerAuthController::class);
        $authController->persistCustomerSession($request, $customer, $site, $offerPaymentPending, $rememberMe);

        LoginSecurity::recordAttempt($request, $customer->user_name, 'Customer login (2FA verified)', 'success', $customer);

        if ($offerPaymentPending) {
            return redirect('/member-offer.php');
        }

        return redirect('/dashboard.php');
    }

    /**
     * Resend a fresh code to the customer's registered email.
     */
    public function resend(Request $request)
    {
        $pending = $request->session()->get('customer_pending_2fa');
        if (! $pending) {
            return redirect('/login.php');
        }

        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        // Ensure the pending session belongs to the current site to prevent
        // cross-site code reuse.
        if (trim((string) ($pending['site_key'] ?? '')) !== $site->legacyKey) {
            $request->session()->forget('customer_pending_2fa');
            return redirect('/login.php')
                ->withErrors(['auth' => 'Your session has expired. Please sign in again.'])
                ->onlyInput('user_id');
        }

        $userId  = (int) $pending['user_id'];
        $customer = AdminUser::query()->customers()->forWebsite($site->legacyKey)->find($userId);

        if ($customer) {
            $email = trim((string) $customer->user_email);
            if ($email !== '') {
                $code = TwoFactorAuth::issueCode('customer', $userId, $site->legacyKey);
                TwoFactorAuth::sendCode($email, (string) ($customer->display_name ?: $customer->user_name), $code, $site->displayLabel());
            }
        }

        return redirect()->route('customer.2fa.show')
            ->with('success', 'A new verification code has been sent to your registered email address.');
    }
}
