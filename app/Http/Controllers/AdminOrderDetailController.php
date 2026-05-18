<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\QuoteNegotiation;
use App\Support\AttachmentPreview;
use App\Support\AdminNavigation;
use App\Support\AdminOrderQueues;
use App\Support\CustomerReleaseGate;
use App\Support\OrderAutomation;
use App\Support\OrderWorkflow;
use App\Support\OrderWorkflowMetaManager;
use App\Support\PortalMailer;
use App\Support\PricingResolver;
use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use App\Support\SiteResolver;
use App\Support\SignupOfferService;
use App\Support\SystemEmailTemplates;
use App\Support\TeamPricing;
use App\Support\UploadSecurity;
use App\Support\UploadFileName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminOrderDetailController extends Controller
{
    public function showByRoute(Request $request, Order $order, string $page = 'order')
    {
        $request->query->set('oid', (string) $order->order_id);
        $request->query->set('page', $page);

        return $this->show($request);
    }

    public function show(Request $request)
    {
        $orderId = (int) $request->query('oid');
        $requestedPage = $this->normalizePage((string) $request->query('page', 'order'));

        $order = Order::query()
            ->with(['customer:user_id,user_name,first_name,last_name,user_email,urgent_fee,normal_fee,middle_fee,usre_type_id,is_active', 'assignee:user_id,user_name,first_name,last_name'])
            ->findOrFail($orderId);

        $page = $this->normalizeDetailPage($requestedPage, $order);

        if ($page !== $requestedPage) {
            return redirect()->to($this->detailUrl(
                $order->order_id,
                $page,
                (string) $request->query('back', '')
            ));
        }

        if ($order->customer) {
            $siteContext = SiteResolver::fromLegacyKey(
                (string) ($order->website ?: $order->customer->website ?: config('sites.primary_legacy_key', '1dollar'))
            ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
            OrderAutomation::syncCustomer($order->customer, $siteContext);
            $order->refresh();
            $order->load(['customer:user_id,user_name,first_name,last_name,user_email,urgent_fee,normal_fee,middle_fee,usre_type_id,is_active', 'assignee:user_id,user_name,first_name,last_name']);
        }

        $advancePayment = AdvancePayment::query()
            ->where('status', 1)
            ->where('order_id', $orderId)
            ->first();

        $ordersController = app(AdminOrdersController::class);
        $attachmentGroups = $this->attachmentGroups($order, $page);
        $customerDeliveryGate = CustomerReleaseGate::summary($order);
        $workflowMeta = OrderWorkflowMetaManager::forOrder($order);
        $backQueue = $this->normalizeBackContext($request->query('back', $this->defaultBackQueue($page, $order)));
        $latestQuoteNegotiation = null;

        if (in_array($page, ['quote', 'vector'], true) && Schema::hasTable('quote_negotiations')) {
            $latestQuoteNegotiation = QuoteNegotiation::query()
                ->where('order_id', $orderId)
                ->latest('id')
                ->first();
        }

        $site = SiteResolver::fromLegacyKey(
            (string) ($order->website ?: $order->customer?->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        return view('admin.orders.show', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'order' => $order,
            'page' => $page,
            'backQueue' => $backQueue,
            'backLabel' => $this->backLabel($backQueue),
            'backUrl' => $this->backUrl($backQueue),
            'showAssignWorkflow' => ! $this->isBillingReportBack($backQueue),
            'attachmentGroups' => $attachmentGroups,
            'customerComments' => OrderComment::query()->where('order_id', $orderId)->where('comment_source', 'customerComments')->where('comments', '!=', '')->latest('id')->get(),
            'adminComments' => OrderComment::query()->where('order_id', $orderId)->whereIn('comment_source', ['admin', 'customer'])->latest('id')->get(),
            'teamComments' => OrderComment::query()->where('order_id', $orderId)->where('comment_source', 'team')->latest('id')->get(),
            'advancePayment' => $advancePayment,
            'assignUrl' => url('/v/assign-order.php?design_id='.$orderId.'&assign_to='.$order->assign_to.'&page='.$page.'&status='.$order->status.'&back='.rawurlencode($backQueue)),
            'canConvertQuote' => $this->canConvertQuote($order),
            'canDeleteOrder' => $this->canDeleteOrder($order),
            'customerPaidFlag' => $ordersController->hasCustomerPaid($order),
            'canMarkPaidOrder' => $ordersController->canMarkPaidOrder($order),
            'approvedBillingFlag' => $ordersController->hasApprovedBilling($order),
            'canApproveOrder' => $ordersController->canApproveOrder($order),
            'customerDeliveryGate' => $customerDeliveryGate,
            'workflowMeta' => $workflowMeta,
            'hasWorkflowMetaTable' => OrderWorkflowMetaManager::hasTable(),
            'sendCustomerNotificationDefault' => OrderWorkflowMetaManager::shouldSendCustomerNotification($order),
            'canCompleteFromAdmin' => $this->canCompleteFromAdmin($order),
            'offerAdjustedAmount'  => (function () use ($order) {
                $raw = (float) ($order->stitches_price ?: $order->total_amount ?: 0);
                if ($raw <= 0 || trim((string) $order->stitches) === '') {
                    return null; // no stitches yet — let AJAX handle it
                }
                $adjusted = SignupOfferService::applyEligibleFirstOrderAmount($order, $raw);
                return $adjusted < $raw ? number_format($adjusted, 2, '.', '') : null;
            })(),
            'latestQuoteNegotiation' => $latestQuoteNegotiation,
            'completionEmailTemplateOptions' => SystemEmailTemplates::selectionOptions(
                $site,
                $page === 'quote' ? 'customer_quote_completed' : 'customer_order_completed'
            ),
            'negotiationEmailTemplateOptions' => in_array($page, ['quote', 'vector'], true)
                ? SystemEmailTemplates::selectionOptions($site, 'customer_quote_negotiation_response')
                : [],
        ]);
    }

    public function convertQuoteToOrder(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'back' => ['nullable', 'string'],
        ], [], [
            'order_id' => 'quote',
            'back' => 'return page',
        ]);

        $order = Order::query()->findOrFail((int) $validated['order_id']);
        abort_unless($this->canConvertQuote($order), 404);
        $this->convertAcceptedQuoteToOrder($order, now());

        return redirect()->to($this->detailUrl(
            $order->order_id,
            $this->pageForOrder($order),
            AdminOrderQueues::normalize((string) ($validated['back'] ?: 'new-orders'))
        ))->with('success', 'Quote converted to order successfully.');
    }

    public function addComment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'page' => ['required', 'string'],
            'back' => ['nullable', 'string'],
            'comment_source' => ['required', 'in:admin,customer,team'],
            'comments' => ['required', 'string'],
        ], [], [
            'order_id' => 'order',
            'page' => 'page',
            'back' => 'return page',
            'comment_source' => 'comment type',
            'comments' => 'comment',
        ]);

        $now = now()->format('Y-m-d H:i:s');

        OrderComment::query()->create([
            'order_id' => $validated['order_id'],
            'comments' => $validated['comments'],
            'source_page' => $validated['comment_source'],
            'comment_source' => $validated['comment_source'],
            'date_added' => $now,
            'date_modified' => $now,
        ]);

        return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
            ->with('success', 'Comment added successfully.');
    }

    public function respondToQuoteNegotiation(Request $request)
    {
        abort_unless(Schema::hasTable('quote_negotiations'), 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'negotiation_id' => ['required', 'integer'],
            'page' => ['required', 'string'],
            'back' => ['nullable', 'string'],
            'action' => ['required', 'in:accept,reject'],
            'admin_counter_amount' => ['nullable', 'numeric', 'gt:0'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
            'customer_email_template_id' => ['nullable', 'integer'],
        ], [], [
            'order_id' => 'quote',
            'negotiation_id' => 'price response',
            'page' => 'page',
            'back' => 'return page',
            'action' => 'decision',
            'admin_counter_amount' => 'counter amount',
            'admin_note' => 'admin note',
            'customer_email_template_id' => 'email template',
        ]);

        $order = Order::query()->findOrFail((int) $validated['order_id']);
        abort_unless(in_array($this->normalizePage((string) $validated['page']), ['quote', 'vector'], true), 404);
        abort_unless(in_array((string) $order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true), 404);

        $negotiation = QuoteNegotiation::query()
            ->where('id', (int) $validated['negotiation_id'])
            ->where('order_id', $order->order_id)
            ->firstOrFail();

        abort_unless(in_array((string) $negotiation->status, ['pending_admin_review', 'customer_replied'], true), 404);

        $adminUser = $request->attributes->get('adminUser');
        $adminName = (string) ($adminUser?->user_name ?: 'admin');
        $now = now()->format('Y-m-d H:i:s');
        $adminNote = trim((string) ($validated['admin_note'] ?? ''));
        $counterAmount = array_key_exists('admin_counter_amount', $validated) && $validated['admin_counter_amount'] !== null
            ? round((float) $validated['admin_counter_amount'], 2)
            : null;
        $site = SiteResolver::fromLegacyKey(
            (string) ($order->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
        $emailOverride = SystemEmailTemplates::selectedTemplateOverride(
            $site,
            'customer_quote_negotiation_response',
            isset($validated['customer_email_template_id']) ? (int) $validated['customer_email_template_id'] : null
        );

        if ((string) $validated['action'] === 'accept') {
            $acceptedAmount = $negotiation->customer_target_amount !== null
                ? round((float) $negotiation->customer_target_amount, 2)
                : $this->money($order->total_amount ?: $order->stitches_price);
            $formattedAmount = number_format($acceptedAmount, 2, '.', '');

            $this->convertAcceptedQuoteToOrder($order, now(), $formattedAmount);
            $order->update([
                'total_amount' => $formattedAmount,
                'stitches_price' => $formattedAmount,
            ]);

            $negotiation->update($this->quoteNegotiationPayload([
                'status' => 'accepted_by_admin',
                'admin_counter_amount' => $formattedAmount,
                'admin_note' => $adminNote !== '' ? $adminNote : 'Customer requested price approved.',
                'resolved_by_user_id' => $adminUser?->user_id,
                'resolved_by_name' => $adminName,
                'resolved_at' => $now,
                'updated_at' => $now,
            ]));

            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => $this->buildAdminQuoteNegotiationComment(
                    'Accepted customer requested price',
                    $formattedAmount,
                    $adminNote
                ),
                'source_page' => 'admin',
                'comment_source' => 'admin',
                'date_added' => $now,
                'date_modified' => $now,
            ]);

            $this->sendQuoteNegotiationResponseEmail($order, $negotiation, 'accepted', $formattedAmount, $adminNote, $emailOverride);

            return $this->redirectToDetail($order->order_id, $this->pageForOrder($order), $validated['back'] ?? null)
                ->with('success', 'Customer requested price accepted and the quote has been converted to an order.');
        }

        $status = $counterAmount !== null ? 'counter_offered' : 'request_declined';

        if ($counterAmount !== null) {
            $formattedAmount = number_format($counterAmount, 2, '.', '');
            $order->update([
                'status' => 'done',
                'total_amount' => $formattedAmount,
                'stitches_price' => $formattedAmount,
                'modified_date' => $now,
            ]);
        } else {
            $formattedAmount = number_format($this->money($order->total_amount ?: $order->stitches_price), 2, '.', '');
            $order->update([
                'status' => 'done',
                'modified_date' => $now,
            ]);
        }

        $negotiation->update($this->quoteNegotiationPayload([
            'status' => $status,
            'admin_counter_amount' => $counterAmount !== null ? $formattedAmount : null,
            'admin_note' => $adminNote !== '' ? $adminNote : ($counterAmount !== null
                ? 'A revised quote has been prepared for customer review.'
                : 'The requested price could not be approved.'),
            'resolved_by_user_id' => $adminUser?->user_id,
            'resolved_by_name' => $adminName,
            'resolved_at' => $now,
            'updated_at' => $now,
        ]));

        OrderComment::query()->create([
            'order_id' => $order->order_id,
            'comments' => $this->buildAdminQuoteNegotiationComment(
                $counterAmount !== null ? 'Sent counter offer on quote negotiation' : 'Declined requested quote price',
                $counterAmount !== null ? $formattedAmount : null,
                $adminNote
            ),
            'source_page' => 'admin',
            'comment_source' => 'admin',
            'date_added' => $now,
            'date_modified' => $now,
        ]);

        $this->sendQuoteNegotiationResponseEmail(
            $order,
            $negotiation,
            $counterAmount !== null ? 'countered' : 'declined',
            $formattedAmount,
            $adminNote,
            $emailOverride
        );

        return $this->redirectToDetail($order->order_id, 'quote', $validated['back'] ?? null)
            ->with('success', $counterAmount !== null
                ? 'Counter offer saved. The quote is ready for customer review.'
                : 'Customer price request rejected. The quote is ready for customer review.');
    }

    public function deleteComment(Request $request, OrderComment $comment)
    {
        $orderId = (int) $request->query('oid', $comment->order_id);
        $page = $this->normalizePage((string) $request->query('page', 'order'));
        $back = (string) $request->query('back', '');

        $comment->delete();

        return $this->redirectToDetail($orderId, $page, $back)
            ->with('success', 'Comment deleted successfully.');
    }

    public function uploadAttachment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'page' => ['required', 'string'],
            'back' => ['nullable', 'string'],
            'source' => ['required', 'in:complaint,customer,team'],
            'files.*' => ['required', 'file', 'max:30720'],
        ], [
            'files.*.max' => 'Each file must be 30 MB or smaller.',
        ], [
            'order_id' => 'order',
            'page' => 'page',
            'back' => 'return page',
            'source' => 'upload type',
            'files' => 'files',
            'files.*' => 'file',
        ]);

        $files = $request->file('files', []);

        if ($files === []) {
            return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
                ->with('success', 'No files were selected.');
        }

        $profile = (string) $validated['source'] === 'team' ? 'production' : 'source';
        $uploadValidationError = UploadSecurity::assertAllowedFiles($files, $profile);
        if ($uploadValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, $profile, 'Admin attachment upload was rejected on the detail screen.', [
                'order_id' => (int) $validated['order_id'],
                'source' => (string) $validated['source'],
                'file_names' => collect($files)
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
                ->withErrors(['files' => $uploadValidationError]);
        }

        $submittedAt = now();
        $submitDate = $submittedAt->format('Y-m-d H:i:s');
        $prefix = $submittedAt->format('Y-m-d G-i');
        $page = $this->normalizePage((string) $validated['page']);
        $resolvedSource = $this->resolvedUploadSource((string) $validated['source'], $page);
        $folder = $this->uploadFolderForSource($resolvedSource);

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $original = $this->cleanFileName($file->getClientOriginalName());
            $storedName = $prefix.'_(('.$validated['order_id'].'))_'.$original;
            $displayName = '('.$validated['order_id'].') '.$original;

            $storedPath = SharedUploads::storeUploadedFile($file, $folder, $storedName, $submittedAt);

            Attachment::query()->create([
                'order_id' => $validated['order_id'],
                'file_name' => $original,
                'file_name_with_date' => $storedPath,
                'file_name_with_order_id' => $displayName,
                'file_source' => $resolvedSource,
                'date_added' => $submitDate,
            ]);
        }

        return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
            ->with('success', 'Files uploaded successfully.');
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $path = $this->attachmentAbsolutePath($attachment);
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->file_name_with_order_id ?: $attachment->file_name ?: basename($path));
    }

    public function previewAttachment(Request $request, Attachment $attachment)
    {
        $path = $this->attachmentAbsolutePath($attachment);
        $displayName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name ?: basename($path));
        abort_unless(AttachmentPreview::isSupported($displayName), 404);

        if ((bool) $request->route('raw') || $request->boolean('raw')) {
            return AttachmentPreview::inlineResponse($path, $displayName);
        }

        return view('admin.attachments.preview', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'displayName' => $displayName,
            'previewKind' => AttachmentPreview::kindForFileName($displayName),
            'rawUrl' => url('/v/attachments/'.$attachment->id.'/preview/raw'),
            'downloadUrl' => url('/v/attachments/'.$attachment->id.'/download'),
            'backUrl' => $this->detailUrl(
                (int) $attachment->order_id,
                (string) $request->query('page', $this->pageForAttachmentPreview($attachment)),
                $request->query('back')
            ),
            'textContent' => AttachmentPreview::kindForFileName($displayName) === 'text'
                ? AttachmentPreview::textContents($path)
                : null,
        ]);
    }

    public function deleteAttachment(Request $request, Attachment $attachment)
    {
        $orderId = (int) $request->query('oid', $attachment->order_id);
        $page = $this->normalizePage((string) $request->query('page', 'order'));
        $back = (string) $request->query('back', '');

        $path = $this->attachmentAbsolutePath($attachment);
        if (is_file($path)) {
            @unlink($path);
        }

        $attachment->delete();

        return $this->redirectToDetail($orderId, $page, $back)
            ->with('success', 'Attachment removed successfully.');
    }

    public function selectFilesForCustomer(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'page' => ['required', 'string'],
            'back' => ['nullable', 'string'],
            'attachment_ids' => ['array'],
            'attachment_ids.*' => ['integer'],
        ], [], [
            'order_id' => 'order',
            'page' => 'page',
            'back' => 'return page',
            'attachment_ids' => 'files',
            'attachment_ids.*' => 'file',
        ]);

        $selectedIds = collect($validated['attachment_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        if ($selectedIds === []) {
            return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
                ->with('success', 'No files were selected for customer delivery.');
        }

        $attachments = Attachment::query()
            ->where('order_id', $validated['order_id'])
            ->whereIn('file_source', ['team', 'sewout', 'scanned'])
            ->get();

        $order = Order::query()->with('customer')->findOrFail((int) $validated['order_id']);
        $released = 0;
        $alreadyReleased = 0;
        $restored = 0;
        $failed = 0;

        foreach ($attachments as $attachment) {
            $selected = in_array((int) $attachment->id, $selectedIds, true);
            $desiredSource = null;

            if ($selected) {
                $desiredSource = 'sewout';
            }

            if ($desiredSource !== null) {
                if ($attachment->file_source === $desiredSource) {
                    $alreadyReleased++;
                    continue;
                }

                $source = $this->attachmentAbsolutePath($attachment);
                $target = SharedUploads::ensureParentDirectory(
                    $this->uploadDirectory($desiredSource).DIRECTORY_SEPARATOR.$attachment->file_name_with_date
                );
                $targetExists = is_file($target);

                if (! is_file($source) && ! $targetExists) {
                    $failed++;
                    continue;
                }

                if ($targetExists || @copy($source, $target)) {
                    if (is_file($source) && realpath($source) !== realpath($target)) {
                        @unlink($source);
                    }
                    $attachment->update(['file_source' => $desiredSource]);
                    $released++;
                } else {
                    $failed++;
                }

                continue;
            }

            if (in_array((string) $attachment->file_source, ['sewout', 'scanned'], true)) {
                $source = $this->attachmentAbsolutePath($attachment);
                $target = SharedUploads::ensureParentDirectory(
                    $this->uploadDirectory('team').DIRECTORY_SEPARATOR.$attachment->file_name_with_date
                );

                if (! is_file($source)) {
                    continue;
                }

                if (@copy($source, $target)) {
                    @unlink($source);
                    $attachment->update(['file_source' => 'team']);
                    $restored++;
                }
            }
        }

        $message = ($released > 0 || $alreadyReleased > 0)
            ? 'Customer delivery files updated successfully.'
            : ($restored > 0 ? 'Customer delivery files were restricted to the current allowed set.' : 'No files were released to the customer.');

        $redirect = $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
            ->with('success', $message);

        if ($failed > 0) {
            $redirect->withErrors(['delivery' => 'Some selected files could not be prepared for customer delivery. Please verify the shared upload folders and try again.']);
        }

        return $redirect;
    }

    public function previewPrice(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'stitches' => ['required', 'string', 'max:255'],
        ], [], [
            'order_id' => 'order',
            'stitches' => 'stitches or hours',
        ]);

        $order = Order::query()->with('customer')->findOrFail((int) $validated['order_id']);
        $acceptedNegotiationAmount = $this->acceptedNegotiationAmount($order);
        $result = $acceptedNegotiationAmount !== null
            ? $this->normalizeCompletionUnits($order, (string) $validated['stitches'])
            : PricingResolver::forAdminCompletion($order, (string) $validated['stitches']);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        $order->stitches = (string) $result['units'];
        $amount = $acceptedNegotiationAmount !== null
            ? number_format($acceptedNegotiationAmount, 2, '.', '')
            : number_format(
                SignupOfferService::applyEligibleFirstOrderAmount($order, (float) $result['amount']),
                2, '.', ''
            );

        return response()->json([
            'stitches' => $result['units'],
            'amount' => $amount,
        ]);
    }

    public function complete(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
        ], [], [
            'order_id' => 'order',
        ]);

        $order = Order::query()->with('customer')->findOrFail($request->input('order_id'));
        $page = $this->pageForOrder($order);

        if ($page === 'vector') {
            $composedHours = TeamPricing::composeHours(
                $request->input('work_hours'),
                $request->input('work_minutes')
            );

            if ($composedHours !== null) {
                $request->merge(['stitches' => $composedHours]);
            }
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'page' => ['nullable', 'string'],
            'back' => ['nullable', 'string'],
            'stitches' => ['required', 'string'],
            'stamount' => ['nullable', 'string'],
            'ddlStatus' => ['required', 'in:done,Disapproved,disapproved'],
            'orderpaid' => ['nullable', 'string'],
            'send_customer_notification' => ['nullable', 'string'],
            'customer_email_template_id' => ['nullable', 'integer'],
        ], [], [
            'order_id' => 'order',
            'page' => 'page',
            'back' => 'return page',
            'stitches' => 'stitches',
            'stamount' => 'amount',
            'ddlStatus' => 'status',
            'orderpaid' => 'payment status',
            'send_customer_notification' => 'customer notification option',
            'customer_email_template_id' => 'email template',
        ]);

        $stitches = trim((string) $validated['stitches']);
        $status = $validated['ddlStatus'] === 'Disapproved' ? 'disapproved' : strtolower($validated['ddlStatus']);

        if (! $this->canCompleteFromAdmin($order)) {
            return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                ->withErrors(['detail' => 'Only ready jobs, actively assigned team jobs, or unassigned new orders can be completed from this screen.']);
        }

        if (($page !== 'vector') && ! preg_match('/^\d+(\.\d+)?$/', $stitches)) {
            return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                ->withErrors(['stitches' => 'No. Of Stitches must be a numeric value.']);
        }

        if ($request->filled('orderpaid')) {
            AdvancePayment::query()->where('order_id', $order->order_id)->update(['status' => 0]);
            Billing::query()->where('order_id', $order->order_id)->update(Billing::writablePayload([
                'is_paid' => 0,
                'is_advance' => 0,
            ]));
        }

        if ($status === 'done' && $this->requiresCustomerFilesForCompletion($order)) {
            $hasCustomerFiles = Attachment::query()
                ->where('order_id', $order->order_id)
                ->where('file_source', 'sewout')
                ->exists();

            if (! $hasCustomerFiles) {
                return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                    ->withErrors(['detail' => 'No files are selected for the customer yet.']);
            }
        }

        $adminAmount = trim((string) ($validated['stamount'] ?? ''));
        $acceptedNegotiationAmount = $this->acceptedNegotiationAmount($order);
        if ($adminAmount === '') {
            $pricing = $acceptedNegotiationAmount !== null
                ? $this->normalizeCompletionUnits($order, $stitches)
                : PricingResolver::forAdminCompletion($order, $stitches);

            if (! $pricing['ok']) {
                return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                    ->withErrors(['stamount' => $pricing['message']]);
            }

            $stitches = (string) $pricing['units'];
            $adminAmount = $acceptedNegotiationAmount !== null
                ? number_format($acceptedNegotiationAmount, 2, '.', '')
                : (string) $pricing['amount'];
        } elseif ($adminAmount !== 'first order is free') {
            if (! is_numeric($adminAmount)) {
                return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                    ->withErrors(['stamount' => 'Amount must be numeric.']);
            }
        }

        // Ensure the order model reflects the stitches being submitted so that
        // the first-order-free eligibility check sees the correct stitch count
        // (the DB update happens later; we set the attribute now for the check).
        $order->stitches = $stitches;

        if ($adminAmount !== 'first order is free' && is_numeric($adminAmount)) {
            $rawAmount = round((float) $adminAmount, 2);
            // Use a positive sentinel when admin entered $0 so the offer service can
            // properly evaluate eligibility. If no offer applies, the service returns
            // the sentinel unchanged (> 0), and since $rawAmount === 0 we leave
            // $adminAmount as '0.00' — which then hits the >0 validation below.
            // If an offer DOES apply (returns 0), we treat it as free regardless.
            $applied = SignupOfferService::applyEligibleFirstOrderAmount($order, $rawAmount > 0 ? $rawAmount : 0.01);
            if ($applied <= 0) {
                // Offer brought price to zero — legitimate first-order-free.
                $adminAmount = 'first order is free';
            } elseif ($rawAmount > 0) {
                // Offer may have partially reduced the price, or no offer applied.
                $adminAmount = number_format($applied, 2, '.', '');
            }
            // $rawAmount === 0 and no offer → $adminAmount stays '0.00' → blocked below.
        }

        if ($status === 'done' && $adminAmount !== 'first order is free' && is_numeric($adminAmount) && (float) $adminAmount <= 0) {
            return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
                ->withErrors(['stamount' => 'Amount must be greater than 0.00 before this job can be completed.']);
        }

        $order->update([
            'completion_date' => now()->format('Y-m-d H:i:s'),
            'stitches_price' => $adminAmount === 'first order is free' ? '0.00' : $adminAmount,
            'status' => $status,
            'total_amount' => $adminAmount,
            'stitches' => $stitches,
        ]);

        // When the first-order offer makes the order free, zero out any billing
        // record that was created at approval time with the full price so the
        // billing page and release gate both reflect the free status.
        if ($adminAmount === 'first order is free') {
            $freeBilling = Billing::query()
                ->active()
                ->where('order_id', $order->order_id)
                ->where('approved', 'yes')
                ->where('payment', 'no')
                ->first();

            if ($freeBilling) {
                $freeBilling->update(Billing::writablePayload([
                    'amount'     => '0.00',
                    'payment'    => 'yes',
                    'is_paid'    => 1,
                    'comments'   => 'First order free — signup offer applied at completion.',
                ]));
            }
        }

        $sendCustomerNotification = OrderWorkflowMetaManager::shouldSendCustomerNotification($order, $request->boolean('send_customer_notification'));
        $site = SiteResolver::fromLegacyKey(
            (string) ($order->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
        $emailOverride = SystemEmailTemplates::selectedTemplateOverride(
            $site,
            $page === 'quote' ? 'customer_quote_completed' : 'customer_order_completed',
            isset($validated['customer_email_template_id']) ? (int) $validated['customer_email_template_id'] : null
        );

        if ($status === 'done' && $sendCustomerNotification) {
            $this->sendCompletionEmail($order, $page, $stitches, (string) $adminAmount, $emailOverride);
        }

        return $this->redirectToDetail($order->order_id, $page, $validated['back'] ?? null)
            ->with('success', $status === 'done'
                ? (($page === 'quote' ? 'Quotation' : 'Order').' completed successfully.'.($sendCustomerNotification ? '' : ' Customer notification was suppressed for this admin-created record.'))
                : 'Order marked as disapproved.');
    }

    public function saveDeliveryControls(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'page' => ['required', 'string'],
            'back' => ['nullable', 'string'],
            'order_credit_limit' => ['nullable', 'numeric', 'min:0'],
            'delivery_override' => ['required', 'in:auto,preview_only'],
        ], [], [
            'order_id' => 'order',
            'page' => 'page',
            'back' => 'return page',
            'order_credit_limit' => 'order credit limit',
            'delivery_override' => 'customer file access',
        ]);

        if (! OrderWorkflowMetaManager::hasTable()) {
            return $this->redirectToDetail((int) $validated['order_id'], (string) $validated['page'], $validated['back'] ?? null)
                ->withErrors(['delivery' => 'Delivery controls require the `order_workflow_meta` table.']);
        }

        $order = Order::query()->findOrFail((int) $validated['order_id']);

        OrderWorkflowMetaManager::ensure($order, [
            'delivery_override' => (string) $validated['delivery_override'],
            'order_credit_limit' => $request->filled('order_credit_limit') ? round((float) $validated['order_credit_limit'], 2) : null,
        ]);

        return $this->redirectToDetail($order->order_id, (string) $validated['page'], $validated['back'] ?? null)
            ->with('success', 'Customer file access updated successfully.');
    }

    private function attachmentGroups(Order $order, string $page): array
    {
        $baseSource = $page === 'quote' ? 'quote' : 'order';
        $teamAttachments = Attachment::query()
            ->where('order_id', $order->order_id)
            ->whereIn('file_source', ['team', 'sewout', 'scanned'])
            ->orderByRaw("CASE WHEN file_source = 'sewout' THEN 0 WHEN file_source = 'scanned' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get()
            ->unique('file_name_with_date')
            ->values();

        return [
            'complaint' => Attachment::query()->where('order_id', $order->order_id)->where('file_source', 'edit order')->orderByDesc('id')->get(),
            'order' => Attachment::query()->where('order_id', $order->order_id)->whereIn('file_source', [$baseSource, 'quote', 'vector', 'color'])->orderByDesc('id')->get(),
            'team' => $teamAttachments,
        ];
    }

    private function normalizePage(string $page): string
    {
        return in_array($page, ['quote', 'order', 'vector'], true) ? $page : 'order';
    }

    private function pageForOrder(Order $order): string
    {
        $page = OrderWorkflow::pageForOrder($order);

        return in_array($page, ['order', 'quote', 'vector'], true) ? $page : 'order';
    }

    private function normalizeDetailPage(string $page, Order $order): string
    {
        $resolvedPage = $this->pageForOrder($order);

        if ($page === $resolvedPage) {
            return $page;
        }

        if (! in_array($page, ['order', 'quote', 'vector'], true)) {
            return $resolvedPage;
        }

        return $resolvedPage;
    }

    private function canConvertQuote(Order $order): bool
    {
        return is_null($order->end_date)
            && in_array((string) $order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true);
    }

    private function quoteNegotiationPayload(array $payload): array
    {
        $columns = Schema::getColumnListing('quote_negotiations');

        return collect($payload)
            ->only($columns)
            ->all();
    }

    private function acceptedNegotiationAmount(Order $order): ?float
    {
        if (! Schema::hasTable('quote_negotiations')) {
            return null;
        }

        $negotiation = QuoteNegotiation::query()
            ->where('order_id', $order->order_id)
            ->latest('id')
            ->first();

        if (! $negotiation || (string) $negotiation->status !== 'accepted_by_admin') {
            return null;
        }

        $amount = $negotiation->admin_counter_amount ?? $negotiation->customer_target_amount;

        return is_numeric($amount) ? round((float) $amount, 2) : null;
    }

    private function normalizeCompletionUnits(Order $order, string $units): array
    {
        $units = trim($units);

        if (in_array((string) $order->order_type, ['vector', 'q-vector', 'color', 'qcolor'], true)) {
            $normalizedHours = TeamPricing::normalizeHours($units);

            if ($normalizedHours === null) {
                return [
                    'ok' => false,
                    'message' => 'Enter total hours as a whole number or HH:MM to calculate the price.',
                ];
            }

            return [
                'ok' => true,
                'units' => $normalizedHours,
                'amount' => $this->acceptedNegotiationAmount($order),
            ];
        }

        if ($units === '' || preg_match('/^\d+(\.\d+)?$/', $units) !== 1 || (float) $units <= 0) {
            return [
                'ok' => false,
                'message' => 'Enter a numeric stitch count to calculate the price.',
            ];
        }

        return [
            'ok' => true,
            'units' => $units,
            'amount' => $this->acceptedNegotiationAmount($order),
        ];
    }

    private function buildAdminQuoteNegotiationComment(string $headline, ?string $amount, string $note): string
    {
        $parts = [$headline];

        if ($amount !== null && $amount !== '') {
            $parts[] = 'Amount: $'.$amount;
        }

        if (trim($note) !== '') {
            $parts[] = 'Note: '.trim($note);
        }

        return implode("\n", $parts);
    }

    private function canDeleteOrder(Order $order): bool
    {
        if (! is_null($order->end_date)) {
            return false;
        }

        if (! in_array((string) $order->order_type, ['order', 'vector', 'color', 'quote', 'digitzing', 'q-vector', 'qcolor'], true)) {
            return false;
        }

        return ! Billing::query()
            ->where('order_id', $order->order_id)
            ->whereNull('end_date')
            ->where(function ($query) {
                $query->where('payment', 'yes')
                    ->orWhere('is_paid', 1)
                    ->orWhere('is_advance', 1);
            })
            ->exists();
    }

    private function convertAcceptedQuoteToOrder(Order $order, \Illuminate\Support\Carbon $now, ?string $amount = null): void
    {
        $convertedOrderType = match ((string) $order->order_type) {
            'q-vector' => 'vector',
            'qcolor' => 'color',
            default => 'order',
        };

        $convertedType = in_array((string) $order->order_type, ['q-vector', 'qcolor'], true) ? 'vector' : 'digitizing';
        $columns = Schema::getColumnListing('orders');

        $order->update(collect([
            'order_type' => $convertedOrderType,
            'type' => $convertedType,
            'status' => 'Underprocess',
            'assign_to' => 0,
            'assigned_date' => null,
            'submit_date' => $now->format('Y-m-d H:i:s'),
            'completion_date' => $this->completionDate((string) ($order->turn_around_time ?: 'Standard'), $now),
            'vender_complete_date' => null,
            'working' => '',
            'modified_date' => $now->format('Y-m-d H:i:s'),
            'total_amount' => $amount ?? $order->total_amount,
            'stitches_price' => $amount ?? $order->stitches_price,
        ])->only($columns)->all());

        OrderWorkflowMetaManager::ensure($order, [
            'created_source' => 'admin_quote_conversion',
        ]);

        Attachment::query()
            ->where('order_id', $order->order_id)
            ->where('file_source', 'quote')
            ->update(['file_source' => 'order']);

        Attachment::query()
            ->where('order_id', $order->order_id)
            ->where('file_source', 'edit quote')
            ->update(['file_source' => 'edit order']);
    }

    private function completionDate(string $turnaround, \Illuminate\Support\Carbon $submittedAt): string
    {
        $hours = match (strtolower(trim($turnaround))) {
            'superrush' => 8,
            'priority' => 12,
            default => 24,
        };

        return $submittedAt->copy()->addHours($hours)->format('Y-m-d H:i:s');
    }

    private function canCompleteFromAdmin(Order $order): bool
    {
        $status = strtolower(trim((string) $order->status));

        if ($status === 'ready') {
            return true;
        }

        $isAssignedToTeam = ! in_array((string) $order->assign_to, ['', '0'], true);
        if (! $isAssignedToTeam) {
            return in_array($status, ['underprocess'], true);
        }

        return ! in_array($status, ['done', 'approved', 'disapproved'], true);
    }

    private function requiresCustomerFilesForCompletion(Order $order): bool
    {
        if ((string) $order->order_type === 'qquote') {
            return false;
        }

        return ! in_array((string) $order->order_type, OrderWorkflow::quoteManagementTypes(), true);
    }

    private function redirectToDetail(int $orderId, string $page, ?string $back)
    {
        return redirect()->to($this->detailUrl($orderId, $page, $back));
    }

    private function detailUrl(int $orderId, string $page, mixed $back = null): string
    {
        $normalizedPage = $this->normalizePage($page);
        $normalizedBack = trim((string) $back) !== '' ? $this->normalizeBackContext($back) : null;
        $base = url('/v/orders/'.$orderId.'/detail/'.$normalizedPage);

        return $normalizedBack !== null
            ? $base.'?'.http_build_query(['back' => $normalizedBack])
            : $base;
    }

    private function attachmentAbsolutePath(Attachment $attachment): string
    {
        $folder = match ($attachment->file_source) {
            'team', 'orderTeamImages' => 'team',
            'sewout' => 'sewout',
            'scanned' => 'scanned',
            'quote' => 'quotes',
            'admin' => 'admin',
            default => 'order',
        };
        $fallbackFolders = in_array((string) $attachment->file_source, ['order', 'edit order', 'vector', 'color', 'orderTeamImages'], true)
            ? ['quotes']
            : [];

        return SharedUploads::firstExistingPath((string) $attachment->file_name_with_date, $folder, $fallbackFolders);
    }

    private function pageForAttachmentPreview(Attachment $attachment): string
    {
        return match ((string) $attachment->file_source) {
            'quote' => 'quote',
            'vector', 'color' => 'vector',
            default => 'order',
        };
    }

    private function uploadDirectory(string $folder): string
    {
        return SharedUploads::folder($folder);
    }

    private function resolvedUploadSource(string $source, string $page): string
    {
        return match ($source) {
            'complaint' => 'edit order',
            'customer' => $page === 'quote' ? 'quote' : ($page === 'vector' ? 'vector' : 'order'),
            default => $source,
        };
    }

    private function uploadFolderForSource(string $source): string
    {
        return match ($source) {
            'team', 'orderTeamImages' => 'team',
            'scanned' => 'scanned',
            'sewout' => 'sewout',
            'quote' => 'quotes',
            'admin' => 'admin',
            default => 'order',
        };
    }

    private function cleanFileName(string $fileName): string
    {
        $clean = UploadFileName::sanitize($fileName);

        return trim($clean) !== '' ? $clean : Str::random(12);
    }

    private function defaultBackQueue(string $page, Order $order): string
    {
        if ($page === 'quote') {
            return match ($order->status) {
                'Underprocess' => 'new-quotes',
                'Ready' => 'designer-completed-quotes',
                'done' => 'completed-quotes',
                default => 'assigned-quotes',
            };
        }

        return match ($order->status) {
            'Underprocess' => ((int) $order->assign_to > 0 ? 'designer-orders' : 'new-orders'),
            'Ready' => 'designer-completed',
            'done' => 'approval-waiting',
            'approved' => 'approved-orders',
            'disapprove' => 'designer-orders',
            'disapproved' => 'disapproved-orders',
            default => 'all-orders',
        };
    }

    private function normalizeBackContext(mixed $back): string
    {
        $raw = trim((string) $back);

        return match ($raw) {
            'payment-due-report', 'payment-recieved-report', 'all-payment-due', 'payment-recieved' => $raw,
            default => AdminOrderQueues::normalize($raw),
        };
    }

    private function backLabel(string $back): string
    {
        return match ($back) {
            'all-payment-due' => 'Due Payment',
            'payment-recieved' => 'Received Payment',
            'payment-due-report' => 'Payment Due',
            'payment-recieved-report' => 'Payment Received',
            default => AdminOrderQueues::label($back),
        };
    }

    private function backUrl(string $back): string
    {
        return match ($back) {
            'all-payment-due' => url('/v/all-payment-due.php'),
            'payment-recieved' => url('/v/payment-recieved.php'),
            'payment-due-report' => url('/v/payment-due-report.php'),
            'payment-recieved-report' => url('/v/payment-recieved-report.php'),
            default => AdminOrderQueues::url($back),
        };
    }

    private function isBillingReportBack(string $back): bool
    {
        return in_array($back, ['all-payment-due', 'payment-recieved', 'payment-due-report', 'payment-recieved-report'], true);
    }

    private function sendCompletionEmail(Order $order, string $page, string $stitches, string $amount, ?array $override = null): void
    {
        $customer = $order->customer;

        if (
            ! $customer
            || (int) ($customer->usre_type_id ?? 0) !== AdminUser::TYPE_CUSTOMER
            || PortalMailer::normalizeRecipient($customer->user_email) === null
        ) {
            return;
        }

        $site = SiteResolver::fromLegacyKey(
            (string) ($order->website ?: $customer->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        $context = $this->completionEmailContext($order, $page, $stitches, $amount, $site);
        $templateKey = $page === 'quote' ? 'customer_quote_completed' : 'customer_order_completed';

        SystemEmailTemplates::send(
            (string) $customer->user_email,
            $templateKey,
            $site,
            [
                'customer_name' => trim((string) ($customer->display_name ?: $customer->user_name)),
                'customer_email' => (string) $customer->user_email,
                'order_id' => (string) $order->order_id,
                'design_name' => (string) ($order->design_name ?? ''),
                'order_type' => $page === 'quote' ? 'Quote' : 'Order',
                'status' => (string) ($order->status ?? ''),
                'amount' => (string) $amount,
                'stitches' => (string) $stitches,
                'body_label' => (string) $context['bodyLabel'],
                'review_url' => (string) $context['reviewUrl'],
            ],
            fn () => [
                'subject' => $context['title'].' - '.$context['companyName'],
                'body' => view('admin.orders.mail-complete', $context)->render(),
            ],
            $override
        );
    }

    private function completionEmailContext(Order $order, string $page, string $stitches, string $amount, $site = null): array
    {
        $site ??= SiteResolver::fromLegacyKey(
            (string) ($order->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        $baseUrl = $this->customerPortalBaseUrl($site?->host);
        $websiteAddress = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: ($site?->host ?: config('sites.primary_host', 'localhost')));
        $companyName = trim((string) ($site?->brandName ?: $site?->name ?: config('app.name', '')));

        if ($companyName === '' || $companyName === 'Laravel') {
            $companyName = $websiteAddress;
        }

        $title = $page === 'quote' ? 'Quotation Completed' : 'Order Completed';
        $bodyLabel = $page === 'quote'
            ? (($order->order_type === 'vector' || $order->width === '') ? 'Hours' : 'Stitches')
            : ($order->order_type === 'vector' ? 'Time Spent by Artist' : 'Stitches');
        $reviewUrl = $baseUrl.($page === 'quote'
            ? '/view-quote-detail.php?order_id='.$order->order_id
            : '/view-order-detail.php?order_id='.$order->order_id);

        return [
            'title' => $title,
            'companyName' => $companyName,
            'websiteAddress' => $websiteAddress,
            'page' => $page,
            'order' => $order,
            'amount' => $amount,
            'stitches' => $stitches,
            'bodyLabel' => $bodyLabel,
            'reviewUrl' => $reviewUrl,
        ];
    }

    private function sendQuoteNegotiationResponseEmail(Order $order, QuoteNegotiation $negotiation, string $decision, string $amount, string $adminNote, ?array $override = null): void
    {
        $customer = $order->customer;

        if (! $customer) {
            $customer = AdminUser::query()->find($order->user_id);
        }

        if (
            ! $customer
            || (int) ($customer->usre_type_id ?? 0) !== AdminUser::TYPE_CUSTOMER
            || PortalMailer::normalizeRecipient((string) $customer->user_email) === null
        ) {
            return;
        }

        $site = SiteResolver::fromLegacyKey(
            (string) ($order->website ?: $customer->website ?: config('sites.primary_legacy_key', '1dollar'))
        ) ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        $reviewUrl = $this->customerPortalBaseUrl($site?->host)
            .($decision === 'accepted'
                ? '/view-order-detail.php?order_id='.$order->order_id
                : '/view-quote-detail.php?order_id='.$order->order_id);
        $formattedAmount = '$'.number_format((float) $amount, 2);

        SystemEmailTemplates::send(
            (string) $customer->user_email,
            'customer_quote_negotiation_response',
            $site,
            [
                'customer_name' => trim((string) ($customer->display_name ?: $customer->user_name)),
                'customer_email' => (string) $customer->user_email,
                'order_id' => (string) $order->order_id,
                'design_name' => (string) ($order->design_name ?? ''),
                'order_type' => 'Quote Negotiation',
                'status' => match ($decision) {
                    'accepted' => 'Accepted',
                    'countered' => 'Counter Offer Sent',
                    default => 'Request Reviewed',
                },
                'amount' => $formattedAmount,
                'review_url' => $reviewUrl,
                'quotes_url' => $this->customerPortalBaseUrl($site?->host).'/view-quotes.php',
                'portal_url' => $this->customerPortalBaseUrl($site?->host).'/dashboard.php',
                'message' => $this->defaultQuoteNegotiationMessage($decision, $formattedAmount, $adminNote, $negotiation),
            ],
            fn () => [
                'subject' => $this->defaultQuoteNegotiationSubject($decision, $site?->displayLabel() ?: '1Dollar Digitizing'),
                'body' => $this->defaultQuoteNegotiationBody(
                    trim((string) ($customer->display_name ?: $customer->user_name)),
                    (string) ($site?->displayLabel() ?: '1Dollar Digitizing'),
                    $order,
                    $reviewUrl,
                    $this->defaultQuoteNegotiationMessage($decision, $formattedAmount, $adminNote, $negotiation),
                    $formattedAmount
                ),
            ],
            $override
        );
    }

    private function defaultQuoteNegotiationSubject(string $decision, string $siteLabel): string
    {
        return match ($decision) {
            'accepted' => 'Your requested quote amount has been approved - '.$siteLabel,
            'countered' => 'Your quote has a revised price - '.$siteLabel,
            default => 'Your quote request has been reviewed - '.$siteLabel,
        };
    }

    private function defaultQuoteNegotiationMessage(string $decision, string $formattedAmount, string $adminNote, QuoteNegotiation $negotiation): string
    {
        $base = match ($decision) {
            'accepted' => 'We approved your requested target amount of '.$formattedAmount.'.',
            'countered' => 'We reviewed your request and prepared a revised quote of '.$formattedAmount.'.',
            default => 'We reviewed your requested target amount and kept the current quote pricing in place.',
        };

        $note = trim($adminNote) !== '' ? trim($adminNote) : trim((string) ($negotiation->admin_note ?? ''));

        return $note !== '' ? $base.' '.$note : $base;
    }

    private function defaultQuoteNegotiationBody(string $customerName, string $siteLabel, Order $order, string $reviewUrl, string $message, string $formattedAmount): string
    {
        $customerName = e($customerName);
        $siteLabel = e($siteLabel);
        $designName = e((string) ($order->design_name ?? ''));
        $message = e($message);

        return <<<HTML
<p>Hello {$customerName},</p>
<p>{$message}</p>
<p><strong>Reference ID:</strong> {$order->order_id}</p>
<p><strong>Design Name:</strong> {$designName}</p>
<p><strong>Current Amount:</strong> {$formattedAmount}</p>
<p>You can review the latest quote status here:</p>
<p><a href="{$reviewUrl}">Review Quote</a></p>
<p>Kind regards,<br>{$siteLabel}</p>
HTML;
    }

    private function customerPortalBaseUrl(?string $fallbackHost = null): string
    {
        $configuredUrl = trim((string) (config('app.force_url') ?: config('app.url', '')));

        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/');
        }

        $host = trim((string) ($fallbackHost ?: config('sites.primary_host', 'localhost')));
        $scheme = $host === 'localhost' ? 'http' : 'https';

        return $scheme.'://'.$host;
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }
}
