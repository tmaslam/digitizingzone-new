<?php

namespace App\Support;

use App\Models\Order;

class TeamWorkQueues
{
    private const DEFAULT_QUEUE = 'new-orders';

    private const DEFINITIONS = [
        'new-orders' => [
            'label' => 'New Orders',
            'summary' => 'Assigned order work that has not been started yet.',
            'count_key' => 'new_orders',
        ],
        'working-orders' => [
            'label' => 'Working Orders',
            'summary' => 'Assigned order work that already has a start time and is currently in progress.',
            'count_key' => 'working_orders',
        ],
        'disapproved-orders' => [
            'label' => 'Disapproved Orders',
            'summary' => 'Work returned for revision before it can move forward again.',
            'count_key' => 'disapproved_orders',
        ],
        'quotes' => [
            'label' => 'New Quotes',
            'summary' => 'Assigned quote work waiting for production completion.',
            'count_key' => 'quotes',
        ],
        'quick-quotes' => [
            'label' => 'Quick Quotes',
            'summary' => 'Quick-quote requests assigned to the current production user.',
            'count_key' => 'quick_quotes',
        ],
    ];

    public static function normalize(?string $value): string
    {
        $raw = strtolower(trim((string) $value));

        if ($raw === '') {
            return self::DEFAULT_QUEUE;
        }

        if (isset(self::DEFINITIONS[$raw])) {
            return $raw;
        }

        return match ($raw) {
            'disapproved' => 'disapproved-orders',
            'working' => 'working-orders',
            'new_orders', 'new orders' => 'new-orders',
            'working_orders', 'under process orders', 'underprocess' => 'working-orders',
            'disapproved_orders', 'disapproved orders' => 'disapproved-orders',
            'new quotes' => 'quotes',
            'quick quotes list', 'quick_quotes' => 'quick-quotes',
            default => self::DEFAULT_QUEUE,
        };
    }

    public static function definition(string $queue): array
    {
        return self::DEFINITIONS[self::normalize($queue)];
    }

    public static function label(string $queue): string
    {
        return self::definition($queue)['label'];
    }

    public static function summary(string $queue): string
    {
        return self::definition($queue)['summary'];
    }

    public static function countKey(string $queue): string
    {
        return self::definition($queue)['count_key'];
    }

    public static function url(string $queue): string
    {
        return url('/team/queues/'.self::normalize($queue));
    }

    public static function navigation(array $counts): array
    {
        $visibleQueues = array_values(array_filter(array_keys(self::DEFINITIONS), fn (string $key) => $key !== 'quick-quotes'));

        return array_map(function (string $key) use ($counts) {
            return [
                'key' => $key,
                'label' => self::label($key),
                'summary' => self::summary($key),
                'url' => self::url($key),
                'count' => (int) ($counts[self::countKey($key)] ?? 0),
            ];
        }, $visibleQueues);
    }

    public static function detailModeForQueue(string $queue): string
    {
        return match (self::normalize($queue)) {
            'quotes' => 'quote',
            'disapproved-orders' => 'disapproved',
            default => 'order',
        };
    }

    public static function detailModeForOrder(Order $order): string
    {
        if (in_array((string) $order->status, ['disapprove', 'disapproved'], true)) {
            return 'disapproved';
        }

        if (in_array((string) $order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true)) {
            return 'quote';
        }

        return 'order';
    }

    public static function detailUrl(Order $order, ?string $mode = null, ?string $queue = null): string
    {
        $resolvedMode = self::normalizeMode($mode ?: self::detailModeForOrder($order));
        $url = url('/team/orders/'.$order->order_id.'/detail/'.$resolvedMode);
        $resolvedQueue = trim((string) $queue) !== '' ? self::normalize($queue) : '';

        return $resolvedQueue !== ''
            ? $url.'?queue='.rawurlencode($resolvedQueue)
            : $url;
    }

    public static function normalizeMode(?string $value): string
    {
        $raw = strtolower(trim((string) $value));

        return match ($raw) {
            'quote' => 'quote',
            'disapproved', 'disapprove' => 'disapproved',
            default => 'order',
        };
    }

    public static function backUrl(string $queueOrMode): string
    {
        $normalized = self::normalize($queueOrMode);
        if (isset(self::DEFINITIONS[$normalized])) {
            return self::url($normalized);
        }

        return match (self::normalizeMode($queueOrMode)) {
            'quote' => self::url('quotes'),
            'disapproved' => self::url('disapproved-orders'),
            default => self::url('new-orders'),
        };
    }
}
