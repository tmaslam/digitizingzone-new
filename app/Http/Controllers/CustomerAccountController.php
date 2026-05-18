<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\Order;
use App\Support\AdminReferenceData;
use App\Support\CustomerBalance;
use App\Support\InvoicePdf;
use App\Support\LegacyQuerySupport;
use App\Support\PasswordManager;
use App\Support\PortalMailer;
use App\Support\SiteContext;
use App\Support\TrustedTwoFactorDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerAccountController extends Controller
{
    public function profile(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        return view('customer.account.profile', [
            'pageTitle' => 'My Profile',
            'customer' => $customer,
            'countries' => AdminReferenceData::countries(),
            'companyTypes' => AdminReferenceData::companyTypes(),
            'accountSummary' => [
                'available_balance' => CustomerBalance::available((int) $customer->user_id, $site->legacyKey),
                'deposit_balance' => CustomerBalance::deposit($customer->topup),
                'credit_limit' => $this->money($customer->customer_approval_limit),
                'single_order_limit' => $this->money($customer->single_approval_limit),
                'payment_terms' => (int) ($customer->payment_terms ?: 0),
                'package_type' => (string) ($customer->package_type ?: 'Standard'),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $customer = $this->customer($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'company_type' => ['nullable', 'string', 'max:100'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'user_city' => ['nullable', 'string', 'max:120'],
            'user_country' => ['required', 'string', 'max:150'],
            'user_phone' => ['required', 'string', 'max:50'],
        ]);

        $customer->update([
            'first_name' => trim((string) ($validated['first_name'] ?? '')),
            'last_name' => trim((string) ($validated['last_name'] ?? '')),
            'company' => trim((string) ($validated['company'] ?? '')),
            'company_type' => trim((string) ($validated['company_type'] ?? '')),
            'company_address' => trim((string) ($validated['company_address'] ?? '')),
            'zip_code' => trim((string) ($validated['zip_code'] ?? '')),
            'user_city' => trim((string) ($validated['user_city'] ?? '')),
            'user_country' => trim((string) ($validated['user_country'] ?? '')),
            'user_phone' => trim((string) ($validated['user_phone'] ?? '')),
        ]);

        return redirect('/my-profile.php')->with('success', 'Your profile has been updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $customer = $this->customer($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);

        if (! PasswordManager::matches($customer, (string) $validated['current_password'])) {
            return back()->withErrors(['password' => 'The current password you entered does not match this site account.']);
        }

        if ((string) $validated['current_password'] === (string) $validated['new_password']) {
            return back()->withErrors(['password' => 'Please choose a different password for this account.']);
        }

        $customer->forceFill(array_merge(
            PasswordManager::payload((string) $validated['new_password']),
            ['exist_customer' => '1']
        ))->save();

        return redirect('/my-profile.php')->with('success', 'Your password has been updated successfully.');
    }

    public function toggleTwoFactor(Request $request)
    {
        $customer = $this->customer($request);

        $action = trim((string) $request->input('action', ''));

        if (! in_array($action, ['enable', 'disable'], true)) {
            return back()->withErrors(['2fa' => 'Invalid request.']);
        }

        $enable = $action === 'enable';
        $site = $this->site($request);

        $customer->update(['two_factor_enabled' => $enable ? 1 : 0]);

        if (! $enable) {
            TrustedTwoFactorDevice::revokeForUser('customer', (int) $customer->user_id, $site->legacyKey);
            TrustedTwoFactorDevice::revokeCurrent($request, 'customer', $site->legacyKey);
        }

        $message = $enable
            ? 'Two-factor authentication has been enabled. You will now receive a verification code by email each time you sign in.'
            : 'Two-factor authentication has been disabled for your account.';

        return redirect('/my-profile.php')->with('success', $message);
    }

    public function invoices(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');
        $today = Carbon::now();
        $defaultFrom = $today->copy()->startOfYear()->toDateString();
        $defaultTo = $today->toDateString();
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $usingDefaultRange = false;

        if ($dateFrom === '' && $dateTo === '') {
            $usingDefaultRange = true;
            if ($today->dayOfYear <= 90) {
                $dateFrom = $today->copy()->subDays(89)->toDateString();
                $dateTo = $today->toDateString();
            } else {
                $dateFrom = $defaultFrom;
                $dateTo = $defaultTo;
            }
        }

        $invoiceGroupsQuery = $this->paidBillingQuery($customer, $site)
            ->when($dateFrom !== '', function ($query) use ($dateFrom) {
                $query->whereDate('trandtime', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo) {
                $query->whereDate('trandtime', '<=', $dateTo);
            })
            ->selectRaw('transid, MAX(trandtime) as invoice_date, COUNT(DISTINCT order_id) as total_designs, SUM(CAST(amount AS DECIMAL(12,2))) as total_amount')
            ->groupBy('transid')
            ->orderByDesc('invoice_date');

        if ($request->query('export') === 'csv') {
            $rows = $invoiceGroupsQuery->get();

            return $this->csvResponse('customer-invoices', ['Transaction ID', 'Payment Date', 'Total Designs', 'Amount'], $rows->map(
                fn ($invoice) => [
                    (string) $invoice->transid,
                    (string) ($invoice->invoice_date ?: '-'),
                    (int) $invoice->total_designs,
                    number_format((float) $invoice->total_amount, 2, '.', ''),
                ]
            )->all());
        }

        $invoiceGroups = $invoiceGroupsQuery
            ->paginate(20)
            ->withQueryString();

        return view('customer.invoices.index', [
            'pageTitle' => 'My Invoices',
            'invoiceGroups' => $invoiceGroups,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'defaultFrom' => $defaultFrom,
            'defaultTo' => $defaultTo,
            'usingDefaultRange' => $usingDefaultRange,
        ]);
    }

    public function invoiceDetail(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');
        $transactionId = trim((string) $request->query('transid', ''));

        abort_unless($transactionId !== '', 404);

        $items = $this->paidBillingQuery($customer, $site)
            ->where('transid', $transactionId)
            ->with('order')
            ->orderByDesc('bill_id')
            ->get();

        abort_unless($items->isNotEmpty(), 404);

        $invoiceDate = (string) ($items->first()?->trandtime ?: '');
        $invoiceTotal = round($items->sum(fn (Billing $billing) => $this->money($billing->amount ?: $billing->order?->total_amount)), 2);

        if ($request->query('download') === 'pdf') {
            $primaryBilling = $items->first();
            $paymentMethod = match (strtolower((string) ($site->activePaymentProvider ?: ''))) {
                'stripe' => 'Stripe',
                '2checkout_hosted' => '2Checkout.com',
                '2checkout' => '2Checkout.com',
                default => ($primaryBilling && filled((string) $primaryBilling->payer_id) ? 'Stripe' : 'Online Payment'),
            };
            $customerAddress = $this->customerInvoiceAddress($customer);
            $invoiceNumber = $items->count() === 1
                ? '#'.(string) $items->first()->order_id
                : '#'.preg_replace('/[^A-Za-z0-9\-]+/', '-', trim($transactionId, "- \t\n\r\0\x0B"));

            $pdf = InvoicePdf::render([
                'title' => 'Invoice '.$transactionId,
                'site_label' => $site->displayLabel(),
                'site_address' => $site->companyAddress,
                'support_email' => $site->supportEmail,
                'customer_name' => $customer->display_name,
                'customer_address' => $customerAddress,
                'customer_phone' => $customer->user_phone ?: '',
                'invoice_number' => $invoiceNumber,
                'transaction_id' => $transactionId,
                'invoice_date' => $this->formatInvoiceHeaderDate($invoiceDate),
                'invoice_total' => number_format($invoiceTotal, 2, '.', ''),
                'payment_method' => $paymentMethod,
                'payment_summary' => $this->invoicePaymentSummary($paymentMethod, $transactionId),
                'items' => $items->map(function (Billing $billing) {
                    return [
                        'description' => 'Design: '.(string) ($billing->order?->design_name ?: 'Order #'.$billing->order_id),
                        'date' => $this->formatInvoiceLineDate((string) ($billing->order?->completion_date ?: $billing->trandtime ?: '-')),
                        'quantity' => '1',
                        'price' => number_format((float) preg_replace('/[^0-9.\-]/', '', (string) ($billing->amount ?: $billing->order?->total_amount)), 2, '.', ''),
                        'amount' => number_format((float) preg_replace('/[^0-9.\-]/', '', (string) ($billing->amount ?: $billing->order?->total_amount)), 2, '.', ''),
                    ];
                })->all(),
            ]);

            $invoiceFileName = sprintf(
                '1dollar-digitizing-invoice-%s.pdf',
                preg_replace('/[^A-Za-z0-9\-]+/', '-', trim($transactionId, "- \t\n\r\0\x0B"))
            );

            return response($pdf, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$invoiceFileName.'"',
            ]);
        }

        return view('customer.invoices.show', [
            'pageTitle' => 'Invoice Detail',
            'transactionId' => $transactionId,
            'invoiceItems' => $items,
            'invoiceDate' => $invoiceDate,
            'invoiceTotal' => $invoiceTotal,
        ]);
    }

    private function customerInvoiceAddress(AdminUser $customer): string
    {
        $lines = array_filter([
            trim((string) ($customer->company_address ?: '')),
            trim(implode(', ', array_filter([
                (string) ($customer->user_city ?: ''),
                (string) ($customer->zip_code ?: ''),
            ]))),
            trim((string) ($customer->user_country ?: '')),
        ], fn (?string $value) => $value !== null && trim($value) !== '');

        return implode("\n", $lines);
    }

    private function formatInvoiceHeaderDate(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('F d, Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatInvoiceLineDate(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return '-';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function invoicePaymentSummary(string $paymentMethod, string $transactionId): string
    {
        $transactionId = trim($transactionId);

        if ($transactionId === '') {
            return 'Payment Method: '.$paymentMethod;
        }

        return 'Payment Method: '.$paymentMethod.', Transaction ID: '.$transactionId;
    }

    public function referralInvoices(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        $referralRows = collect();
        if (Schema::hasTable('earned_credit')) {
            $rows = DB::table('earned_credit')
                ->where('userid', $customer->user_id)
                ->where('status', 'add')
                ->get();

            $referredUsers = AdminUser::query()
                ->customers()
                ->active()
                ->forWebsite($site->legacyKey)
                ->whereIn('user_id', $rows->pluck('refre_id')->filter()->all())
                ->get()
                ->keyBy('user_id');

            $referralRows = $rows
                ->filter(fn ($row) => $referredUsers->has((int) $row->refre_id))
                ->map(function ($row) use ($referredUsers) {
                    $referredUser = $referredUsers->get((int) $row->refre_id);

                    return (object) [
                        'referred_name' => $referredUser?->display_name ?? 'Referral',
                        'transaction_id' => (string) ($row->transaction_id ?? '-'),
                        'credit' => round((float) ($row->credit ?? 0), 2),
                    ];
                })
                ->values();
        }

        $creditInvoiceRows = collect();
        if (Schema::hasTable('billing_credit')) {
            $siteTransIds = $this->paidBillingQuery($customer, $site)
                ->pluck('transid')
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->unique()
                ->values()
                ->all();

            if ($siteTransIds !== []) {
                $creditInvoiceRows = DB::table('billing_credit')
                    ->where('userid', $customer->user_id)
                    ->whereIn('transid', $siteTransIds)
                    ->orderByDesc('submitdate')
                    ->get()
                    ->map(fn ($row) => (object) [
                        'transid' => (string) ($row->transid ?? '-'),
                        'total_billing' => round((float) ($row->total_billing ?? 0), 2),
                        'credit_points' => round((float) ($row->credit_points ?? 0), 2),
                        'submitdate' => (string) ($row->submitdate ?? ''),
                    ]);
            }
        }

        return view('customer.credit.index', [
            'pageTitle' => 'Credit & Invoice History',
            'referralRows' => $referralRows,
            'creditInvoiceRows' => $creditInvoiceRows,
            'referralTotal' => round((float) $referralRows->sum('credit'), 2),
            'creditAppliedTotal' => round((float) $creditInvoiceRows->sum('credit_points'), 2),
        ]);
    }

    public function paidAdvanceOrders(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        $orders = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('advance_pay', '1')
            ->where('status', 'done')
            ->whereIn('order_id', function ($query) use ($customer, $site) {
                $query->select('order_id')
                    ->from('billing')
                    ->where('user_id', $customer->user_id)
                    ->where('approved', 'yes')
                    ->where('payment', 'yes')
                    ->where(fn ($activeQuery) => LegacyQuerySupport::applyActiveEndDate($activeQuery, 'billing'))
                    ->where(function ($billingQuery) use ($site) {
                        $billingQuery->where('website', $site->legacyKey)
                            ->orWhereNull('website')
                            ->orWhere('website', '');
                    });
            })
            ->orderByDesc('order_id')
            ->paginate(20)
            ->withQueryString();

        return view('customer.orders.paid-advance', [
            'pageTitle' => 'Advance Orders',
            'orders' => $orders,
        ]);
    }

    public function refundForm(Request $request)
    {
        return view('customer.account.refund-apply', [
            'pageTitle' => 'Apply Refund',
            'customer' => $this->customer($request),
        ]);
    }

    public function submitRefund(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = (string) config('mail.admin_alert_address', $site->supportEmail);
        $subject = '['.$site->displayLabel().'] Refund request from customer #'.$customer->user_id;
        $body = view('customer.emails.refund-request', [
            'siteContext' => $site,
            'customer' => $customer,
            'reason' => trim((string) $validated['comments']),
            'submittedAt' => now(),
        ])->render();

        $sent = PortalMailer::sendHtml($recipient, $subject, $body);

        return $sent
            ? redirect('/refund-apply.php')->with('success', 'Your refund request has been sent successfully.')
            : back()->withErrors(['refund' => 'We could not send your refund request right now. Please try again or contact support directly.']);
    }

    private function paidBillingQuery(AdminUser $customer, SiteContext $site)
    {
        return Billing::query()
            ->active()
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'yes')
            ->whereNotNull('transid')
            ->whereNotIn('transid', ['advnce', 'ADVANCE', ''])
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            });
    }

    private function customer(Request $request): AdminUser
    {
        return $request->attributes->get('customerUser');
    }

    private function site(Request $request): SiteContext
    {
        return $request->attributes->get('siteContext');
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function csvResponse(string $fileName, array $headers, array $rows): Response
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'.csv"',
        ]);
    }
}
