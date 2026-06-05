<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\SupervisorTeamMember;
use App\Support\AdminNavigation;
use App\Support\AdminOrderQueues;
use App\Support\AdminReferenceData;
use App\Support\CustomerPricing;
use App\Support\DownstreamSharing;
use App\Support\PasswordManager;
use App\Support\PortalMailer;
use App\Support\SecurityAudit;
use App\Support\SignupOfferService;
use App\Support\TrustedTwoFactorDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminProfileController extends Controller
{
    public function adminPasswordForm(Request $request)
    {
        $adminUser = $request->attributes->get('adminUser');

        abort_unless($adminUser && (int) $adminUser->usre_type_id === AdminUser::TYPE_ADMIN, 403);

        return view('admin.people.admin-password', [
            'adminUser' => $adminUser,
            'navCounts' => AdminNavigation::counts(),
        ]);
    }

    public function toggleTwoFactor(Request $request)
    {
        $adminUser = $request->attributes->get('adminUser');

        abort_unless($adminUser && (int) $adminUser->usre_type_id === AdminUser::TYPE_ADMIN, 403);

        $action = trim((string) $request->input('action', ''));

        if (! in_array($action, ['enable', 'disable'], true)) {
            return back()->withErrors(['2fa' => 'Invalid request.']);
        }

        $enable = $action === 'enable';

        $adminUser->update(['two_factor_enabled' => $enable ? 1 : 0]);

        if (! $enable) {
            TrustedTwoFactorDevice::revokeForUser('admin', (int) $adminUser->user_id);
            TrustedTwoFactorDevice::revokeCurrent($request, 'admin');
        }

        $message = $enable
            ? 'Two-factor authentication has been enabled. You will now receive a verification code by email each time you sign in.'
            : 'Two-factor authentication has been disabled for your account.';

        return redirect()->to(url('/v/change-password.php'))->with('success', $message);
    }

    public function adminPasswordSave(Request $request)
    {
        $adminUser = $request->attributes->get('adminUser');

        abort_unless($adminUser && (int) $adminUser->usre_type_id === AdminUser::TYPE_ADMIN, 403);

        $validated = $request->validate([
            'txtPassword' => ['required', 'string', 'min:6', 'max:100'],
            'txtCPassword' => ['required', 'same:txtPassword'],
        ], [
            'txtCPassword.same' => 'The confirm password must match the password.',
        ], [
            'txtPassword' => 'password',
            'txtCPassword' => 'confirm password',
        ]);

        $adminUser->forceFill(PasswordManager::payload((string) $validated['txtPassword']))->save();
        TrustedTwoFactorDevice::revokeForUser('admin', (int) $adminUser->user_id);

        return redirect()->to(url('/v/change-password.php'))
            ->with('success', 'Admin password updated successfully.');
    }

    public function customerShow(Request $request)
    {
        $customer = AdminUser::query()->findOrFail((int) $request->query('uid'));

        return view('admin.people.customer-show', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customer' => $customer,
        ]);
    }

    public function resetCustomerPassword(Request $request, AdminUser $customer)
    {
        // Guard 1 — middleware already enforces admin.auth (TYPE_ADMIN only),
        // but be explicit: the acting user must be a verified admin.
        $actingAdmin = $request->attributes->get('adminUser');
        abort_unless($actingAdmin instanceof AdminUser && (int) $actingAdmin->usre_type_id === AdminUser::TYPE_ADMIN, 403);

        // Guard 2 — target must be a customer account, never an admin/team/supervisor.
        abort_unless((int) $customer->usre_type_id === AdminUser::TYPE_CUSTOMER, 403);

        // Guard 3 — an admin cannot use this endpoint on their own account.
        abort_if((int) $actingAdmin->user_id === (int) $customer->user_id, 403);

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6', 'max:100'],
        ], [], [
            'new_password' => 'new password',
        ]);

        $customer->forceFill(PasswordManager::payload((string) $validated['new_password']))->save();

        // Notify the customer so they are aware their password was changed by support.
        $customerEmail = trim((string) $customer->user_email);
        if ($customerEmail !== '') {
            $name = trim((string) ($customer->display_name ?: $customer->user_name));
            $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#19232e;padding:32px;">'
                .'<p>Hi '.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').',</p>'
                .'<p>Your account password was recently reset by a support representative. You can sign in now using your new password.</p>'
                .'<p>If you did not request this change or do not recognise it, please contact support immediately.</p>'
                .'</body></html>';
            PortalMailer::sendHtml($customerEmail, 'Your password has been reset', $body);
        }

        // Audit trail — every admin-initiated customer password reset is logged.
        SecurityAudit::record(
            $request,
            'admin.customer_password_reset',
            'Admin reset customer password.',
            [
                'admin_user_id' => $actingAdmin->user_id,
                'admin_user_name' => $actingAdmin->user_name,
                'target_customer_id' => $customer->user_id,
                'target_customer_name' => $customer->user_name,
            ],
            'info'
        );

        $source = trim((string) $request->input('source'));
        $redirectUrl = url('/v/customer-detail.php?uid='.$customer->user_id.($source ? '&source='.rawurlencode($source) : ''));

        return redirect()->to($redirectUrl)->with('success', 'Customer password has been reset successfully.');
    }

    public function customerEdit(Request $request)
    {
        $customer = AdminUser::query()->findOrFail((int) $request->query('uid'));

        return view('admin.people.customer-edit', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customer' => $customer,
            'companyTypes' => AdminReferenceData::companyTypes(),
            'countries' => AdminReferenceData::countries(),
        ]);
    }

    public function customerUpdate(Request $request)
    {
        $customer = AdminUser::query()->findOrFail((int) $request->input('uid'));
        $source = trim((string) $request->input('source'));

        $validated = $request->validate([
            'user_name' => ['nullable', 'string', 'max:150'],
            'txtPassword' => ['nullable', 'string', 'min:6', 'max:100'],
            'txtFirstName' => ['nullable', 'string', 'max:150'],
            'txtLastName' => ['nullable', 'string', 'max:150'],
            'txtCompany' => ['nullable', 'string', 'max:150'],
            'selCompanyTypes' => ['nullable', 'string', 'max:150'],
            'txtEmail' => ['nullable', 'string', 'max:150'],
            'txtCompanyAddress' => ['nullable', 'string', 'max:255'],
            'txtZipCode' => ['nullable', 'string', 'max:20'],
            'txtCity' => ['nullable', 'string', 'max:150'],
            'selCountry' => ['nullable', 'string', 'max:150'],
            'txtTelephone' => ['nullable', 'string', 'max:150'],
            'txtFax' => ['nullable', 'string', 'max:150'],
            'txtContactPerson' => ['nullable', 'string', 'max:150'],
            'txtSignupIp' => ['nullable', 'ip'],
            'is_active' => ['required', 'in:0,1'],
            'user_term' => ['nullable', 'string', 'max:20'],
            'normal_fee' => ['nullable', 'string', 'max:20'],
            'middle_fee' => ['nullable', 'string', 'max:20'],
            'urgent_fee' => ['nullable', 'string', 'max:20'],
            'super_fee' => ['nullable', 'string', 'max:20'],
            'payment_terms' => ['nullable', 'string', 'max:5'],
            'customer_pending_order_limit' => ['nullable', 'string', 'max:11'],
            'customer_approval_limit' => ['nullable', 'numeric', 'min:0'],
            'single_approval_limit' => ['nullable', 'numeric', 'min:0'],
            'topup' => ['nullable', 'numeric', 'min:0'],
            'max_num_stiches' => ['nullable', 'string', 'max:11'],
        ], [], [
            'user_name' => 'user name',
            'txtPassword' => 'password',
            'txtFirstName' => 'first name',
            'txtLastName' => 'last name',
            'txtCompany' => 'company',
            'selCompanyTypes' => 'company type',
            'txtEmail' => 'email address',
            'txtCompanyAddress' => 'company address',
            'txtZipCode' => 'zip code',
            'txtCity' => 'city',
            'selCountry' => 'country',
            'txtTelephone' => 'telephone',
            'txtFax' => 'fax',
            'txtContactPerson' => 'contact person',
            'txtSignupIp' => 'signup IP address',
            'is_active' => 'status',
            'user_term' => 'account type',
            'normal_fee' => 'normal fee',
            'middle_fee' => 'express fee',
            'urgent_fee' => 'urgent fee',
            'super_fee' => 'super rush fee',
            'payment_terms' => 'payment terms',
            'customer_pending_order_limit' => 'pending order limit',
            'customer_approval_limit' => 'approval limit',
            'single_approval_limit' => 'single approval limit',
            'topup' => 'top up',
            'max_num_stiches' => 'maximum stitches',
        ]);

        $updates = [
            'user_name' => $validated['user_name'] ?? '',
            'first_name' => $validated['txtFirstName'] ?? '',
            'last_name' => $validated['txtLastName'] ?? '',
            'company' => $validated['txtCompany'] ?? '',
            'company_type' => $validated['selCompanyTypes'] ?? '',
            'user_email' => $validated['txtEmail'] ?? '',
            'company_address' => $validated['txtCompanyAddress'] ?? '',
            'zip_code' => $validated['txtZipCode'] ?? '',
            'user_city' => $validated['txtCity'] ?? '',
            'user_country' => $validated['selCountry'] ?? '',
            'user_phone' => $validated['txtTelephone'] ?? '',
            'user_fax' => $validated['txtFax'] ?? '',
            'contact_person' => $validated['txtContactPerson'] ?? '',
            'userip_addrs' => trim((string) ($validated['txtSignupIp'] ?? '')),
            'is_active' => (int) $validated['is_active'],
            'user_term' => trim((string) ($validated['user_term'] ?? '')),
            'customer_pending_order_limit' => $validated['customer_pending_order_limit'] ?? '',
            'customer_approval_limit' => $request->filled('customer_approval_limit') ? number_format((float) $validated['customer_approval_limit'], 2, '.', '') : '',
            'single_approval_limit' => $request->filled('single_approval_limit') ? number_format((float) $validated['single_approval_limit'], 2, '.', '') : '',
            'payment_terms' => $validated['payment_terms'] ?? '',
            'usre_type_id' => (int) $customer->usre_type_id,
            'max_num_stiches' => $validated['max_num_stiches'] ?? '',
            'topup' => $request->filled('topup') ? number_format((float) $validated['topup'], 2, '.', '') : '',
        ];

        $updates = array_merge($updates, CustomerPricing::customPricingPayload($validated));

        if ($request->filled('txtPassword')) {
            $updates = array_merge($updates, PasswordManager::payload((string) $validated['txtPassword']));
        }

        $customer->update($updates);

        if ((int) $updates['is_active'] === 1) {
            SignupOfferService::adminFinalizeCustomerActivation(
                $customer->fresh(),
                (string) ($request->attributes->get('adminUser')?->user_name ?: 'admin')
            );

            $customer->refresh();

            if ((string) ($customer->exist_customer ?? '') !== '1') {
                $customer->update(['exist_customer' => '1']);
            }
        }

        $redirectUrl = $source === 'customer-approvals'
            ? url('/v/customer-approvals.php')
            : url('/v/customer_list.php');

        return redirect()->to($redirectUrl)
            ->with('success', 'Customer information updated successfully.');
    }

    public function teamForm(Request $request)
    {
        $team = $request->filled('user_id')
            ? AdminUser::query()->findOrFail((int) $request->query('user_id'))
            : new AdminUser(['usre_type_id' => 2, 'is_active' => 1]);
        abort_unless(in_array((int) ($team->usre_type_id ?: AdminUser::TYPE_TEAM), [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true), 404);
        $accountType = (int) ($team->usre_type_id ?: AdminUser::TYPE_TEAM) === AdminUser::TYPE_SUPERVISOR ? 'supervisor' : 'team';

        return view('admin.people.team-form', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'team' => $team,
            'mode' => $team->exists ? 'edit' : 'create',
            'accountType' => $accountType,
        ]);
    }

    public function teamSave(Request $request)
    {
        $team = $request->filled('user_id')
            ? AdminUser::query()->findOrFail((int) $request->input('user_id'))
            : new AdminUser();
        if ($team->exists) {
            abort_unless(in_array((int) $team->usre_type_id, [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true), 404);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'account_type' => ['required', 'in:team,supervisor'],
            'txtTeamName' => ['required', 'string', 'max:150'],
            'txtPassword' => [$team->exists ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
            'txtCPassword' => [$team->exists ? 'nullable' : 'required', 'same:txtPassword'],
            'txtEmail' => ['required', 'email', 'max:150'],
        ], [
            'txtCPassword.same' => 'The confirm password must match the password.',
        ], [
            'user_id' => 'user',
            'account_type' => 'account type',
            'txtTeamName' => 'user name',
            'txtPassword' => 'password',
            'txtCPassword' => 'confirm password',
            'txtEmail' => 'email address',
        ]);

        $targetType = match ($validated['account_type']) {
            'supervisor' => AdminUser::TYPE_SUPERVISOR,
            default => AdminUser::TYPE_TEAM,
        };

        $duplicateQuery = AdminUser::query()
            ->where('user_name', $validated['txtTeamName']);

        if (in_array($targetType, [AdminUser::TYPE_TEAM, AdminUser::TYPE_SUPERVISOR], true)) {
            $duplicateQuery->teamPortalUsers();
        } else {
            $duplicateQuery->where('usre_type_id', $targetType);
        }

        if ($team->exists) {
            $duplicateQuery->where('user_id', '!=', $team->user_id);
        }

        if ($duplicateQuery->exists()) {
            return back()->withErrors(['txtTeamName' => 'User name already exists.'])->withInput();
        }

        $team->fill([
            'user_name' => $validated['txtTeamName'],
            'user_email' => $validated['txtEmail'],
            'usre_type_id' => $targetType,
            'is_active' => $team->exists ? (int) ($team->is_active ?? 1) : 1,
        ]);

        if (! $team->exists) {
            $createdBy = $request->attributes->get('adminUser')?->user_name ?: 'admin';
            $team->fill([
                'first_name' => '',
                'last_name' => '',
                'security_key' => Str::random(40),
                'company' => '',
                'company_type' => '',
                'alternate_email' => '',
                'company_address' => '',
                'zip_code' => '',
                'user_city' => '',
                'user_country' => '',
                'user_phone' => '',
                'user_fax' => '',
                'contact_person' => '',
                'middle_fee' => 1.50,
                'super_fee' => 0,
                'date_added' => now()->format('Y-m-d H:i:s'),
                'userip_addrs' => '',
                'digitzing_format' => '',
                'vertor_format' => '',
                'topup' => '',
                'exist_customer' => '0',
                'user_term' => '',
                'package_type' => '',
                'real_user' => '0',
                'ref_code' => '',
                'ref_code_other' => '',
                'register_by' => $createdBy,
            ]);
        }

        if ($request->filled('txtPassword')) {
            $team->fill(PasswordManager::payload((string) $validated['txtPassword']));
        }

        $team->save();

        if ($targetType === AdminUser::TYPE_TEAM && $request->filled('supervisor_user_id')) {
            SupervisorTeamMember::query()->updateOrCreate([
                'supervisor_user_id' => (int) $request->input('supervisor_user_id'),
                'member_user_id' => $team->user_id,
            ], [
                'date_added' => now()->format('Y-m-d H:i:s'),
                'end_date' => null,
                'deleted_by' => null,
            ]);
        }

        $accountType = $targetType === AdminUser::TYPE_SUPERVISOR ? 'Supervisor' : 'Team';
        $redirectUrl = url('/v/show-all-teams.php');

        return redirect()->to($redirectUrl)
            ->with('success', $team->wasRecentlyCreated ? $accountType.' has been successfully created.' : $accountType.' information updated successfully.');
    }

    public function assignForm(Request $request)
    {
        $orderId = (int) $request->query('design_id');
        $page = in_array($request->query('page'), ['order', 'quote', 'vector'], true) ? (string) $request->query('page') : 'order';

        $order = Order::query()
            ->with(['customer:user_id,user_name,first_name,last_name,user_email', 'assignee:user_id,user_name,user_email'])
            ->findOrFail($orderId);

        $shareableAttachments = Attachment::query()
            ->where('order_id', $orderId)
            ->whereIn('file_source', [$page === 'quote' ? 'quote' : 'order', 'quote', 'vector', 'color', 'edit order'])
            ->orderByDesc('id')
            ->get();

        $handoffComments = OrderComment::query()
            ->where('order_id', $orderId)
            ->where('comment_source', 'orderTeamComments')
            ->latest('id')
            ->get();

        $sharedAttachmentKeys = Attachment::query()
            ->where('order_id', $orderId)
            ->where('file_source', 'orderTeamImages')
            ->pluck('file_name_with_date')
            ->filter()
            ->all();
        $defaultSelectedAttachmentIds = $shareableAttachments
            ->when($sharedAttachmentKeys !== [], function ($attachments) use ($sharedAttachmentKeys) {
                return $attachments->filter(fn (Attachment $attachment) => in_array($attachment->file_name_with_date, $sharedAttachmentKeys, true));
            })
            ->when($sharedAttachmentKeys === [], fn ($attachments) => $attachments)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $customerSubmissionText = DownstreamSharing::customerSubmissionText($order);
        $existingSharedCustomerText = DownstreamSharing::existingSharedCustomerText($order);
        $existingHandoffText = DownstreamSharing::existingHandoffText($order);
        $customerCommentMode = 'original';

        if ((int) $order->notes_by_admin === 0 && $existingSharedCustomerText === '' && $existingHandoffText === '') {
            $customerCommentMode = 'original';
        } elseif ($order->notes_by_admin) {
            $customerCommentMode = $existingSharedCustomerText !== '' && $existingSharedCustomerText !== $customerSubmissionText
                ? 'edited'
                : 'original';
        } else {
            $customerCommentMode = 'none';
        }

        $backQueue = AdminOrderQueues::normalize((string) $request->query('back', 'all-orders'));

        return view('admin.orders.assign', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'order' => $order,
            'page' => $page,
            'backQueue' => $backQueue,
            'backLabel' => AdminOrderQueues::label($backQueue),
            'teams' => AdminUser::query()->teamPortalUsers()->active()->where('is_active', 1)->orderByRaw('CASE WHEN usre_type_id = ? THEN 0 ELSE 1 END', [AdminUser::TYPE_SUPERVISOR])->orderBy('user_name')->get(),
            'shareableAttachments' => $shareableAttachments,
            'handoffComments' => $handoffComments,
            'defaultSelectedAttachmentIds' => $defaultSelectedAttachmentIds,
            'sharedAttachmentKeys' => $sharedAttachmentKeys,
            'customerSubmissionText' => $customerSubmissionText,
            'existingSharedCustomerText' => $existingSharedCustomerText,
            'existingHandoffText' => $existingHandoffText,
            'customerCommentMode' => $customerCommentMode,
        ]);
    }

    public function assignSave(Request $request)
    {
        $validated = $request->validate([
            'design_id' => ['required', 'integer'],
            'page' => ['required', 'in:order,quote,vector'],
            'status' => ['nullable', 'string'],
            'back' => ['nullable', 'string'],
            'team' => ['required', 'integer'],
            'handoff_comment' => ['nullable', 'string'],
            'customer_comment_mode' => ['required', 'in:none,original,edited'],
            'shared_customer_comment' => ['nullable', 'string'],
            'attachment_ids' => ['array'],
            'attachment_ids.*' => ['integer'],
            'notes_by_admin' => ['nullable', 'string'],
        ], [], [
            'design_id' => 'order',
            'page' => 'page',
            'status' => 'status',
            'back' => 'return page',
            'team' => 'team member',
            'handoff_comment' => 'handoff comment',
            'customer_comment_mode' => 'customer note sharing',
            'shared_customer_comment' => 'shared customer note',
            'attachment_ids' => 'files',
            'attachment_ids.*' => 'file',
            'notes_by_admin' => 'admin notes option',
        ]);

        $order = Order::query()->findOrFail((int) $validated['design_id']);
        $team = AdminUser::query()->teamPortalUsers()->active()->where('is_active', 1)->findOrFail((int) $validated['team']);
        $submitDate = now()->format('Y-m-d G:i');
        $currentAssignee = (string) $order->assign_to;

        $customerSubmissionText = DownstreamSharing::customerSubmissionText($order);
        $shareMode = (string) $validated['customer_comment_mode'];
        $sharedCustomerComment = match ($shareMode) {
            'original' => $customerSubmissionText,
            'edited' => trim((string) ($validated['shared_customer_comment'] ?? '')),
            default => '',
        };

        if ($shareMode === 'edited' && $sharedCustomerComment === '') {
            return back()->withErrors(['shared_customer_comment' => 'Please enter the customer note text you want to share downstream.'])->withInput();
        }

        $order->update([
            'notes_by_admin' => $shareMode === 'none' ? 0 : 1,
        ]);

        DownstreamSharing::replaceSharedComments($order, [
            [
                'comments' => $sharedCustomerComment,
                'source_page' => 'customer-shared',
            ],
            [
                'comments' => trim((string) ($validated['handoff_comment'] ?? '')),
                'source_page' => 'handoff',
            ],
        ]);

        Attachment::query()
            ->where('order_id', $order->order_id)
            ->where('file_source', 'orderTeamImages')
            ->delete();

        foreach ($validated['attachment_ids'] ?? [] as $attachmentId) {
            $attachment = Attachment::query()
                ->where('order_id', $order->order_id)
                ->find($attachmentId);

            if (! $attachment) {
                continue;
            }

            Attachment::query()->create([
                'order_id' => $order->order_id,
                'file_name' => $attachment->file_name,
                'file_name_with_date' => $attachment->file_name_with_date,
                'file_name_with_order_id' => $attachment->file_name_with_order_id,
                'file_source' => 'orderTeamImages',
                'date_added' => $submitDate,
            ]);
        }

        $requestedStatus = strtolower((string) ($validated['status'] ?? ''));

        if ($currentAssignee !== '' && $currentAssignee !== '0' && $validated['page'] === 'order' && $requestedStatus !== 'disapproved') {
            Billing::query()
                ->where('order_id', $order->order_id)
                ->where('approved', 'yes')
                ->where('payment', 'no')
                ->delete();
        }

        $nextStatus = $requestedStatus === 'disapproved' ? 'disapprove' : 'Underprocess';

        $order->update([
            'assign_to' => $team->user_id,
            'status' => $nextStatus,
            'assigned_date' => $submitDate,
        ]);

        $this->sendAssignmentMail($order, $team, $validated['page'], $currentAssignee, $requestedStatus);

        $detailUrl = url('/v/orders/'.$order->order_id.'/detail/'.$validated['page']);
        $back = isset($validated['back']) ? AdminOrderQueues::normalize((string) $validated['back']) : null;

        return redirect()->to($back !== null && $back !== ''
            ? $detailUrl.'?'.http_build_query(['back' => $back])
            : $detailUrl)
            ->with('success', 'Order assignment updated successfully.');
    }

    private function sendAssignmentMail(Order $order, AdminUser $team, string $page, string $currentAssignee, string $status): void
    {
        if (! $team->user_email) {
            return;
        }

        if ($currentAssignee === '' || $currentAssignee === '0') {
            if ($page === 'order') {
                $subject = 'New Order has been assigned';
                $text = 'A new order has been assigned to you.';
            } elseif ($page === 'vector') {
                $subject = 'New Vector order has been assigned';
                $text = 'A new vector order has been assigned to you.';
            } else {
                $subject = 'New Quotation has been assigned';
                $text = 'A new quotation has been assigned to you.';
            }
        } else {
            if ($page === 'order' && $status === 'disapproved') {
                $subject = 'Order has been disapproved';
                $text = 'An order has been disapproved and reassigned to you by admin.';
            } elseif ($page === 'vector') {
                $subject = 'Vector has been reassigned';
                $text = 'A vector order has been reassigned to you.';
            } elseif ($page === 'quote') {
                $subject = 'Quotation has been reassigned';
                $text = 'A quotation has been reassigned to you.';
            } else {
                $subject = 'Order has been reassigned';
                $text = 'An order has been reassigned to you by admin.';
            }
        }

        $detailUrl = url('/v/orders/'.$order->order_id.'/detail/'.$page);
        $itemLabel = $page === 'quote' ? 'Quote' : 'Order';
        $queueLabel = ucfirst($page);
        $body = view('admin.emails.team-assignment', [
            'teamName' => trim((string) ($team->display_name ?: $team->user_name ?: 'Team Member')),
            'message' => $text,
            'orderId' => $order->order_id,
            'queueLabel' => $queueLabel,
            'itemLabel' => $itemLabel,
            'detailUrl' => $detailUrl,
            'loginUrl' => url('/'),
        ])->render();

        PortalMailer::sendHtml($team->user_email, $subject, $body);
    }
}
