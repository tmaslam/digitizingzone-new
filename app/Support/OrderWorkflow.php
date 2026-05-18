<?php

namespace App\Support;

use App\Models\Order;

class OrderWorkflow
{
    public static function quoteManagementTypes(): array
    {
        return ['quote', 'digitzing', 'q-vector', 'qcolor'];
    }

    public static function orderManagementTypes(): array
    {
        return ['order', 'vector', 'color'];
    }

    public static function isVectorWork(Order|string $orderOrType, ?string $legacyType = null): bool
    {
        $orderType = $orderOrType instanceof Order ? (string) $orderOrType->order_type : (string) $orderOrType;
        $legacyType = $orderOrType instanceof Order ? (string) $orderOrType->type : (string) $legacyType;

        return in_array($orderType, ['vector', 'q-vector', 'color', 'qcolor'], true)
            || strtolower(trim($legacyType)) === 'vector';
    }

    public static function workTypeLabel(Order $order): string
    {
        return self::isVectorWork($order) ? 'Vector' : 'Digitizing';
    }

    public static function flowContextLabel(Order $order): string
    {
        if ((string) $order->order_type === 'qquote') {
            return 'Quick Quote';
        }

        return in_array((string) $order->order_type, self::quoteManagementTypes(), true) ? 'Quote' : 'Order';
    }

    public static function pageForOrder(Order $order): string
    {
        if ((string) $order->order_type === 'qquote') {
            return 'qquote';
        }

        if (self::isVectorWork($order)) {
            return 'vector';
        }

        return in_array((string) $order->order_type, self::quoteManagementTypes(), true) ? 'quote' : 'order';
    }

    public static function createTypeMapping(string $flowContext, string $workType): array
    {
        $flowContext = strtolower(trim($flowContext));
        $workType = strtolower(trim($workType));

        return match (true) {
            $flowContext === 'code' && $workType === 'vector' => [
                'order_type' => 'q-vector',
                'type' => 'vector',
                'page' => 'vector',
                'source_file_source' => 'vector',
            ],
            $flowContext === 'code' => [
                'order_type' => 'digitzing',
                'type' => 'digitizing',
                'page' => 'quote',
                'source_file_source' => 'quote',
            ],
            $workType === 'vector' => [
                'order_type' => 'vector',
                'type' => 'vector',
                'page' => 'vector',
                'source_file_source' => 'vector',
            ],
            default => [
                'order_type' => 'order',
                'type' => 'digitizing',
                'page' => 'order',
                'source_file_source' => 'order',
            ],
        };
    }
}
