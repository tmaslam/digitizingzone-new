<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\QuoteNegotiation;
use App\Support\AttachmentPreview;
use App\Support\CustomerAttachmentAccess;
use App\Support\CustomerBalance;
use App\Support\HttpCache;
use App\Support\CustomerReleaseGate;
use App\Support\CustomerRememberLogin;
use App\Support\CustomerWorkflowStatus;
use App\Support\HostedPaymentProviders;
use App\Support\LegacyQuerySupport;
use App\Support\OrderAutomation;
use App\Support\OrderWorkflowMetaManager;
use App\Support\PortalMailer;
use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use App\Support\SiteContext;
use App\Support\SignupOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');

        $orderCount = $this->activeOrdersQuery($customer, $site)->count();
        $quoteCount = $this->activeQuotesQuery($customer, $site)->count();
        $billingTotal = (float) $this->unpaidBillingQuery($customer, $site)->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));
        $billingCount = $this->unpaidBillingQuery($customer, $site)->count();
        $paidCount = $this->paidOrdersQuery($customer, $site)->count();
        $availableBalance = CustomerBalance::available((int) $customer->user_id, $site->legacyKey);
        $depositBalance = CustomerBalance::deposit($customer->topup);
        $placement = $this->placementState($customer, $site);

        $recentOrders = $this->activeOrdersQuery($customer, $site)
            ->orderByDesc('order_id')
            ->limit(5)
            ->get();

        $recentQuotes = $this->activeQuotesQuery($customer, $site)
            ->orderByDesc('order_id')
            ->limit(5)
            ->get();

        $recentBilling = $this->unpaidBillingQuery($customer, $site)
            ->with('order')
            ->orderByDesc('bill_id')
            ->limit(5)
            ->get();

        $orderStatusCounts = [
            'action_needed' => $this->activeOrdersQuery($customer, $site)->whereIn('status', ['done', 'disapprove', 'disapproved'])->count(),
            'in_production' => $this->activeOrdersQuery($customer, $site)->whereIn('status', ['Underprocess', 'Ready'])->count(),
        ];

        $quoteStatusCounts = [
            'ready_for_response' => $this->activeQuotesQuery($customer, $site)->where('status', 'done')->count(),
            'feedback_pending' => $this->activeQuotesQuery($customer, $site)->whereIn('status', ['disapprove', 'disapproved'])->count(),
        ];

        return view('customer.dashboard', [
            'pageTitle' => 'Dashboard',
            'customer' => $customer,
            'site' => $site,
            'metrics' => [
                'orders' => $orderCount,
                'quotes' => $quoteCount,
                'billing_count' => $billingCount,
                'billing_total' => $billingTotal,
                'paid' => $paidCount,
                'available_balance' => $availableBalance,
                'deposit_balance' => $depositBalance,
                'credit_limit' => $this->money($customer->customer_approval_limit),
                'single_order_limit' => $this->money($customer->single_approval_limit),
            ],
            'placement' => $placement,
            'recentOrders' => $recentOrders,
            'recentQuotes' => $recentQuotes,
            'recentBilling' => $recentBilling,
            'orderStatusCounts' => $orderStatusCounts,
            'quoteStatusCounts' => $quoteStatusCounts,
        ]);
    }

    public function orders(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);

        $ordersQuery = $this->activeOrdersQuery($customer, $site)
            ->orderByDesc('order_id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('customer-orders', ['Order ID', 'Design Name', 'Submitted', 'Status'], (clone $ordersQuery)->get()->map(
                fn (Order $order) => [
                    $order->order_id,
                    $order->design_name ?: '-',
                    $order->submit_date ?: '-',
                    CustomerWorkflowStatus::label($order),
                ]
            )->all());
        }

        $orders = $ordersQuery->paginate(20)->withQueryString();
        $orders->getCollection()->transform(function (Order $order) {
            $order->can_customer_cancel_flag = $this->canCustomerCancelOrder($order);

            return $order;
        });

        return view('customer.orders.index', [
            'pageTitle' => 'My Orders',
            'customer' => $customer,
            'orders' => $orders,
            'orderSummary' => [
                'total' => $this->activeOrdersQuery($customer, $site)->count(),
                'action_needed' => $this->activeOrdersQuery($customer, $site)->whereIn('status', ['done', 'disapprove', 'disapproved'])->count(),
                'in_production' => $this->activeOrdersQuery($customer, $site)->whereIn('status', ['Underprocess', 'Ready'])->count(),
            ],
        ]);
    }

    public function showOrder(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $orderId = (int) $request->query('order_id', $request->query('oid', 0));
        $order = $this->findCustomerOrder($customer, $site, $orderId, ['order', 'vector', 'color']);

        abort_unless($order, 404);

        return view('customer.orders.show', $this->orderViewData($order, $customer, false));
    }

    public function quotes(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);

        $quotesQuery = $this->activeQuotesQuery($customer, $site)
            ->orderByDesc('order_id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('customer-quotes', ['Quote ID', 'Design Name', 'Quoted Amount', 'Status'], (clone $quotesQuery)->get()->map(
                fn (Order $quote) => [
                    $quote->order_id,
                    $quote->design_name ?: '-',
                    $quote->total_amount ?: $quote->stitches_price ?: '0.00',
                    CustomerWorkflowStatus::label($quote, true),
                ]
            )->all());
        }

        $quotes = $quotesQuery->paginate(20)->withQueryString();
        $quotes->getCollection()->transform(function (Order $quote) {
            $quote->can_customer_delete_flag = $this->canCustomerDeleteQuote($quote);

            return $quote;
        });

        return view('customer.quotes.index', [
            'pageTitle' => 'My Quotes',
            'customer' => $customer,
            'quotes' => $quotes,
            'quoteSummary' => [
                'total' => $this->activeQuotesQuery($customer, $site)->count(),
                'ready_for_response' => $this->activeQuotesQuery($customer, $site)->where('status', 'done')->count(),
                'feedback_pending' => $this->activeQuotesQuery($customer, $site)->whereIn('status', ['disapprove', 'disapproved'])->count(),
            ],
        ]);
    }

    public function showQuote(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $orderId = (int) $request->query('order_id', 0);
        $quote = $this->findCustomerOrder($customer, $site, $orderId, ['quote', 'digitzing', 'q-vector', 'qcolor']);

        if (! $quote) {
            $convertedOrder = $this->findCustomerOrder($customer, $site, $orderId, ['order', 'vector', 'color']);

            if ($convertedOrder) {
                $origin = trim((string) $request->query('origin', 'quotes'));

                return redirect(url('/view-order-detail.php').'?'.http_build_query([
                    'order_id' => $convertedOrder->order_id,
                    'origin' => $origin === 'quotes' ? 'orders' : $origin,
                ]));
            }
        }

        abort_unless($quote, 404);

        return view('customer.quotes.show', $this->orderViewData($quote, $customer, true));
    }

    public function billing(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');

        $rows = $this->unpaidBillingQuery($customer, $site)
            ->with('order')
            ->orderByDesc('bill_id')
            ->paginate(20)
            ->withQueryString();

        return view('customer.billing.index', [
            'pageTitle' => 'My Billing',
            'billingRows' => $rows,
            'outstandingTotal' => (float) $this->unpaidBillingQuery($customer, $site)->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))')),
            'availableBalance' => CustomerBalance::available((int) $customer->user_id, $site->legacyKey),
            'depositBalance' => CustomerBalance::deposit($customer->topup),
            'paymentProviders' => HostedPaymentProviders::configuredOptions($site),
            'billingSummary' => [
                'invoice_count' => $this->unpaidBillingQuery($customer, $site)->count(),
                'largest_invoice' => (float) $this->unpaidBillingQuery($customer, $site)->max(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))')) ?: 0.0,
            ],
        ]);
    }

    public function payWithDeposit(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        $billings = $this->unpaidBillingQuery($customer, $site)
            ->with('order')
            ->orderBy('bill_id')
            ->get();

        if ($billings->isEmpty()) {
            return redirect(url('/view-billing.php'))->with('success', 'No outstanding invoices to pay.');
        }

        $totalOutstanding = $billings->sum(function ($billing) {
            return (float) ($billing->order?->total_amount ?: $billing->amount);
        });

        $availableBalance = CustomerBalance::available((int) $customer->user_id, $site->legacyKey);
        $deposit = CustomerBalance::deposit($customer->topup);
        $combinedBalance = $availableBalance + $deposit;

        if ($combinedBalance + 0.0001 < $totalOutstanding) {
            return redirect(url('/view-billing.php'))->with('error', 'Insufficient balance. Your available balance + deposit ($' . number_format($combinedBalance, 2) . ') is less than the outstanding total ($' . number_format($totalOutstanding, 2) . ').');
        }

        $paidCount = 0;
        foreach ($billings as $billing) {
            if (CustomerBalance::applyToBilling($billing, 'customer-deposit')) {
                $paidCount++;
            }
        }

        return redirect(url('/view-billing.php'))->with('success', $paidCount . ' invoice(s) paid successfully using your deposit balance.');
    }

    public function processAccountUpgrade(Request $request)
    {
        $customer = $this->customer($request);

        // Mark account as upgraded — customer stays logged in
        // but can no longer place new orders/quotes on the legacy portal
        $customer->update([
            'user_term' => 'upgraded',
        ]);

        $secret = '9p16O7dpkzwZc8y6rbwGTzKcnfYZqiXFQj534uwk1Ig=';
        $token = $this->makeUpgradeToken($customer->user_id, $secret);

        return response()->json([
            'success' => true,
            'redirect' => 'https://1dollardigitizing.com/migration.php?legacy_customer_id=' . urlencode($token),
        ]);
    }

    public function archive(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        CustomerBalance::settleZeroAmountBillings((int) $customer->user_id, $site->legacyKey, 'system-auto');

        $search = trim((string) $request->query('search', ''));
        $defaultFrom = '';
        $defaultTo   = '';
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo   = trim((string) $request->query('date_to', ''));
        $isDefaultRange = $dateFrom === $defaultFrom && $dateTo === $defaultTo;

        $sortColumn = in_array($request->query('sort'), ['order_id', 'design_name', 'completion_date'], true)
            ? $request->query('sort') : 'order_id';
        $sortDir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = $this->paidOrdersQuery($customer, $site);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('order_id', (int) $search)
                        ->orWhere('design_name', 'like', '%'.$search.'%');
                } else {
                    $q->where('design_name', 'like', '%'.$search.'%');
                }
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('completion_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('completion_date', '<=', $dateTo);
        }

        $query->orderBy($sortColumn, $sortDir);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('customer-paid-orders', ['Order ID', 'Design Name', 'Completion Date'], (clone $query)->get()->map(
                fn (Order $order) => [
                    $order->order_id,
                    $order->design_name ?: '-',
                    $order->completion_date ?: '-',
                ]
            )->all());
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('customer.archive.index', [
            'pageTitle'      => 'Paid Orders',
            'orders'         => $orders,
            'search'         => $search,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'defaultFrom'    => $defaultFrom,
            'defaultTo'      => $defaultTo,
            'isDefaultRange' => $isDefaultRange,
            'sort'           => $sortColumn,
            'dir'            => $sortDir,
        ]);
    }

    public function downloadPaidOrdersZip(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo   = trim((string) $request->query('date_to', ''));

        $query = $this->paidOrdersQuery($customer, $site)->orderBy('order_id');

        if ($dateFrom !== '') {
            $query->whereDate('completion_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('completion_date', '<=', $dateTo);
        }

        $orders = $query->get();

        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir . '/paid_orders_' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE) !== true) {
            abort(500, 'Could not create ZIP archive.');
        }

        foreach ($orders as $order) {
            // Fetch every attachment for this order so nothing is missed
            // (scanned, sewout, orderTeamImages, team, source files, etc.)
            $allAttachments = Attachment::query()
                ->where('order_id', $order->order_id)
                ->get()
                ->filter(fn (Attachment $attachment) => CustomerAttachmentAccess::attachmentAllowedForCustomer($order, $attachment));

            $orderRef = $order->order_num ?: $order->order_id;

            foreach ($allAttachments as $attachment) {
                $fullPath = CustomerAttachmentAccess::absolutePath($attachment);
                if (! is_file($fullPath)) {
                    continue;
                }

                $fileName = (string) ($attachment->file_name ?: basename($fullPath));
                $source   = (string) $attachment->file_source;

                // Prefix source/original uploads so they are easy to distinguish
                $zipPath = in_array($source, CustomerAttachmentAccess::SOURCE_FILE_SOURCES, true)
                    ? 'order_' . $orderRef . '/source file - ' . $fileName
                    : 'order_' . $orderRef . '/' . $fileName;

                $zip->addFile($fullPath, $zipPath);
            }
        }

        $zip->close();

        $suffix = '';
        if ($dateFrom !== '' || $dateTo !== '') {
            $suffix = '-' . ($dateFrom ?: 'start') . '-to-' . ($dateTo ?: 'end');
        }

        return response()->download($tmpFile, 'paid-orders' . $suffix . '.zip')->deleteFileAfterSend(true);
    }

    public function download(Request $request)
    {
        $customer = $this->customer($request);
        $attachmentId = (int) $request->query('attachment_id', 0);
        $fileParam = (string) $request->query('file', '');
        $download = CustomerAttachmentAccess::findDownload($customer, $attachmentId, $fileParam);

        if (! $download) {
            SecurityAudit::recordFileAccessDenied($request, 'Customer download was denied.', [
                'attachment_id' => $attachmentId,
                'requested_file' => $fileParam,
                'customer_user_id' => $customer->user_id,
            ]);

            abort(403, 'File Not Available');
        }

        /** @var Attachment $attachment */
        $attachment = $download['attachment'];

        $response = response()->download(
            $download['full_path'],
            (string) ($attachment->file_name ?: basename($download['full_path']))
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return HttpCache::applyPrivateNoStore($response);
    }

    public function preview(Request $request)
    {
        $customer = $this->customer($request);
        $attachmentId = (int) $request->query('attachment_id', 0);
        $download = CustomerAttachmentAccess::findDownload($customer, $attachmentId, (string) $request->query('file', ''));

        if (! $download) {
            SecurityAudit::recordFileAccessDenied($request, 'Customer preview was denied because the attachment could not be resolved.', [
                'attachment_id' => $attachmentId,
                'customer_user_id' => $customer->user_id,
            ]);

            abort(403, 'Preview Not Available');
        }

        /** @var Order $order */
        $order = $download['order'];
        /** @var Attachment $attachment */
        $attachment = $download['attachment'];
        if (! CustomerAttachmentAccess::previewAllowed($order, $attachment)) {
            SecurityAudit::recordFileAccessDenied($request, 'Customer preview was denied because the file type is not preview-safe.', [
                'attachment_id' => $attachment->attachment_id,
                'order_id' => $order->order_id,
                'attachment_type' => $attachment->attachment_type,
                'customer_user_id' => $customer->user_id,
            ]);

            abort(404);
        }

        $fileName = (string) ($attachment->file_name ?: basename($download['full_path']));

        return AttachmentPreview::inlineResponse($download['full_path'], $fileName);
    }

    public function approve(Request $request, int $orderId)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $order = $this->findCustomerOrder($customer, $site, $orderId, ['order', 'vector', 'color', 'quote', 'digitzing']);

        abort_unless($order && (string) $order->status === 'done', 404);

        $amount = $this->orderAmount($order);
        $approvalTime = now()->format('Y-m-d H:i:s');

        if ($this->isFreeOrder($order)) {
            $order->update([
                'status' => 'approved',
                'modified_date' => $approvalTime,
            ]);
            $this->sendAdminAlertForCustomerAction($customer, $site, $order, 'Customer Approved Order');

            return redirect(url('/view-archive-orders.php'))->with('success', 'Your order has been approved and moved to paid orders.');
        }

        $billing = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->orderByDesc('bill_id')
            ->first();

        if ($billing) {
            $billing->update($this->billingPayload(
                $order,
                $customer,
                $amount,
                'no',
                'Order approved.',
                now()->format('Y-m-d H:i'),
                $billing
            ));
        } else {
            $billing = Billing::query()->create($this->billingPayload(
                $order,
                $customer,
                $amount,
                'no',
                'Order approved.',
                now()->format('Y-m-d H:i')
            ));
        }

        $order->update([
            'status' => 'approved',
            'modified_date' => $approvalTime,
        ]);
        $this->sendAdminAlertForCustomerAction($customer, $site, $order, 'Customer Approved Order');

        if ($billing) {
            SignupOfferService::redeemClaimForOrder($order, $billing, 'signup-offer');
            $billing = $billing->loadMissing('order');

            if (CustomerBalance::applyToBilling($billing, 'customer-approval')) {
                return redirect(url('/view-archive-orders.php'))->with('success', 'Your order has been approved and paid using your available balance.');
            }

            if ((string) $billing->fresh()->payment === 'yes' && $this->orderAmount($order) <= 0) {
                return redirect(url('/view-invoices.php'))->with('success', 'Your order has been approved and recorded as a no-charge invoice.');
            }
        }

        return redirect(url('/view-billing.php'))->with('success', 'Your order has been approved and sent to billing.');
    }

    public function approveCompatibility(Request $request)
    {
        return $this->approve($request, (int) $request->query('order_id', 0));
    }

    public function switchQuoteToOrder(Request $request, int $orderId)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $quote = $this->findCustomerOrder($customer, $site, $orderId, ['quote', 'digitzing', 'q-vector', 'qcolor']);

        abort_unless($quote, 404);

        $validated = $request->validate([
            'response_comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $placement = $this->placementState($customer, $site);
        if (! $placement['can_place']) {
            return back()->withErrors(['workflow' => $placement['warning']]);
        }

        $now = now();
        $comment = trim((string) ($validated['response_comment'] ?? ''));

        $attachments = Attachment::query()
            ->where('order_id', $quote->order_id)
            ->whereIn('file_source', ['quote', 'edit quote'])
            ->get();

        foreach ($attachments as $attachment) {
            $sourcePath = SharedUploads::path('quotes'.DIRECTORY_SEPARATOR.(string) $attachment->file_name_with_date);
            $targetPath = SharedUploads::path('order'.DIRECTORY_SEPARATOR.(string) $attachment->file_name_with_date);
            $targetDirectory = dirname($targetPath);

            if (! is_dir($targetDirectory)) {
                @mkdir($targetDirectory, 0777, true);
            }

            if (is_file($sourcePath) && ! is_file($targetPath)) {
                @copy($sourcePath, $targetPath);
            }

            $newSource = (string) $attachment->file_source === 'edit quote' ? 'edit order' : 'order';
            $attachment->update([
                'file_source' => $newSource,
            ]);

            Attachment::query()->firstOrCreate([
                'order_id' => $quote->order_id,
                'file_name_with_date' => $attachment->file_name_with_date,
                'file_source' => 'orderTeamImages',
            ], [
                'file_name' => $attachment->file_name,
                'file_name_with_order_id' => $attachment->file_name_with_order_id,
                'date_added' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        if ($comment !== '') {
            OrderComment::query()->create([
                'order_id' => $quote->order_id,
                'comments' => $comment,
                'source_page' => 'customerComments',
                'comment_source' => 'customerComments',
                'date_added' => $now->format('Y-m-d H:i:s'),
                'date_modified' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        DB::transaction(function () use ($quote, $now) {
            // Re-fetch with a write lock to prevent duplicate conversions.
            $fresh = Order::query()->lockForUpdate()->find($quote->order_id);

            if (! $fresh || ! in_array((string) $fresh->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true)) {
                return;
            }

            $fresh->update([
                'order_type' => $this->convertedOrderType($fresh),
                'type' => $this->convertedWorkType($fresh),
                'status' => 'Underprocess',
                'assign_to' => 0,
                'assigned_date' => null,
                'submit_date' => $now->format('Y-m-d H:i:s'),
                'completion_date' => $this->completionDate((string) ($fresh->turn_around_time ?: 'Standard'), $now),
                'modified_date' => $now->format('Y-m-d H:i:s'),
            ]);

            OrderWorkflowMetaManager::ensure($fresh, [
                'created_source' => 'customer_quote_conversion',
            ]);
        });

        $this->sendAdminAlertForCustomerAction($customer, $site, $quote, 'Customer Converted Quote To Order', $comment);

        OrderAutomation::syncCustomer($customer, $site, true);

        return redirect(url('/view-quotes.php'))->with('success', 'Your quote has been converted to an order successfully.');
    }

    public function quoteFeedback(Request $request, int $orderId)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $quote = $this->findCustomerOrder($customer, $site, $orderId, ['quote', 'digitzing']);
        if (! $quote) {
            $quote = $this->findCustomerOrder($customer, $site, $orderId, ['q-vector', 'qcolor']);
        }

        abort_unless($quote, 404);
        abort_unless(strtolower(trim((string) $quote->status)) === 'done', 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'max:100'],
            'reason_text' => ['nullable', 'string', 'max:5000'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $now = now()->format('Y-m-d H:i:s');
        $targetAmount = round((float) $validated['target_amount'], 2);

        if (Schema::hasTable('quote_negotiations')) {
            QuoteNegotiation::query()->create([
                'site_id' => $site->id,
                'order_id' => $quote->order_id,
                'customer_user_id' => $customer->user_id,
                'legacy_website' => $site->legacyKey,
                'status' => 'pending_admin_review',
                'customer_reason_code' => $validated['reason_code'],
                'customer_reason_text' => trim((string) ($validated['reason_text'] ?? '')),
                'customer_target_amount' => $targetAmount,
                'quoted_amount' => $this->orderAmount($quote),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $feedbackComment = $this->buildQuoteFeedbackComment($validated['reason_code'], (string) ($validated['reason_text'] ?? ''), $targetAmount);

        OrderComment::query()->create([
            'order_id' => $quote->order_id,
            'comments' => $feedbackComment,
            'source_page' => 'customerComments',
            'comment_source' => 'customerComments',
            'date_added' => $now,
            'date_modified' => $now,
        ]);

        $quote->update([
            'status' => 'disapproved',
            'modified_date' => $now,
        ]);
        $this->sendAdminAlertForCustomerAction($customer, $site, $quote, 'Customer Submitted Quote Feedback', $feedbackComment);

        return redirect(url('/view-quotes.php'))->with('success', 'Your quote feedback has been sent for admin review.');
    }

    public function cancelOrder(Request $request, int $orderId)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $order = $this->findCustomerOrder($customer, $site, $orderId, ['order', 'vector', 'color']);

        abort_unless($order && $this->canCustomerCancelOrder($order), 404);

        $this->softDeleteCustomerOrder($order, $customer->user_name ?: 'customer');

        return redirect(url('/view-orders.php'))->with('success', 'Your order has been cancelled successfully.');
    }

    public function deleteQuote(Request $request, int $orderId)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $quote = $this->findCustomerOrder($customer, $site, $orderId, ['quote', 'digitzing', 'q-vector', 'qcolor']);

        abort_unless($quote && $this->canCustomerDeleteQuote($quote), 404);

        $this->softDeleteCustomerOrder($quote, $customer->user_name ?: 'customer');

        return redirect(url('/view-quotes.php'))->with('success', 'Your quote has been deleted successfully.');
    }

    private function orderViewData(Order $order, AdminUser $customer, bool $isQuote): array
    {
        $releaseSummary = CustomerReleaseGate::summary($order);
        $sourceAttachments = CustomerAttachmentAccess::sourceAttachments($order);
        $releasedAttachments = CustomerAttachmentAccess::releasedAttachments($order);
        $visibleReleasedAttachments = $releasedAttachments->values();
        $lockedReleasedCount = $releasedAttachments
            ->filter(fn (Attachment $attachment) => ! CustomerAttachmentAccess::attachmentAllowedForCustomer($order, $attachment))
            ->count();
        $quoteStatus = strtolower(trim((string) $order->status));
        $latestNegotiation = null;
        if ($isQuote && Schema::hasTable('quote_negotiations')) {
            $latestNegotiation = QuoteNegotiation::query()
                ->where('order_id', $order->order_id)
                ->where('customer_user_id', $customer->user_id)
                ->latest('id')
                ->first();
        }
        $customerComments = OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'customerComments')
            ->orderByDesc('id')
            ->get();
        $internalComments = OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'customer')
            ->orderBy('id')
            ->get();
        $backLink = $this->detailBackLink(request(), $isQuote);
        $statusLabel = CustomerWorkflowStatus::label($order, $isQuote);

        if (! $isQuote && strtolower(trim((string) $order->status)) === 'approved' && $backLink['url'] === url('/view-archive-orders.php')) {
            $statusLabel = 'Paid';
        }

        return [
            'pageTitle' => $isQuote ? 'Quote Detail' : 'Order Detail',
            'customer' => $customer,
            'order' => $order,
            'isQuote' => $isQuote,
            'sourceAttachments' => $sourceAttachments,
            'releasedAttachments' => $visibleReleasedAttachments,
            'lockedReleasedCount' => $lockedReleasedCount,
            'customerComments' => $customerComments,
            'internalComments' => $internalComments,
            'releaseSummary' => $releaseSummary,
            'previewNotice' => $releaseSummary['full_release_allowed']
                ? 'Released files are ready below, and image or PDF previews stay available in the browser.'
                : 'PDF and image previews remain available now. Production digitizing files unlock after payment or available credit coverage.',
            'statusLabel' => $statusLabel,
            'statusTone' => CustomerWorkflowStatus::tone($order, $isQuote),
            'statusHint' => CustomerWorkflowStatus::actionHint($order, $isQuote),
            'showApproveAction' => (string) $order->status === 'done' && ! $isQuote,
            'showQuoteAcceptAction' => $isQuote && $quoteStatus === 'done',
            'showQuoteRejectAction' => $isQuote && $quoteStatus === 'done',
            'showQuoteFeedbackSent' => $isQuote && in_array($quoteStatus, ['disapprove', 'disapproved'], true),
            'showQuoteSwitchEarly' => $isQuote && ! in_array($quoteStatus, ['done', 'approved', 'disapprove', 'disapproved'], true),
            'negotiationOpen' => Schema::hasTable('quote_negotiations'),
            'latestQuoteNegotiation' => $latestNegotiation,
            'showOrderCancelAction' => ! $isQuote && $this->canCustomerCancelOrder($order),
            'showQuoteDeleteAction' => $isQuote && $this->canCustomerDeleteQuote($order),
            'backLink' => $backLink,
        ];
    }

    private function activeOrdersQuery(AdminUser $customer, SiteContext $site)
    {
        return Order::query()
            ->active()
            ->orderManagement()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('status', '!=', 'approved')
            ->where(fn ($query) => LegacyQuerySupport::applyBlankOrZeroFlag($query, 'orders', 'advance_pay'));
    }

    private function activeQuotesQuery(AdminUser $customer, SiteContext $site)
    {
        return Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', ['digitzing', 'quote', 'q-vector', 'qcolor']);
    }

    private function unpaidBillingQuery(AdminUser $customer, SiteContext $site)
    {
        return Billing::query()
            ->active()
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->whereRaw('CAST(amount AS DECIMAL(12,2)) > 0')
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            });
    }

    private function paidOrdersQuery(AdminUser $customer, SiteContext $site)
    {
        return Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('status', 'approved')
            ->where('is_active', '1')
            ->where(function ($q) {
                // Hide completed orders with completion date before 2025
                $q->whereNull('completion_date')
                    ->orWhere('completion_date', '')
                    ->orWhere('completion_date', '0000-00-00')
                    ->orWhereDate('completion_date', '>=', '2025-01-01');
            })
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
            });
    }

    private function findCustomerOrder(AdminUser $customer, SiteContext $site, int $orderId, array $types): ?Order
    {
        if ($orderId <= 0) {
            return null;
        }

        return Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('order_id', $orderId)
            ->whereIn('order_type', $types)
            ->first();
    }

    private function canCustomerCancelOrder(Order $order): bool
    {
        if (! in_array((string) $order->order_type, ['order', 'vector', 'color'], true)) {
            return false;
        }

        if ((string) $order->status !== 'Underprocess') {
            return false;
        }

        if (! in_array((string) $order->assign_to, ['', '0'], true)) {
            return false;
        }

        return ! Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->exists();
    }

    private function canCustomerDeleteQuote(Order $order): bool
    {
        return in_array((string) $order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true);
    }

    private function softDeleteCustomerOrder(Order $order, string $deletedBy): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        $orderColumns = Schema::getColumnListing('orders');
        $order->update(collect([
            'end_date' => $timestamp,
            'deleted_by' => $deletedBy,
            'is_active' => 0,
        ])->only($orderColumns)->all());

        $commentColumns = Schema::hasTable('comments') ? Schema::getColumnListing('comments') : [];
        if ($commentColumns !== []) {
            $commentPayload = collect([
                'end_date' => $timestamp,
                'deleted_by' => $deletedBy,
            ])->only($commentColumns)->all();

            if ($commentPayload !== []) {
                OrderComment::query()
                    ->where('order_id', $order->order_id)
                    ->update($commentPayload);
            }
        }

        $attachmentColumns = Schema::hasTable('attach_files') ? Schema::getColumnListing('attach_files') : [];
        if ($attachmentColumns !== []) {
            $attachmentPayload = collect([
                'end_date' => $timestamp,
                'deleted_by' => $deletedBy,
            ])->only($attachmentColumns)->all();

            if ($attachmentPayload !== []) {
                Attachment::query()
                    ->where('order_id', $order->order_id)
                    ->update($attachmentPayload);
            }
        }

        if (Schema::hasTable('quote_negotiations')) {
            QuoteNegotiation::query()
                ->where('order_id', $order->order_id)
                ->update([
                    'updated_at' => $timestamp,
                    'status' => 'deleted',
                ]);
        }
    }

    private function placementState(AdminUser $customer, SiteContext $site): array
    {
        OrderAutomation::syncCustomer($customer, $site);
        $pendingOrders = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', ['order', 'vector', 'color'])
            ->where('status', 'done')
            ->count();

        $pendingAmount = (float) Billing::query()
            ->active()
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            })
            ->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));

        $creditLimit = $this->money($customer->customer_approval_limit);
        $pendingLimit = max(0, (int) $customer->customer_pending_order_limit);

        $warning = null;
        $canPlace = true;

        if ($creditLimit > 0 && $pendingAmount >= $creditLimit) {
            $canPlace = false;
            $warning = 'You have exceeded your credit limit of US$'.number_format($creditLimit, 2).'. Please clear billing or contact support to continue.';
        } elseif ($pendingLimit > 0 && $pendingOrders >= $pendingLimit) {
            $warning = "You already have {$pendingLimit} orders waiting for approval. The oldest approval-waiting order will be automatically pushed to billing when you submit new work.";
        }

        return [
            'can_place' => $canPlace,
            'pending_orders' => $pendingOrders,
            'pending_amount' => $pendingAmount,
            'warning' => $warning,
        ];
    }

    private function convertedOrderType(Order $order): string
    {
        return match ((string) $order->order_type) {
            'q-vector' => 'vector',
            'qcolor' => 'color',
            default => 'order',
        };
    }

    private function convertedWorkType(Order $order): string
    {
        return match ((string) $order->order_type) {
            'q-vector', 'vector', 'qcolor', 'color' => 'vector',
            default => 'digitizing',
        };
    }

    private function completionDate(string $turnaround, \Illuminate\Support\Carbon $submittedAt): string
    {
        $hours = match (strtolower($turnaround)) {
            'superrush' => 8,
            'priority' => 12,
            default => 24,
        };

        return $submittedAt->copy()->addHours($hours)->format('Y-m-d H:i:s');
    }

    private function orderAmount(Order $order): float
    {
        $amount = $this->money($order->total_amount);

        if ($amount <= 0) {
            $amount = $this->money($order->stitches_price);
        }

        return $amount;
    }

    private function isFreeOrder(Order $order): bool
    {
        return trim(strtolower((string) $order->total_amount)) === 'first order is free'
            || $this->orderAmount($order) <= 0;
    }

    private function buildQuoteFeedbackComment(string $reasonCode, string $reasonText, ?float $targetAmount): string
    {
        $parts = ['Quote feedback: '.str_replace('_', ' ', $reasonCode)];

        if ($targetAmount !== null) {
            $parts[] = 'Acceptable price: $'.number_format($targetAmount, 2);
        }

        $reasonText = trim($reasonText);
        if ($reasonText !== '') {
            $parts[] = 'Details: '.$reasonText;
        }

        return implode("\n", $parts);
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

    private function makeUpgradeToken(int $userId, string $secret): string
    {
        $key       = hash('sha256', $secret, true);
        $iv        = random_bytes(16);
        $payload   = $userId . ':' . time();
        $encrypted = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
    }

    private function detailBackLink(Request $request, bool $isQuote): array
    {
        $origin = trim((string) $request->query('origin', ''));

        return match ($origin) {
            'archive' => ['url' => url('/view-archive-orders.php'), 'label' => 'Back to Paid Orders'],
            'billing' => ['url' => url('/view-billing.php'), 'label' => 'Back to Billing'],
            'invoices' => ['url' => url('/view-invoices.php'), 'label' => 'Back to Invoices'],
            default => [
                'url' => $isQuote ? url('/view-quotes.php') : url('/view-orders.php'),
                'label' => $isQuote ? 'Back to Quotes' : 'Back to Orders',
            ],
        };
    }

    private function sendAdminAlertForCustomerAction(AdminUser $customer, SiteContext $site, Order $order, string $subject, string $comment = ''): void
    {
        $recipient = (string) config('mail.admin_alert_address', $site->supportEmail);

        if (PortalMailer::normalizeRecipient($recipient) === null) {
            return;
        }

        $customerName = trim((string) ($customer->display_name ?: $customer->user_name));
        $detailUrl = $this->adminDetailUrl($order);
        $designName = e((string) ($order->design_name ?? ''));
        $customerLabel = e($customerName);
        $customerEmail = e((string) ($customer->user_email ?? ''));
        $orderType = e((string) ($order->order_type ?? ''));
        $status = e((string) ($order->status ?? ''));
        $subjectLabel = e($subject);
        $commentHtml = trim($comment) !== '' ? '<p><strong>Customer Notes:</strong> '.e($comment).'</p>' : '';

        $body = <<<HTML
<p>{$subjectLabel} on {$site->displayLabel()}.</p>
<p><strong>Order ID:</strong> {$order->order_id}</p>
<p><strong>Design Name:</strong> {$designName}</p>
<p><strong>Customer:</strong> {$customerLabel}</p>
<p><strong>Email:</strong> {$customerEmail}</p>
<p><strong>Order Type:</strong> {$orderType}</p>
<p><strong>Current Status:</strong> {$status}</p>
{$commentHtml}
<p><a href="{$detailUrl}">Open order detail</a></p>
HTML;

        PortalMailer::sendHtml($recipient, $subject, $body);
    }

    private function adminDetailUrl(Order $order): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $path = '/v/view-order-detail.php?order_id='.$order->order_id.'&back=new-orders';

        return $baseUrl !== '' ? $baseUrl.$path : $path;
    }

    private function billingPayload(
        Order $order,
        AdminUser $customer,
        float $amount,
        string $payment = 'no',
        string $comments = 'Order approved.',
        ?string $approveDate = null,
        ?Billing $existingBilling = null
    ): array {
        $columns = Schema::getColumnListing('billing');
        $payload = [
            'user_id' => $customer->user_id,
            'order_id' => $order->order_id,
            'approved' => 'yes',
            'amount' => number_format($amount, 2, '.', ''),
            'earned_amount' => '',
            'payment' => $payment,
            'approve_date' => $approveDate ?: now()->format('Y-m-d H:i'),
            'comments' => $existingBilling?->comments ?: $comments,
            'transid' => $existingBilling?->transid ?: '',
            'trandtime' => $existingBilling?->trandtime,
            'website' => (string) ($order->website ?: config('sites.primary_legacy_key', '1dollar')),
            'site_id' => $order->site_id,
            'payer_id' => $existingBilling?->payer_id,
            'is_paid' => $payment === 'yes' ? 1 : 0,
            'is_advance' => (int) ($existingBilling?->is_advance ?: 0),
        ];

        return collect($payload)
            ->only($columns)
            ->all();
    }

    private function csvResponse(string $prefix, array $headers, array $rows): StreamedResponse
    {
        $filename = sprintf('%s-%s.csv', $prefix, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
