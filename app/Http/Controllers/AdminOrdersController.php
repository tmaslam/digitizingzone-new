<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\Attachment;
use App\Models\AdvancePayment;
use App\Models\QuoteNegotiation;
use App\Support\AdminNavigation;
use App\Support\AdminOrderQueues;
use App\Support\ApprovedBillingSync;
use App\Support\OrderAutomation;
use App\Support\OrderWorkflow;
use App\Support\SignupOfferService;
use App\Support\TurnaroundTracking;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrdersController extends Controller
{
    public function compatibilityListRedirect(Request $request, string $page)
    {
        return redirect()->to(AdminOrderQueues::url(
            AdminOrderQueues::normalize($page),
            $request->except(['page', 'queue'])
        ));
    }

    public function compatibilityDetailRedirect(Request $request)
    {
        $orderId = (int) $request->query('oid');
        $page = in_array((string) $request->query('page', 'order'), ['order', 'quote', 'vector'], true)
            ? (string) $request->query('page', 'order')
            : 'order';
        $back = trim((string) $request->query('back', ''));
        $base = url('/v/orders/'.$orderId.'/detail/'.$page);

        return redirect()->to($back !== ''
            ? $base.'?'.http_build_query(['back' => AdminOrderQueues::normalize($back)])
            : $base);
    }

    public function index(Request $request, ?string $queue = null)
    {
        $queueKey = AdminOrderQueues::normalize($queue ?: (string) $request->query('queue', $request->query('page', 'new-orders')));
        $pageTitle = AdminOrderQueues::label($queueKey);
        $category = AdminOrderQueues::category($queueKey);
        $navCounts = AdminNavigation::counts();
        OrderAutomation::syncSite((string) config('sites.primary_legacy_key', '1dollar'));

        if (in_array($category, ['approved_orders', 'all_orders'], true)) {
            ApprovedBillingSync::syncMissingApprovedBillings();
        }

        $ordersQuery = $this->buildQuery($category)
            ->with(['customer:user_id,first_name,last_name,user_name', 'assignee:user_id,user_name'])
            ->when($request->filled('txt_orderid'), function (Builder $query) use ($request) {
                $query->where('order_id', 'like', '%'.$request->string('txt_orderid')->trim().'%');
            })
            ->when($request->filled('txt_designname'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txt_designname')->trim().'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_custname'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txt_custname')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->orderBy(
                $this->sortColumn((string) $request->input('column_name'), 'order_id', [
                    'order_id',
                    'order_type',
                    'design_name',
                    'assign_to',
                    'status',
                    'total_amount',
                    'submit_date',
                    'completion_date',
                ]),
                $this->sortDirection((string) $request->input('sort'), 'desc')
            );

        if ($request->query('export') === 'csv') {
            $rows = (clone $ordersQuery)->get();
            $this->decorateOrders($rows);

            return $this->csvResponse(
                'admin-'.$queueKey,
                ['Order ID', 'Work Type', 'Customer', 'Design Name', 'Assigned To', 'Status', 'Turnaround', 'Schedule', 'Payment', 'Amount', 'Submitted'],
                $rows->map(fn (Order $order) => [
                    $order->order_id,
                    $order->work_type_label,
                    $order->customer_name,
                    $order->design_name ?: '-',
                    $order->assignee_name,
                    $order->status ?: '-',
                    $order->turnaround_label ?: ($order->turn_around_time ?: '-'),
                    $order->turnaround_status_label ?: 'Schedule Unknown',
                    $order->customer_paid_flag ? 'Paid' : 'Unpaid',
                    $order->total_amount ?: '0.00',
                    $order->submit_date ?: '-',
                ])->all()
            );
        }

        $orders = $ordersQuery->paginate(25)->withQueryString();

        $this->decorateOrders($orders->getCollection());

        return view('admin.orders.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => $navCounts,
            'pageTitle' => $pageTitle,
            'orders' => $orders,
            'category' => $category,
            'queueKey' => $queueKey,
            'queueMeta' => AdminOrderQueues::definition($queueKey),
            'queueNavigation' => AdminOrderQueues::navigation($navCounts, AdminOrderQueues::group($queueKey)),
            'currentQueueUrl' => AdminOrderQueues::url($queueKey),
        ]);
    }

    public function markPaid(Request $request, Order $order)
    {
        abort_unless($this->canMarkPaidOrder($order), 404);

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:150'],
        ], [], [
            'transaction_id' => 'transaction id',
        ]);

        if (! $this->hasCustomerPaid($order)) {
            $now = now();

            $order->update([
                'status' => 'approved',
                'modified_date' => $now->format('Y-m-d H:i:s'),
            ]);

            $billing = Billing::query()
                ->active()
                ->where('order_id', $order->order_id)
                ->orderByDesc('bill_id')
                ->first();

            $payload = [
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'approved' => 'yes',
                'amount' => (string) ($order->total_amount ?: '0.00'),
                'earned_amount' => '',
                'payment' => 'yes',
                'approve_date' => $billing?->approve_date ?: $now->format('Y-m-d G:i'),
                'comments' => 'Order marked as paid. Transaction ID: '.trim((string) $validated['transaction_id']),
                'transid' => trim((string) $validated['transaction_id']),
                'trandtime' => $now->format('Y-m-d H:i:s'),
                'website' => $order->website ?: '1dollar',
                'is_paid' => 1,
                'is_advance' => 0,
            ];
            $payload = Billing::writablePayload($payload);

            if ($billing) {
                $billing->update($payload);
            } else {
                Billing::query()->create($payload);
            }
        }

        return $this->redirectAfterAction($request, $order)
            ->with('success', 'Order marked as paid successfully.');
    }

    public function approve(Request $request, Order $order)
    {
        abort_unless($this->canApproveOrder($order), 404);

        $validated = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0'],
        ], [], [
            'approved_amount' => 'approval amount',
        ]);

        $approvedAmount = number_format(
            SignupOfferService::applyEligibleFirstOrderAmount($order, round((float) $validated['approved_amount'], 2)),
            2,
            '.',
            ''
        );

        $order->update([
            'status' => 'approved',
            'modified_date' => now()->format('Y-m-d H:i:s'),
            'total_amount' => $approvedAmount,
            'stitches_price' => $approvedAmount,
        ]);

        $billing = Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->orderByDesc('bill_id')
            ->first();

        if ($billing) {
            $billing->update(Billing::writablePayload([
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'approved' => 'yes',
                'amount' => $approvedAmount,
                'earned_amount' => '',
                'payment' => (string) $billing->payment === 'yes' ? 'yes' : 'no',
                'approve_date' => $billing->approve_date ?: now()->format('Y-m-d G:i'),
                'comments' => $billing->comments ?: 'Order approved.',
                'website' => $order->website ?: '1dollar',
                'is_paid' => (string) $billing->payment === 'yes' ? 1 : 0,
                'is_advance' => 0,
            ]));
        } else {
            $billing = Billing::query()->create(Billing::writablePayload([
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'approved' => 'yes',
                'amount' => $approvedAmount,
                'earned_amount' => '',
                'payment' => 'no',
                'approve_date' => now()->format('Y-m-d G:i'),
                'comments' => 'Order approved.',
                'website' => $order->website ?: '1dollar',
                'is_paid' => 0,
                'is_advance' => 0,
            ]));
        }

        SignupOfferService::redeemClaimForOrder($order, $billing, 'signup-offer');

        return $this->redirectAfterAction($request, $order)
            ->with('success', 'Order approved successfully.');
    }

    public function destroy(Request $request, Order $order)
    {
        abort_unless($this->canDeleteOrder($order), 404);

        $adminUser = $request->attributes->get('adminUser');
        $timestamp = now()->format('Y-m-d H:i:s');
        $deletedBy = $adminUser?->user_name ?: 'admin';

        $order->update([
            'end_date' => $timestamp,
            'deleted_by' => $deletedBy,
        ]);

        OrderComment::query()
            ->where('order_id', $order->order_id)
            ->update([
                'end_date' => $timestamp,
                'deleted_by' => $deletedBy,
            ]);

        Attachment::query()
            ->where('order_id', $order->order_id)
            ->update([
                'end_date' => $timestamp,
                'deleted_by' => $deletedBy,
            ]);

        Billing::query()
            ->where('order_id', $order->order_id)
            ->update([
                'end_date' => $timestamp,
                'deleted_by' => $deletedBy,
            ]);

        AdvancePayment::query()
            ->where('order_id', $order->order_id)
            ->update(['status' => 0]);

        return redirect()->to(url('/v/orders.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Order deleted successfully.');
    }

    private function buildQuery(string $category): Builder
    {
        return match ($category) {
            'new_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->where('status', 'Underprocess')
                ->whereNotIn('order_id', Billing::query()->select('order_id')->where('payment', 'yes'))
                ->unassigned(),
            'disapproved_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->where('status', 'disapproved'),
            'designer_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->whereIn('status', ['Underprocess', 'disapprove'])
                ->assigned(),
            'designer_completed_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->where('status', 'Ready')
                ->assigned(),
            'approval_waiting_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->where('status', 'done'),
            'approved_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->where('status', 'approved')
                ->whereNotIn('order_id', Billing::query()->select('order_id')->where(function ($query) {
                    $query->where('payment', 'yes')
                        ->orWhere('is_paid', 1);
                })),
            'new_quotes' => Order::query()
                ->active()
                ->quoteManagement()
                ->where('status', 'Underprocess')
                ->unassigned(),
            'assigned_quotes' => Order::query()
                ->active()
                ->quoteManagement()
                ->where('status', 'Underprocess')
                ->assigned(),
            'designer_completed_quotes' => Order::query()
                ->active()
                ->quoteManagement()
                ->where('status', 'Ready'),
            'completed_quotes' => Order::query()
                ->active()
                ->quoteManagement()
                ->whereIn('status', ['done', 'disapprove', 'disapproved']),
            'quote_negotiations' => Order::query()
                ->active()
                ->quoteManagement()
                ->whereIn('order_id', QuoteNegotiation::query()
                    ->whereIn('status', ['pending_admin_review', 'customer_replied'])
                    ->select('order_id')
                ),
            'all_orders' => Order::query()
                ->active()
                ->orderManagement()
                ->whereIn('status', ['Underprocess', 'disapprove', 'disapproved', 'Ready', 'done', 'approved']),
            default => Order::query()
                ->active()
                ->orderManagement()
                ->where(function ($query) {
                    $query->whereIn('status', ['Underprocess', 'disapprove', 'disapproved', 'Ready', 'done'])
                        ->orWhere(function ($approvedQuery) {
                            $approvedQuery->where('status', 'approved')
                                ->whereNotIn('order_id', Billing::query()->select('order_id')->where(function ($billingQuery) {
                                    $billingQuery->where('payment', 'yes')
                                        ->orWhere('is_paid', 1);
                                }));
                        });
                }),
        };
    }

    public function canDeleteOrder(Order $order): bool
    {
        if (! is_null($order->end_date)) {
            return false;
        }

        if (! $this->isDeleteEligibleWorkflowType($order)) {
            return false;
        }

        return ! Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where(function ($query) {
                $query->where('payment', 'yes')
                    ->orWhere('is_paid', 1)
                    ->orWhere('is_advance', 1);
            })
            ->exists();
    }

    public function canMarkPaidOrder(Order $order): bool
    {
        return is_null($order->end_date)
            && $this->isOrderManagementType($order)
            && (string) $order->status === 'approved'
            && ! $this->hasCustomerPaid($order);
    }

    public function canApproveOrder(Order $order): bool
    {
        return is_null($order->end_date)
            && $this->isOrderManagementType($order)
            && in_array((string) $order->status, ['done', 'disapproved'], true)
            && ! $this->hasApprovedBilling($order);
    }

    public function hasCustomerPaid(Order $order): bool
    {
        return Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where(function ($query) {
                $query->where('is_paid', 1)
                    ->orWhere('payment', 'yes');
            })
            ->exists();
    }

    public function hasApprovedBilling(Order $order): bool
    {
        return Billing::query()
            ->active()
            ->where('order_id', $order->order_id)
            ->where('approved', 'yes')
            ->exists();
    }

    private function sortColumn(string $column, string $default, array $allowed): string
    {
        return in_array($column, $allowed, true) ? $column : $default;
    }

    private function sortDirection(string $direction, string $default = 'desc'): string
    {
        $direction = strtolower($direction);

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
    }

    private function decorateOrders(Collection $orders): void
    {
        $orderIds = $orders->pluck('order_id')->filter()->map(fn ($id) => (int) $id)->values();

        if ($orderIds->isEmpty()) {
            return;
        }

        $billingSummary = Billing::query()
            ->active()
            ->select('order_id', 'approved', 'payment', 'is_paid', 'is_advance')
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        $orders->transform(function (Order $order) use ($billingSummary) {
            $billings = $billingSummary->get($order->order_id, collect());

            $customerPaidFlag = $billings->contains(function (Billing $billing) {
                return (string) $billing->payment === 'yes' || (int) $billing->is_paid === 1;
            });

            $approvedBillingFlag = $billings->contains(fn (Billing $billing) => (string) $billing->approved === 'yes');
            $hasBlockingBilling = $billings->contains(function (Billing $billing) {
                return (string) $billing->payment === 'yes'
                    || (int) $billing->is_paid === 1
                    || (int) $billing->is_advance === 1;
            });

            $order->setAttribute('customer_paid_flag', $customerPaidFlag);
            $order->setAttribute('approved_billing_flag', $approvedBillingFlag);
            $turnaround = TurnaroundTracking::summary($order);
            $order->setAttribute('turnaround_label', $turnaround['label_with_timing']);
            $order->setAttribute('turnaround_status_label', $turnaround['status_label']);
            $order->setAttribute('turnaround_status_tone', $turnaround['status_tone']);
            $order->setAttribute('turnaround_remaining_label', $turnaround['remaining_label']);
            $order->setAttribute('can_mark_paid_flag', is_null($order->end_date)
                && $this->isOrderManagementType($order)
                && (string) $order->status === 'approved'
                && ! $customerPaidFlag);
            $order->setAttribute('can_approve_flag', is_null($order->end_date) && $this->isOrderManagementType($order) && in_array((string) $order->status, ['done', 'disapproved'], true) && ! $approvedBillingFlag);
            $order->setAttribute('can_delete_flag', $this->canDeleteOrder($order));

            return $order;
        });
    }

    private function isOrderManagementType(Order $order): bool
    {
        return in_array((string) $order->order_type, ['order', 'vector', 'color'], true);
    }

    private function isDeleteEligibleWorkflowType(Order $order): bool
    {
        return in_array((string) $order->order_type, ['order', 'vector', 'color', 'quote', 'digitzing', 'q-vector', 'qcolor'], true);
    }

    private function isNewIntakeOrder(Order $order): bool
    {
        return (string) $order->status === 'Underprocess'
            && in_array((string) $order->assign_to, ['', '0'], true);
    }

    private function redirectAfterAction(Request $request, Order $order)
    {
        if ($request->input('return_to') === 'detail') {
            $page = $this->normalizeDetailPage((string) $request->input('detail_page', 'order'), $order);
            $back = trim((string) $request->input('back', ''));
            $base = url('/v/orders/'.$order->order_id.'/detail/'.$page);

            return redirect()->to($back !== ''
                ? $base.'?'.http_build_query(['back' => AdminOrderQueues::normalize($back)])
                : $base);
        }

        $queue = AdminOrderQueues::normalize((string) $request->input('queue', $request->query('queue', $request->query('page', 'new-orders'))));

        return redirect()->to(AdminOrderQueues::url(
            $queue,
            $request->except(['_token', 'return_to', 'detail_page', 'back', 'page', 'queue', 'transaction_id'])
        ));
    }

    private function normalizeDetailPage(string $page, Order $order): string
    {
        if (in_array($page, ['order', 'quote', 'vector'], true)) {
            return $page;
        }

        $resolvedPage = OrderWorkflow::pageForOrder($order);

        return in_array($resolvedPage, ['order', 'quote', 'vector'], true) ? $resolvedPage : 'order';
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
