<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Order;

class PricingResolver
{
    public static function forAdminEntry(
        AdminUser $customer,
        string $flowContext,
        string $workType,
        string $turnaroundTime,
        string $units
    ): array {
        $flowContext = strtolower(trim($flowContext));
        $workType = strtolower(trim($workType));
        $units = trim($units);

        if ($workType === 'vector') {
            $normalizedHours = TeamPricing::normalizeHours($units);

            if ($normalizedHours === null) {
                return [
                    'ok' => false,
                    'message' => 'Enter total hours as a whole number or HH:MM to calculate the price.',
                ];
            }

            $error = SitePricing::configurationError(
                null,
                (string) ($customer->website ?: config('sites.primary_legacy_key', '1dollar')),
                $customer->site_id ? (int) $customer->site_id : null,
                'vector',
                $turnaroundTime
            );

            if ($error !== null) {
                return [
                    'ok' => false,
                    'message' => $error,
                ];
            }

            $amount = SitePricing::vector(
                (string) ($customer->website ?: config('sites.primary_legacy_key', '1dollar')),
                $customer->site_id ? (int) $customer->site_id : null,
                $turnaroundTime,
                $normalizedHours
            );

            return [
                'ok' => true,
                'units' => $normalizedHours,
                'amount' => $amount,
            ];
        }

        if ($units === '' || preg_match('/^\d+(\.\d+)?$/', $units) !== 1 || (float) $units <= 0) {
            return [
                'ok' => false,
                'message' => 'Enter a numeric stitch count to calculate the price.',
            ];
        }

        $error = SitePricing::configurationError(
            $customer,
            (string) ($customer->website ?: config('sites.primary_legacy_key', '1dollar')),
            $customer->site_id ? (int) $customer->site_id : null,
            'digitizing',
            $turnaroundTime
        );

        if ($error !== null) {
            return [
                'ok' => false,
                'message' => $error,
            ];
        }

        $amount = SitePricing::embroidery(
            $customer,
            (string) ($customer->website ?: config('sites.primary_legacy_key', '1dollar')),
            $customer->site_id ? (int) $customer->site_id : null,
            $turnaroundTime,
            $units
        );

        return [
            'ok' => true,
            'units' => $units,
            'amount' => $amount,
        ];
    }

    public static function forAdminCompletion(Order $order, string $units): array
    {
        $units = trim($units);
        $orderType = (string) $order->order_type;
        $turnaroundTime = (string) $order->turn_around_time;

        if (in_array($orderType, ['vector', 'q-vector', 'color', 'qcolor'], true)) {
            $normalizedHours = TeamPricing::normalizeHours($units);
            $customer = $order->customer;
            $resolvedSiteId = $order->site_id ? (int) $order->site_id : ($customer?->site_id ? (int) $customer->site_id : null);
            $resolvedWebsite = (string) ($order->website ?: $customer?->website ?: config('sites.primary_legacy_key', '1dollar'));

            if ($normalizedHours === null) {
                return [
                    'ok' => false,
                    'message' => 'Enter total hours as a whole number or HH:MM to calculate the price.',
                ];
            }

            $workType = in_array($orderType, ['color', 'qcolor'], true) ? 'color' : 'vector';
            $error = SitePricing::configurationError(
                null,
                $resolvedWebsite,
                $resolvedSiteId,
                $workType,
                $turnaroundTime
            );

            if ($error !== null) {
                return [
                    'ok' => false,
                    'message' => $error,
                ];
            }

            $amount = match ($orderType) {
                'color', 'qcolor' => SitePricing::color(
                    $resolvedWebsite,
                    $resolvedSiteId,
                    $turnaroundTime,
                    $normalizedHours
                ),
                default => SitePricing::vector(
                    $resolvedWebsite,
                    $resolvedSiteId,
                    $turnaroundTime,
                    $normalizedHours
                ),
            };

            return [
                'ok' => true,
                'units' => $normalizedHours,
                'amount' => round($amount, 2),
            ];
        }

        if ($units === '' || preg_match('/^\d+(\.\d+)?$/', $units) !== 1 || (float) $units <= 0) {
            return [
                'ok' => false,
                'message' => 'Enter a numeric stitch count to calculate the price.',
            ];
        }

        $customer = $order->customer;
        $error = SitePricing::configurationError(
            $customer,
            (string) ($order->website ?: $customer?->website ?: config('sites.primary_legacy_key', '1dollar')),
            $order->site_id ? (int) $order->site_id : ($customer?->site_id ? (int) $customer->site_id : null),
            'digitizing',
            $turnaroundTime
        );

        if ($error !== null) {
            return [
                'ok' => false,
                'message' => $error,
            ];
        }

        $amount = SitePricing::embroidery(
            $customer,
            (string) ($order->website ?: $customer?->website ?: config('sites.primary_legacy_key', '1dollar')),
            $order->site_id ? (int) $order->site_id : ($customer?->site_id ? (int) $customer->site_id : null),
            $turnaroundTime,
            $units
        );

        return [
            'ok' => true,
            'units' => $units,
            'amount' => round($amount, 2),
        ];
    }

    public static function forTeamQuickQuote(Order $order, string $units): array
    {
        $units = trim($units);
        $priceType = filled((string) $order->sew_out) ? 'order' : 'quote';

        if ($priceType === 'quote') {
            $normalizedHours = TeamPricing::normalizeHours($units);
            $resolvedSiteId = $order->site_id ? (int) $order->site_id : ($order->customer?->site_id ? (int) $order->customer->site_id : null);
            $resolvedWebsite = (string) ($order->website ?: $order->customer?->website ?: config('sites.primary_legacy_key', '1dollar'));

            if ($normalizedHours === null) {
                return [
                    'ok' => false,
                    'message' => 'Please enter total hours as a whole number or HH:MM to complete this quote.',
                ];
            }

            $error = SitePricing::configurationError(
                null,
                $resolvedWebsite,
                $resolvedSiteId,
                'vector',
                (string) $order->turn_around_time
            );

            if ($error !== null) {
                return [
                    'ok' => false,
                    'message' => $error,
                ];
            }

            return [
                'ok' => true,
                'units' => $normalizedHours,
                'amount' => SitePricing::vector(
                    $resolvedWebsite,
                    $resolvedSiteId,
                    (string) $order->turn_around_time,
                    $normalizedHours
                ),
            ];
        }

        if ($units === '' || preg_match('/^\d+(\.\d+)?$/', $units) !== 1 || (float) $units <= 0) {
            return [
                'ok' => false,
                'message' => 'No. Of Stitches must be a numeric value.',
            ];
        }

        $error = SitePricing::configurationError(
            $order->customer,
            (string) ($order->website ?: $order->customer?->website ?: config('sites.primary_legacy_key', '1dollar')),
            $order->site_id ? (int) $order->site_id : ($order->customer?->site_id ? (int) $order->customer->site_id : null),
            'digitizing',
            (string) $order->turn_around_time
        );

        if ($error !== null) {
            return [
                'ok' => false,
                'message' => $error,
            ];
        }

        return [
            'ok' => true,
            'units' => $units,
            'amount' => SitePricing::embroidery(
                $order->customer,
                (string) ($order->website ?: $order->customer?->website ?: config('sites.primary_legacy_key', '1dollar')),
                $order->site_id ? (int) $order->site_id : ($order->customer?->site_id ? (int) $order->customer->site_id : null),
                (string) $order->turn_around_time,
                $units
            ),
        ];
    }
}
