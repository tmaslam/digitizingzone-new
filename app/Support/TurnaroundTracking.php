<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;

class TurnaroundTracking
{
    private const DETAILED_OVERDUE_LIMIT_MINUTES = 4320;

    public static function normalize(?string $turnaround): string
    {
        return match (strtolower(trim((string) $turnaround))) {
            'priority' => 'priority',
            'superrush', 'super rush' => 'superrush',
            'express' => 'superrush',
            default => 'standard',
        };
    }

    public static function durationHours(?string $turnaround): int
    {
        return match (self::normalize($turnaround)) {
            'priority' => 12,
            'superrush' => 6,
            default => 24,
        };
    }

    public static function label(?string $turnaround): string
    {
        return match (self::normalize($turnaround)) {
            'priority' => 'Priority',
            'superrush' => 'Super Rush',
            default => 'Standard',
        };
    }

    public static function labelWithTiming(?string $turnaround): string
    {
        $label = self::label($turnaround);
        $hours = self::durationHours($turnaround);

        return sprintf('%s (%d Hrs.)', $label, $hours);
    }

    public static function summary(Order $order): array
    {
        $label = self::label((string) $order->turn_around_time);
        $labelWithTiming = self::labelWithTiming((string) $order->turn_around_time);
        $hours = self::durationHours((string) $order->turn_around_time);
        $submittedAt = self::parseDate($order->submit_date);
        $dueAt = self::parseDate($order->completion_date);

        if (! $dueAt && $submittedAt) {
            $dueAt = $submittedAt->copy()->addHours($hours);
        }

        if (! $submittedAt || ! $dueAt) {
            return [
                'label' => $label,
                'label_with_timing' => $labelWithTiming,
                'hours' => $hours,
                'status_label' => 'Schedule Unknown',
                'status_tone' => '',
                'due_at' => null,
                'remaining_label' => '-',
            ];
        }

        if (self::isQuoteNegotiationWaiting($order)) {
            return [
                'label' => $label,
                'label_with_timing' => $labelWithTiming,
                'hours' => $hours,
                'status_label' => 'Awaiting Admin Review',
                'status_tone' => 'warning',
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
                'remaining_label' => 'Customer requested price review',
            ];
        }

        $now = now();
        $minutesRemaining = $now->diffInMinutes($dueAt, false);
        $isCompleted = in_array(strtolower(trim((string) $order->status)), ['done', 'approved'], true);
        $statusLabel = 'In Progress';
        $statusTone = 'success';

        if ($isCompleted) {
            $statusLabel = 'Completed';
            $statusTone = 'success';
        } elseif ($minutesRemaining < 0) {
            $statusLabel = 'Past Due';
            $statusTone = 'danger';
        } elseif ($minutesRemaining <= 120) {
            $statusLabel = 'Due Soon';
            $statusTone = 'warning';
        }

        return [
            'label' => $label,
            'label_with_timing' => $labelWithTiming,
            'hours' => $hours,
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
            'remaining_label' => self::remainingLabel($minutesRemaining),
        ];
    }

    private static function remainingLabel(int $minutesRemaining): string
    {
        if ($minutesRemaining < 0) {
            $lateMinutes = abs($minutesRemaining);

            if ($lateMinutes > self::DETAILED_OVERDUE_LIMIT_MINUTES) {
                return 'Overdue - needs attention';
            }

            $hours = intdiv($lateMinutes, 60);
            $minutes = $lateMinutes % 60;

            return $hours > 0
                ? sprintf('%dh %dm overdue', $hours, $minutes)
                : sprintf('%dm overdue', $minutes);
        }

        $hours = intdiv($minutesRemaining, 60);
        $minutes = $minutesRemaining % 60;

        return $hours > 0
            ? sprintf('%dh %dm left', $hours, $minutes)
            : sprintf('%dm left', $minutes);
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

    private static function isQuoteNegotiationWaiting(Order $order): bool
    {
        $status = strtolower(trim((string) $order->status));
        $orderType = strtolower(trim((string) $order->order_type));

        if (! in_array($status, ['disapprove', 'disapproved'], true)) {
            return false;
        }

        return in_array($orderType, ['quote', 'digitzing', 'q-vector', 'qcolor'], true);
    }
}
