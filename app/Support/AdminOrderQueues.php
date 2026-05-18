<?php

namespace App\Support;

class AdminOrderQueues
{
    private const DEFAULT_QUEUE = 'new-orders';

    private const DEFINITIONS = [
        'all-orders' => [
            'label' => 'All Orders',
            'category' => 'all_orders',
            'group' => 'orders',
            'summary' => 'All order-management records across the active workflow.',
            'chip' => 'All Order Records',
            'count_key' => 'all_orders',
        ],
        'new-orders' => [
            'label' => 'New Orders',
            'category' => 'new_orders',
            'group' => 'orders',
            'summary' => 'New order intake waiting for admin review and assignment.',
            'chip' => 'New Intake',
            'count_key' => 'new_orders',
        ],
        'disapproved-orders' => [
            'label' => 'Disapproved Orders',
            'category' => 'disapproved_orders',
            'group' => 'orders',
            'summary' => 'Orders currently marked disapproved and waiting for the next step.',
            'chip' => 'Disapproved',
            'count_key' => 'disapproved_orders',
        ],
        'designer-orders' => [
            'label' => 'Designer Orders',
            'category' => 'designer_orders',
            'group' => 'orders',
            'summary' => 'Orders currently assigned and in production.',
            'chip' => 'Assigned Orders',
            'count_key' => 'designer_orders',
        ],
        'designer-completed' => [
            'label' => 'Designer Completed',
            'category' => 'designer_completed_orders',
            'group' => 'orders',
            'summary' => 'Work returned by production and ready for admin review.',
            'chip' => 'Review Ready',
            'count_key' => 'designer_completed_orders',
        ],
        'approval-waiting' => [
            'label' => 'Customer Approval Waiting',
            'category' => 'approval_waiting_orders',
            'group' => 'orders',
            'summary' => 'Completed jobs waiting on customer approval or an admin decision.',
            'chip' => 'Approval Queue',
            'count_key' => 'approval_waiting_orders',
        ],
        'approved-orders' => [
            'label' => 'Approved Orders',
            'category' => 'approved_orders',
            'group' => 'orders',
            'summary' => 'Approved records that are still unpaid and need payment follow-up.',
            'chip' => 'Approved / Unpaid',
            'count_key' => 'approved_orders',
        ],
        'new-quotes' => [
            'label' => 'New Quotes',
            'category' => 'new_quotes',
            'group' => 'quotes',
            'summary' => 'New quote intake waiting for admin review and assignment.',
            'chip' => 'New Quotes',
            'count_key' => 'new_quotes',
        ],
        'assigned-quotes' => [
            'label' => 'Assigned Quotes',
            'category' => 'assigned_quotes',
            'group' => 'quotes',
            'summary' => 'Quotes currently assigned and in production.',
            'chip' => 'Assigned Quotes',
            'count_key' => 'assigned_quotes',
        ],
        'designer-completed-quotes' => [
            'label' => 'Designer Completed Quotes',
            'category' => 'designer_completed_quotes',
            'group' => 'quotes',
            'summary' => 'Quotes returned by production and ready for admin review.',
            'chip' => 'Quote Review Ready',
            'count_key' => 'designer_completed_quotes',
        ],
        'completed-quotes' => [
            'label' => 'Completed Quotes',
            'category' => 'completed_quotes',
            'group' => 'quotes',
            'summary' => 'Completed quotes plus customer price responses waiting on admin follow-up.',
            'chip' => 'Quote Response Queue',
            'count_key' => 'completed_quotes',
        ],
        'quote-negotiations' => [
            'label' => 'Quote Negotiations',
            'category' => 'quote_negotiations',
            'group' => 'quotes',
            'summary' => 'Quotes where the customer rejected pricing and is waiting on admin review.',
            'chip' => 'Negotiation Review',
            'count_key' => 'quote_negotiations',
        ],
    ];

    public static function normalize(?string $value): string
    {
        return self::match($value) ?? self::DEFAULT_QUEUE;
    }

    public static function match(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $slug = strtolower($raw);
        if (isset(self::DEFINITIONS[$slug])) {
            return $slug;
        }

        foreach (self::DEFINITIONS as $key => $definition) {
            if (strcasecmp($raw, $definition['label']) === 0 || strcasecmp($raw, $definition['category']) === 0) {
                return $key;
            }
        }

        return null;
    }

    public static function definition(string $queue): array
    {
        $queue = self::normalize($queue);

        return self::DEFINITIONS[$queue];
    }

    public static function label(string $queue): string
    {
        return self::definition($queue)['label'];
    }

    public static function category(string $queue): string
    {
        return self::definition($queue)['category'];
    }

    public static function chip(string $queue): string
    {
        return self::definition($queue)['chip'];
    }

    public static function summary(string $queue): string
    {
        return self::definition($queue)['summary'];
    }

    public static function group(string $queue): string
    {
        return self::definition($queue)['group'];
    }

    public static function isQuoteQueue(string $queue): bool
    {
        return self::group($queue) === 'quotes';
    }

    public static function url(string $queue, array $query = []): string
    {
        $queue = self::normalize($queue);
        $base = url('/v/orders/'.$queue);

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    public static function navigation(array $counts, string $group): array
    {
        $items = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            if ($definition['group'] !== $group) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $definition['label'],
                'url' => self::url($key),
                'count' => (int) ($counts[$definition['count_key']] ?? 0),
            ];
        }

        return $items;
    }
}
