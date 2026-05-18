<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;

class CustomerReleaseGate
{
    public static function summary(Order $order): array
    {
        $customer = $order->customer;
        $meta = OrderWorkflowMetaManager::forOrder($order);
        $currentApprovedUnpaidAmount = (float) Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));
        $hasApprovedBilling = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where('approved', 'yes')
            ->exists();
        // If there is an approved billing record at $0 (e.g. promo/free order), treat amount as 0
        // rather than falling back to the raw order price fields.
        $amount = $currentApprovedUnpaidAmount > 0
            ? $currentApprovedUnpaidAmount
            : ($hasApprovedBilling ? 0.0 : self::orderAmount($order));
        $paid = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where(function ($query) {
                $query->where('payment', 'yes')
                    ->orWhere('is_paid', 1);
            })
            ->exists();

        $legacyWebsite = trim((string) $order->website) !== ''
            ? trim((string) $order->website)
            : (string) config('sites.primary_legacy_key', CustomerBalance::DEFAULT_SITE);
        $availableBalance = (float) CustomerBalance::available((int) $order->user_id, $legacyWebsite);
        $prepaidAmount = self::toMoney($customer?->topup);
        $singleLimit = self::toMoney($meta?->order_credit_limit);
        $customerSingleLimit = self::toMoney($customer?->single_approval_limit);
        $globalLimit = self::toMoney($customer?->customer_approval_limit);
        $outstandingDue = (float) Billing::query()
            ->active()
            ->where('user_id', $order->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->forWebsite($legacyWebsite)
            ->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));

        $projectedExposure = $outstandingDue + ($currentApprovedUnpaidAmount > 0 ? 0 : max($amount, 0));
        $previewOnlyOverride = (string) ($meta?->delivery_override ?: 'auto') === 'preview_only';
        $adminOrderCreditAllowed = $singleLimit > 0
            && $amount <= $singleLimit + 0.0001;
        $customerCreditAllowed = $customerSingleLimit > 0
            && $amount <= $customerSingleLimit + 0.0001
            && ($globalLimit <= 0 || $projectedExposure <= $globalLimit + 0.0001);
        $fullReleaseAllowed = $paid
            || $amount <= 0
            || ($availableBalance + $prepaidAmount + 0.0001 >= $amount)
            || $adminOrderCreditAllowed
            || $customerCreditAllowed;
        $fullReleaseAllowed = $previewOnlyOverride ? false : $fullReleaseAllowed;

        $reason = match (true) {
            $previewOnlyOverride => 'Preview-only delivery is enforced for this order.',
            $paid => 'Payment is already recorded, so released production files can be shared with the customer.',
            $amount <= 0 => 'This order currently has no charge, so released production files can be shared with the customer.',
            $availableBalance + $prepaidAmount + 0.0001 >= $amount => 'Available customer credit covers this order, so released production files can be shared with the customer.',
            $adminOrderCreditAllowed => 'This order is within the explicit release limit set by admin, so released production files can be shared.',
            $customerCreditAllowed => 'This order is within the customer credit limit, so released production files can be shared.',
            default => 'Only preview-safe files should be available until payment or approved credit covers the order.',
        };

        return [
            'amount' => $amount,
            'paid' => $paid,
            'available_balance' => $availableBalance,
            'prepaid_amount' => $prepaidAmount,
            'single_limit' => $singleLimit > 0 ? $singleLimit : $customerSingleLimit,
            'global_limit' => $globalLimit,
            'outstanding_due' => $outstandingDue,
            'projected_exposure' => $projectedExposure,
            'current_unpaid_amount' => $currentApprovedUnpaidAmount,
            'delivery_override' => (string) ($meta?->delivery_override ?: 'auto'),
            'order_credit_limit' => $singleLimit,
            'full_release_allowed' => $fullReleaseAllowed,
            'mode_label' => $fullReleaseAllowed ? 'Ready for Full Release' : 'Preview Files Only',
            'reason' => $reason,
        ];
    }

    public static function attachmentAllowedForCustomer(Order $order, Attachment $attachment): bool
    {
        $summary = self::summary($order);

        return $summary['full_release_allowed'] || self::isPreviewAttachment($attachment);
    }

    public static function isPreviewAttachment(Attachment $attachment): bool
    {
        $fileName = (string) (
            $attachment->file_name
            ?: $attachment->file_name_with_order_id
            ?: $attachment->file_name_with_date
            ?: ''
        );
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
    }

    private static function orderAmount(Order $order): float
    {
        $amount = self::toMoney($order->total_amount);

        if ($amount <= 0) {
            $amount = self::toMoney($order->stitches_price);
        }

        return $amount;
    }

    private static function toMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }
}
