<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\SitePromotionClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerApprovalQueue
{
    public const STATE_PENDING_VERIFICATION = 'pending_verification';
    public const STATE_PENDING_ADMIN_APPROVAL = 'pending_admin_approval';
    public const STATE_PENDING_WELCOME_PAYMENT = 'pending_welcome_payment';

    public static function userIds(?string $website = null, ?string $state = null): array
    {
        return match ($state) {
            self::STATE_PENDING_VERIFICATION => self::pendingVerificationUserIds($website),
            self::STATE_PENDING_ADMIN_APPROVAL => self::pendingAdminApprovalUserIds($website),
            self::STATE_PENDING_WELCOME_PAYMENT => self::pendingWelcomePaymentUserIds($website),
            default => self::uniqueIds(array_merge(
                self::pendingVerificationUserIds($website),
                self::pendingAdminApprovalUserIds($website),
                self::pendingWelcomePaymentUserIds($website),
            )),
        };
    }

    public static function pendingVerificationUserIds(?string $website = null): array
    {
        $manualApprovalIds = self::normalizeIds(
            AdminUser::query()
                ->customers()
                ->active()
                ->forWebsite($website)
                ->where('is_active', 0)
                ->where('exist_customer', '0')
                ->where('user_term', 'dc')
                ->get()
                ->filter(fn (AdminUser $customer) => self::hasActivationToken($customer))
                ->pluck('user_id')
                ->all()
        );

        if (! Schema::hasTable('site_promotion_claims')) {
            $welcomePaymentIds = self::normalizeIds(
                AdminUser::query()
                    ->customers()
                    ->active()
                    ->forWebsite($website)
                    ->where('is_active', 0)
                    ->where('exist_customer', '0')
                    ->where('user_term', 'ip')
                    ->pluck('user_id')
                    ->all()
            );

            return self::uniqueIds(array_merge($manualApprovalIds, $welcomePaymentIds));
        }

        $welcomePaymentIds = SitePromotionClaim::query()
            ->where('status', SignupOfferService::STATUS_PENDING_VERIFICATION)
            ->when($website !== null && trim($website) !== '', function ($query) use ($website) {
                $query->where('website', trim($website));
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        return self::uniqueIds(array_merge($manualApprovalIds, $welcomePaymentIds));
    }

    public static function pendingAdminApprovalUserIds(?string $website = null): array
    {
        return self::normalizeIds(
            AdminUser::query()
                ->customers()
                ->active()
                ->forWebsite($website)
                ->where('is_active', 0)
                ->where('user_term', 'dc')
                ->get()
                ->filter(fn (AdminUser $customer) => self::stateForCustomer($customer) === self::STATE_PENDING_ADMIN_APPROVAL)
                ->pluck('user_id')
                ->all()
        );
    }

    public static function pendingWelcomePaymentUserIds(?string $website = null): array
    {
        return self::uniqueIds(SignupOfferService::verifiedPendingPaymentUserIds($website));
    }

    public static function claimStatusMap(array $userIds, ?string $website = null): array
    {
        if ($userIds === [] || ! Schema::hasTable('site_promotion_claims')) {
            return [];
        }

        return SitePromotionClaim::query()
            ->select('user_id', 'status', 'required_payment_amount', 'payment_transaction_id', 'payment_reference', 'paid_at')
            ->whereIn('user_id', $userIds)
            ->when($website !== null && trim($website) !== '', function ($query) use ($website) {
                $query->where('website', trim($website));
            })
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->mapWithKeys(function (SitePromotionClaim $claim) {
                $status = SignupOfferService::claimNeedsWelcomePayment($claim)
                    ? SignupOfferService::STATUS_PENDING_PAYMENT
                    : (string) $claim->status;

                return [(int) $claim->user_id => $status];
            })
            ->all();
    }

    public static function stateForCustomer(AdminUser $customer, ?string $claimStatus = null): string
    {
        $existCustomer = trim((string) ($customer->exist_customer ?? ''));

        if ($existCustomer === 'pending_admin_approval') {
            return self::STATE_PENDING_ADMIN_APPROVAL;
        }

        if (
            trim(strtolower((string) ($customer->user_term ?? ''))) === 'dc'
            && (int) ($customer->is_active ?? 0) !== 1
            && ! self::hasActivationToken($customer)
        ) {
            return self::STATE_PENDING_ADMIN_APPROVAL;
        }

        if (trim(strtolower((string) ($customer->user_term ?? ''))) === 'ip'
            && $claimStatus === SignupOfferService::STATUS_PENDING_PAYMENT) {
            return self::STATE_PENDING_WELCOME_PAYMENT;
        }

        return self::STATE_PENDING_VERIFICATION;
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_PENDING_VERIFICATION => 'Pending Email Verification',
            self::STATE_PENDING_ADMIN_APPROVAL => 'Waiting For Admin Approval',
            self::STATE_PENDING_WELCOME_PAYMENT => 'Verified / Welcome Payment Pending',
            default => 'Pending',
        };
    }

    public static function stateFilterOptions(): array
    {
        return [
            '' => 'All Signup States',
            self::STATE_PENDING_VERIFICATION => 'Pending Email Verification',
            self::STATE_PENDING_ADMIN_APPROVAL => 'Waiting For Admin Approval',
            self::STATE_PENDING_WELCOME_PAYMENT => 'Verified / Welcome Payment Pending',
        ];
    }

    private static function normalizeIds(array $ids): array
    {
        return self::uniqueIds(
            array_map(static fn ($id) => (int) $id, $ids)
        );
    }

    private static function uniqueIds(array $ids): array
    {
        return array_values(array_filter(array_unique($ids), static fn (int $id) => $id > 0));
    }

    private static function hasActivationToken(AdminUser $customer): bool
    {
        if (! Schema::hasTable('customer_activation_tokens')) {
            return false;
        }

        return DB::table('customer_activation_tokens')
            ->where('customer_user_id', $customer->user_id)
            ->when(trim((string) $customer->website) !== '', function ($query) use ($customer) {
                $query->where('site_legacy_key', trim((string) $customer->website));
            })
            ->exists();
    }
}
