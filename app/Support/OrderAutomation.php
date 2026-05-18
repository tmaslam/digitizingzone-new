<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\Order;
use App\Models\QuoteNegotiation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class OrderAutomation
{
    public static function syncCustomer(AdminUser $customer, SiteContext $site, bool $submissionTriggered = false): void
    {
        if ((int) ($customer->is_active ?? 1) !== 1) {
            return;
        }

        self::autoApproveByPaymentTerms($customer, $site);
        self::enforcePendingApprovalLimit($customer, $site, $submissionTriggered);
        self::enforceCreditLimitExposure($customer, $site);
    }

    public static function syncSite(string $legacyWebsite, int $limit = 100): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('orders')) {
            return;
        }

        $site = SiteResolver::fromLegacyKey($legacyWebsite)
            ?: SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        $customers = AdminUser::query()
            ->where('usre_type_id', AdminUser::TYPE_CUSTOMER)
            ->where(function ($query) use ($legacyWebsite) {
                $query->where('website', $legacyWebsite);

                if ($legacyWebsite === (string) config('sites.primary_legacy_key', '1dollar')) {
                    $query->orWhereNull('website')->orWhere('website', '');
                }
            })
            ->where(function ($query) {
                $query->where(function ($termQuery) {
                    $termQuery->whereNotNull('payment_terms')
                        ->where('payment_terms', '!=', '')
                        ->where('payment_terms', '!=', '0');
                })->orWhere(function ($limitQuery) {
                    $limitQuery->whereNotNull('customer_pending_order_limit')
                        ->where('customer_pending_order_limit', '!=', '')
                        ->where('customer_pending_order_limit', '!=', '0');
                })->orWhere(function ($creditQuery) {
                    $creditQuery->whereNotNull('customer_approval_limit')
                        ->where('customer_approval_limit', '!=', '')
                        ->where('customer_approval_limit', '!=', '0');
                });
            })
            ->orderBy('user_id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($customers as $customer) {
            self::syncCustomer($customer, $site, false);
        }
    }

    private static function autoApproveByPaymentTerms(AdminUser $customer, SiteContext $site): void
    {
        $paymentTermsDays = max(0, (int) ($customer->payment_terms ?? 0));
        if ($paymentTermsDays <= 0) {
            return;
        }

        $now = now();
        $orders = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', OrderWorkflow::orderManagementTypes())
            ->where('status', 'done')
            ->orderBy('order_id')
            ->get();

        foreach ($orders as $order) {
            if (self::shouldStayInApprovalWaiting($order)) {
                continue;
            }

            $baseDate = self::approvalReferenceDate($order);
            if (! $baseDate || $baseDate->copy()->addDays($paymentTermsDays)->greaterThan($now)) {
                continue;
            }

            self::approveOrderToBilling($order, $customer, sprintf('Order auto-approved after %d day payment term.', $paymentTermsDays));
        }
    }

    private static function enforcePendingApprovalLimit(AdminUser $customer, SiteContext $site, bool $submissionTriggered): void
    {
        $pendingLimit = max(0, (int) ($customer->customer_pending_order_limit ?? 0));
        if ($pendingLimit <= 0) {
            return;
        }

        $approvalWaiting = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', OrderWorkflow::orderManagementTypes())
            ->where('status', 'done')
            ->get()
            ->sortBy(fn (Order $order) => self::approvalQueueSortKey($order))
            ->values();

        $overflowCount = $approvalWaiting->count() - $pendingLimit + ($submissionTriggered ? 1 : 0);
        if ($overflowCount <= 0) {
            return;
        }

        $approvalWaiting
            ->reject(fn (Order $order) => self::shouldStayInApprovalWaiting($order))
            ->values()
            ->take($overflowCount)
            ->each(function (Order $order) use ($customer) {
            self::approveOrderToBilling($order, $customer, 'Order approved automatically because the pending order limit was exceeded.');
            });
    }

    private static function enforceCreditLimitExposure(AdminUser $customer, SiteContext $site): void
    {
        $creditLimit = round((float) ($customer->customer_approval_limit ?? 0), 2);
        if ($creditLimit <= 0) {
            return;
        }

        $currentExposure = self::unpaidApprovedBillingTotal($customer, $site);
        if ($currentExposure >= $creditLimit) {
            return;
        }

        $approvalWaiting = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', OrderWorkflow::orderManagementTypes())
            ->where('status', 'done')
            ->get()
            ->sortBy(fn (Order $order) => self::approvalQueueSortKey($order))
            ->reject(fn (Order $order) => self::shouldStayInApprovalWaiting($order))
            ->values();

        if ($approvalWaiting->count() <= 1) {
            return;
        }

        foreach ($approvalWaiting->slice(0, $approvalWaiting->count() - 1) as $order) {
            $amount = self::orderAmount($order);
            if ($amount <= 0) {
                continue;
            }

            self::approveOrderToBilling($order, $customer, 'Order approved automatically because the customer credit limit was reached.');
            $currentExposure += $amount;

            if ($currentExposure >= $creditLimit) {
                break;
            }
        }
    }

    private static function approveOrderToBilling(Order $order, AdminUser $customer, string $comments): void
    {
        $amount = self::orderAmount($order);
        $approvedAt = now();
        $existingBilling = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->orderByDesc('bill_id')
            ->first();

        if ($amount > 0) {
                Billing::query()->updateOrCreate(
                [
                    'order_id' => $order->order_id,
                    'user_id' => $customer->user_id,
                    'end_date' => null,
                ],
                Billing::writablePayload([
                    'approved' => 'yes',
                    'amount' => number_format($amount, 2, '.', ''),
                    'earned_amount' => (string) ($existingBilling?->earned_amount ?? ''),
                    'payment' => (string) ($existingBilling?->payment ?: 'no'),
                    'approve_date' => $approvedAt->format('Y-m-d H:i'),
                    'comments' => $existingBilling?->comments ?: $comments,
                    'transid' => $existingBilling?->transid ?: '',
                    'trandtime' => $existingBilling?->trandtime,
                    'website' => (string) ($order->website ?: $customer->website ?: config('sites.primary_legacy_key', '1dollar')),
                    'site_id' => $order->site_id ?: $customer->site_id,
                    'payer_id' => $existingBilling?->payer_id,
                    'is_paid' => (int) ($existingBilling?->is_paid ?: 0),
                    'is_advance' => (int) ($existingBilling?->is_advance ?: 0),
                ])
            );
        }

        $order->update([
            'status' => 'approved',
            'modified_date' => $approvedAt->format('Y-m-d H:i:s'),
        ]);
    }

    private static function orderAmount(Order $order): float
    {
        $amount = trim((string) ($order->total_amount ?: $order->stitches_price ?: ''));

        return is_numeric($amount) ? round((float) $amount, 2) : 0.0;
    }

    private static function unpaidApprovedBillingTotal(AdminUser $customer, SiteContext $site): float
    {
        return (float) Billing::query()
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
            ->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));
    }

    private static function approvalReferenceDate(Order $order): ?Carbon
    {
        foreach ([
            $order->completion_date,
            $order->modified_date,
            $order->vender_complete_date,
            $order->submit_date,
        ] as $candidate) {
            $parsed = self::parseDate($candidate);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);

        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function shouldStayInApprovalWaiting(Order $order): bool
    {
        if ((string) $order->status !== 'done') {
            return false;
        }

        if (! Schema::hasTable('quote_negotiations')) {
            return false;
        }

        return QuoteNegotiation::query()
            ->where('order_id', $order->order_id)
            ->exists();
    }

    private static function approvalQueueSortKey(Order $order): string
    {
        $reference = self::approvalReferenceDate($order);
        $timestamp = $reference?->format('Y-m-d H:i:s') ?: '9999-12-31 23:59:59';

        return $timestamp.'|'.str_pad((string) $order->order_id, 12, '0', STR_PAD_LEFT);
    }
}
