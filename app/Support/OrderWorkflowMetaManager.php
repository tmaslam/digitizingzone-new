<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderWorkflowMeta;
use Illuminate\Support\Facades\Schema;

class OrderWorkflowMetaManager
{
    public static function hasTable(): bool
    {
        return Schema::hasTable('order_workflow_meta');
    }

    public static function forOrder(Order|int $order): ?OrderWorkflowMeta
    {
        if (! self::hasTable()) {
            return null;
        }

        $orderId = $order instanceof Order ? (int) $order->order_id : (int) $order;

        return OrderWorkflowMeta::query()
            ->active()
            ->where('order_id', $orderId)
            ->first();
    }

    public static function ensure(Order|int $order, array $attributes = []): ?OrderWorkflowMeta
    {
        if (! self::hasTable()) {
            return null;
        }

        $orderId = $order instanceof Order ? (int) $order->order_id : (int) $order;
        $meta = OrderWorkflowMeta::query()->firstOrNew(['order_id' => $orderId]);

        if (! $meta->exists) {
            $meta->fill([
                'created_source' => 'customer',
                'historical_backfill' => 0,
                'suppress_customer_notifications' => 0,
                'delivery_override' => 'auto',
                'order_credit_limit' => null,
                'date_added' => now()->format('Y-m-d H:i:s'),
                'end_date' => null,
                'deleted_by' => null,
            ]);
        }

        $meta->fill(array_merge($attributes, [
            'date_modified' => now()->format('Y-m-d H:i:s'),
        ]));
        $meta->save();

        return $meta;
    }

    public static function isAdminCreated(Order|int $order): bool
    {
        return in_array((string) (self::forOrder($order)?->created_source ?: 'customer'), ['admin_assisted', 'admin_backfill'], true);
    }

    public static function isHistorical(Order|int $order): bool
    {
        return (int) (self::forOrder($order)?->historical_backfill ?: 0) === 1;
    }

    public static function isQuoteConverted(Order|int $order): bool
    {
        return in_array((string) (self::forOrder($order)?->created_source ?: ''), [
            'customer_quote_conversion',
            'admin_quote_conversion',
        ], true);
    }

    public static function shouldSendCustomerNotification(Order $order, ?bool $requested = null): bool
    {
        $meta = self::forOrder($order);

        if (! $meta || ! self::isAdminCreated($order)) {
            return true;
        }

        $suppressedByDefault = (int) ($meta->suppress_customer_notifications ?? 0) === 1
            || (int) ($meta->historical_backfill ?? 0) === 1;

        if ($requested !== null) {
            return $requested;
        }

        return ! $suppressedByDefault;
    }
}
