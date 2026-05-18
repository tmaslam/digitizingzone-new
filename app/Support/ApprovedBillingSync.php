<?php

namespace App\Support;

use App\Models\Billing;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class ApprovedBillingSync
{
    public static function syncMissingApprovedBillings(): int
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('billing')) {
            return 0;
        }

        $synced = 0;

        Order::query()
            ->active()
            ->orderManagement()
            ->where('status', 'approved')
            ->whereNotIn('order_id', Billing::query()->active()->select('order_id')->where('approved', 'yes'))
            ->orderBy('order_id')
            ->chunk(200, function ($orders) use (&$synced) {
                foreach ($orders as $order) {
                    if (self::ensureForOrder($order) !== null) {
                        $synced++;
                    }
                }
            });

        return $synced;
    }

    public static function ensureForOrder(Order $order): ?Billing
    {
        if ((string) $order->status !== 'approved' || ! in_array((string) $order->order_type, ['order', 'vector', 'color'], true)) {
            return null;
        }

        $approvedBilling = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where('approved', 'yes')
            ->orderByDesc('bill_id')
            ->first();

        if ($approvedBilling) {
            return $approvedBilling;
        }

        $latestBilling = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->orderByDesc('bill_id')
            ->first();

        $payload = [
            'user_id' => $order->user_id,
            'order_id' => $order->order_id,
            'approved' => 'yes',
            'amount' => (string) ($order->total_amount ?: $latestBilling?->amount ?: '0.00'),
            'earned_amount' => '',
            'payment' => $latestBilling && ((string) $latestBilling->payment === 'yes' || (int) $latestBilling->is_paid === 1) ? 'yes' : 'no',
            'approve_date' => $latestBilling?->approve_date ?: ($order->completion_date ?: now()->format('Y-m-d G:i')),
            'comments' => $latestBilling?->comments ?: 'Approved order synchronized into billing.',
            'transid' => $latestBilling?->transid ?: '',
            'trandtime' => $latestBilling?->trandtime,
            'website' => $order->website ?: config('sites.primary_legacy_key', '1dollar'),
            'is_paid' => $latestBilling && ((string) $latestBilling->payment === 'yes' || (int) $latestBilling->is_paid === 1) ? 1 : 0,
            'is_advance' => (int) ($latestBilling?->is_advance ?: 0),
        ];
        $payload = Billing::writablePayload($payload);

        if ($latestBilling) {
            $latestBilling->update($payload);

            return $latestBilling->fresh();
        }

        return Billing::query()->create($payload);
    }
}
