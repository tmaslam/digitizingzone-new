<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\CustomerCreditLedger;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CustomerBalance
{
    public const DEFAULT_SITE = '1dollar';

    public static function deposit(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    public static function available(int $userId, ?string $website = null): float
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return 0.0;
        }

        return (float) CustomerCreditLedger::query()
            ->active()
            ->where('user_id', $userId)
            ->forWebsite(self::normalizeWebsite($website))
            ->sum('amount');
    }

    public static function addPaymentCredit(int $userId, string $website, float $amount, string $referenceNo, string $createdBy, ?string $notes = null, string $entryType = 'payment'): void
    {
        if (! Schema::hasTable('customer_credit_ledger') || $amount <= 0) {
            return;
        }

        $website = self::normalizeWebsite($website);

        CustomerCreditLedger::query()->updateOrCreate(
            [
                'reference_no' => $referenceNo,
            ],
            self::ledgerPayload([
                'user_id' => $userId,
                'site_id' => self::siteIdForWebsite($website),
                'website' => $website,
                'entry_type' => $entryType,
                'amount' => round($amount, 2),
                'notes' => $notes ?: 'Customer payment added to available balance.',
                'created_by' => $createdBy,
                'date_added' => now()->format('Y-m-d H:i:s'),
                'end_date' => null,
                'deleted_by' => null,
            ])
        );
    }

    public static function clearPaymentReference(string $referenceNo, string $deletedBy): void
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return;
        }

        CustomerCreditLedger::query()
            ->where(function ($query) use ($referenceNo) {
                $query
                    ->where('reference_no', $referenceNo)
                    ->orWhere('reference_no', 'like', $referenceNo.':%');
            })
            ->update([
                'end_date' => now()->format('Y-m-d H:i:s'),
                'deleted_by' => $deletedBy,
            ]);
    }

    public static function removePaymentCredit(string $referenceNo, string $deletedBy): void
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return;
        }

        CustomerCreditLedger::query()
            ->whereIn('entry_type', ['payment', 'overpayment'])
            ->where('reference_no', $referenceNo)
            ->update([
                'end_date' => now()->format('Y-m-d H:i:s'),
                'deleted_by' => $deletedBy,
            ]);
    }

    public static function recordIncomingPayment(
        int $userId,
        float $amount,
        string $referenceNo,
        string $createdBy,
        ?string $notes = null,
        ?string $transactionId = null,
        bool $applyToDue = true,
        ?string $website = null
    ): array {
        if (! Schema::hasTable('customer_credit_ledger') || $amount <= 0) {
            return [
                'applied_invoices' => 0,
                'applied_amount' => 0.0,
                'balance_amount' => 0.0,
                'status' => 'none',
            ];
        }

        $website = self::normalizeWebsite($website);
        $remaining = round($amount, 2);
        $appliedAmount = 0.0;
        $appliedInvoices = 0;
        $dueTotal = 0.0;

        $dueInvoices = collect();

        if ($applyToDue) {
            $dueInvoices = self::dueInvoicesForIncomingPayment($userId, $website);

            $dueTotal = round((float) $dueInvoices->sum(function (Billing $billing) {
                return (float) ($billing->order?->total_amount ?: $billing->amount);
            }), 2);

            foreach ($dueInvoices as $billing) {
                $invoiceAmount = round((float) ($billing->order?->total_amount ?: $billing->amount), 2);

                if ($invoiceAmount <= 0 || $remaining + 0.0001 < $invoiceAmount) {
                    continue;
                }

                CustomerCreditLedger::query()->create(self::ledgerPayload([
                    'user_id' => $userId,
                    'billing_id' => $billing->bill_id,
                    'order_id' => $billing->order_id,
                    'site_id' => self::siteIdForWebsite(self::billingWebsite($billing, $website)),
                    'website' => self::billingWebsite($billing, $website),
                    'entry_type' => 'applied',
                    'amount' => round($invoiceAmount * -1, 2),
                    'reference_no' => $referenceNo.':billing:'.$billing->bill_id,
                    'notes' => 'Applied manual payment to invoice #'.$billing->bill_id.'.',
                    'created_by' => $createdBy,
                    'date_added' => now()->format('Y-m-d H:i:s'),
                ]));

                $billing->update([
                    'payment' => 'yes',
                    'trandtime' => now()->format('Y-m-d H:i:s'),
                    'transid' => $transactionId ?: $referenceNo,
                    'comments' => 'paid from manual payment entry',
                ]);

                $remaining = round($remaining - $invoiceAmount, 2);
                $appliedAmount = round($appliedAmount + $invoiceAmount, 2);
                $appliedInvoices++;
            }
        }

        if ($remaining > 0.0001) {
            $entryType = ($applyToDue && $dueTotal > 0 && $amount > $dueTotal + 0.0001)
                ? 'overpayment'
                : 'payment';

            CustomerCreditLedger::query()->updateOrCreate(
                [
                    'reference_no' => $referenceNo,
                ],
                self::ledgerPayload([
                    'user_id' => $userId,
                    'site_id' => self::siteIdForWebsite($website),
                    'website' => $website,
                    'entry_type' => $entryType,
                    'amount' => round($remaining, 2),
                    'notes' => $notes ?: ($entryType === 'overpayment'
                        ? 'Extra customer payment kept as available balance.'
                        : 'Customer payment recorded as available balance.'),
                    'created_by' => $createdBy,
                    'date_added' => now()->format('Y-m-d H:i:s'),
                    'end_date' => null,
                    'deleted_by' => null,
                ])
            );
        }

        return [
            'applied_invoices' => $appliedInvoices,
            'applied_amount' => round($appliedAmount, 2),
            'balance_amount' => round(max($remaining, 0), 2),
            'status' => match (true) {
                $appliedInvoices > 0 && $remaining > 0.0001 => 'overpayment',
                $appliedInvoices > 0 => 'actual',
                $remaining > 0.0001 => 'credit',
                default => 'none',
            },
        ];
    }

    public static function applyToBilling(Billing $billing, string $createdBy): bool
    {
        $invoiceAmount = (float) ($billing->order?->total_amount ?: $billing->amount);
        if ($invoiceAmount <= 0) {
            $billing->update(self::filterBillingUpdateColumns([
                'payment' => 'yes',
                'is_paid' => 1,
                'trandtime' => now()->format('Y-m-d H:i:s'),
                'transid' => 'NO-CHARGE-'.$billing->bill_id,
                'comments' => 'no-charge invoice settled automatically',
            ]));

            return true;
        }

        $website = self::billingWebsite($billing);
        $available = self::available((int) $billing->user_id, $website);
        $deposit = self::storedDeposit((int) $billing->user_id);

        if ($available + $deposit + 0.0001 < $invoiceAmount) {
            return false;
        }

        $remaining = round($invoiceAmount, 2);
        $usedBalance = 0.0;
        $usedDeposit = 0.0;

        if (Schema::hasTable('customer_credit_ledger') && $available > 0.0001) {
            $usedBalance = round(min($available, $remaining), 2);

            if ($usedBalance > 0.0001) {
                CustomerCreditLedger::query()->create(self::ledgerPayload([
                    'user_id' => $billing->user_id,
                    'billing_id' => $billing->bill_id,
                    'order_id' => $billing->order_id,
                    'site_id' => self::siteIdForWebsite($website),
                    'website' => $website,
                    'entry_type' => 'applied',
                    'amount' => round($usedBalance * -1, 2),
                    'reference_no' => 'billing:'.$billing->bill_id,
                    'notes' => 'Applied customer balance to invoice #'.$billing->bill_id.'.',
                    'created_by' => $createdBy,
                    'date_added' => now()->format('Y-m-d H:i:s'),
                ]));

                $remaining = round($remaining - $usedBalance, 2);
            }
        }

        if ($remaining > 0.0001 && $deposit > 0.0001) {
            $usedDeposit = round(min($deposit, $remaining), 2);

            if ($usedDeposit > 0.0001) {
                self::updateStoredDeposit((int) $billing->user_id, round($deposit - $usedDeposit, 2));
                $remaining = round($remaining - $usedDeposit, 2);
            }
        }

        if ($remaining > 0.0001) {
            return false;
        }

        $paymentSource = match (true) {
            $usedBalance > 0.0001 && $usedDeposit > 0.0001 => 'paid from customer balance and advance deposit',
            $usedDeposit > 0.0001 => 'paid from advance deposit',
            default => 'paid from customer balance',
        };

        $billing->update(self::filterBillingUpdateColumns([
            'payment' => 'yes',
            'is_paid' => 1,
            'trandtime' => now()->format('Y-m-d H:i:s'),
            'transid' => 'stored-funds',
            'comments' => $paymentSource,
        ]));

        return true;
    }

    private static function filterBillingUpdateColumns(array $payload): array
    {
        if (! Schema::hasTable('billing')) {
            return $payload;
        }

        $columns = Schema::getColumnListing('billing');

        return array_intersect_key($payload, array_flip($columns));
    }

    private static function ledgerPayload(array $payload): array
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip(Schema::getColumnListing('customer_credit_ledger')));
    }

    private static function dueInvoicesForIncomingPayment(int $userId, string $website): Collection
    {
        $query = Billing::query()
            ->with('order')
            ->active()
            ->where('user_id', $userId)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->orderBy('approve_date')
            ->orderBy('bill_id');

        $siteScoped = (clone $query)
            ->forWebsite($website)
            ->get();

        if ($siteScoped->isNotEmpty()) {
            return $siteScoped;
        }

        $primaryWebsite = self::normalizeWebsite((string) config('sites.primary_legacy_key', self::DEFAULT_SITE));
        if (strcasecmp($website, $primaryWebsite) !== 0) {
            return $siteScoped;
        }

        return $query->get();
    }

    public static function settleZeroAmountBillings(int $userId, ?string $website = null, string $createdBy = 'system-auto'): int
    {
        $billings = Billing::query()
            ->with('order')
            ->active()
            ->where('user_id', $userId)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->forWebsite(self::normalizeWebsite($website))
            ->orderBy('bill_id')
            ->get();

        $settled = 0;

        foreach ($billings as $billing) {
            $invoiceAmount = (float) ($billing->order?->total_amount ?: $billing->amount);
            $billingAmount = (float) $billing->amount;

            // Skip only when both the effective invoice amount AND the billing
            // amount itself are positive. A negative billing amount means the
            // record is erroneous or already covered and should be settled.
            if ($invoiceAmount > 0.0001 && $billingAmount > 0.0001) {
                continue;
            }

            if (self::applyToBilling($billing, $createdBy)) {
                $settled++;
            }
        }

        return $settled;
    }

    public static function balances(string $website = '', string $userSearch = '', string $nameSearch = ''): Collection
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return collect();
        }

        $website = $website !== '' ? self::normalizeWebsite($website) : '';
        $rows = CustomerCreditLedger::query()
            ->selectRaw('user_id, SUM(amount) as balance_total')
            ->active()
            ->when($website !== '', fn ($query) => $query->forWebsite($website))
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > 0.0001')
            ->get();

        $userIds = $rows->pluck('user_id')->unique()->all();
        $customers = AdminUser::query()
            ->customers()
            ->active()
            ->whereIn('user_id', $userIds)
            ->when($userSearch !== '', fn ($query) => $query->where('user_id', 'like', '%'.$userSearch.'%'))
            ->when($nameSearch !== '', fn ($query) => $query->where(function ($inner) use ($nameSearch) {
                $inner->where('user_name', 'like', '%'.$nameSearch.'%')
                    ->orWhere('first_name', 'like', '%'.$nameSearch.'%')
                    ->orWhere('last_name', 'like', '%'.$nameSearch.'%')
                    ->orWhere('user_email', 'like', '%'.$nameSearch.'%');
            }))
            ->get()
            ->keyBy('user_id');

        return $rows
            ->filter(fn ($row) => $customers->has($row->user_id))
            ->map(function ($row) use ($customers) {
                $row->customer = $customers[$row->user_id];

                return $row;
            })
            ->values();
    }

    public static function normalizeWebsite(?string $website = null): string
    {
        $website = trim((string) $website);
        $primaryWebsite = (string) config('sites.primary_legacy_key', self::DEFAULT_SITE);

        if ($website !== '' && strcasecmp($primaryWebsite, '1dollar') === 0) {
            $normalized = strtolower($website);

            if (in_array($normalized, ['oned', 'one-dollar', 'one_dollar', '1-dollar'], true)) {
                return $primaryWebsite;
            }
        }

        return $website !== '' ? $website : $primaryWebsite;
    }

    private static function billingWebsite(Billing $billing, ?string $fallback = null): string
    {
        $billingWebsite = trim((string) $billing->website);
        if ($billingWebsite !== '') {
            return $billingWebsite;
        }

        $orderWebsite = trim((string) $billing->order?->website);
        if ($orderWebsite !== '') {
            return $orderWebsite;
        }

        return self::normalizeWebsite($fallback);
    }

    private static function storedDeposit(int $userId): float
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'topup')) {
            return 0.0;
        }

        return self::deposit(AdminUser::query()->where('user_id', $userId)->value('topup'));
    }

    private static function updateStoredDeposit(int $userId, float $amount): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'topup')) {
            return;
        }

        AdminUser::query()
            ->where('user_id', $userId)
            ->update([
                'topup' => number_format(max($amount, 0), 2, '.', ''),
            ]);
    }

    private static function siteIdForWebsite(?string $website): ?int
    {
        $website = self::normalizeWebsite($website);

        if (! Schema::hasTable('sites')) {
            return null;
        }

        $siteId = Site::query()->where('legacy_key', $website)->value('id');

        return $siteId ? (int) $siteId : null;
    }
}
