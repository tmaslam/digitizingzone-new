<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\AdminUser;
use App\Models\CustomerCreditLedger;
use App\Models\Order;
use App\Support\AdminNavigation;
use App\Support\ApprovedBillingSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBillingController extends Controller
{
    public function due(Request $request)
    {
        ApprovedBillingSync::syncMissingApprovedBillings();

        $billingsQuery = $this->buildDueQuery($request);

        if ($request->query('export') === 'csv') {
            $rows = $billingsQuery->get();
            $paidLookup = $this->paidStatusByOrder($rows->pluck('order_id')->filter()->all());

            return $this->csvResponse('due-payment', ['Order ID', 'Customer Name', 'Amount', 'Approve Date', 'Paid / Unpaid', 'Website'], $rows->map(
                fn (Billing $billing) => [
                    $billing->order_id,
                    $billing->customer?->display_name ?: '-',
                    number_format((float) $billing->amount, 2, '.', ''),
                    $billing->approve_date ?: '-',
                    ($paidLookup[$billing->order_id] ?? false) ? 'Paid' : 'Unpaid',
                    $billing->website ?: '-',
                ]
            )->all());
        }

        $billings = $billingsQuery
            ->paginate(50)
            ->withQueryString();

        $paidLookup = $this->paidStatusByOrder($billings->getCollection()->pluck('order_id')->filter()->all());
        $billings->getCollection()->transform(function (Billing $billing) use ($paidLookup) {
            $billing->setAttribute('payment_status_flag', (bool) ($paidLookup[$billing->order_id] ?? false));

            return $billing;
        });

        return view('admin.billing.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'title' => 'Due Payment',
            'subtitle' => 'Review approved unpaid order billing and open the related order directly.',
            'mode' => 'due',
            'backContext' => 'all-payment-due',
            'filterAction' => url('/v/all-payment-due.php'),
            'billings' => $billings,
            'defaultColumn' => 'approve_date',
            'defaultDirection' => 'desc',
        ]);
    }

    public function received(Request $request)
    {
        $billingsQuery = $this->buildReceivedQuery($request);

        if ($request->query('export') === 'csv') {
            $rows = $billingsQuery->get();

            return $this->csvResponse('received-payment', ['Order ID', 'Customer Name', 'Amount', 'Transaction Date', 'Transaction ID', 'Payment Status', 'Website'], $rows->map(
                fn (Billing $billing) => [
                    $billing->order_id,
                    $billing->customer?->display_name ?: '-',
                    number_format((float) $billing->amount, 2, '.', ''),
                    $billing->trandtime ?: '-',
                    $billing->transid ?: '-',
                    (string) ($billing->payment ?: '-'),
                    $billing->website ?: '-',
                ]
            )->all());
        }

        $billings = $billingsQuery
            ->paginate(50)
            ->withQueryString();

        return view('admin.billing.index', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'title' => 'Received Payment',
            'subtitle' => 'Review paid order billing and open the related order directly.',
            'mode' => 'received',
            'backContext' => 'payment-recieved',
            'filterAction' => url('/v/payment-recieved.php'),
            'billings' => $billings,
            'defaultColumn' => 'trandtime',
            'defaultDirection' => 'desc',
        ]);
    }

    public function destroy(Request $request, Billing $billing)
    {
        $adminUser = $request->attributes->get('adminUser');

        $billing->update([
            'end_date' => now()->format('Y-m-d H:i:s'),
            'deleted_by' => $adminUser?->user_name ?: 'admin',
        ]);

        $returnTo = (string) $request->input('return_to', 'due');
        $redirectUrl = match ($returnTo) {
            'received-report' => url('/v/payment-recieved-report.php'),
            'received' => url('/v/payment-recieved.php'),
            'due-report' => url('/v/payment-due-report.php'),
            default => url('/v/all-payment-due.php'),
        };

        return redirect()->to($redirectUrl.'?'.http_build_query($request->except(['_token', 'return_to'])))
            ->with('success', 'Billing record removed successfully.');
    }

    private function buildDueQuery(Request $request): Builder
    {
        $hasOrdersTable = Schema::hasTable('orders');
        $sortColumn = $this->sortColumn((string) $request->input('column_name'), 'approve_date', ['order_id', 'design_name', 'customer_name', 'amount', 'approve_date', 'is_paid', 'website']);
        $sortDirection = $this->sortDirection((string) $request->input('sort'), 'desc');

        $query = Billing::query()
            ->with([
                'customer:user_id,user_name,user_email,first_name,last_name',
            ])
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->when($hasOrdersTable, fn (Builder $query) => $query->whereHas('order'))
            ->when($request->filled('txtorderID'), function (Builder $query) use ($request) {
                $query->where('order_id', 'like', '%'.(string) $request->string('txtorderID')->trim().'%');
            })
            ->when($request->filled('txt_ordername') && $hasOrdersTable, function (Builder $query) use ($request) {
                $term = '%'.$request->string('txt_ordername')->trim().'%';
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
                $term = '%'.$request->string('txtFirstName')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtLastName'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txtLastName')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('last_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            });

        if ($hasOrdersTable) {
            $query->with(['order:order_id,order_type,design_name,subject,order_num']);
        }

        return $this->applySort($query, $sortColumn, $sortDirection);
    }

    private function buildReceivedQuery(Request $request): Builder
    {
        $hasOrdersTable = Schema::hasTable('orders');
        $sortColumn = $this->sortColumn((string) $request->input('column_name'), 'trandtime', ['order_id', 'design_name', 'customer_name', 'amount', 'trandtime', 'transid', 'payment', 'website']);
        $sortDirection = $this->sortDirection((string) $request->input('sort'), 'desc');

        $query = Billing::query()
            ->with([
                'customer:user_id,user_name,user_email,first_name,last_name',
            ])
            ->active()
            ->where('approved', 'yes')
            ->where('payment', 'yes')
            ->when($hasOrdersTable, fn (Builder $query) => $query->whereHas('order'))
            ->when($request->filled('txtorderID'), function (Builder $query) use ($request) {
                $query->where('order_id', 'like', '%'.(string) $request->string('txtorderID')->trim().'%');
            })
            ->when($request->filled('txtFirstName'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txtFirstName')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtLastName'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txtLastName')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('last_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txt_transid'), function (Builder $query) use ($request) {
                $query->where('transid', 'like', '%'.trim((string) $request->string('txt_transid')).'%');
            })
            ->when($request->filled('txt_ordername') && $hasOrdersTable, function (Builder $query) use ($request) {
                $term = '%'.$request->string('txt_ordername')->trim().'%';
                $query->whereHas('order', function (Builder $orderQuery) use ($term) {
                    $orderQuery
                        ->where('design_name', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('order_num', 'like', $term);
                });
            })
            ->when($request->filled('txt_customername'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txt_customername')->trim().'%';
                $query->whereHas('customer', function (Builder $customerQuery) use ($term) {
                    $customerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            });

        if ($hasOrdersTable) {
            $query->with(['order:order_id,order_type,design_name,subject,order_num']);
        }

        return $this->applySort($query, $sortColumn, $sortDirection);
    }

    private function applySort(Builder $query, string $sortColumn, string $sortDirection): Builder
    {
        return match ($sortColumn) {
            'design_name' => $query
                ->orderBy(
                    Order::query()
                        ->select('design_name')
                        ->whereColumn('orders.order_id', 'billing.order_id')
                        ->limit(1),
                    $sortDirection
                )
                ->orderBy('bill_id', 'desc'),
            'customer_name' => $query
                ->orderBy(
                    AdminUser::query()
                        ->selectRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))")
                        ->whereColumn('users.user_id', 'billing.user_id')
                        ->limit(1),
                    $sortDirection
                )
                ->orderBy(
                    AdminUser::query()
                        ->select('user_name')
                        ->whereColumn('users.user_id', 'billing.user_id')
                        ->limit(1),
                    $sortDirection
                )
                ->orderBy('bill_id', 'desc'),
            default => $query->orderBy($sortColumn, $sortDirection),
        };
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

    private function sortColumn(string $column, string $default, array $allowed = ['order_id', 'amount', 'approve_date', 'is_paid']): string
    {
        return in_array($column, $allowed, true) ? $column : $default;
    }

    private function sortDirection(string $direction, string $default = 'desc'): string
    {
        $direction = strtolower($direction);

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
    }

    private function csvResponse(string $prefix, array $headers, array $rows): StreamedResponse
    {
        $filename = $prefix.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
