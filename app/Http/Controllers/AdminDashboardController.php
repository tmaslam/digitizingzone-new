<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Support\AdminNavigation;
use App\Support\CustomerBalance;
use App\Support\SecurityAlertSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $navCounts = AdminNavigation::counts();
        $hasCreditLedger = Schema::hasTable('customer_credit_ledger');
        $customerCreditInventory = $hasCreditLedger ? CustomerBalance::balances() : collect();

        $financialSnapshot = [
            'due_invoices' => Billing::query()->active()->where('approved', 'yes')->where('payment', 'no')->count(),
            'due_amount' => (float) Billing::query()->active()->where('approved', 'yes')->where('payment', 'no')->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))')),
            'received_invoices' => Billing::query()->active()->where('approved', 'yes')->where('payment', 'yes')->count(),
            'received_amount' => (float) Billing::query()->active()->where('approved', 'yes')->where('payment', 'yes')->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))')),
            'customer_balance' => $hasCreditLedger
                ? (float) $customerCreditInventory->sum(fn ($row) => (float) $row->balance_total)
                : null,
            'customers_with_credit' => $hasCreditLedger ? $customerCreditInventory->count() : 0,
        ];

        $operationsSnapshot = [
            'active_customers' => $navCounts['customers'],
            'blocked_customers' => $navCounts['blocked_customers'],
            'team_accounts' => AdminUser::query()->teams()->active()->where('is_active', 1)->count(),
            'supervisors' => AdminUser::query()->supervisors()->active()->where('is_active', 1)->count(),
            'all_open_work' => $navCounts['all_orders'],
        ];

        $workflowFocus = [
            'review_ready' => ($navCounts['designer_completed_orders'] ?? 0) + ($navCounts['designer_completed_quotes'] ?? 0),
            'approval_waiting' => $navCounts['approval_waiting_orders'] ?? 0,
            'new_work' => ($navCounts['new_orders'] ?? 0) + ($navCounts['new_quotes'] ?? 0),
            'assigned_work' => ($navCounts['designer_orders'] ?? 0) + ($navCounts['assigned_quotes'] ?? 0),
        ];

        $securityWatch = SecurityAlertSummary::summary();

        return view('admin.dashboard', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => $navCounts,
            'financialSnapshot' => $financialSnapshot,
            'operationsSnapshot' => $operationsSnapshot,
            'workflowFocus' => $workflowFocus,
            'securityWatch' => $securityWatch,
            'hasCreditLedger' => $hasCreditLedger,
        ]);
    }
}
