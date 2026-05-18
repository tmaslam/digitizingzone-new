<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\BlockIp;
use App\Models\Blog;
use App\Models\CustomerPayment;
use App\Models\CustomerCreditLedger;
use App\Models\EmailTemplate;
use App\Models\LoginHistory;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\SecurityAuditEvent;
use App\Models\Site;
use App\Support\AdminNavigation;
use App\Support\ApprovedBillingSync;
use App\Support\CustomerBalance;
use App\Support\PortalMailer;
use App\Support\SecurityAlertSummary;
use App\Support\SiteResolver;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminToolsController extends Controller
{
    public function dueReport(Request $request)
    {
        ApprovedBillingSync::syncMissingApprovedBillings();

        $groupsQuery = $this->dueReportGroupsQuery($request);

        if ($request->query('export') === 'csv') {
            $groups = $groupsQuery->get();
            [$customers, $balancesByCustomer] = $this->customerContextForBillingGroups($groups);

            return $this->csvResponse('payment-due-report', ['Invoice Ref', 'User ID', 'Customer', 'Total Design', 'Amount', 'Available Balance'], $groups->map(
                fn ($group) => [
                    '#'.$group->bill_id,
                    $group->user_id,
                    $customers->get($group->user_id)?->display_name ?: '-',
                    $group->total_design,
                    number_format((float) $group->amount_total, 2, '.', ''),
                    number_format((float) ($balancesByCustomer[$group->user_id] ?? 0), 2, '.', ''),
                ]
            )->all());
        }

        $groups = $groupsQuery
            ->paginate(50)
            ->withQueryString();

        [$customers, $balancesByCustomer] = $this->customerContextForBillingGroups($groups->getCollection());

        return view('admin.tools.due-report', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'groups' => $groups,
            'customers' => $customers,
            'balancesByCustomer' => $balancesByCustomer,
            'totalApproved' => (float) $groups->getCollection()->sum('amount_total'),
        ]);
    }

    public function dueReportDetail(Request $request)
    {
        $userId = (int) $request->query('uid');
        $customer = AdminUser::query()->findOrFail($userId);
        $source = $request->query('source') === 'all-payment-due' ? 'all-payment-due' : 'payment-due-report';

        $entriesQuery = Billing::query()
            ->with('order:order_id,design_name,completion_date,stitches,total_amount')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where('user_id', $userId)
            ->orderBy('bill_id');

        if ($request->query('export') === 'csv') {
            $entries = $entriesQuery->get();

            return $this->csvResponse('payment-due-detail-'.$userId, ['Bill ID', 'Order ID', 'Design Name', 'Completion Date', 'Stitches', 'Amount', 'Payment'], $entries->map(
                fn (Billing $billing) => [
                    $billing->bill_id,
                    $billing->order_id,
                    $billing->order?->design_name ?: '-',
                    $billing->order?->completion_date ?: '-',
                    $billing->order?->stitches ?: '-',
                    number_format((float) ($billing->order?->total_amount ?: $billing->amount), 2, '.', ''),
                    (string) $billing->payment,
                ]
            )->all());
        }

        $entries = $entriesQuery->get();

        $currentBalance = CustomerBalance::available($userId);
        $balanceEntries = Schema::hasTable('customer_credit_ledger')
            ? CustomerCreditLedger::query()
                ->active()
                ->where('user_id', $userId)
                ->orderByDesc('date_added')
                ->limit(10)
                ->get()
            : collect();

        return view('admin.tools.due-report-detail', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customer' => $customer,
            'entries' => $entries,
            'sum' => $entries->sum(fn (Billing $billing) => (float) $billing->order?->total_amount ?: (float) $billing->amount),
            'currentBalance' => $currentBalance,
            'balanceEntries' => $balanceEntries,
            'source' => $source,
        ]);
    }

    public function dueReportPopupRedirect(Request $request)
    {
        return redirect()->to(url('/v/payment-due-detail.php').'?'.http_build_query([
            'uid' => $request->query('uid'),
        ]));
    }

    public function dueReportPayCustomer(Request $request)
    {
        $validated = $request->validate([
            'uid' => ['required', 'integer'],
            'transaction_id' => ['required', 'string'],
            'source' => ['nullable', 'string'],
        ], [], [
            'uid' => 'customer',
            'transaction_id' => 'transaction ID',
            'source' => 'source',
        ]);

        Billing::query()
            ->active()
            ->where('user_id', $validated['uid'])
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->update(Billing::writablePayload([
                'payment' => 'yes',
                'is_paid' => 1,
                'trandtime' => now()->format('Y-m-d H:i:s'),
                'transid' => $validated['transaction_id'],
                'comments' => 'admin payment recorded',
            ]));

        $redirectTarget = ($validated['source'] ?? null) === 'all-payment-due'
            ? url('/v/payment-recieved.php')
            : url('/v/payment-recieved-report.php');

        return redirect()->to($redirectTarget)
            ->with('success', 'Customer payment status updated successfully.');
    }

    public function dueReportPayInvoice(Request $request, Billing $billing)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
            'source' => ['nullable', 'string'],
        ], [], [
            'transaction_id' => 'transaction ID',
            'source' => 'source',
        ]);

        $billing->update(Billing::writablePayload([
            'approved' => (string) ($billing->approved ?: 'yes'),
            'payment' => 'yes',
            'is_paid' => 1,
            'trandtime' => now()->format('Y-m-d H:i:s'),
            'transid' => $validated['transaction_id'],
            'comments' => 'admin payment recorded',
        ]));

        return redirect()->to(url('/v/payment-due-detail.php?'.http_build_query(array_filter([
            'uid' => $billing->user_id,
            'source' => $validated['source'] ?? null,
        ]))))
            ->with('success', 'Invoice marked as paid successfully.');
    }

    public function applyCustomerBalance(Request $request, Billing $billing)
    {
        abort_unless(Schema::hasTable('customer_credit_ledger'), 404);

        $adminUser = $request->attributes->get('adminUser');

        if (! CustomerBalance::applyToBilling($billing->loadMissing('order'), $adminUser?->user_name ?: 'admin')) {
            return back()->withErrors(['credit' => 'The available customer balance is not enough to pay this invoice.']);
        }

        return redirect()->to(url('/v/payment-due-detail.php?'.http_build_query(array_filter([
            'uid' => $billing->user_id,
            'source' => $request->input('source'),
        ]))))->with('success', 'Customer balance applied successfully.');
    }

    public function applyCustomerBalanceToCustomer(Request $request)
    {
        abort_unless(Schema::hasTable('customer_credit_ledger'), 404);

        $validated = $request->validate([
            'uid' => ['required', 'integer'],
        ], [], [
            'uid' => 'customer',
        ]);

        $entries = Billing::query()
            ->with('order')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where('user_id', (int) $validated['uid'])
            ->orderBy('bill_id')
            ->get();

        $adminUser = $request->attributes->get('adminUser');
        $applied = 0;

        foreach ($entries as $billing) {
            if (! CustomerBalance::applyToBilling($billing, $adminUser?->user_name ?: 'admin')) {
                break;
            }

            $applied++;
        }

        if ($applied === 0) {
            return back()->withErrors(['credit' => 'There is not enough available balance to pay any invoice on this customer.']);
        }

        return redirect()->to(url('/v/payment-due-detail.php?'.http_build_query(array_filter([
            'uid' => $validated['uid'],
            'source' => $request->input('source'),
        ]))))->with('success', 'Customer balance applied to '.$applied.' invoice(s).');
    }

    public function receivedReport(Request $request)
    {
        $groupsQuery = $this->receivedReportGroupsQuery($request);

        if ($request->query('export') === 'csv') {
            $groups = $groupsQuery->get();
            [$customers] = $this->customerContextForBillingGroups($groups);

            return $this->csvResponse('payment-received-report', ['Invoice Ref', 'User ID', 'Customer', 'Total Design', 'Amount'], $groups->map(
                fn ($group) => [
                    '#'.$group->bill_id,
                    $group->user_id,
                    $customers->get($group->user_id)?->display_name ?: '-',
                    $group->total_design,
                    number_format((float) $group->amount_total, 2, '.', ''),
                ]
            )->all());
        }

        $groups = $groupsQuery
            ->paginate(50)
            ->withQueryString();

        [$customers] = $this->customerContextForBillingGroups($groups->getCollection());

        return view('admin.tools.received-report', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'groups' => $groups,
            'customers' => $customers,
            'totalReceived' => (float) $groups->getCollection()->sum('amount_total'),
        ]);
    }

    public function receivedReportDetail(Request $request)
    {
        $userId = (int) $request->query('uid');
        $customer = AdminUser::query()->findOrFail($userId);
        $source = $request->query('source') === 'payment-recieved' ? 'payment-recieved' : 'payment-recieved-report';

        $entriesQuery = Billing::query()
            ->with('order:order_id,design_name,completion_date,stitches,total_amount')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'yes')
            ->where('user_id', $userId)
            ->orderBy('bill_id');

        if ($request->query('export') === 'csv') {
            $entries = $entriesQuery->get();

            return $this->csvResponse('payment-received-detail-'.$userId, ['Bill ID', 'Order ID', 'Design Name', 'Completion Date', 'Stitches', 'Amount', 'Transaction ID', 'Paid At'], $entries->map(
                fn (Billing $billing) => [
                    $billing->bill_id,
                    $billing->order_id,
                    $billing->order?->design_name ?: '-',
                    $billing->order?->completion_date ?: '-',
                    $billing->order?->stitches ?: '-',
                    number_format((float) ($billing->order?->total_amount ?: $billing->amount), 2, '.', ''),
                    $billing->transid ?: '-',
                    $billing->trandtime ?: '-',
                ]
            )->all());
        }

        $entries = $entriesQuery->get();

        return view('admin.tools.received-report-detail', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customer' => $customer,
            'entries' => $entries,
            'sum' => $entries->sum(fn (Billing $billing) => (float) $billing->order?->total_amount ?: (float) $billing->amount),
            'source' => $source,
        ]);
    }

    public function receivedReportPopupRedirect(Request $request)
    {
        return redirect()->to(url('/v/payment-recieved-detail.php').'?'.http_build_query([
            'uid' => $request->query('uid'),
        ]));
    }

    public function teamReport(Request $request)
    {
        $teams = AdminUser::query()
            ->teamPortalUsers()
            ->active()
            ->where('is_active', 1)
            ->orderByRaw('CASE WHEN usre_type_id = ? THEN 0 ELSE 1 END', [AdminUser::TYPE_SUPERVISOR])
            ->orderBy('user_name')
            ->get();
        $months = Order::query()
            ->active()
            ->where('status', 'approved')
            ->pluck('completion_date')
            ->map(fn ($date) => is_string($date) && strlen($date) >= 7 ? substr($date, 0, 7) : null)
            ->filter(fn ($month) => $month && $month !== '0000-00')
            ->unique()
            ->sortDesc()
            ->values();

        $orders = collect();
        if ($request->filled('team') || $request->filled('month')) {
            $orders = Order::query()
                ->active()
                ->with('assignee:user_id,user_name')
                ->when($request->filled('team'), fn (Builder $query) => $query->where('assign_to', (int) $request->query('team')))
                ->when($request->filled('month'), fn (Builder $query) => $query->where('completion_date', 'like', trim((string) $request->string('month')).'%'))
                ->where('status', 'approved')
                ->orderBy(
                    $this->sortColumn((string) $request->input('column_name'), 'completion_date', ['order_id', 'order_type', 'design_name', 'stitches', 'total_amount', 'completion_date']),
                    $this->sortDirection((string) $request->input('sort'), 'desc')
                )
                ->get();
        } else {
            $currentYearPrefix = now()->format('Y');
            $orders = Order::query()
                ->active()
                ->with('assignee:user_id,user_name')
                ->where('status', 'approved')
                ->where('completion_date', 'like', $currentYearPrefix.'%')
                ->orderBy(
                    $this->sortColumn((string) $request->input('column_name'), 'completion_date', ['order_id', 'order_type', 'design_name', 'stitches', 'total_amount', 'completion_date']),
                    $this->sortDirection((string) $request->input('sort'), 'desc')
                )
                ->get();
        }

        $reviewedLookup = OrderComment::query()
            ->where('comment_source', 'supervisorReview')
            ->whereIn('order_id', $orders->pluck('order_id')->all())
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $orders = $orders->map(function (Order $order) use ($reviewedLookup) {
            $order->setAttribute('supervisor_checked_flag', $reviewedLookup->has((int) $order->order_id));

            return $order;
        });

        if ($request->query('export') === 'csv') {
            $filename = 'team-report-'.now()->format('Ymd-His').'.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            $callback = function () use ($orders) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Order ID', 'Design Type', 'Design Name', 'Assigned To', 'Supervisor Checked', 'Stitches', 'Total Amount', 'Completion Date']);

                foreach ($orders as $order) {
                    fputcsv($handle, [
                        $order->order_id,
                        $order->work_type_label,
                        $order->design_name ?: '',
                        $order->assignee?->user_name ?: '',
                        $order->supervisor_checked_flag ? 'Yes' : 'No',
                        $order->stitches ?: '',
                        is_numeric($order->total_amount) ? number_format((float) $order->total_amount, 2, '.', '') : (string) ($order->total_amount ?: '0.00'),
                        $order->completion_date ?: '',
                    ]);
                }

                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        return view('admin.tools.team-report', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'teams' => $teams,
            'months' => $months,
            'orders' => $orders,
            'summary' => [
                'total_orders' => $orders->count(),
                'supervisor_checked' => $orders->filter(fn (Order $order) => (bool) $order->supervisor_checked_flag)->count(),
                'total_amount' => $orders->sum(fn (Order $order) => (float) $order->total_amount),
            ],
        ]);
    }

    public function loginHistory(Request $request)
    {
        $historyQuery = LoginHistory::query()
            ->when($request->filled('txtUserIP'), function (Builder $query) use ($request) {
                $query->where('IP_Address', 'like', '%'.trim((string) $request->string('txtUserIP')).'%');
            })
            ->when($request->filled('txtLoginName'), function (Builder $query) use ($request) {
                $query->where('Login_Name', 'like', '%'.trim((string) $request->string('txtLoginName')).'%');
            })
            ->when($request->filled('txtStatus'), function (Builder $query) use ($request) {
                $query->where('Status', 'like', '%'.trim((string) $request->string('txtStatus')).'%');
            })
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'Date_Added', ['IP_Address', 'Login_Name', 'Date_Added', 'Status']), $this->sortDirection((string) $request->input('sort'), 'desc'));

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('login-history', ['IP Address', 'Login Name', 'Date Added', 'Reason'], $historyQuery->get()->map(
                fn ($entry) => [
                    $entry->IP_Address,
                    $entry->Login_Name,
                    $entry->Date_Added,
                    $entry->Status,
                ]
            )->all());
        }

        $history = $historyQuery
            ->paginate(50)
            ->withQueryString();

        return view('admin.tools.login-history', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'history' => $history,
        ]);
    }

    public function securityEvents(Request $request)
    {
        abort_unless(Schema::hasTable('security_audit_events'), 404);

        $eventsQuery = SecurityAuditEvent::query()
            ->when($request->filled('txtPortal'), function (Builder $query) use ($request) {
                $query->where('portal', trim((string) $request->string('txtPortal')));
            })
            ->when($request->filled('txtSeverity'), function (Builder $query) use ($request) {
                $query->where('severity', trim((string) $request->string('txtSeverity')));
            })
            ->when($request->filled('txtEventType'), function (Builder $query) use ($request) {
                $query->where('event_type', 'like', '%'.trim((string) $request->string('txtEventType')).'%');
            })
            ->when($request->filled('txtActor'), function (Builder $query) use ($request) {
                $query->where('actor_login', 'like', '%'.trim((string) $request->string('txtActor')).'%');
            })
            ->when($request->filled('txtUserIP'), function (Builder $query) use ($request) {
                $query->where('ip_address', 'like', '%'.trim((string) $request->string('txtUserIP')).'%');
            })
            ->when($request->filled('txtPath'), function (Builder $query) use ($request) {
                $query->where('request_path', 'like', '%'.trim((string) $request->string('txtPath')).'%');
            })
            ->orderBy(
                $this->sortColumn((string) $request->input('column_name'), 'created_at', [
                    'created_at',
                    'severity',
                    'portal',
                    'event_type',
                    'actor_login',
                    'ip_address',
                    'request_path',
                ]),
                $this->sortDirection((string) $request->input('sort'), 'desc')
            );

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('security-events', ['Time', 'Severity', 'Portal', 'Event', 'Actor', 'Actor User ID', 'IP', 'Method', 'Path', 'Message'], $eventsQuery->get()->map(
                fn (SecurityAuditEvent $event) => [
                    $event->created_at,
                    $event->severity,
                    $event->portal,
                    $event->event_type,
                    $event->actor_login ?: '-',
                    $event->actor_user_id ?: '',
                    $event->ip_address,
                    $event->request_method,
                    $event->request_path,
                    $event->message,
                ]
            )->all());
        }

        $events = $eventsQuery
            ->paginate(50)
            ->withQueryString();

        return view('admin.tools.security-events', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'events' => $events,
            'securityWatch' => SecurityAlertSummary::summary(),
        ]);
    }

    public function blockedCustomers(Request $request)
    {
        $customers = AdminUser::query()
            ->blockedCustomerAccounts()
            ->when($request->filled('txtUserID'), fn (Builder $query) => $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%'))
            ->when($request->filled('txtUserName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtUserName')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('user_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtEmail'), fn (Builder $query) => $query->where('user_email', 'like', '%'.trim((string) $request->string('txtEmail')).'%'))
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'user_id', ['user_id', 'user_name', 'user_email', 'user_country', 'is_active', 'userip_addrs', 'date_added']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(30)
            ->withQueryString();

        return view('admin.tools.blocked-customers', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customers' => $customers,
        ]);
    }

    public function unblockCustomer(Request $request, AdminUser $customer)
    {
        $isBlockedExistingCustomer = AdminUser::query()
            ->blockedCustomerAccounts()
            ->whereKey($customer->getKey())
            ->exists();

        if (! $isBlockedExistingCustomer) {
            return redirect()->to(url('/v/customer-approvals.php'))
                ->with('error', 'This account is not in the inactive customer workflow. Pending signup accounts should be handled from Customer Approvals.');
        }

        if (trim((string) $customer->user_term) === 'blocked') {
            // Pre-approval blocked account — restore to the manual admin-approval path
            // so it reappears in the approval queue without requiring re-payment.
            $customer->update([
                'is_active'      => 0,
                'user_term'      => 'dc',
                'exist_customer' => '0',
            ]);

            return redirect()->to(url('/v/block-customer_list.php').'?'.http_build_query($request->except('_token')))
                ->with('success', 'Customer has been restored to the signup approval queue.');
        }

        $customer->update(['is_active' => 1]);

        return redirect()->to(url('/v/block-customer_list.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Customer has been unblocked successfully.');
    }

    public function deleteBlockedCustomer(Request $request, AdminUser $customer)
    {
        $customer->update([
            'end_date' => now()->format('Y-m-d H:i:s'),
            'deleted_by' => $request->attributes->get('adminUser')?->user_name ?: 'admin',
        ]);

        return redirect()->to(url('/v/block-customer_list.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Customer has been deleted successfully.');
    }

    public function blockedIps(Request $request)
    {
        $ips = BlockIp::query()
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'id', ['id', 'ipaddress']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(30)
            ->withQueryString();

        return view('admin.tools.blocked-ips', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'ips' => $ips,
        ]);
    }

    public function storeBlockedIp(Request $request)
    {
        $validated = $request->validate([
            'txtUserID' => ['required', 'ip'],
        ], [
            'txtUserID.required' => 'Please enter an IP address.',
            'txtUserID.ip' => 'Please enter a valid IP address.',
        ], [
            'txtUserID' => 'IP address',
        ]);

        BlockIp::query()->firstOrCreate([
            'ipaddress' => trim((string) $validated['txtUserID']),
        ]);

        return redirect()->to(url('/v/blocked-ip-list.php'))
            ->with('success', 'IP Address has been blocked successfully.');
    }

    public function deleteBlockedIp(BlockIp $blockIp)
    {
        $blockIp->delete();

        return redirect()->to(url('/v/blocked-ip-list.php'))
            ->with('success', 'IP Address has been deleted successfully.');
    }

    public function transactionHistory(Request $request)
    {
        $hasPaymentsTable = Schema::hasTable('customerpayments');
        $records = collect();
        $paginator = null;
        $totalAmount = 0.0;
        $totalDue = 0.0;
        $linkedCustomers = collect();

        if ($hasPaymentsTable) {
            $paymentQuery = CustomerPayment::query()
                ->active()
                ->when($request->filled('txtUserID') && Schema::hasTable('customer_credit_ledger'), function ($query) use ($request) {
                    $userId = (int) trim((string) $request->string('txtUserID'));

                    if ($userId <= 0) {
                        return;
                    }

                    $paymentRefs = CustomerCreditLedger::query()
                        ->active()
                        ->where('user_id', $userId)
                        ->whereIn('entry_type', ['payment', 'overpayment'])
                        ->pluck('reference_no')
                        ->filter(fn ($reference) => str_starts_with((string) $reference, 'customerpayments:'))
                        ->map(fn ($reference) => (int) str_replace('customerpayments:', '', (string) $reference))
                        ->filter(fn ($seqNo) => $seqNo > 0)
                        ->values();

                    if ($paymentRefs->isEmpty()) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereIn('Seq_No', $paymentRefs->all());
                })
                ->when($request->filled('txtSeqNo'), fn ($query) => $query->where('Seq_No', 'like', '%'.trim((string) $request->string('txtSeqNo')).'%'))
                ->when($request->filled('txtTransID'), function ($query) use ($request) {
                    $term = '%'.trim((string) $request->string('txtTransID')).'%';
                    $query->where(function ($searchQuery) use ($term) {
                        $searchQuery
                            ->where('Transaction_ID', 'like', $term)
                            ->orWhere('Seq_No', 'like', $term);
                    });
                })
                ->when($request->filled('txtSource'), function ($query) use ($request) {
                    $term = '%'.trim((string) $request->string('txtSource')).'%';
                    $query->where(function ($searchQuery) use ($term) {
                        $searchQuery
                            ->where('Payment_Source', 'like', $term)
                            ->orWhere('Notes', 'like', $term);
                    });
                });

            $paginator = (clone $paymentQuery)
                ->orderBy($this->sortColumn((string) $request->input('column_name'), 'Effective_Date', ['Seq_No', 'Payment_Amount', 'Payment_Source', 'Transaction_ID', 'Effective_Date']), $this->sortDirection((string) $request->input('sort'), 'desc'))
                ->paginate(50)
                ->withQueryString();
            $records = $paginator->getCollection();
            $totalAmount = (clone $paymentQuery)->sum(DB::raw('CAST(Payment_Amount AS DECIMAL(12,2))'));

            $linkedCustomers = $this->customerContextForPayments($records);
        }

        $totalDue = (float) Billing::query()
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));

        return view('admin.tools.transaction-history', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'hasPaymentsTable' => $hasPaymentsTable,
            'records' => $records,
            'paginator' => $paginator,
            'totalAmount' => $totalAmount,
            'totalDue' => $totalDue,
            'hasCustomerBalanceTable' => Schema::hasTable('customer_credit_ledger'),
            'linkedCustomers' => $linkedCustomers,
        ]);
    }

    public function paymentForm(Request $request)
    {
        $hasPaymentsTable = Schema::hasTable('customerpayments');
        $payment = null;
        $linkedCredit = null;
        $paymentSummary = null;
        $customer = null;
        $dueTotal = 0.0;

        if ($hasPaymentsTable && $request->filled('id')) {
            $payment = CustomerPayment::query()->active()->find($request->query('id'));
        }

        if ($payment && Schema::hasTable('customer_credit_ledger')) {
            $referenceNo = 'customerpayments:'.$payment->Seq_No;
            $ledgerEntries = CustomerCreditLedger::query()
                ->active()
                ->where(function ($query) use ($referenceNo) {
                    $query
                        ->where('reference_no', $referenceNo)
                        ->orWhere('reference_no', 'like', $referenceNo.':%');
                })
                ->get();

            $linkedCredit = $ledgerEntries
                ->whereIn('entry_type', ['payment', 'overpayment'])
                ->first();

            $paymentSummary = [
                'applied_amount' => round((float) abs($ledgerEntries->where('entry_type', 'applied')->sum('amount')), 2),
                'balance_amount' => round((float) $ledgerEntries->whereIn('entry_type', ['payment', 'overpayment'])->sum('amount'), 2),
                'status' => $ledgerEntries->contains('entry_type', 'overpayment')
                    ? 'Overpayment'
                    : ($ledgerEntries->where('entry_type', 'applied')->isNotEmpty() ? 'Applied To Invoice(s)' : ($linkedCredit ? 'Saved As Balance' : 'Not Linked')),
            ];

            $customerId = (int) ($linkedCredit?->user_id ?: $ledgerEntries->first()?->user_id ?: 0);
            if ($customerId > 0) {
                $customer = AdminUser::query()->customers()->active()->find($customerId);
                $dueTotal = (float) Billing::query()
                    ->active()
                    ->where('approved', 'yes')
                    ->where('payment', 'no')
                    ->where('user_id', $customerId)
                    ->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));
            }
        } elseif ($request->filled('user_id')) {
            $customerId = (int) $request->query('user_id');
            if ($customerId > 0) {
                $customer = AdminUser::query()->customers()->active()->find($customerId);
                if ($customer) {
                    $dueTotal = (float) Billing::query()
                        ->active()
                        ->where('approved', 'yes')
                        ->where('payment', 'no')
                        ->where('user_id', $customerId)
                        ->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));
                }
            }
        }

        if ($payment && ! $linkedCredit && Schema::hasTable('customer_credit_ledger')) {
            $linkedCredit = CustomerCreditLedger::query()
                ->active()
                ->whereIn('entry_type', ['payment', 'overpayment'])
                ->where('reference_no', 'customerpayments:'.$payment->Seq_No)
                ->first();
        }

        return view('admin.tools.payment-form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'hasPaymentsTable' => $hasPaymentsTable,
            'payment' => $payment,
            'linkedCredit' => $linkedCredit,
            'paymentSummary' => $paymentSummary,
            'customer' => $customer,
            'dueTotal' => $dueTotal,
            'amount' => (string) $request->query('amount', $payment?->Payment_Amount ?: ''),
        ]);
    }

    public function customerLookup(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['customers' => []]);
        }

        $customers = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->where(function (Builder $query) use ($term) {
                $like = '%'.$term.'%';

                $query
                    ->where('user_id', 'like', $like)
                    ->orWhere('user_name', 'like', $like)
                    ->orWhere('user_email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$like]);
            })
            ->orderBy('user_id')
            ->limit(12)
            ->get(['user_id', 'user_name', 'user_email', 'first_name', 'last_name']);

        $dueTotals = Billing::query()
            ->selectRaw('user_id, SUM(CAST(amount AS DECIMAL(12,2))) as due_total')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->whereIn('user_id', $customers->pluck('user_id')->all())
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'customers' => $customers->map(function (AdminUser $customer) use ($dueTotals) {
                $dueTotal = (float) ($dueTotals[$customer->user_id]->due_total ?? 0);

                return [
                    'user_id' => (int) $customer->user_id,
                    'display_name' => $customer->display_name,
                    'user_name' => (string) $customer->user_name,
                    'user_email' => (string) $customer->user_email,
                    'due_total' => round($dueTotal, 2),
                    'summary' => trim(implode(' | ', array_filter([
                        $customer->display_name,
                        $customer->user_name ? '@'.$customer->user_name : null,
                        $customer->user_email,
                    ]))),
                ];
            })->values(),
        ]);
    }

    public function paymentSave(Request $request)
    {
        abort_unless(Schema::hasTable('customerpayments'), 404);

        $validated = $request->validate([
            'Seq_No' => ['nullable', 'integer'],
            'Payment_Amount' => ['required', 'string'],
            'Payment_Source' => ['required', 'string'],
            'Transaction_ID' => ['required', 'string'],
            'user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'apply_to_due' => ['nullable', 'in:0,1'],
        ], [], [
            'Seq_No' => 'transaction',
            'Payment_Amount' => 'payment amount',
            'Payment_Source' => 'payment source',
            'Transaction_ID' => 'transaction ID',
            'user_id' => 'customer user ID',
            'notes' => 'notes',
            'apply_to_due' => 'payment application',
        ]);

        $isEdit = $request->filled('Seq_No');

        $payment = $isEdit
            ? CustomerPayment::query()->findOrFail((int) $request->input('Seq_No'))
            : new CustomerPayment();

        $customerId = (int) ($validated['user_id'] ?? 0);
        $customer = $customerId > 0 ? AdminUser::query()->customers()->active()->find($customerId) : null;
        if ($customerId > 0 && ! $customer) {
            return back()->withErrors(['user_id' => 'The selected customer user ID was not found.'])->withInput();
        }

        $normalizedWebsite = CustomerBalance::normalizeWebsite($customer?->website);
        $resolvedSiteId = $customer?->site_id
            ? (int) $customer->site_id
            : (Schema::hasTable('sites')
                ? (int) (Site::query()->where('legacy_key', $normalizedWebsite)->value('id') ?: 0)
                : 0);

        $payment->fill([
            'Payment_Amount' => $validated['Payment_Amount'],
            'Payment_Source' => $validated['Payment_Source'],
            'Transaction_ID' => $validated['Transaction_ID'],
            'Effective_Date' => now()->format('Y-m-d H:i:s'),
            'Website' => $normalizedWebsite,
        ]);

        if (Schema::hasColumn('customerpayments', 'site_id')) {
            $payment->setAttribute('site_id', $resolvedSiteId > 0 ? $resolvedSiteId : null);
        }

        $payment->save();

        $referenceNo = 'customerpayments:'.$payment->Seq_No;
        $adminName = $request->attributes->get('adminUser')?->user_name ?: 'admin';
        $paymentAmount = (float) preg_replace('/[^0-9.\-]/', '', (string) $validated['Payment_Amount']);
        $applyToDue = ! $isEdit && $request->boolean('apply_to_due', true);
        $result = null;

        if ($customer) {
            if ($isEdit) {
                CustomerBalance::removePaymentCredit($referenceNo, $adminName);
                CustomerBalance::addPaymentCredit(
                    $customer->user_id,
                    $normalizedWebsite,
                    $paymentAmount,
                    $referenceNo,
                    $adminName,
                    $validated['notes'] ?? null
                );
            } else {
                CustomerBalance::clearPaymentReference($referenceNo, $adminName);
                $result = CustomerBalance::recordIncomingPayment(
                    $customer->user_id,
                    $paymentAmount,
                    $referenceNo,
                    $adminName,
                    $validated['notes'] ?? null,
                    $validated['Transaction_ID'],
                    $applyToDue,
                    $normalizedWebsite
                );
            }
        } else {
            CustomerBalance::clearPaymentReference($referenceNo, $adminName);
        }

        return redirect()->to(url('/v/transaction-history.php'))
            ->with('success', $this->paymentSuccessMessage($isEdit, $result));
    }

    private function paymentSuccessMessage(bool $isEdit, ?array $result): string
    {
        if ($isEdit || ! $result) {
            return $isEdit
                ? 'Transaction updated successfully. Existing invoice allocations were not recalculated.'
                : 'Transaction created successfully.';
        }

        return match ($result['status'] ?? 'none') {
            'overpayment' => 'Transaction created successfully. '.$result['applied_invoices'].' invoice(s) were paid and '.number_format((float) $result['balance_amount'], 2).' was kept as customer balance.',
            'actual' => 'Transaction created successfully. '.$result['applied_invoices'].' invoice(s) were paid from this payment.',
            'credit' => 'Transaction created successfully. The payment was recorded as available customer balance.',
            default => 'Transaction created successfully.',
        };
    }

    private function customerContextForPayments($records)
    {
        if (! Schema::hasTable('customer_credit_ledger')) {
            return collect();
        }

        $references = collect($records)
            ->map(fn ($record) => 'customerpayments:'.(int) $record->Seq_No)
            ->values();

        if ($references->isEmpty()) {
            return collect();
        }

        $userIdsByReference = CustomerCreditLedger::query()
            ->active()
            ->whereIn('reference_no', $references->all())
            ->whereIn('entry_type', ['payment', 'overpayment'])
            ->pluck('user_id', 'reference_no');

        if ($userIdsByReference->isEmpty()) {
            return collect();
        }

        $customers = AdminUser::query()
            ->whereIn('user_id', $userIdsByReference->values()->filter()->all())
            ->get(['user_id', 'user_name', 'user_email', 'first_name', 'last_name'])
            ->keyBy('user_id');

        return $userIdsByReference->map(fn ($userId) => $customers->get((int) $userId));
    }

    public function customerPaymentInventory(Request $request)
    {
        $userSearch = trim((string) $request->query('txtUserID', ''));
        $nameSearch = trim((string) $request->query('txtUserName', ''));
        $balances = CustomerBalance::balances('', $userSearch, $nameSearch);

        $dueAmounts = Billing::query()
            ->selectRaw('user_id, SUM(CAST(amount AS DECIMAL(12,2))) as due_total')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return view('admin.tools.customer-payment-inventory', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'balances' => $balances,
            'dueAmounts' => $dueAmounts,
            'hasCustomerBalanceTable' => Schema::hasTable('customer_credit_ledger'),
        ]);
    }

    public function notifyCustomers(Request $request)
    {
        $selectedWebsite = trim((string) $request->input('website', SiteResolver::forRequest($request)->legacyKey));
        $sortColumn = $this->sortColumn((string) $request->input('column_name'), 'user_id', [
            'user_id',
            'user_name',
            'first_name',
            'last_name',
            'user_email',
        ]);
        $sortDirection = $this->sortDirection((string) $request->input('sort'), 'asc');
        $searchTerm = trim((string) $request->input('search'));

        $customers = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->forWebsite($selectedWebsite)
            ->when($searchTerm !== '', function (Builder $query) use ($searchTerm) {
                $term = '%'.$searchTerm.'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('user_id', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->orderBy($sortColumn, $sortDirection)
            ->get(['user_id', 'user_name', 'first_name', 'last_name', 'user_email', 'website']);
        $hasEmailTemplates = Schema::hasTable('email_templates');
        $templates = collect();
        $selectedTemplate = null;

        if ($hasEmailTemplates) {
            $templates = EmailTemplate::query()
                ->active()
                ->orderBy('template_name')
                ->get(['id', 'template_name', 'subject', 'body']);

            if ($request->filled('template_id')) {
                $selectedTemplate = $templates->firstWhere('id', (int) $request->query('template_id'));
            }
        }

        return view('admin.tools.notify-customers', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customers' => $customers,
            'hasEmailTemplates' => $hasEmailTemplates,
            'searchTerm' => $searchTerm,
            'selectedWebsite' => $selectedWebsite,
            'templates' => $templates,
            'sites' => $this->siteOptions(),
            'selectedTemplate' => $selectedTemplate,
        ]);
    }

    public function sendNotifyCustomers(Request $request)
    {
        $validated = $request->validate([
            'website' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'template_id' => ['nullable', 'integer'],
            'recipients' => ['array'],
            'recipients.*' => ['integer'],
        ], [
            'recipients.*.integer' => 'One or more selected customers are invalid.',
        ], [
            'website' => 'website',
            'subject' => 'subject',
            'body' => 'message',
            'recipients' => 'recipients',
            'recipients.*' => 'customer',
        ]);

        $customerIds = collect($validated['recipients'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $emails = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->forWebsite($validated['website'])
            ->whereIn('user_id', $customerIds)
            ->pluck('user_email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();

        $emails = $emails->unique()->values();

        if ($emails->isEmpty()) {
            return back()->withErrors(['recipients' => 'Please select one or more recipients.'])->withInput();
        }

        $sent = 0;
        $totalRecipients = $emails->count();
        $templateId = (int) ($validated['template_id'] ?? 0);
        $isHtmlTemplate = $templateId > 0
            && Schema::hasTable('email_templates')
            && EmailTemplate::query()->where('id', $templateId)->exists();
        $body = $isHtmlTemplate
            ? (string) $validated['body']
            : nl2br(e((string) $validated['body']));

        foreach ($emails as $email) {
            if (PortalMailer::sendHtml($email, (string) $validated['subject'], $body)) {
                $sent++;
            }
        }

        if ($sent === 0) {
            return back()
                ->withErrors(['recipients' => 'No email could be sent to the selected recipients. Please verify the portal mail settings and try again.'])
                ->withInput();
        }

        return redirect()->to(url('/v/notify-customers.php').'?'.http_build_query(['website' => $validated['website']]))
            ->with('success', $sent === $totalRecipients
                ? 'Email sent to '.$sent.' addresses.'
                : 'Email sent to '.$sent.' of '.$totalRecipients.' selected addresses.'
            );
    }

    public function quickQuotes(Request $request)
    {
        $hasQuickQuotes = Schema::hasTable('qucik_quote_users');
        $page = (string) $request->query('page', 'Quick Quotes List');
        $quickQuotes = collect();
        $paginator = null;

        if ($hasQuickQuotes) {
            $quickQuoteQuery = DB::table('orders')
                ->join('qucik_quote_users', 'orders.order_id', '=', 'qucik_quote_users.customer_oid')
                ->leftJoin('users', 'orders.assign_to', '=', 'users.user_id')
                ->where('orders.order_type', 'qquote')
                ->whereNull('orders.end_date')
                ->when($page === 'Completed Quick Quotes', fn ($query) => $query->whereIn('orders.status', ['done', 'disapproved', 'Disapproved']))
                ->when($page !== 'Completed Quick Quotes', fn ($query) => $query->whereNotIn('orders.status', ['done', 'disapproved', 'Disapproved']))
                ->when($request->filled('txt_orderid'), fn ($query) => $query->where('orders.order_id', 'like', '%'.trim((string) $request->string('txt_orderid')).'%'))
                ->when($request->filled('txt_custname'), fn ($query) => $query->where('qucik_quote_users.customer_name', 'like', '%'.trim((string) $request->string('txt_custname')).'%'))
                ->select([
                    'orders.order_id',
                    'orders.turn_around_time',
                    'orders.submit_date',
                    'orders.completion_date',
                    'orders.status',
                    'orders.order_type',
                    'qucik_quote_users.customer_name',
                    DB::raw('COALESCE(users.user_name, "Unassigned") as assign_name'),
                ])
                ->orderBy($this->sortColumn((string) $request->input('column_name'), 'order_id', ['order_id', 'customer_name', 'turn_around_time', 'submit_date', 'completion_date', 'status', 'assign_name', 'order_type']), $this->sortDirection((string) $request->input('sort'), 'desc'));

            if ($request->query('export') === 'csv') {
                return $this->csvResponse('quick-quotes', ['Order ID', 'Customer', 'Turnaround', 'Submit Date', 'Completion Date', 'Status', 'Assigned To', 'Type'], $quickQuoteQuery->get()->map(
                    fn ($quote) => [
                        $quote->order_id,
                        $quote->customer_name ?: '-',
                        $quote->turn_around_time ?: '-',
                        $quote->submit_date ?: '-',
                        $quote->completion_date ?: '-',
                        $quote->status ?: '-',
                        $quote->assign_name ?: 'Unassigned',
                        $quote->order_type ?: '-',
                    ]
                )->all());
            }

            $paginator = $quickQuoteQuery->paginate(100)->withQueryString();
            $quickQuotes = $paginator->getCollection();
        }

        return view('admin.tools.quick-quotes', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'hasQuickQuotes' => $hasQuickQuotes,
            'pageTitle' => $page,
            'quickQuotes' => $quickQuotes,
            'paginator' => $paginator,
        ]);
    }

    public function deleteQuickQuotes(Request $request)
    {
        $ids = collect($request->input('order_ids', []))
            ->merge($request->filled('order_id') ? [$request->input('order_id')] : [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['order_ids' => 'Please select at least one quick quote to delete.']);
        }

        $userName = $request->attributes->get('adminUser')?->user_name ?: 'admin';
        $timestamp = now()->format('Y-m-d H:i:s');

        Order::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $userName]);
        OrderComment::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $userName]);
        DB::table('team_comments')->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $userName]);
        Attachment::query()->whereIn('order_id', $ids)->update(['end_date' => $timestamp, 'deleted_by' => $userName]);

        return redirect()->to(url('/v/ordersquick.php?'.http_build_query(['page' => $request->input('page', 'Quick Quotes List')])))
            ->with('success', 'Quick quote record(s) deleted successfully.');
    }

    public function blogs(Request $request)
    {
        $blogs = Blog::query()
            ->where(function (Builder $query) {
                $query->whereNull('end_date')->orWhere('end_date', '');
            })
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'id', ['id', 'title', 'decription', 'date']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(50)
            ->withQueryString();

        return view('admin.tools.blogs', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'blogs' => $blogs,
        ]);
    }

    public function deleteBlog(Blog $blog)
    {
        $blog->update(['end_date' => now()->format('Y-m-d H:i:s')]);

        return redirect()->to(url('/v/show-all-blogs.php'))
            ->with('success', 'Blog removed successfully.');
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

    private function csvResponse(string $prefix, array $headers, array $rows)
    {
        $filename = $prefix.'-'.now()->format('Ymd-His').'.csv';
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function dueReportGroupsQuery(Request $request): Builder
    {
        return Billing::query()
            ->selectRaw('MIN(bill_id) as bill_id, user_id, SUM(CAST(amount AS DECIMAL(12,2))) as amount_total, COUNT(*) as total_design')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->when($request->filled('txtInvoiceNumber'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtInvoiceNumber')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('bill_id', 'like', $term)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                            $customerQuery
                                ->where('user_email', 'like', $term)
                                ->orWhere('user_name', 'like', $term)
                                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                        });
                });
            })
            ->when($request->filled('txtUserID'), function (Builder $query) use ($request) {
                $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%');
            })
            ->when($request->filled('txtorderID'), function (Builder $query) use ($request) {
                $query->where('order_id', 'like', '%'.trim((string) $request->string('txtorderID')).'%');
            })
            ->when($request->filled('txt_ordername'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_ordername')).'%';
                $query->whereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_amount'), function (Builder $query) use ($request) {
                $query->where('amount', 'like', '%'.trim((string) $request->string('txt_amount')).'%');
            })
            ->when($request->filled('txtFirstName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtFirstName')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtLastName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtLastName')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->groupBy('user_id')
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'bill_id', ['bill_id', 'user_id', 'total_design', 'amount_total']), $this->sortDirection((string) $request->input('sort'), 'desc'));
    }

    private function dueReportRowsQuery(Request $request): Builder
    {
        $hasOrdersTable = Schema::hasTable('orders');

        $query = Billing::query()
            ->with([
                'customer:user_id,user_name,user_email,first_name,last_name',
            ])
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->when($request->filled('txtInvoiceNumber'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtInvoiceNumber')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('bill_id', 'like', $term)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                            $customerQuery
                                ->where('user_email', 'like', $term)
                                ->orWhere('user_name', 'like', $term)
                                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                        });
                });
            })
            ->when($request->filled('txtUserID'), fn (Builder $query) => $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%'))
            ->when($request->filled('txtorderID'), fn (Builder $query) => $query->where('order_id', 'like', '%'.trim((string) $request->string('txtorderID')).'%'))
            ->when($request->filled('txt_ordername') && $hasOrdersTable, function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_ordername')).'%';
                $query->whereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_amount'), fn (Builder $query) => $query->where('amount', 'like', '%'.trim((string) $request->string('txt_amount')).'%'))
            ->when($request->filled('txtFirstName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtFirstName')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtLastName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtLastName')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->orderBy(
                $this->sortColumn((string) $request->input('column_name'), 'approve_date', ['order_id', 'amount', 'approve_date', 'is_paid', 'website']),
                $this->sortDirection((string) $request->input('sort'), 'desc')
            );

        if ($hasOrdersTable) {
            $query->with(['order:order_id,order_type,design_name,subject,order_num']);
        }

        return $query;
    }

    private function receivedReportGroupsQuery(Request $request): Builder
    {
        return Billing::query()
            ->selectRaw('MIN(bill_id) as bill_id, user_id, SUM(CAST(amount AS DECIMAL(12,2))) as amount_total, COUNT(*) as total_design')
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'yes')
            ->when($request->filled('txtInvoiceNumber'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtInvoiceNumber')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('bill_id', 'like', $term)
                        ->orWhere('transid', 'like', $term)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                            $customerQuery
                                ->where('user_email', 'like', $term)
                                ->orWhere('user_name', 'like', $term)
                                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                        });
                });
            })
            ->when($request->filled('txtUserID'), function (Builder $query) use ($request) {
                $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%');
            })
            ->when($request->filled('txtorderID'), function (Builder $query) use ($request) {
                $query->where('order_id', 'like', '%'.trim((string) $request->string('txtorderID')).'%');
            })
            ->when($request->filled('txt_transid'), function (Builder $query) use ($request) {
                $query->where('transid', 'like', '%'.trim((string) $request->string('txt_transid')).'%');
            })
            ->when($request->filled('txt_ordername'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_ordername')).'%';
                $query->whereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_customername'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_customername')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->groupBy('user_id')
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'bill_id', ['bill_id', 'user_id', 'total_design', 'amount_total']), $this->sortDirection((string) $request->input('sort'), 'desc'));
    }

    private function receivedReportRowsQuery(Request $request): Builder
    {
        $hasOrdersTable = Schema::hasTable('orders');

        $query = Billing::query()
            ->with([
                'customer:user_id,user_name,user_email,first_name,last_name',
            ])
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'yes')
            ->when($request->filled('txtInvoiceNumber'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtInvoiceNumber')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('bill_id', 'like', $term)
                        ->orWhere('transid', 'like', $term)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term) {
                            $customerQuery
                                ->where('user_email', 'like', $term)
                                ->orWhere('user_name', 'like', $term)
                                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                        });
                });
            })
            ->when($request->filled('txtUserID'), fn (Builder $query) => $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%'))
            ->when($request->filled('txtorderID'), fn (Builder $query) => $query->where('order_id', 'like', '%'.trim((string) $request->string('txtorderID')).'%'))
            ->when($request->filled('txt_transid'), fn (Builder $query) => $query->where('transid', 'like', '%'.trim((string) $request->string('txt_transid')).'%'))
            ->when($request->filled('txt_ordername') && $hasOrdersTable, function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_ordername')).'%';
                $query->whereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_customername'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txt_customername')).'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->orderBy(
                $this->sortColumn((string) $request->input('column_name'), 'trandtime', ['order_id', 'amount', 'trandtime', 'transid', 'payment', 'website']),
                $this->sortDirection((string) $request->input('sort'), 'desc')
            );

        if ($hasOrdersTable) {
            $query->with(['order:order_id,order_type,design_name,subject,order_num']);
        }

        return $query;
    }

    private function paidStatusByOrder(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return Billing::query()
            ->active()
            ->whereIn('order_id', $orderIds)
            ->where(function (Builder $query) {
                $query->where('payment', 'yes')
                    ->orWhere('is_paid', 1);
            })
            ->pluck('order_id')
            ->mapWithKeys(fn ($orderId) => [(int) $orderId => true])
            ->all();
    }

    private function customerContextForBillingGroups($rows): array
    {
        $customerIds = collect($rows)->pluck('user_id')->filter()->unique()->all();
        $customers = AdminUser::query()
            ->whereIn('user_id', $customerIds === [] ? [0] : $customerIds)
            ->get()
            ->keyBy('user_id');

        foreach ($customerIds as $customerId) {
            if ($customers->has($customerId)) {
                continue;
            }

            $placeholder = new AdminUser();
            $placeholder->forceFill([
                'user_id' => $customerId,
                'user_name' => 'Deleted customer',
                'first_name' => '',
                'last_name' => '',
                'user_email' => '',
            ]);

            $customers->put($customerId, $placeholder);
        }

        $balancesByCustomer = [];

        if (Schema::hasTable('customer_credit_ledger')) {
            $balancesByCustomer = CustomerCreditLedger::query()
                ->selectRaw('user_id, SUM(amount) as balance_total')
                ->active()
                ->whereIn('user_id', $customerIds === [] ? [0] : $customerIds)
                ->groupBy('user_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->user_id => (float) $row->balance_total])
                ->all();
        }

        return [$customers, $balancesByCustomer];
    }

    private function siteOptions(): array
    {
        if (Schema::hasTable('sites')) {
            return Site::query()
                ->active()
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(['legacy_key', 'name', 'brand_name'])
                ->map(fn (Site $site) => [
                    'legacy_key' => (string) $site->legacy_key,
                    'label' => (string) ($site->brand_name ?: $site->name ?: $site->legacy_key),
                ])
                ->values()
                ->all();
        }

        $site = SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        return [[
            'legacy_key' => $site->legacyKey,
            'label' => $site->brandName,
        ]];
    }

}
