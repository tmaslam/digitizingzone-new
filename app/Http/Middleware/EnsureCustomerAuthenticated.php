<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\CustomerRememberLogin;
use App\Support\HttpCache;
use App\Support\SecurityAudit;
use App\Support\SiteContext;
use App\Support\SignupOfferService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = (int) $request->session()->get('customer_user_id', 0);
        $siteKey = trim((string) $request->session()->get('customer_site_key', ''));
        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        if ($customerId <= 0 || $siteKey === '' || strcasecmp($siteKey, $site->legacyKey) !== 0) {
            $rememberedCustomer = CustomerRememberLogin::restore($request, $site);

            if ($rememberedCustomer) {
                $request->attributes->set('customerUser', $rememberedCustomer);

                if (
                    SignupOfferService::customerShouldCompleteOfferPayment($site, $rememberedCustomer)
                    && ! $request->is('member-offer.php')
                    && ! $request->is('member-offer.php/pay')
                    && ! $request->is('simulate-2checkout/*')
                    && ! $request->is('logout.php')
                ) {
                    return redirect('/member-offer.php');
                }

                return HttpCache::applyPrivateNoStore($next($request));
            }

            return $this->reject($request, 'Customer session was missing or did not match the current site.');
        }

        $customer = AdminUser::query()
            ->customers()
            ->where('user_id', $customerId)
            ->first();

        $offerPaymentPending = $customer
            && $site->matchesLegacyKey((string) $customer->website)
            && ($customer->site_id === null || $site->id === null || (int) $customer->site_id === (int) $site->id)
            && SignupOfferService::customerShouldCompleteOfferPayment($site, $customer);

        if (
            ! $customer
            || ! $site->matchesLegacyKey((string) $customer->website)
            || ($customer->site_id !== null && $site->id !== null && (int) $customer->site_id !== (int) $site->id)
        ) {
            return $this->reject($request, 'Customer session did not resolve to an active account for this site.', [
                'customer_user_id' => $customerId,
                'session_site_key' => $siteKey,
            ]);
        }

        if ((int) $customer->is_active !== 1 && ! $offerPaymentPending) {
            return $this->reject($request, 'Customer session did not resolve to an active account for this site.', [
                'customer_user_id' => $customerId,
                'session_site_key' => $siteKey,
            ]);
        }

        $request->attributes->set('customerUser', $customer);

        if (
            $offerPaymentPending
            && ! $this->allowsOfferPaymentPath($request)
        ) {
            return redirect('/member-offer.php');
        }

        return HttpCache::applyPrivateNoStore($next($request));
    }

    private function reject(Request $request, string $message, array $details = []): Response
    {
        SecurityAudit::recordUnauthorizedAccess($request, $message, $details);

        $request->session()->forget([
            'customer_user_id',
            'customer_user_name',
            'customer_site_key',
        ]);

        return redirect('/login.php');
    }

    private function allowsOfferPaymentPath(Request $request): bool
    {
        return $request->is('member-offer.php')
            || $request->is('member-offer.php/pay')
            || $request->is('simulate-2checkout/*')
            || $request->is('logout.php');
    }

}
