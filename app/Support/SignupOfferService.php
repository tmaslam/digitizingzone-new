<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\SitePromotion;
use App\Models\SitePromotionClaim;
use Illuminate\Support\Facades\Schema;

class SignupOfferService
{
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_REDEEMED = 'redeemed';

    public static function available(SiteContext $site): bool
    {
        return $site->id !== null
            && Schema::hasTable('site_promotions')
            && Schema::hasTable('site_promotion_claims');
    }

    public static function activeSignupOffer(SiteContext $site): ?SitePromotion
    {
        if (! self::available($site)) {
            return null;
        }

        $now = now()->format('Y-m-d H:i:s');

        return SitePromotion::query()
            ->signupOffers()
            ->active()
            ->where('site_id', $site->id)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function offerSummary(?SitePromotion $promotion): ?array
    {
        if (! $promotion) {
            return null;
        }

        $config = $promotion->config();
        $paymentAmount = self::configMoney($config, 'onboarding_payment_amount', self::configMoney($config, 'required_payment_amount', (float) $promotion->discount_value));
        $creditAmount = self::configMoney($config, 'credit_amount', $paymentAmount);
        $firstOrderFlatAmount = self::configMoney($config, 'first_order_flat_amount', $paymentAmount);
        $firstOrderFreeUnderStitches = self::configInteger($config, 'first_order_free_under_stitches', 0);

        return [
            'headline' => trim((string) ($config['headline'] ?? $promotion->promotion_name ?: 'New member welcome offer')),
            'summary' => trim((string) ($config['summary'] ?? 'Verify your email address, then complete the secure welcome payment so we know you are a legitimate customer.')),
            'verification_message' => trim((string) ($config['verification_message'] ?? 'After signup, check your inbox and spam or junk folder for the verification email.')),
            'payment_amount' => $paymentAmount,
            'credit_amount' => $creditAmount,
            'first_order_flat_amount' => $firstOrderFlatAmount,
            'first_order_free_under_stitches' => $firstOrderFreeUnderStitches,
            'offer_code' => trim((string) ($promotion->promotion_code ?? '')),
        ];
    }

    public static function createClaimForCustomer(SiteContext $site, AdminUser $customer): ?SitePromotionClaim
    {
        $promotion = self::activeSignupOffer($site);
        if (! $promotion) {
            return null;
        }

        $existing = self::claimForCustomer(
            $site,
            $customer,
            [self::STATUS_PENDING_VERIFICATION, self::STATUS_PENDING_PAYMENT, self::STATUS_PAID]
        );

        if ($existing) {
            return $existing;
        }

        $summary = self::offerSummary($promotion);
        $now = now()->format('Y-m-d H:i:s');

        return SitePromotionClaim::query()->create([
            'site_id' => $promotion->site_id,
            'site_promotion_id' => $promotion->id,
            'user_id' => $customer->user_id,
            'website' => $site->legacyKey,
            'status' => self::STATUS_PENDING_VERIFICATION,
            'verification_required' => 1,
            'payment_required' => ($summary['payment_amount'] ?? 0) > 0 ? 1 : 0,
            'required_payment_amount' => number_format((float) ($summary['payment_amount'] ?? 0), 2, '.', ''),
            'credit_amount' => number_format((float) ($summary['credit_amount'] ?? 0), 2, '.', ''),
            'first_order_flat_amount' => ($summary['first_order_flat_amount'] ?? 0) > 0
                ? number_format((float) $summary['first_order_flat_amount'], 2, '.', '')
                : null,
            'offer_snapshot_json' => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function claimForCustomer(SiteContext $site, AdminUser $customer, array $statuses = []): ?SitePromotionClaim
    {
        if (! self::available($site)) {
            return null;
        }

        $query = SitePromotionClaim::query()
            ->with('promotion')
            ->where('site_id', $site->id)
            ->where('user_id', $customer->user_id)
            ->where('website', $site->legacyKey);

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderByDesc('id')->first();
    }

    public static function pendingPaymentClaimForCustomer(SiteContext $site, AdminUser $customer): ?SitePromotionClaim
    {
        $claim = self::claimForCustomer($site, $customer, [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID]);

        if (! $claim || (float) $claim->required_payment_amount <= 0) {
            return null;
        }

        if (self::claimNeedsWelcomePayment($claim)) {
            if ((string) $claim->status !== self::STATUS_PENDING_PAYMENT) {
                $claim->update([
                    'status' => self::STATUS_PENDING_PAYMENT,
                    'payment_required' => 1,
                    'paid_at' => null,
                    'payment_reference' => self::isAdminApprovalReference((string) $claim->payment_reference) ? null : $claim->payment_reference,
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            return $claim->fresh(['promotion']);
        }

        return null;
    }

    public static function paidClaimForCustomer(SiteContext $site, AdminUser $customer): ?SitePromotionClaim
    {
        $claim = self::claimForCustomer($site, $customer, [self::STATUS_PAID]);

        return $claim && self::claimCountsAsCompletedOffer($claim) ? $claim : null;
    }

    public static function verifiedPendingPaymentUserIds(?string $website = null): array
    {
        if (! Schema::hasTable('site_promotion_claims')) {
            return [];
        }

        return SitePromotionClaim::query()
            ->whereIn('status', [self::STATUS_PENDING_PAYMENT, self::STATUS_PAID])
            ->whereNotNull('verified_at')
            ->when($website !== null && trim($website) !== '', function ($query) use ($website) {
                $query->where('website', trim($website));
            })
            ->get()
            ->filter(fn (SitePromotionClaim $claim) => self::claimNeedsWelcomePayment($claim))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function adminApprovePendingPayment(AdminUser $customer, string $actor = 'admin'): bool
    {
        if (! Schema::hasTable('site_promotion_claims')) {
            return false;
        }

        $claim = SitePromotionClaim::query()
            ->where('user_id', $customer->user_id)
            ->where('website', trim((string) $customer->website))
            ->where('status', self::STATUS_PENDING_PAYMENT)
            ->whereNotNull('verified_at')
            ->orderByDesc('id')
            ->first();

        if (! $claim) {
            return false;
        }

        $claim->update([
            'status' => self::STATUS_PENDING_PAYMENT,
            'payment_required' => 1,
            'payment_reference' => 'admin-approved-account:'.$actor,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public static function completeManualApprovalClaim(AdminUser $customer, string $actor = 'admin'): bool
    {
        if (! Schema::hasTable('site_promotion_claims')) {
            return false;
        }

        $claim = SitePromotionClaim::query()
            ->where('user_id', $customer->user_id)
            ->where('website', trim((string) $customer->website))
            ->whereIn('status', [
                self::STATUS_PENDING_VERIFICATION,
                self::STATUS_PENDING_PAYMENT,
                self::STATUS_PAID,
            ])
            ->whereNull('redeemed_order_id')
            ->orderByDesc('id')
            ->first();

        if (! $claim) {
            return false;
        }

        $completedAt = now()->format('Y-m-d H:i:s');

        $claim->update(self::claimPayload([
            'status' => self::STATUS_PAID,
            'verified_at' => $claim->verified_at ?: $completedAt,
            'payment_required' => 0,
            'payment_reference' => 'admin-approved-offer:'.$actor,
            'paid_at' => $completedAt,
            'updated_at' => $completedAt,
        ]));

        return true;
    }

    public static function adminFinalizeCustomerActivation(AdminUser $customer, string $actor = 'admin'): bool
    {
        $changed = false;

        if (Schema::hasTable('customer_activation_tokens')) {
            $deleted = \Illuminate\Support\Facades\DB::table('customer_activation_tokens')
                ->where('customer_user_id', $customer->user_id)
                ->when(trim((string) $customer->website) !== '', function ($query) use ($customer) {
                    $query->where('site_legacy_key', trim((string) $customer->website));
                })
                ->delete();

            $changed = $changed || $deleted > 0;
        }

        if (self::completeManualApprovalClaim($customer, $actor)) {
            $changed = true;
        }

        return $changed;
    }

    public static function adminVerifyPendingClaim(AdminUser $customer): bool
    {
        if (! Schema::hasTable('site_promotion_claims')) {
            return false;
        }

        $claim = SitePromotionClaim::query()
            ->where('user_id', $customer->user_id)
            ->where('website', trim((string) $customer->website))
            ->where('status', self::STATUS_PENDING_VERIFICATION)
            ->orderByDesc('id')
            ->first();

        if (! $claim) {
            return false;
        }

        $verifiedAt = $claim->verified_at ?: now()->format('Y-m-d H:i:s');
        $nextStatus = (float) $claim->required_payment_amount > 0
            ? self::STATUS_PENDING_PAYMENT
            : self::STATUS_PAID;

        $payload = [
            'status' => $nextStatus,
            'verified_at' => $verifiedAt,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        if ($nextStatus === self::STATUS_PAID) {
            $payload['paid_at'] = $verifiedAt;
            $payload['payment_required'] = 0;
        }

        $claim->update(self::claimPayload($payload));

        return true;
    }

    public static function markClaimVerified(SiteContext $site, AdminUser $customer): ?SitePromotionClaim
    {
        $claim = self::claimForCustomer($site, $customer, [self::STATUS_PENDING_VERIFICATION, self::STATUS_PENDING_PAYMENT]);

        if (! $claim) {
            return null;
        }

        $nextStatus = (float) $claim->required_payment_amount > 0
            ? self::STATUS_PENDING_PAYMENT
            : self::STATUS_PAID;

        $claim->update(self::claimPayload([
            'status' => $nextStatus,
            'verified_at' => $claim->verified_at ?: now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]));

        return $claim->fresh(['promotion']);
    }

    public static function bindTransaction(SitePromotionClaim $claim, PaymentTransaction $transaction): void
    {
        $claim->update(self::claimPayload([
            'payment_transaction_id' => $transaction->id,
            'payment_reference' => $transaction->merchant_reference,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]));
    }

    private static function claimPayload(array $payload): array
    {
        if (! Schema::hasTable('site_promotion_claims')) {
            return [];
        }

        $columns = Schema::getColumnListing('site_promotion_claims');

        return array_intersect_key($payload, array_flip($columns));
    }

    public static function reconcilePaidClaimTransaction(
        PaymentTransaction $transaction,
        SiteContext $site,
        float $confirmedAmount,
        string $providerReference
    ): array {
        $claim = SitePromotionClaim::query()
            ->with(['customer', 'promotion'])
            ->where(function ($query) use ($transaction) {
                $query->where('payment_transaction_id', $transaction->id)
                    ->orWhere('payment_reference', $transaction->merchant_reference);
            })
            ->orderByDesc('id')
            ->first();

        if (! $claim || (int) $claim->user_id !== (int) $transaction->user_id || (int) $claim->site_id !== (int) $transaction->site_id) {
            return [
                'ok' => false,
                'message' => 'The welcome-offer payment could not be matched to a signup record.',
            ];
        }

        $expectedAmount = round((float) $claim->required_payment_amount, 2);
        if ($expectedAmount > 0 && $confirmedAmount + 0.0001 < $expectedAmount) {
            return [
                'ok' => false,
                'message' => 'The welcome-offer payment amount did not fully match the required verification payment.',
            ];
        }

        $creditAmount = round((float) $claim->credit_amount, 2);
        if ($creditAmount > 0) {
            CustomerBalance::addPaymentCredit(
                (int) $claim->user_id,
                $site->legacyKey,
                $creditAmount,
                $transaction->merchant_reference.':signup-offer',
                'signup-offer',
                'Welcome-offer payment stored as customer credit.',
                'payment'
            );
        }

        $claim->update([
            'status' => self::STATUS_PAID,
            'payment_transaction_id' => $transaction->id,
            'payment_reference' => $providerReference !== '' ? $providerReference : $transaction->merchant_reference,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Activate the customer account now that the welcome payment is confirmed.
        $customer = $claim->customer;
        if ($customer && (int) $customer->is_active !== 1) {
            $customer->update([
                'is_active' => 1,
                'exist_customer' => '1',
            ]);
        }

        return [
            'ok' => true,
            'message' => $creditAmount > 0
                ? 'Your email is verified and your welcome payment was recorded. The amount is available for your first order on this website.'
                : 'Your email is verified and your welcome payment was recorded successfully.',
        ];
    }

    public static function applyEligibleFirstOrderAmount(Order $order, float $amount): float
    {
        $claim = self::eligiblePaidClaimForOrder($order);
        if (! $claim) {
            return round($amount, 2);
        }

        $snapshot = self::claimSnapshot($claim);
        $stitchThreshold = self::configInteger($snapshot, 'first_order_free_under_stitches', 0);

        if ($stitchThreshold > 0) {
            if (self::qualifiesForFreeUnderStitches($order, $stitchThreshold)) {
                return 0.0;
            }

            // Charge only for stitches above the threshold (proportional).
            // Example: 12k stitches on a 10k threshold at $X → charge (2k/12k) × $X.
            $excessCharge = self::excessStitchCharge($order, $stitchThreshold, $amount);
            if ($excessCharge !== null) {
                return $excessCharge;
            }
        }

        // Flat-amount offer only applies to digitizing orders, not vector/color.
        if (! in_array((string) $order->order_type, ['order', 'digitzing'], true)) {
            return round($amount, 2);
        }

        $flatAmount = round((float) $claim->first_order_flat_amount, 2);
        if ($flatAmount <= 0) {
            return round($amount, 2);
        }

        return $flatAmount;
    }

    private static function excessStitchCharge(Order $order, int $stitchThreshold, float $fullAmount): ?float
    {
        if (! in_array((string) $order->order_type, ['order', 'digitzing'], true)) {
            return null;
        }

        $stitches = (float) trim((string) ($order->stitches ?? ''));

        if ($stitches <= 0 || $stitches <= (float) $stitchThreshold) {
            return null;
        }

        $excess = $stitches - (float) $stitchThreshold;

        return max(0.0, round(($excess / $stitches) * $fullAmount, 2));
    }

    public static function redeemClaimForOrder(Order $order, ?Billing $billing = null, string $createdBy = 'signup-offer'): void
    {
        $claim = self::eligiblePaidClaimForOrder($order);
        if (! $claim) {
            return;
        }

        $claim->update([
            'status' => self::STATUS_REDEEMED,
            'redeemed_order_id' => $order->order_id,
            'redeemed_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        if ($billing && (string) $billing->payment === 'no') {
            CustomerBalance::applyToBilling($billing, $createdBy);
        }
    }

    public static function customerShouldCompleteOfferPayment(SiteContext $site, AdminUser $customer): bool
    {
        return self::pendingPaymentClaimForCustomer($site, $customer) !== null;
    }

    private static function eligiblePaidClaimForOrder(Order $order): ?SitePromotionClaim
    {
        if (! Schema::hasTable('site_promotion_claims') || ! Schema::hasTable('orders')) {
            return null;
        }

        $website = self::effectiveWebsiteKeyForOrder($order);

        // Also match a claim already redeemed for this exact order so that
        // re-approvals (admin editing after initial approval) continue to
        // respect the free-order benefit instead of reverting to full price.
        $claimQuery = SitePromotionClaim::query()
            ->with('promotion')
            ->where('user_id', $order->user_id)
            ->where(function ($q) use ($order) {
                $q->where(function ($paid) {
                    $paid->where('status', self::STATUS_PAID)
                         ->whereNull('redeemed_order_id');
                })->orWhere(function ($redeemed) use ($order) {
                    $redeemed->where('status', self::STATUS_REDEEMED)
                             ->where('redeemed_order_id', $order->order_id);
                });
            });

        self::applyWebsiteFilter($claimQuery, 'website', $website);

        $claim = $claimQuery->orderBy('id')->first();

        if (! $claim) {
            return null;
        }

        if (! self::claimCountsAsCompletedOffer($claim)) {
            return null;
        }

        $firstEligibleOrderId = Order::query()
            ->active()
            ->where('user_id', $order->user_id)
            ->forWebsite($website)
            ->whereIn('order_type', ['order', 'vector', 'color', 'digitzing'])
            ->orderBy('order_id')
            ->value('order_id');

        if ((int) $firstEligibleOrderId !== (int) $order->order_id) {
            return null;
        }

        return $claim;
    }

    private static function configMoney(array $config, string $key, float $default = 0.0): float
    {
        $value = $config[$key] ?? $default;

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : round($default, 2);
    }

    private static function configInteger(array $config, string $key, int $default = 0): int
    {
        $value = $config[$key] ?? $default;

        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }

        $clean = preg_replace('/[^0-9\-]/', '', (string) $value);

        return is_numeric($clean) ? max(0, (int) $clean) : $default;
    }

    private static function claimSnapshot(SitePromotionClaim $claim): array
    {
        $decoded = json_decode((string) ($claim->offer_snapshot_json ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function qualifiesForFreeUnderStitches(Order $order, int $stitchThreshold): bool
    {
        if (! in_array((string) $order->order_type, ['order', 'digitzing'], true)) {
            return false;
        }

        $stitches = trim((string) ($order->stitches ?? ''));

        if ($stitches === '' || ! is_numeric($stitches)) {
            return false;
        }

        return (float) $stitches > 0 && (float) $stitches <= (float) $stitchThreshold;
    }

    public static function claimNeedsWelcomePayment(SitePromotionClaim $claim): bool
    {
        return (float) $claim->required_payment_amount > 0
            && ! self::isAdminApprovalReference((string) $claim->payment_reference)
            && ! self::claimHasActualPayment($claim)
            && ! empty($claim->verified_at)
            && (string) $claim->status !== self::STATUS_PENDING_VERIFICATION;
    }

    public static function claimHasActualPayment(SitePromotionClaim $claim): bool
    {
        $status = (string) $claim->status;
        if ($status !== self::STATUS_PAID && $status !== self::STATUS_REDEEMED) {
            return false;
        }

        if (self::isAdminApprovalReference((string) $claim->payment_reference)) {
            return false;
        }

        if (! empty($claim->payment_transaction_id)) {
            return true;
        }

        return trim((string) $claim->payment_reference) !== '' && ! empty($claim->paid_at);
    }

    public static function claimCountsAsCompletedOffer(SitePromotionClaim $claim): bool
    {
        if (self::claimHasActualPayment($claim)) {
            return true;
        }

        $status = (string) $claim->status;
        if ($status !== self::STATUS_PAID && $status !== self::STATUS_REDEEMED) {
            return false;
        }

        return self::isAdminApprovalReference((string) $claim->payment_reference)
            && (int) $claim->payment_required === 0
            && ! empty($claim->paid_at);
    }

    private static function isAdminApprovalReference(string $reference): bool
    {
        return str_starts_with(trim(strtolower($reference)), 'admin-approved');
    }

    private static function effectiveWebsiteKeyForOrder(Order $order): string
    {
        $website = trim((string) ($order->website ?: $order->customer?->website ?: config('sites.primary_legacy_key', '1dollar')));

        return $website !== '' ? $website : (string) config('sites.primary_legacy_key', '1dollar');
    }

    private static function applyWebsiteFilter($query, string $column, ?string $website): void
    {
        $website = trim((string) $website);

        if ($website === '') {
            return;
        }

        $primaryWebsite = (string) config('sites.primary_legacy_key', '1dollar');

        $query->where(function ($siteQuery) use ($column, $website, $primaryWebsite) {
            $siteQuery->where($column, $website);

            if (strcasecmp($website, $primaryWebsite) === 0) {
                $siteQuery
                    ->orWhereNull($column)
                    ->orWhere($column, '');
            }
        });
    }
}
