<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Billing;
use App\Models\Order;
use App\Models\QuoteNegotiation;
use Illuminate\Support\Facades\Schema;

class AdminNavigation
{
    public static function counts(): array
    {
        return [
            'new_orders' => Order::query()->active()->orderManagement()->where('status', 'Underprocess')
                ->whereNotIn('order_id', Billing::query()->select('order_id')->where('payment', 'yes'))
                ->unassigned()
                ->count(),
            'all_orders' => Order::query()->active()
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
                })
                ->count(),
            'disapproved_orders' => Order::query()->active()->orderManagement()->where('status', 'disapproved')->count(),
            'designer_orders' => Order::query()->active()->orderManagement()->whereIn('status', ['Underprocess', 'disapprove'])->assigned()->count(),
            'designer_completed_orders' => Order::query()->active()->orderManagement()->where('status', 'Ready')->assigned()->count(),
            'approval_waiting_orders' => Order::query()->active()->orderManagement()->where('status', 'done')->count(),
            'approved_orders' => Order::query()->active()->orderManagement()->where('status', 'approved')
                ->whereNotIn('order_id', Billing::query()->select('order_id')->where(function ($query) {
                    $query->where('payment', 'yes')
                        ->orWhere('is_paid', 1);
                }))
                ->count(),
            'due_payments' => Billing::query()->active()->where('approved', 'yes')->where('payment', 'no')->whereHas('order')->whereRaw('CAST(amount AS DECIMAL(12,2)) > 0')->count(),
            'received_payments' => Billing::query()->active()->where('approved', 'yes')->where('payment', 'yes')->whereHas('order')->count(),
            'new_quotes' => Order::query()->active()->quoteManagement()->where('status', 'Underprocess')->unassigned()->count(),
            'assigned_quotes' => Order::query()->active()->quoteManagement()->where('status', 'Underprocess')->assigned()->count(),
            'designer_completed_quotes' => Order::query()->active()->quoteManagement()->where('status', 'Ready')->count(),
            'completed_quotes' => Order::query()->active()->quoteManagement()->whereIn('status', ['done', 'disapprove', 'disapproved'])->count(),
            'quote_negotiations' => Schema::hasTable('quote_negotiations')
                ? Order::query()
                    ->active()
                    ->quoteManagement()
                    ->whereIn('order_id', QuoteNegotiation::query()
                        ->whereIn('status', ['pending_admin_review', 'customer_replied'])
                        ->select('order_id')
                    )
                    ->distinct('order_id')
                    ->count('order_id')
                : 0,
            'customers' => AdminUser::query()->customers()->active()->where('is_active', 1)->count(),
            'pending_customer_approvals' => count(CustomerApprovalQueue::userIds()),
            'blocked_customers' => AdminUser::query()->blockedCustomerAccounts()->count(),
            'security_alerts' => SecurityAlertSummary::actionableCount(),
            'teams' => AdminUser::query()->teamPortalUsers()->active()->where('is_active', 1)->count(),
        ];
    }
}
