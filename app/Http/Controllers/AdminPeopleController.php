<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminNavigation;
use App\Support\CustomerApprovalQueue;
use App\Support\SignupOfferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminPeopleController extends Controller
{
    public function customers(Request $request)
    {
        $customers = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->when($request->filled('txtUserID'), function (Builder $query) use ($request) {
                $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%');
            })
            ->when($request->filled('txtUserName'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txtUserName')->trim().'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('user_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtEmail'), function (Builder $query) use ($request) {
                $query->where('user_email', 'like', '%'.$request->string('txtEmail')->trim().'%');
            })
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'user_id', ['user_id', 'user_name', 'user_email', 'user_country', 'userip_addrs', 'date_added']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(30)
            ->withQueryString();

        return view('admin.people.customers', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customers' => $customers,
        ]);
    }

    public function pendingApprovals(Request $request)
    {
        $approvalState = trim((string) $request->input('approval_state'));
        $queueUserIds = CustomerApprovalQueue::userIds(null, $approvalState !== '' ? $approvalState : null);
        $claimStatuses = CustomerApprovalQueue::claimStatusMap($queueUserIds);

        $customers = AdminUser::query()
            ->customers()
            ->active()
            ->whereIn('user_id', $queueUserIds === [] ? [0] : $queueUserIds)
            ->when($request->filled('txtUserID'), function (Builder $query) use ($request) {
                $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%');
            })
            ->when($request->filled('txtUserName'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('txtUserName')->trim().'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('user_name', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('txtEmail'), function (Builder $query) use ($request) {
                $query->where('user_email', 'like', '%'.$request->string('txtEmail')->trim().'%');
            })
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'user_id', ['user_id', 'user_name', 'user_email', 'website', 'user_country', 'date_added']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(30)
            ->withQueryString();

        $customers->getCollection()->transform(function (AdminUser $customer) use ($claimStatuses) {
            $approvalState = CustomerApprovalQueue::stateForCustomer(
                $customer,
                $claimStatuses[(int) $customer->user_id] ?? null
            );

            $customer->approval_state = $approvalState;
            $customer->approval_state_label = CustomerApprovalQueue::stateLabel($approvalState);
            $customer->signup_path_label = trim((string) $customer->user_term) === 'ip'
                ? 'Welcome Payment'
                : 'Admin Approval';

            return $customer;
        });

        return view('admin.people.pending-approvals', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customers' => $customers,
            'approvalState' => $approvalState,
            'approvalStateOptions' => CustomerApprovalQueue::stateFilterOptions(),
        ]);
    }

    public function verifyCustomerEmail(Request $request, AdminUser $customer)
    {
        abort_unless((int) $customer->usre_type_id === AdminUser::TYPE_CUSTOMER, 404);

        $approvalState = CustomerApprovalQueue::stateForCustomer(
            $customer,
            CustomerApprovalQueue::claimStatusMap([(int) $customer->user_id], trim((string) $customer->website))[(int) $customer->user_id] ?? null
        );

        if ($approvalState !== CustomerApprovalQueue::STATE_PENDING_VERIFICATION) {
            return redirect()->to($this->withQuery('/v/customer-approvals.php', $request->except('_token')))
                ->with('error', 'This customer account is not waiting for email verification.');
        }

        $this->clearActivationToken($customer);

        if (trim(strtolower((string) ($customer->user_term ?? ''))) === 'dc') {
            $customer->update([
                'is_active' => 0,
                'exist_customer' => '0',
            ]);

            $message = 'Customer email has been marked verified and the account is now waiting for admin approval.';
        } else {
            if (! SignupOfferService::adminVerifyPendingClaim($customer)) {
                return redirect()->to($this->withQuery('/v/customer-approvals.php', $request->except('_token')))
                    ->with('error', 'No pending verification record was found for this customer account.');
            }

            $customer->update([
                'is_active' => 0,
                'exist_customer' => '0',
            ]);

            $message = 'Customer email has been marked verified. The account is now waiting for the customer welcome payment.';
        }

        return redirect()->to($this->withQuery('/v/customer-approvals.php', $request->except('_token')))
            ->with('success', $message);
    }

    public function approveCustomer(Request $request, AdminUser $customer)
    {
        abort_unless((int) $customer->usre_type_id === AdminUser::TYPE_CUSTOMER, 404);

        $adminName = $request->attributes->get('adminUser')?->user_name ?: 'admin';
        $isManualApprovalSignup = trim(strtolower((string) ($customer->user_term ?? ''))) === 'dc';

        $customer->update([
            'is_active' => 1,
            'exist_customer' => '1',
        ]);

        $welcomePaymentPending = $isManualApprovalSignup
            ? false
            : SignupOfferService::adminApprovePendingPayment($customer, $adminName);

        if ($isManualApprovalSignup) {
            SignupOfferService::completeManualApprovalClaim($customer, $adminName);
        }

        return redirect()->to($this->withQuery('/v/customer-approvals.php', $request->except('_token')))
            ->with('success', $welcomePaymentPending
                ? 'Customer has been approved successfully. The welcome payment offer still remains pending until the $1 payment is completed.'
                : 'Customer has been approved successfully.');
    }

    public function blockCustomer(Request $request, AdminUser $customer)
    {
        abort_unless((int) $customer->usre_type_id === 1, 404);

        $returnTo = trim((string) $request->input('return_to', ''));

        $updateData = ['is_active' => 0];

        if ($returnTo === 'customer-approvals') {
            // Pre-approval block: set user_term='blocked' so the account
            // disappears from all approval-queue queries (which check for
            // user_term='dc' or user_term='ip') but remains visible in the
            // Inactive Customers report via the widened scopeBlockedCustomerAccounts.
            $updateData['user_term'] = 'blocked';

            // Cancel any pending promotion claim so this user_id is no longer
            // returned by verifiedPendingPaymentUserIds() queue lookups.
            if (Schema::hasTable('site_promotion_claims')) {
                DB::table('site_promotion_claims')
                    ->where('user_id', $customer->user_id)
                    ->whereIn('status', [
                        SignupOfferService::STATUS_PENDING_VERIFICATION,
                        SignupOfferService::STATUS_PENDING_PAYMENT,
                    ])
                    ->update(['status' => 'rejected', 'updated_at' => now()->format('Y-m-d H:i:s')]);
            }
        }

        $customer->update($updateData);

        $redirectBase = $returnTo === 'customer-approvals'
            ? url('/v/customer-approvals.php')
            : url('/v/customer_list.php');

        return redirect()->to($redirectBase.'?'.http_build_query($request->except(['_token', 'return_to'])))
            ->with('success', 'Customer has been blocked successfully.');
    }

    public function deleteCustomer(Request $request, AdminUser $customer)
    {
        abort_unless((int) $customer->usre_type_id === 1, 404);

        $adminUser = $request->attributes->get('adminUser');

        $customer->update([
            'end_date' => now()->format('Y-m-d H:i:s'),
            'deleted_by' => $adminUser?->user_name ?: 'admin',
        ]);

        return redirect()->to(url('/v/customer_list.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Customer has been deleted successfully.');
    }

    public function teams(Request $request)
    {
        $statusFilter = trim((string) $request->input('status', 'active'));

        $teams = AdminUser::query()
            ->teamPortalUsers()
            ->when($statusFilter === 'active', fn (Builder $q) => $q->where('is_active', 1))
            ->when($statusFilter === 'locked', fn (Builder $q) => $q->where('is_active', 0))
            ->when($request->filled('txtUserID'), function (Builder $query) use ($request) {
                $query->where('user_id', 'like', '%'.trim((string) $request->string('txtUserID')).'%');
            })
            ->when($request->filled('txtUserName'), function (Builder $query) use ($request) {
                $term = '%'.trim((string) $request->string('txtUserName')).'%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('user_name', 'like', $term)
                        ->orWhere('user_email', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$term]);
                });
            })
            ->when($request->filled('account_type'), function (Builder $query) use ($request) {
                $type = trim((string) $request->string('account_type'));

                if ($type === 'team') {
                    $query->where('usre_type_id', AdminUser::TYPE_TEAM);
                } elseif ($type === 'supervisor') {
                    $query->where('usre_type_id', AdminUser::TYPE_SUPERVISOR);
                }
            })
            ->orderBy($this->sortColumn((string) $request->input('column_name'), 'user_id', ['user_id', 'user_name', 'date_added']), $this->sortDirection((string) $request->input('sort'), 'desc'))
            ->paginate(50)
            ->withQueryString();

        return view('admin.people.teams', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'teams' => $teams,
        ]);
    }

    public function disableTeam(Request $request, AdminUser $team)
    {
        abort_unless(in_array((int) $team->usre_type_id, [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true), 404);

        $team->update(['is_active' => 0]);

        return redirect()->to(url('/v/show-all-teams.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Team account has been removed successfully.');
    }

    public function unlockTeam(Request $request, AdminUser $team)
    {
        abort_unless(in_array((int) $team->usre_type_id, [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true), 404);

        $team->update(['is_active' => 1]);

        return redirect()->to(url('/v/show-all-teams.php').'?'.http_build_query($request->except('_token')))
            ->with('success', 'Team account has been unlocked and is now active.');
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

    private function withQuery(string $path, array $query): string
    {
        $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

        return $query === [] ? url($path) : url($path).'?'.http_build_query($query);
    }

    private function clearActivationToken(AdminUser $customer): void
    {
        if (! Schema::hasTable('customer_activation_tokens')) {
            return;
        }

        DB::table('customer_activation_tokens')
            ->where('customer_user_id', $customer->user_id)
            ->when(trim((string) $customer->website) !== '', function ($query) use ($customer) {
                $query->where('site_legacy_key', trim((string) $customer->website));
            })
            ->delete();
    }
}
