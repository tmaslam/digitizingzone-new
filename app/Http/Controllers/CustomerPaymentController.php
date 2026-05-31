<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\PaymentProviderEvent;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionItem;
use App\Support\CustomerBalance;
use App\Support\HostedPaymentProviders;
use App\Support\SecurityAudit;
use App\Support\SiteContext;
use App\Support\SiteResolver;
use App\Support\SignupOfferService;
use App\Support\OrderWorkflowMetaManager;
use App\Support\StripeHostedCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerPaymentController extends Controller
{
    public function instantPaymentLanding(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect(url('/view-billing.php'))->with('success', 'Use the secure billing page to complete your payment.');
        }

        return redirect(url('/login.php'))->with('success', 'Please log in to continue to the secure payment area.');
    }

    public function showSignupOffer(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $claim = SignupOfferService::pendingPaymentClaimForCustomer($site, $customer);

        if (! $claim) {
            return redirect(url('/dashboard.php'))->with('success', 'Your welcome-offer payment is already complete for this website.');
        }

        return view('customer.payments.signup-offer', [
            'pageTitle' => 'Welcome Offer',
            'customer' => $customer,
            'site' => $site,
            'claim' => $claim,
            'offer' => SignupOfferService::offerSummary($claim->promotion),
            'paymentProviders' => HostedPaymentProviders::configuredOptions($site),
        ]);
    }

    public function startSignupOffer(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $claim = SignupOfferService::pendingPaymentClaimForCustomer($site, $customer);

        if (! $claim) {
            return redirect('/dashboard.php')->with('success', 'Your welcome-offer payment is already complete for this website.');
        }

        abort_unless($this->paymentTablesReady(), 500, 'Payment tables are not installed yet.');

        $provider = $this->selectedPaymentProvider($request);

        if (! $this->paymentGatewayReady($provider)) {
            return redirect(url('/member-offer.php'))->withErrors([
                'payment' => HostedPaymentProviders::label($provider).' is not configured completely yet. Please contact support before attempting the welcome-offer payment.',
            ]);
        }

        $requestedAmount = round((float) $claim->required_payment_amount, 2);
        if ($requestedAmount <= 0) {
            return redirect(url('/member-offer.php'))->withErrors([
                'payment' => 'The configured welcome-offer payment amount is not valid.',
            ]);
        }

        $merchantReference = $this->merchantReference($site, $customer, 'WELCOME');
        $now = now()->format('Y-m-d H:i:s');

        $transaction = PaymentTransaction::query()->create([
            'site_id' => $site->id,
            'user_id' => $customer->user_id,
            'order_id' => null,
            'billing_id' => null,
            'legacy_website' => $site->legacyKey,
            'provider' => $provider,
            'merchant_reference' => $merchantReference,
            'payment_scope' => 'signup_offer',
            'status' => 'initiated',
            'currency' => 'USD',
            'requested_amount' => number_format($requestedAmount, 2, '.', ''),
            'return_url' => $this->hostedReturnUrl($request),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SignupOfferService::bindTransaction($claim, $transaction);

        $checkoutItems = collect([
            [
                'invoice' => 'Offer',
                'title' => trim((string) ($claim->promotion?->promotion_name ?: 'New member welcome offer')),
                'amount' => (float) $transaction->requested_amount,
            ],
        ]);

        return $this->checkoutResponse(
            $site,
            $customer,
            $transaction,
            $checkoutItems,
            'Offer',
            '/member-offer.php',
            'Back To Welcome Offer'
        );
    }

    public function startOutstanding(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');

        $billings = Billing::query()
            ->active()
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            })
            ->with('order')
            ->orderBy('approve_date')
            ->orderBy('bill_id')
            ->get();

        if ($billings->isEmpty()) {
            return redirect(url('/view-invoices.php'))->with('success', 'No unpaid invoices remain. Any no-charge invoices are already available in your invoice history.');
        }

        return $this->startCheckout($request, $site, $customer, $billings, 'outstanding_balance');
    }

    public function startSingle(Request $request, Billing $billing)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');

        $billing = Billing::query()
            ->active()
            ->with('order')
            ->where('bill_id', $billing->bill_id)
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            })
            ->first();

        if (! $billing) {
            return redirect('/view-invoices.php')->with('success', 'This invoice is already settled and available in your invoice history.');
        }

        return $this->startCheckout($request, $site, $customer, collect([$billing]), 'single_invoice');
    }

    public function handleReturn(Request $request)
    {
        $site = $this->site($request);
        $merchantReference = trim((string) $request->input('cart_order_id', $request->query('merchant_reference', '')));
        abort_unless($merchantReference !== '', 404);

        $transaction = PaymentTransaction::query()
            ->with(['items.billing.order', 'customer'])
            ->where('merchant_reference', $merchantReference)
            ->firstOrFail();

        abort_unless($this->transactionMatchesSite($transaction, $site), 404);

        if ((string) $transaction->provider === HostedPaymentProviders::STRIPE) {
            return $this->handleStripeReturn($request, $site, $transaction);
        }

        $payload = $request->all();
        $verified = $this->verifyReturnHash($request);
        $confirmedAmount = $this->money($request->input('total', $transaction->requested_amount));
        $providerOrderNumber = trim((string) $request->input('order_number', ''));
        $providerReference = $providerOrderNumber !== '' ? $providerOrderNumber : $merchantReference;

        $this->logCallbackDiagnostic('2checkout.return.received', [
            'payment_transaction_id' => $transaction->id,
            'merchant_reference' => $merchantReference,
            'provider_reference' => $providerReference,
            'verified' => $verified,
            'confirmed_amount' => $confirmedAmount > 0 ? number_format($confirmedAmount, 2, '.', '') : null,
            'site_id' => $site->id,
            'site_key' => $site->legacyKey,
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'query_keys' => array_keys($request->query()),
            'input_keys' => array_keys($request->except(['credit_card', 'ccno', 'card_number', 'cvv', 'cvc', 'expiry', 'exp_month', 'exp_year'])),
        ]);

        $this->logProviderEvent(
            $site,
            $transaction,
            HostedPaymentProviders::TWOCHECKOUT,
            'return',
            $providerReference,
            $verified ? 'verified' : 'invalid',
            $payload
        );

        if ($verified) {
            $result = $this->reconcileTransaction(
                $transaction,
                $site,
                $confirmedAmount > 0 ? $confirmedAmount : (float) $transaction->requested_amount,
                $providerReference,
                $payload
            );
        } else {
            $transaction->update([
                'status' => 'verification_failed',
                'failure_reason' => 'Hosted return hash could not be verified.',
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $result = [
                'ok' => false,
                'message' => 'We received your payment return, but it still needs a quick review before your billing updates are confirmed. Please check your billing page shortly or contact support with your payment reference.',
            ];
        }

        $this->logCallbackDiagnostic('2checkout.return.processed', [
            'payment_transaction_id' => $transaction->id,
            'merchant_reference' => $merchantReference,
            'provider_reference' => $providerReference,
            'verified' => $verified,
            'result_ok' => (bool) ($result['ok'] ?? false),
            'transaction_status' => $transaction->fresh()?->status,
        ]);

        return view('customer.payments.result', [
            'pageTitle' => 'Payment Status',
            'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
            'verified' => $verified,
            'result' => $result,
            'site' => $site,
        ]);
    }

    public function simulateTwocheckout(Request $request, int $transaction)
    {
        $site = $this->site($request);
        $customer = $this->customer($request);
        if (! $this->twocheckoutSimulationEnabledForCustomer($customer)) {
            SecurityAudit::record($request, 'payments.simulation_denied', 'A 2Checkout simulation route was requested without simulation access.', [
                'customer_user_id' => $customer->user_id,
                'site_key' => $site->legacyKey,
                'payment_transaction_id' => $transaction,
            ], 'warning');
            abort(404);
        }
        $transaction = PaymentTransaction::query()
            ->with(['items.billing.order', 'customer'])
            ->findOrFail($transaction);

        abort_unless($this->transactionMatchesSite($transaction, $site), 404);
        abort_unless((int) $transaction->user_id === (int) $customer->user_id, 403);
        abort_unless((string) $transaction->provider === HostedPaymentProviders::TWOCHECKOUT, 404);

        $outcome = $this->requestedTwocheckoutSimulationOutcome($request);
        $reference = 'SIM2CO-'.$transaction->id.'-'.now()->format('His');
        $payload = [
            'simulated' => true,
            'provider' => HostedPaymentProviders::TWOCHECKOUT,
            'merchant_reference' => $transaction->merchant_reference,
            'outcome' => $outcome,
        ];

        if ($outcome === 'failed') {
            $this->logProviderEvent(
                $site,
                $transaction,
                HostedPaymentProviders::TWOCHECKOUT,
                'return',
                $reference,
                'simulated_failed',
                $payload
            );

            $transaction->update([
                'status' => 'failed',
                'failure_reason' => 'Local 2Checkout simulation marked the payment as failed.',
                'provider_transaction_id' => $reference,
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $result = [
                'ok' => false,
                'message' => 'Simulated 2Checkout payment was marked as failed.',
            ];

            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => false,
                'result' => $result,
                'site' => $site,
            ]);
        }

        $this->logProviderEvent(
            $site,
            $transaction,
            HostedPaymentProviders::TWOCHECKOUT,
            'return',
            $reference,
            'simulated_success',
            $payload
        );

        $result = $this->reconcileTransaction(
            $transaction,
            $site,
            (float) $transaction->requested_amount,
            $reference,
            $payload
        );

        return view('customer.payments.result', [
            'pageTitle' => 'Payment Status',
            'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
            'verified' => true,
            'result' => $result,
            'site' => $site,
        ]);
    }

    public function showTwocheckoutSimulator(Request $request, int $transaction)
    {
        $site = $this->site($request);
        $customer = $this->customer($request);
        abort_unless($this->twocheckoutSimulationEnabledForCustomer($customer), 404);

        $transaction = PaymentTransaction::query()
            ->with(['items.billing.order', 'customer'])
            ->findOrFail($transaction);

        abort_unless($this->transactionMatchesSite($transaction, $site), 404);
        abort_unless((int) $transaction->user_id === (int) $customer->user_id, 403);
        abort_unless((string) $transaction->provider === HostedPaymentProviders::TWOCHECKOUT, 404);

        $checkoutItems = $transaction->items->map(function (PaymentTransactionItem $item) {
            return [
                'invoice' => $item->billing_id ?: '-',
                'title' => $item->order?->design_name ?: 'Order #'.$item->order_id,
                'amount' => (float) $item->requested_amount,
            ];
        });

        if ($checkoutItems->isEmpty() && (string) $transaction->payment_scope === 'signup_offer') {
            $checkoutItems = collect([
                [
                    'invoice' => 'Offer',
                    'title' => 'New member welcome offer',
                    'amount' => (float) $transaction->requested_amount,
                ],
            ]);
        }

        $configuredOutcome = $this->twocheckoutSimulationOutcome();

        return view('customer.payments.simulated-checkout', [
            'pageTitle' => 'Hosted Payment Simulation',
            'customer' => $customer,
            'site' => $site,
            'transaction' => $transaction,
            'checkoutItems' => $checkoutItems,
            'providerLabel' => HostedPaymentProviders::label((string) $transaction->provider),
            'configuredOutcome' => $configuredOutcome,
            'completeUrl' => url('/simulate-2checkout/'.$transaction->id.'?outcome=success'),
            'failUrl' => url('/simulate-2checkout/'.$transaction->id.'?outcome=failed'),
            'backUrl' => url('/view-billing.php'),
        ]);
    }

    public function handleNotification(Request $request)
    {
        if (trim((string) $request->header('Stripe-Signature', '')) !== '') {
            return $this->handleStripeWebhook($request);
        }

        $site = $this->site($request);
        $payload = $request->all();
        $merchantReference = $this->notificationMerchantReference($request);
        $transaction = $merchantReference !== ''
            ? PaymentTransaction::query()->with('items.billing.order')->where('merchant_reference', $merchantReference)->first()
            : null;

        if ($transaction && ! $this->transactionMatchesSite($transaction, $site)) {
            $transaction = null;
        }

        $verified = $this->verifyNotificationHash($request);
        $eventReference = trim((string) $request->input('sale_id', $request->input('invoice_id', $merchantReference)));

        $this->logCallbackDiagnostic('2checkout.notification.received', [
            'payment_transaction_id' => $transaction?->id,
            'merchant_reference' => $merchantReference !== '' ? $merchantReference : null,
            'provider_reference' => $eventReference !== '' ? $eventReference : null,
            'verified' => $verified,
            'site_id' => $site->id,
            'site_key' => $site->legacyKey,
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'input_keys' => array_keys($request->except(['credit_card', 'ccno', 'card_number', 'cvv', 'cvc', 'expiry', 'exp_month', 'exp_year'])),
        ]);

        $this->logProviderEvent(
            $site,
            $transaction,
            HostedPaymentProviders::TWOCHECKOUT,
            'notification',
            $eventReference,
            $verified ? 'verified' : 'invalid',
            $payload
        );

        if ($verified && $transaction) {
            $confirmedAmount = $this->money(
                $request->input('invoice_list_amount', $request->input('total', $transaction->requested_amount))
            );

            $this->reconcileTransaction(
                $transaction,
                $site,
                $confirmedAmount > 0 ? $confirmedAmount : (float) $transaction->requested_amount,
                $eventReference !== '' ? $eventReference : $transaction->merchant_reference,
                $payload
            );
        }

        $this->logCallbackDiagnostic('2checkout.notification.processed', [
            'payment_transaction_id' => $transaction?->id,
            'merchant_reference' => $merchantReference !== '' ? $merchantReference : null,
            'provider_reference' => $eventReference !== '' ? $eventReference : null,
            'verified' => $verified,
            'transaction_status' => $transaction?->fresh()?->status,
        ]);

        return response('OK');
    }

    public function legacyProceed(Request $request)
    {
        $this->logCallbackDiagnostic('2checkout.legacy_proceed.received', [
            'merchant_reference' => trim((string) $request->input('cart_order_id', $request->input('merchant_reference', ''))),
            'provider_reference' => trim((string) $request->input('order_number', '')),
            'request_method' => $request->getMethod(),
            'request_path' => $request->path(),
            'input_keys' => array_keys($request->except(['credit_card', 'ccno', 'card_number', 'cvv', 'cvc', 'expiry', 'exp_month', 'exp_year'])),
        ]);

        $params = array_filter([
            'sid' => $request->input('sid'),
            'cart_order_id' => $request->input('cart_order_id'),
            'order_number' => $request->input('order_number'),
            'total' => $request->input('total'),
            'key' => $request->input('key'),
            'merchant_reference' => $request->input('merchant_reference'),
        ], static fn ($value) => trim((string) $value) !== '');

        if (empty($params)) {
            return redirect('/view-billing.php')->with('success', 'Your payment return is still being checked. Please refresh your billing page in a moment, and contact support if the payment does not appear shortly.');
        }

        return redirect('/successpay.php?'.http_build_query($params));
    }

    public function legacyDirectPayment()
    {
        return redirect('/view-billing.php')->withErrors([
            'payment' => 'The older direct card form has been retired. Please use the secure billing checkout for this account.',
        ]);
    }

    private function startCheckout(
        Request $request,
        SiteContext $site,
        AdminUser $customer,
        Collection $billings,
        string $scope
    ) {
        abort_unless($this->paymentTablesReady(), 500, 'Payment tables are not installed yet.');

        $provider = $this->selectedPaymentProvider($request);

        if (! $this->paymentGatewayReady($provider)) {
            return redirect('/view-billing.php')->withErrors([
                'payment' => HostedPaymentProviders::label($provider).' is not configured completely yet. Please contact support before attempting payment.',
            ]);
        }

        $requestedAmount = round((float) $billings->sum(fn (Billing $billing) => $this->billingAmount($billing)), 2);
        if ($requestedAmount <= 0) {
            return redirect('/view-billing.php')->withErrors(['payment' => 'The selected invoice total is not valid for payment.']);
        }

        $merchantReference = $this->merchantReference($site, $customer);
        $now = now()->format('Y-m-d H:i:s');
        $primaryBilling = $billings->first();

        $transaction = PaymentTransaction::query()->create([
            'site_id' => $site->id,
            'user_id' => $customer->user_id,
            'order_id' => $primaryBilling?->order_id,
            'billing_id' => $primaryBilling?->bill_id,
            'legacy_website' => $site->legacyKey,
            'provider' => $provider,
            'merchant_reference' => $merchantReference,
            'payment_scope' => $scope,
            'status' => 'initiated',
            'currency' => 'USD',
            'requested_amount' => number_format($requestedAmount, 2, '.', ''),
            'return_url' => $this->hostedReturnUrl($request),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($billings as $billing) {
            PaymentTransactionItem::query()->create([
                'payment_transaction_id' => $transaction->id,
                'billing_id' => $billing->bill_id,
                'order_id' => $billing->order_id,
                'user_id' => $customer->user_id,
                'legacy_website' => $site->legacyKey,
                'requested_amount' => number_format($this->billingAmount($billing), 2, '.', ''),
                'status' => 'initiated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->checkoutResponse(
            $site,
            $customer,
            $transaction->fresh('items.billing.order'),
            $transaction->fresh('items.billing.order')->items->map(function (PaymentTransactionItem $item) {
                return [
                    'invoice' => $item->billing_id ?: '-',
                    'title' => $item->order?->design_name ?: 'Order #'.$item->order_id,
                    'amount' => (float) $item->requested_amount,
                ];
            }),
            'Invoices',
            '/view-billing.php',
            'Back To Billing'
        );
    }

    private function reconcileTransaction(
        PaymentTransaction $transaction,
        SiteContext $site,
        float $confirmedAmount,
        string $providerReference,
        array $payload
    ): array {
        return DB::transaction(function () use ($transaction, $site, $confirmedAmount, $providerReference, $payload) {
            // Re-fetch with a write lock so concurrent callbacks don't double-reconcile.
            $transaction = PaymentTransaction::query()->lockForUpdate()->find($transaction->id);

            if ((string) $transaction->status === 'success' && $transaction->reconciled_at) {
                return [
                    'ok' => true,
                    'message' => 'This payment has already been recorded successfully.',
                ];
            }

            return $this->doReconcile($transaction, $site, $confirmedAmount, $providerReference, $payload);
        });
    }

    private function doReconcile(
        PaymentTransaction $transaction,
        SiteContext $site,
        float $confirmedAmount,
        string $providerReference,
        array $payload
    ): array {
        $items = $transaction->items()->with('billing.order')->get();
        if ($items->isEmpty()) {
            if ((string) $transaction->payment_scope === 'signup_offer') {
                $result = SignupOfferService::reconcilePaidClaimTransaction($transaction, $site, $confirmedAmount, $providerReference);

                $transaction->update([
                    'status' => $result['ok'] ? 'success' : 'failed',
                    'failure_reason' => $result['ok'] ? null : $result['message'],
                    'provider_transaction_id' => $providerReference,
                    'confirmed_amount' => number_format($confirmedAmount, 2, '.', ''),
                    'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'reconciled_at' => $result['ok'] ? now()->format('Y-m-d H:i:s') : null,
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);

                $this->logCallbackDiagnostic('payments.reconcile.signup_offer', [
                    'payment_transaction_id' => $transaction->id,
                    'merchant_reference' => $transaction->merchant_reference,
                    'provider_reference' => $providerReference,
                    'confirmed_amount' => number_format($confirmedAmount, 2, '.', ''),
                    'result_ok' => (bool) ($result['ok'] ?? false),
                    'transaction_status' => $transaction->fresh()?->status,
                ]);

                return $result;
            }

            $transaction->update([
                'status' => 'failed',
                'failure_reason' => 'Payment transaction has no invoice items attached.',
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => false,
                'message' => 'No invoice items were attached to this payment record.',
            ];
        }

        $expectedAmount = round((float) $items->sum('requested_amount'), 2);
        if ($confirmedAmount + 0.0001 < $expectedAmount) {
            $transaction->update([
                'status' => 'amount_mismatch',
                'failure_reason' => 'Returned payment amount was lower than the invoice total.',
                'confirmed_amount' => number_format($confirmedAmount, 2, '.', ''),
                'provider_transaction_id' => $providerReference,
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => false,
                'message' => 'The payment amount did not fully cover the selected invoices.',
            ];
        }

        $now = now()->format('Y-m-d H:i:s');

        foreach ($items as $item) {
            $billing = Billing::query()
                ->where('bill_id', $item->billing_id)
                ->first();

            if ($billing) {
                $billing->update(Billing::writablePayload([
                    'approved' => 'yes',
                    'payment' => 'yes',
                    'is_paid' => 1,
                    'transid' => $providerReference,
                    'trandtime' => $now,
                    'website' => $billing->website ?: $site->legacyKey,
                    'comments' => $this->appendComment((string) $billing->comments, 'paid through hosted checkout'),
                ]));
            }

            $item->update([
                'confirmed_amount' => number_format((float) $item->requested_amount, 2, '.', ''),
                'status' => 'paid',
                'updated_at' => $now,
            ]);

            // Fix #6: if the order was held at preview-only pending payment, release it now.
            if ($item->order_id && $item->billing?->order) {
                $meta = OrderWorkflowMetaManager::forOrder($item->billing->order);
                if ($meta && (string) $meta->delivery_override === 'preview_only') {
                    $meta->update(['delivery_override' => 'auto']);
                }
            }
        }

        $overpayment = round($confirmedAmount - $expectedAmount, 2);
        if ($overpayment > 0.0001) {
            CustomerBalance::addPaymentCredit(
                (int) $transaction->user_id,
                $site->legacyKey,
                $overpayment,
                $transaction->merchant_reference.':overpayment',
                'customer-payment',
                'Checkout overpayment stored as customer credit.',
                'overpayment'
            );
        }

        $transaction->update([
            'status' => 'success',
            'provider_transaction_id' => $providerReference,
            'confirmed_amount' => number_format($confirmedAmount, 2, '.', ''),
            'provider_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'failure_reason' => null,
            'reconciled_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logCallbackDiagnostic('payments.reconcile.success', [
            'payment_transaction_id' => $transaction->id,
            'merchant_reference' => $transaction->merchant_reference,
            'provider_reference' => $providerReference,
            'confirmed_amount' => number_format($confirmedAmount, 2, '.', ''),
            'expected_amount' => number_format($expectedAmount, 2, '.', ''),
            'overpayment' => $overpayment > 0.0001 ? number_format($overpayment, 2, '.', '') : null,
            'billing_ids' => $items->pluck('billing_id')->filter()->values()->all(),
        ]);

        return [
            'ok' => true,
            'message' => $overpayment > 0.0001
                ? 'Payment was recorded successfully and the extra amount was stored as customer credit.'
                : 'Payment was recorded successfully.',
        ];
    }

    private function paymentTablesReady(): bool
    {
        return Schema::hasTable('payment_transactions')
            && Schema::hasTable('payment_transaction_items')
            && Schema::hasTable('payment_provider_events');
    }

    private function paymentGatewayReady(string $provider): bool
    {
        return HostedPaymentProviders::isReady($provider);
    }

    private function verifyReturnHash(Request $request): bool
    {
        $secretWord = trim((string) config('services.twocheckout.secret_word', ''));
        $sellerId = trim((string) config('services.twocheckout.seller_id', ''));
        $sid = trim((string) $request->input('sid', ''));
        $total = trim((string) $request->input('total', ''));
        $orderNumber = trim((string) $request->input('order_number', ''));
        $key = strtoupper(trim((string) $request->input('key', '')));

        if ($secretWord === '' || $sid === '' || $total === '' || $orderNumber === '' || $key === '') {
            return false;
        }

        if ($sellerId !== '' && $sid !== $sellerId) {
            return false;
        }

        $expected = strtoupper(md5($secretWord.$sid.$orderNumber.$total));

        return hash_equals($expected, $key);
    }

    private function verifyNotificationHash(Request $request): bool
    {
        $secretWord = trim((string) config('services.twocheckout.secret_word', ''));
        $vendorId = trim((string) $request->input('vendor_id', ''));
        $saleId = trim((string) $request->input('sale_id', ''));
        $invoiceId = trim((string) $request->input('invoice_id', ''));
        $md5Hash = strtoupper(trim((string) $request->input('md5_hash', '')));

        if ($secretWord === '' || $vendorId === '' || $saleId === '' || $invoiceId === '' || $md5Hash === '') {
            return false;
        }

        $expected = strtoupper(md5($saleId.$vendorId.$invoiceId.$secretWord));

        return hash_equals($expected, $md5Hash);
    }

    private function notificationMerchantReference(Request $request): string
    {
        foreach (['merchant_order_id', 'vendor_order_id', 'cart_order_id', 'merchant_reference'] as $key) {
            $value = trim((string) $request->input($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function merchantReference(SiteContext $site, AdminUser $customer, string $prefix = 'PAY'): string
    {
        return strtoupper(implode('-', [
            $prefix,
            $site->slug ?: $site->legacyKey,
            $customer->user_id,
            now()->format('YmdHis'),
            Str::upper(Str::random(8)),
        ]));
    }

    private function billingAmount(Billing $billing): float
    {
        return $this->money($billing->amount ?: $billing->order?->total_amount ?: $billing->order?->stitches_price);
    }

    private function transactionMatchesSite(PaymentTransaction $transaction, SiteContext $site): bool
    {
        $legacyWebsite = trim((string) $transaction->legacy_website);

        if ($site->id && $transaction->site_id) {
            return (int) $transaction->site_id === $site->id;
        }

        if ($legacyWebsite !== '') {
            return $site->matchesLegacyKey($legacyWebsite);
        }

        return true;
    }

    private function appendComment(string $existing, string $comment): string
    {
        $existing = trim($existing);

        return $existing === '' ? $comment : $existing.' | '.$comment;
    }

    private function logProviderEvent(
        SiteContext $site,
        ?PaymentTransaction $transaction,
        string $provider,
        string $eventType,
        string $eventReference,
        string $status,
        array $payload
    ): void {
        if (! Schema::hasTable('payment_provider_events')) {
            return;
        }

        PaymentProviderEvent::query()->create([
            'site_id' => $site->id,
            'payment_transaction_id' => $transaction?->id,
            'provider' => $provider,
            'event_type' => $eventType,
            'event_reference' => $eventReference !== '' ? $eventReference : null,
            'status' => $status,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'received_at' => now()->format('Y-m-d H:i:s'),
            'processed_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function customer(Request $request): AdminUser
    {
        return $request->attributes->get('customerUser');
    }

    private function site(Request $request): SiteContext
    {
        return $request->attributes->get('siteContext');
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function selectedPaymentProvider(Request $request): string
    {
        return HostedPaymentProviders::choose((string) $request->input('provider', ''), $this->site($request));
    }

    private function checkoutResponse(
        SiteContext $site,
        AdminUser $customer,
        PaymentTransaction $transaction,
        Collection $checkoutItems,
        string $itemCountLabel,
        string $backUrl,
        string $backLabel
    ) {
        if ((string) $transaction->provider === HostedPaymentProviders::STRIPE) {
            return $this->stripeCheckoutResponse($site, $customer, $transaction, $checkoutItems, $itemCountLabel, $backUrl, $backLabel);
        }

        if ($this->twocheckoutSimulationEnabledForCustomer($customer)) {
            $simulationOutcome = $this->twocheckoutSimulationOutcome();
            $simulationUrl = url('/simulate-2checkout/'.$transaction->id.'/checkout');
            $simulationLabel = $simulationOutcome === 'failed'
                ? 'Continue With Simulated Failed '.HostedPaymentProviders::label((string) $transaction->provider)
                : 'Continue With Simulated '.HostedPaymentProviders::label((string) $transaction->provider);
            $simulationMessage = $this->simulationIsLocalEnvironment()
                ? 'Local test payment mode is enabled. This checkout will follow the configured simulated outcome.'
                : 'Test payment mode is enabled for this account. This checkout will follow the configured simulated outcome.';

            $transaction->update([
                'redirect_url' => $simulationUrl,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return view('customer.payments.checkout', [
                'pageTitle' => 'Secure Payment',
                'customer' => $customer,
                'site' => $site,
                'transaction' => $transaction,
                'providerLabel' => HostedPaymentProviders::label((string) $transaction->provider),
                'purchaseUrl' => null,
                'sellerId' => null,
                'returnUrl' => (string) ($transaction->return_url ?: $this->fallbackReturnUrl()),
                'checkoutItems' => $checkoutItems,
                'itemCountLabel' => $itemCountLabel,
                'backUrl' => $backUrl,
                'backLabel' => $backLabel,
                'autoRedirectUrl' => $simulationUrl,
                'simulationMode' => [
                    'outcome' => $simulationOutcome,
                    'url' => $simulationUrl,
                    'label' => $simulationLabel,
                    'message' => $simulationMessage,
                ],
            ]);
        }

        $transaction->update([
            'redirect_url' => (string) config('services.twocheckout.purchase_url'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return view('customer.payments.checkout', [
            'pageTitle' => 'Secure Payment',
            'customer' => $customer,
            'site' => $site,
            'transaction' => $transaction,
            'providerLabel' => HostedPaymentProviders::label((string) $transaction->provider),
            'purchaseUrl' => (string) config('services.twocheckout.purchase_url'),
            'sellerId' => (string) config('services.twocheckout.seller_id'),
            'returnUrl' => (string) ($transaction->return_url ?: $this->fallbackReturnUrl()),
            'checkoutItems' => $checkoutItems,
            'itemCountLabel' => $itemCountLabel,
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
            'autoRedirectUrl' => null,
        ]);
    }

    private function twocheckoutSimulationEnabledForCustomer(AdminUser $customer): bool
    {
        if (! filter_var(config('services.twocheckout.simulation_enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        if ($this->simulationIsLocalEnvironment()) {
            return true;
        }

        $configuredCustomerId = (int) config('services.twocheckout.simulation_customer_id');
        if ($configuredCustomerId > 0 && (int) $customer->user_id === $configuredCustomerId) {
            return true;
        }

        $configuredCustomerEmail = strtolower(trim((string) config('services.twocheckout.simulation_customer_email', '')));

        return $configuredCustomerEmail !== ''
            && strtolower(trim((string) $customer->user_email)) === $configuredCustomerEmail;
    }

    private function simulationIsLocalEnvironment(): bool
    {
        return strtolower(trim((string) config('app.env', app()->environment()))) === 'local';
    }

    private function twocheckoutSimulationOutcome(): string
    {
        $configured = strtolower(trim((string) config('services.twocheckout.simulation_outcome', 'success')));

        return in_array($configured, ['success', 'failed'], true) ? $configured : 'success';
    }

    private function requestedTwocheckoutSimulationOutcome(Request $request): string
    {
        $requested = strtolower(trim((string) $request->input('outcome', '')));

        if ($requested === 'success' || $requested === 'failed') {
            return $requested;
        }

        return $this->twocheckoutSimulationOutcome();
    }

    private function hostedReturnUrl(Request $request): string
    {
        $root = rtrim((string) $request->getSchemeAndHttpHost(), '/');

        if ($root !== '') {
            return $root.'/payment-proceed.php';
        }

        return $this->fallbackReturnUrl();
    }

    private function fallbackReturnUrl(): string
    {
        return url('/payment-proceed.php');
    }

    private function stripeCheckoutResponse(
        SiteContext $site,
        AdminUser $customer,
        PaymentTransaction $transaction,
        Collection $checkoutItems,
        string $itemCountLabel,
        string $backUrl,
        string $backLabel
    ) {
        $session = StripeHostedCheckout::createSession(
            $transaction,
            $checkoutItems,
            url('/successpay.php?'.http_build_query([
                'provider' => HostedPaymentProviders::STRIPE,
                'merchant_reference' => $transaction->merchant_reference,
            ]).'&session_id={CHECKOUT_SESSION_ID}'),
            url($backUrl),
            (string) ($customer->user_email ?? '')
        );

        if (! $session['ok']) {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => (string) $session['message'],
                'provider_payload' => isset($session['payload']) ? json_encode($session['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return redirect($backUrl)->withErrors([
                'payment' => 'Stripe checkout could not be started. '.trim((string) $session['message']),
            ]);
        }

        $transaction->update([
            'provider_transaction_id' => (string) $session['session_id'],
            'redirect_url' => (string) $session['redirect_url'],
            'provider_payload' => json_encode($session['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return view('customer.payments.checkout', [
            'pageTitle' => 'Secure Payment',
            'customer' => $customer,
            'site' => $site,
            'transaction' => $transaction->fresh('items.billing.order'),
            'providerLabel' => HostedPaymentProviders::label((string) $transaction->provider),
            'purchaseUrl' => null,
            'sellerId' => null,
            'returnUrl' => url('/successpay.php'),
            'checkoutItems' => $checkoutItems,
            'itemCountLabel' => $itemCountLabel,
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
            'autoRedirectUrl' => (string) $session['redirect_url'],
        ]);
    }

    private function handleStripeReturn(Request $request, SiteContext $site, PaymentTransaction $transaction)
    {
        // If the webhook already reconciled this payment before the customer returned,
        // show success immediately instead of re-checking the session.
        if ((string) $transaction->status === 'success' && $transaction->reconciled_at) {
            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => true,
                'result' => [
                    'ok' => true,
                    'message' => 'Payment was recorded successfully.',
                ],
                'site' => $site,
            ]);
        }

        $sessionId = trim((string) $request->input('session_id', $transaction->provider_transaction_id ?: ''));
        if ($sessionId === '') {
            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => false,
                'result' => [
                    'ok' => false,
                    'message' => 'Stripe returned without a session reference. Please contact support before retrying.',
                ],
                'site' => $site,
            ]);
        }

        $lookup = StripeHostedCheckout::fetchSession($sessionId);
        if (! $lookup['ok']) {
            $errorMessage = (string) $lookup['message'];
            $transaction->update([
                'failure_reason' => $errorMessage,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => false,
                'result' => [
                    'ok' => false,
                    'message' => 'We received the Stripe return, but could not confirm the payment yet. Please refresh in a moment or contact support.',
                ],
                'site' => $site,
            ]);
        }

        $session = (array) $lookup['session'];
        $sessionReference = trim((string) ($session['client_reference_id'] ?? data_get($session, 'metadata.merchant_reference', '')));
        $paid = strtolower(trim((string) ($session['payment_status'] ?? ''))) === 'paid';
        $matches = $sessionReference !== '' && hash_equals($transaction->merchant_reference, $sessionReference);

        $this->logProviderEvent(
            $site,
            $transaction,
            HostedPaymentProviders::STRIPE,
            'return',
            $sessionId,
            $matches ? ($paid ? 'verified' : 'pending') : 'invalid',
            $session
        );

        if (! $matches) {
            $transaction->update([
                'status' => 'verification_failed',
                'failure_reason' => 'Stripe session reference did not match the stored payment transaction.',
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => false,
                'result' => [
                    'ok' => false,
                    'message' => 'Stripe returned a session that does not match the stored payment reference.',
                ],
                'site' => $site,
            ]);
        }

        if (! $paid) {
            return view('customer.payments.result', [
                'pageTitle' => 'Payment Status',
                'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
                'verified' => false,
                'result' => [
                    'ok' => false,
                    'message' => 'Payment is being processed. You will receive confirmation shortly. Please check your billing page in a moment.',
                ],
                'site' => $site,
            ]);
        }

        $result = $this->reconcileTransaction(
            $transaction,
            $site,
            StripeHostedCheckout::confirmedAmount($session),
            StripeHostedCheckout::providerReference($session),
            $session
        );

        return view('customer.payments.result', [
            'pageTitle' => 'Payment Status',
            'transaction' => $transaction->fresh(['items.billing.order', 'customer']),
            'verified' => $paid,
            'result' => $result,
            'site' => $site,
        ]);
    }

    public function handleStripeWebhook(Request $request)
    {
        $parsed = StripeHostedCheckout::parseWebhook($request);
        $payload = (string) ($parsed['payload'] ?? $request->getContent());
        $event = $parsed['event'] ?? [];
        $session = (array) data_get($event, 'data.object', []);
        $merchantReference = trim((string) ($session['client_reference_id'] ?? data_get($session, 'metadata.merchant_reference', '')));

        $transaction = $merchantReference !== ''
            ? PaymentTransaction::query()->with('items.billing.order')->where('merchant_reference', $merchantReference)->first()
            : null;

        $site = $transaction ? $this->siteForTransaction($transaction) : $this->site($request);
        $eventId = trim((string) ($event['id'] ?? $merchantReference));

        if (! $parsed['ok']) {
            $this->logProviderEvent(
                $site,
                $transaction,
                HostedPaymentProviders::STRIPE,
                'webhook',
                $eventId,
                'invalid',
                ['body' => $payload]
            );

            return response('invalid', 400);
        }

        $type = trim((string) ($event['type'] ?? 'webhook'));
        $paymentStatus = strtolower(trim((string) ($session['payment_status'] ?? '')));
        $status = 'received';

        if ($transaction && in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && $paymentStatus === 'paid') {
            $result = $this->reconcileTransaction(
                $transaction,
                $site,
                StripeHostedCheckout::confirmedAmount($session),
                StripeHostedCheckout::providerReference($session),
                $event
            );

            $status = $result['ok'] ? 'verified' : 'failed';
        } elseif ($transaction && in_array($type, ['checkout.session.expired', 'payment_intent.payment_failed'], true)) {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => 'Stripe reported that the checkout session was not completed successfully.',
                'provider_payload' => json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $status = 'failed';
        }

        $this->logProviderEvent(
            $site,
            $transaction,
            HostedPaymentProviders::STRIPE,
            $type,
            $eventId,
            $status,
            $event
        );

        return response('OK');
    }

    private function siteForTransaction(PaymentTransaction $transaction): SiteContext
    {
        return SiteResolver::fromLegacyKey((string) $transaction->legacy_website)
            ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
    }

    private function logCallbackDiagnostic(string $message, array $context = []): void
    {
        Log::info($message, $this->sanitizeDiagnosticContext($context));
    }

    private function sanitizeDiagnosticContext(array $context): array
    {
        $sanitized = [];
        $sensitiveKeys = ['credit_card', 'ccno', 'card_number', 'cvv', 'cvc', 'expiry', 'exp_month', 'exp_year', 'payload', 'body', 'key', 'md5_hash'];

        foreach ($context as $key => $value) {
            if (in_array((string) $key, $sensitiveKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = array_values(array_filter(array_map(
                    static fn ($entry) => is_scalar($entry) ? (string) $entry : null,
                    $value
                )));

                continue;
            }

            if (is_bool($value) || is_null($value) || is_numeric($value)) {
                $sanitized[$key] = $value;

                continue;
            }

            $sanitized[$key] = trim((string) $value);
        }

        return $sanitized;
    }
}
