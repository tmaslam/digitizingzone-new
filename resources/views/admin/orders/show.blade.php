@extends('layouts.admin')

@section('title', ($page === 'quote' ? 'Quote' : 'Order').' Detail | Digitizing Zone Admin')
@section('page_heading', ($page === 'quote' ? 'Quote' : 'Order').' Detail '.$order->order_id)
@section('page_subheading', 'Review files, comments, pricing, and completion details for this job.')

@section('content')
    @php
        $turnaround = \App\Support\TurnaroundTracking::summary($order);
    @endphp
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Core Details</h3>
                    <p class="section-copy">Customer, design, status, turnaround, dates, and pricing.</p>
                </div>
                <div class="action-row">
                    <a class="badge" href="{{ $backUrl }}">Back to {{ $backLabel }}</a>
                    @if ($showAssignWorkflow)
                        <a class="badge" href="{{ $assignUrl }}">Assign Workflow</a>
                    @endif
                    @if ($order->customer && (int) ($order->customer->usre_type_id ?? 0) === \App\Models\AdminUser::TYPE_CUSTOMER && (int) ($order->customer->is_active ?? 0) === 1)
                        <form method="post" action="{{ url('/v/simulate-login/'.$order->customer->user_id) }}" onsubmit="return confirm('Start a simulated customer session for support?');">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                            <button type="submit">View As Customer</button>
                        </form>
                    @endif
                    @if ($canMarkPaidOrder)
                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/mark-paid') }}" onsubmit="return confirm('Mark this order as paid?');">
                            @csrf
                            <input type="hidden" name="return_to" value="detail">
                            <input type="hidden" name="detail_page" value="{{ $page }}">
                            <input type="hidden" name="back" value="{{ $backQueue }}">
                            <label for="detail_transaction_id_{{ $order->order_id }}" class="sr-only">Transaction ID</label>
                            <input
                                id="detail_transaction_id_{{ $order->order_id }}"
                                type="text"
                                name="transaction_id"
                                value="{{ old('transaction_id') }}"
                                style="width:150px;"
                                placeholder="Transaction ID"
                                title="Transaction ID"
                                aria-label="transaction id"
                                required
                            >
                            <button type="submit">Mark As Paid (No Email)</button>
                        </form>
                    @elseif ($customerPaidFlag)
                        <span class="badge" style="background:rgba(34,139,94,0.14);color:#1f7a53;border-color:rgba(34,139,94,0.24);">Paid</span>
                    @endif
                    @if ($canApproveOrder)
                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/approve') }}" onsubmit="return confirm('Approve this order?');">
                            @csrf
                            <input type="hidden" name="return_to" value="detail">
                            <input type="hidden" name="detail_page" value="{{ $page }}">
                            <input type="hidden" name="back" value="{{ $backQueue }}">
                            <label for="approved_amount_detail_{{ $order->order_id }}" class="sr-only">Approval Amount</label>
                            <input
                                id="approved_amount_detail_{{ $order->order_id }}"
                                type="number"
                                name="approved_amount"
                                step="0.01"
                                min="0"
                                value="{{ old('approved_amount', $order->total_amount ?: $order->stitches_price ?: '0.00') }}"
                                style="width:140px;"
                                placeholder="Approval Amount"
                                title="Approval Amount"
                                aria-label="Approval amount"
                            >
                            <button type="submit">Approve Order (No Email)</button>
                        </form>
                    @elseif ($approvedBillingFlag || $order->status === 'approved')
                        <span class="badge">Approved</span>
                    @endif
                    @if ($canConvertQuote)
                        <form method="post" action="{{ url('/v/order-detail/convert-quote') }}" onsubmit="return confirm('Convert this quote to an order?');">
                            @csrf
                            <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                            <input type="hidden" name="back" value="{{ $backQueue }}">
                            <button type="submit">Convert To Order</button>
                        </form>
                    @endif
                    @if ($canDeleteOrder)
                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/delete') }}" onsubmit="return confirm('Delete this new order?');">
                            @csrf
                            <input type="hidden" name="queue" value="{{ $backQueue }}">
                            <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete Order</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($canMarkPaidOrder || $canApproveOrder)
                <p class="muted" style="margin:0 0 18px;">
                    Approve Order moves this job into billing. Mark As Paid finalizes an approved invoice as a paid legacy-compatible record. Neither action sends customer, team, or supervisor notification emails.
                </p>
            @endif

            <div class="stats">
                <article class="stat"><span class="muted">Status</span><strong style="font-size:1.25rem;">{{ $order->status ?: '-' }}</strong></article>
                <article class="stat">
                    <span class="muted">Order Type</span>
                    <strong style="font-size:1.1rem;">{{ $order->work_type_label ?: '-' }}</strong>
                    <span class="muted" style="display:block;margin-top:4px;">{{ $order->flow_context_label }} / {{ $order->work_type_label }}</span>
                </article>
                <article class="stat"><span class="muted">Customer</span><strong style="font-size:1.25rem;">{{ $order->customer_name }}</strong></article>
                <article class="stat"><span class="muted">Assigned To</span><strong style="font-size:1.25rem;">{{ $order->assignee_name }}</strong></article>
                <article class="stat"><span class="muted">Amount</span><strong style="font-size:1.25rem;">{{ $offerAdjustedAmount ?? ($order->total_amount ?: '0.00') }}</strong></article>
                <article class="stat"><span class="muted">Payment</span><strong style="font-size:1.25rem;">{{ $customerPaidFlag ? 'Paid' : 'Unpaid' }}</strong></article>
                <article class="stat"><span class="muted">Approval</span><strong style="font-size:1.25rem;">{{ ($approvedBillingFlag || $order->status === 'approved') ? 'Approved' : 'Pending' }}</strong></article>
                <article class="stat"><span class="muted">Customer Delivery</span><strong style="font-size:1.1rem;">{{ $customerDeliveryGate['mode_label'] }}</strong></article>
                <article class="stat"><span class="muted">Record Source</span><strong style="font-size:1.1rem;">{{ $workflowMeta ? ucwords(str_replace('_', ' ', (string) $workflowMeta->created_source)) : 'Customer Submission' }}</strong></article>
            </div>

            <div class="card subcard">
                <div class="card-body">
                    <h4>Customer Delivery Access</h4>
                    <p class="section-copy">{{ $customerDeliveryGate['reason'] }}</p>
                    @if ((float) $customerDeliveryGate['available_balance'] > 0 || (float) $customerDeliveryGate['prepaid_amount'] > 0 || (float) $customerDeliveryGate['outstanding_due'] > 0)
                        <p class="muted" style="margin:0;">Available credit: {{ number_format((float) $customerDeliveryGate['available_balance'], 2) }} | Prepaid balance: {{ number_format((float) $customerDeliveryGate['prepaid_amount'], 2) }} | Outstanding billing: {{ number_format((float) $customerDeliveryGate['outstanding_due'], 2) }}</p>
                    @endif
                    @if ($hasWorkflowMetaTable)
                        <form method="post" action="{{ url('/v/order-detail/delivery-controls') }}" class="toolbar">
                            @csrf
                            <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                            <input type="hidden" name="page" value="{{ $page }}">
                            <input type="hidden" name="back" value="{{ $backQueue }}">
                            <div class="field">
                                <label for="order_credit_limit">Per-Order Release Limit</label>
                                <input id="order_credit_limit" type="number" step="0.01" min="0" name="order_credit_limit" value="{{ old('order_credit_limit', $workflowMeta?->order_credit_limit) }}">
                            </div>
                            <div class="field">
                                <label for="delivery_override">Delivery Rule</label>
                                <select id="delivery_override" name="delivery_override">
                                    <option value="auto" @selected(old('delivery_override', $workflowMeta?->delivery_override ?: 'auto') === 'auto')>Follow Payment / Credit Rules</option>
                                    <option value="preview_only" @selected(old('delivery_override', $workflowMeta?->delivery_override) === 'preview_only')>Preview Files Only</option>
                                </select>
                            </div>
                            <div class="field" style="min-width:auto;">
                                <label>&nbsp;</label>
                                <button type="submit">Save Delivery Controls</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <tbody>
                    <tr><th>Submitted</th><td>{{ $order->submit_date ?: '-' }}</td><th>Completed</th><td>{{ $order->completion_date ?: '-' }}</td></tr>
                    <tr><th>Assigned Date</th><td>{{ $order->assigned_date ?: '-' }}</td><th>Vendor Complete</th><td>{{ $order->vender_complete_date ?: '-' }}</td></tr>
                    <tr><th>Design Name</th><td>{{ $order->design_name ?: '-' }}</td><th>Format</th><td>{{ $order->format ?: '-' }}</td></tr>
                    <tr><th>Fabric Type</th><td>{{ $order->fabric_type ?: '-' }}</td><th>Turnaround</th><td>{{ $turnaround['label_with_timing'] }}</td></tr>
                    <tr><th>Schedule Status</th><td>{{ $turnaround['status_label'] }}</td><th>Time Remaining</th><td>{{ $turnaround['remaining_label'] }}</td></tr>
                    <tr><th>Size</th><td>{{ trim(($order->width ?? '').' x '.($order->height ?? '').' '.($order->measurement ?? '')) }}</td><th>Colors</th><td>{{ $order->no_of_colors ?: 0 }}</td></tr>
                    <tr><th>Color Names</th><td>{{ trim((string) $order->color_names) !== '' ? $order->color_names : '-' }}</td><th>Applique Count</th><td>{{ (string) $order->appliques === 'yes' ? ($order->no_of_appliques ?: 0) : '-' }}</td></tr>
                    <tr><th>Appliques</th><td>{{ $order->appliques ?: '-' }}</td><th>Stitches / Hours</th><td>{{ $order->stitches ?: '-' }}</td></tr>
                    <tr><th>Applique Colors</th><td>{{ trim((string) $order->applique_colors) !== '' ? $order->applique_colors : '-' }}</td><th>Customer Email</th><td>{{ $order->customer?->user_email ?: '-' }}</td></tr>
                    <tr><th>Customer</th><td>{{ $order->customer?->display_name ?: '-' }}</td><th></th><td></td></tr>
                    </tbody>
                </table>
            </div>

            @if (in_array($page, ['quote', 'vector']) && $latestQuoteNegotiation)
                <div class="card subcard" style="margin-top:18px;">
                    <div class="card-body">
                        <h4>Customer Price Response</h4>
                        <p class="section-copy">The customer rejected this quote and sent pricing feedback for admin review.</p>
                        <div class="table-wrap">
                            <table>
                                <tbody>
                                <tr>
                                    <th>Review Status</th>
                                    <td>{{ ucwords(str_replace('_', ' ', (string) $latestQuoteNegotiation->status)) }}</td>
                                    <th>Target Amount</th>
                                    <td>{{ $latestQuoteNegotiation->customer_target_amount !== null ? '$'.number_format((float) $latestQuoteNegotiation->customer_target_amount, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Reason</th>
                                    <td colspan="3">{{ ucwords(str_replace('_', ' ', (string) $latestQuoteNegotiation->customer_reason_code)) ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Customer Notes</th>
                                    <td colspan="3">{{ trim((string) $latestQuoteNegotiation->customer_reason_text) !== '' ? $latestQuoteNegotiation->customer_reason_text : '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        @if (in_array((string) $latestQuoteNegotiation->status, ['pending_admin_review', 'customer_replied'], true))
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-top:16px;">
                                @if ($latestQuoteNegotiation->customer_target_amount !== null)
                                    <form method="post" action="{{ url('/v/order-detail/respond-quote-negotiation') }}" class="card subcard">
                                        @csrf
                                        <div class="card-body">
                                            <h5 style="margin:0 0 10px;">Accept Requested Price</h5>
                                            <p class="muted" style="margin:0 0 12px;">Approve the customer's requested amount and reopen the quote for acceptance.</p>
                                            <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                            <input type="hidden" name="negotiation_id" value="{{ $latestQuoteNegotiation->id }}">
                                            <input type="hidden" name="page" value="{{ $page }}">
                                            <input type="hidden" name="back" value="{{ $backQueue }}">
                                            <input type="hidden" name="action" value="accept">
                                        <label style="display:block;margin-bottom:12px;">
                                            Admin Note
                                            <textarea name="admin_note" rows="3" placeholder="Optional note for the customer."></textarea>
                                        </label>
                                        <label style="display:block;margin-bottom:12px;">
                                            Customer Email Template
                                            <select name="customer_email_template_id">
                                                <option value="">Use Standard Template</option>
                                                @foreach ($negotiationEmailTemplateOptions as $templateOption)
                                                    <option value="{{ $templateOption['id'] }}" @selected((string) old('customer_email_template_id') === (string) $templateOption['id'])>{{ $templateOption['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <button type="submit">Accept Requested Price</button>
                                    </div>
                                </form>
                                @endif

                                <form method="post" action="{{ url('/v/order-detail/respond-quote-negotiation') }}" class="card subcard">
                                    @csrf
                                    <div class="card-body">
                                        <h5 style="margin:0 0 10px;">Reject Or Counter</h5>
                                        <p class="muted" style="margin:0 0 12px;">Reject the requested price, or enter a revised amount to send a counter offer.</p>
                                        <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                        <input type="hidden" name="negotiation_id" value="{{ $latestQuoteNegotiation->id }}">
                                        <input type="hidden" name="page" value="{{ $page }}">
                                        <input type="hidden" name="back" value="{{ $backQueue }}">
                                        <input type="hidden" name="action" value="reject">
                                        <label style="display:block;margin-bottom:12px;">
                                            Counter Offer Amount
                                            <input type="number" name="admin_counter_amount" min="0.01" step="0.01" value="{{ old('admin_counter_amount', $order->total_amount ?: $order->stitches_price ?: '') }}" placeholder="Leave blank to keep the current quote">
                                        </label>
                                        <label style="display:block;margin-bottom:12px;">
                                            Admin Note
                                            <textarea name="admin_note" rows="3" placeholder="Explain the response for the customer.">{{ old('admin_note') }}</textarea>
                                        </label>
                                        <label style="display:block;margin-bottom:12px;">
                                            Customer Email Template
                                            <select name="customer_email_template_id">
                                                <option value="">Use Standard Template</option>
                                                @foreach ($negotiationEmailTemplateOptions as $templateOption)
                                                    <option value="{{ $templateOption['id'] }}" @selected((string) old('customer_email_template_id') === (string) $templateOption['id'])>{{ $templateOption['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <button type="submit">Send Response</button>
                                    </div>
                                </form>
                            </div>
                        @else
                            <p class="muted" style="margin:16px 0 0;">This negotiation has already been reviewed by admin.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Attachments</h3>
                    <p class="section-copy">All files related to this job are grouped by source.</p>
                </div>
            </div>

            @foreach (['complaint' => 'Customer Complaint Attachments', 'order' => 'Order Image Attachments', 'team' => 'Team / Sewout Attachments'] as $key => $label)
                <div class="card subcard">
                    <div class="card-body">
                        <div class="section-head">
                            <h4>{{ $label }}</h4>
                            @if (in_array($key, ['complaint', 'order', 'team'], true))
                                <form method="post" action="{{ url('/v/order-detail/upload') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                    <input type="hidden" name="page" value="{{ $page }}">
                                    <input type="hidden" name="back" value="{{ $backQueue }}">
                                    <input type="hidden" name="source" value="{{ $key === 'order' ? 'customer' : $key }}">
                                    <input type="file" name="files[]" multiple accept="{{ in_array($key, ['complaint', 'order'], true) ? \App\Support\UploadSecurity::acceptAttribute('source') : \App\Support\UploadSecurity::acceptAttribute('production') }}">
                                    <button type="submit">
                                        {{ match($key) {
                                            'complaint' => 'Upload Customer Complaint Files',
                                            'order' => 'Upload Customer Source Files',
                                            'team' => 'Upload Team Files',
                                        } }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        @if (in_array($key, ['complaint', 'order'], true))
                            <p class="muted" style="margin:12px 0 0;">Use this section to add or replace customer-provided files when work arrived by email or needs admin cleanup before assignment.</p>
                        @endif

                        @if ($key === 'team' && $page !== 'quote')
                            @php
                                $customerReleaseFormId = 'customer-release-'.$order->order_id.'-'.$key;
                            @endphp
                            <form id="{{ $customerReleaseFormId }}" method="post" action="{{ url('/v/order-detail/select-for-customer') }}" style="display:none;">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                <input type="hidden" name="page" value="{{ $page }}">
                                <input type="hidden" name="back" value="{{ $backQueue }}">
                            </form>
                            <p class="muted" style="margin:16px 0 12px;">Select any finished files you want available on the customer side. The website will still enforce preview-only versus full download based on its existing payment, credit, and file-extension rules.</p>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px;">
                                <button type="button" class="badge" data-select-all-files="{{ $customerReleaseFormId }}">Select All Files</button>
                                <button type="button" class="badge" data-clear-all-files="{{ $customerReleaseFormId }}">Clear All Files</button>
                            </div>
                        @endif

                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    @if ($key === 'team' && $page !== 'quote')
                                        <th>Select</th>
                                    @endif
                                    <th>File</th>
                                    <th>Source</th>
                                    <th>Added</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if (collect($attachmentGroups[$key])->isEmpty())
                                    <tr><td colspan="{{ $key === 'team' && $page !== 'quote' ? 5 : 4 }}"><div class="empty-state">No attachments in this section.</div></td></tr>
                                @else
                                @foreach ($attachmentGroups[$key] as $attachment)
                                    <tr>
                                        @if ($key === 'team' && $page !== 'quote')
                                            <td>
                                                <input
                                                    form="{{ $customerReleaseFormId }}"
                                                    type="checkbox"
                                                    name="attachment_ids[]"
                                                    value="{{ $attachment->id }}"
                                                    data-customer-release-checkbox="{{ $customerReleaseFormId }}"
                                                    {{ in_array($attachment->file_source, ['sewout', 'scanned'], true) ? 'checked' : '' }}
                                                >
                                            </td>
                                        @endif
                                        <td><a href="{{ url('/v/attachments/'.$attachment->id.'/download') }}">{{ $attachment->file_name_with_order_id ?: $attachment->file_name }}</a></td>
                                        <td>{{ $attachment->file_source }}</td>
                                        <td>{{ $attachment->date_added ?: '-' }}</td>
                                        <td>
                                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                                @if (\App\Support\AttachmentPreview::isSupported((string) ($attachment->file_name ?: $attachment->file_name_with_order_id)))
                                                    @php
                                                        $previewName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name);
                                                        $previewKind = \App\Support\AttachmentPreview::kindForFileName($previewName);
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="badge"
                                                        data-preview-link
                                                        data-preview-kind="{{ $previewKind }}"
                                                        data-preview-url="{{ url('/v/attachments/'.$attachment->id.'/preview/raw') }}"
                                                        data-preview-download="{{ url('/v/attachments/'.$attachment->id.'/download') }}"
                                                        data-preview-title="{{ $previewName }}"
                                                        data-preview-fallback="{{ url('/v/attachments/'.$attachment->id.'/preview?page='.$page.'&back='.rawurlencode($backQueue)) }}"
                                                    >
                                                        Preview
                                                    </button>
                                                @endif
                                                <form method="post" action="{{ url('/v/attachments/'.$attachment->id.'/delete?oid='.$order->order_id.'&page='.$page.'&back='.rawurlencode($backQueue)) }}" onsubmit="return confirm('Delete this attachment?');">
                                                    @csrf
                                                    <button type="submit">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>

                        @if ($key === 'team' && $page !== 'quote')
                            <div><button type="submit" form="{{ $customerReleaseFormId }}">Select Files For Customer</button></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Comments</h3>
                    <p class="section-copy">Customer complaint notes, admin/customer-facing comments, and team comments.</p>
                </div>
            </div>

            @php
                $commentSections = [
                    'customerComments' => ['label' => 'Customer Comments', 'items' => $customerComments, 'newSource' => null],
                    'adminComments' => ['label' => 'Admin / Customer Comments', 'items' => $adminComments, 'newSource' => 'customer'],
                    'teamComments' => ['label' => 'Team Comments', 'items' => $teamComments, 'newSource' => 'team'],
                ];
            @endphp

            @foreach ($commentSections as $section)
                <div class="card subcard">
                    <div class="card-body">
                        <h4 style="margin:0 0 12px;">{{ $section['label'] }}</h4>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Comment</th>
                                    <th>Source</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if (collect($section['items'])->isEmpty())
                                    <tr><td colspan="4" class="muted">No comments in this section.</td></tr>
                                @else
                                @foreach ($section['items'] as $comment)
                                    <tr>
                                        <td>{{ $comment->comments }}</td>
                                        <td>{{ $comment->comment_source }}</td>
                                        <td>{{ $comment->date_modified ?: $comment->date_added ?: '-' }}</td>
                                        <td>
                                            <form method="post" action="{{ url('/v/comments/'.$comment->id.'/delete?oid='.$order->order_id.'&page='.$page.'&back='.rawurlencode($backQueue)) }}" onsubmit="return confirm('Delete this comment?');">
                                                @csrf
                                                <button type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>

                        @if ($section['newSource'])
                            <form method="post" action="{{ url('/v/order-detail/comments') }}" style="margin-top:16px;">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                <input type="hidden" name="page" value="{{ $page }}">
                                <input type="hidden" name="back" value="{{ $backQueue }}">
                                <input type="hidden" name="comment_source" value="{{ $section['newSource'] }}">
                                <div class="field" style="min-width:100%;">
                                    <label>{{ $section['newSource'] === 'customer' ? 'Add Customer-Facing Comment' : 'Add Team Comment' }}</label>
                                    <textarea name="comments" rows="4"></textarea>
                                </div>
                                <div style="margin-top:12px;"><button type="submit">Add Comment</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($canCompleteFromAdmin)
        <section class="card">
            <div class="card-body">
                <h3 style="margin:0 0 6px;font-size:1.15rem;">Complete the {{ $page === 'quote' ? 'Quotation' : 'Order' }}</h3>
                <p class="muted" style="margin:0 0 18px;">
                    Update pricing, status, and completion details for this job.
                    @if (in_array((string) $order->assign_to, ['', '0'], true) && strtolower((string) $order->status) === 'underprocess')
                        This order is not assigned to team or supervisor yet, so admin can complete it directly here when needed.
                    @endif
                </p>

                <form method="post" action="{{ url('/v/order-detail/complete') }}" class="toolbar" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:start;">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                    <input type="hidden" name="page" value="{{ $page }}">
                    <input type="hidden" name="back" value="{{ $backQueue }}">

                    @if ($page === 'vector')
                        @php
                            $normalizedCompletionHours = \App\Support\TeamPricing::normalizeHours((string) old('stitches', $order->stitches));
                            [$completionHoursPart, $completionMinutesPart] = $normalizedCompletionHours
                                ? explode(':', $normalizedCompletionHours, 2)
                                : ['', '0'];
                        @endphp
                        <div class="field">
                            <label>Total Work Time</label>
                            <input type="hidden" id="stitches" name="stitches" value="{{ old('stitches', $order->stitches) }}">
                            <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px;">
                                <div>
                                    <input
                                        id="work_hours"
                                        type="number"
                                        name="work_hours"
                                        min="0"
                                        step="1"
                                        value="{{ old('work_hours', $completionHoursPart) }}"
                                        placeholder="Hours"
                                        inputmode="numeric"
                                    >
                                </div>
                                <div>
                                    <input
                                        id="work_minutes"
                                        type="number"
                                        name="work_minutes"
                                        min="0"
                                        max="59"
                                        step="1"
                                        value="{{ old('work_minutes', $completionMinutesPart) }}"
                                        placeholder="Minutes"
                                        inputmode="numeric"
                                    >
                                </div>
                            </div>
                            <span class="muted">Enter the total production time for this job. Example: `8` hours and `30` minutes for an 8:30 job.</span>
                        </div>
                    @else
                        <div class="field">
                            <label for="stitches">No. Of Stitches</label>
                            <input
                                id="stitches"
                                type="text"
                                name="stitches"
                                value="{{ old('stitches', $order->stitches) }}"
                                inputmode="decimal"
                                placeholder="Enter total stitches"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <span class="muted">Enter the final stitch count used to price and complete this job.</span>
                        </div>
                    @endif

                    <div class="field">
                        <label for="ddlStatus">Status</label>
                        <select id="ddlStatus" name="ddlStatus">
                            <option value="done" @selected(old('ddlStatus', 'done') === 'done')>Vendor Complete</option>
                            @if ($page !== 'quote')
                                <option value="Disapproved" @selected(old('ddlStatus') === 'Disapproved')>Disapproved</option>
                            @endif
                        </select>
                    </div>

                    @if ($advancePayment)
                        <div class="field">
                            <label for="orderpaid">Advance Payment</label>
                            <select id="orderpaid" name="orderpaid">
                                <option value="unpaid">Reset Advance / Mark Unpaid</option>
                            </select>
                        </div>
                    @endif

                    @if ($workflowMeta && in_array((string) $workflowMeta->created_source, ['admin_assisted', 'admin_backfill'], true))
                        <div class="field">
                            <label for="send_customer_notification">Customer Notification</label>
                            <select id="send_customer_notification" name="send_customer_notification">
                                <option value="1" @selected(old('send_customer_notification', $sendCustomerNotificationDefault ? '1' : '0') === '1')>Send Completion Email</option>
                                <option value="0" @selected(old('send_customer_notification', $sendCustomerNotificationDefault ? '1' : '0') === '0')>Do Not Send Completion Email</option>
                            </select>
                        </div>
                    @endif

                    <div class="field">
                        <label for="stamount">Amount</label>
                        <input id="stamount" type="number" step="0.01" min="0" name="stamount" value="{{ old('stamount', $offerAdjustedAmount ?? ($advancePayment ? max(((float) $order->stitches_price - (float) $advancePayment->advance_pay), 0) : $order->stitches_price)) }}">
                        <span class="muted">Leave blank to calculate from stitches or hours.</span>
                    </div>

                    <div class="field" style="min-width:100%;">
                        <label>Email Trigger</label>
                        <div
                            id="order-email-guidance"
                            class="muted"
                            data-customer-email="{{ $order->customer?->user_email ?: '' }}"
                            data-is-admin-created="{{ $workflowMeta && in_array((string) $workflowMeta->created_source, ['admin_assisted', 'admin_backfill'], true) ? '1' : '0' }}"
                            style="padding:12px 14px;border:1px solid rgba(32,64,96,0.12);border-radius:14px;background:#f7fafc;"
                        ></div>
                    </div>

                    <div class="field" style="grid-column:1 / -1;">
                        <label for="customer_email_template_id">Customer Email Template</label>
                        <select id="customer_email_template_id" name="customer_email_template_id">
                            <option value="">Use Standard Template</option>
                            @foreach ($completionEmailTemplateOptions as $templateOption)
                                <option value="{{ $templateOption['id'] }}" @selected((string) old('customer_email_template_id') === (string) $templateOption['id'])>{{ $templateOption['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="muted">Only completion templates for this workflow are shown here.</span>
                    </div>

                    <div style="grid-column:1 / -1;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;padding-top:2px;">
                        <span class="muted">Review the status and email guidance before saving this update.</span>
                        <button type="submit">Save Completion</button>
                    </div>
                </form>

                @if ($advancePayment)
                    <div style="margin-top:18px;" class="muted">
                        Advance received: {{ $advancePayment->advance_pay }}. Current total before adjustment: {{ $order->stitches_price }}.
                    </div>
                @endif
            </div>
        </section>
    @endif
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-select-all-files]').forEach(function (button) {
            button.addEventListener('click', function () {
                const formId = button.getAttribute('data-select-all-files');
                document.querySelectorAll('[data-customer-release-checkbox="' + formId + '"]').forEach(function (checkbox) {
                    checkbox.checked = true;
                });
            });
        });

        document.querySelectorAll('[data-clear-all-files]').forEach(function (button) {
            button.addEventListener('click', function () {
                const formId = button.getAttribute('data-clear-all-files');
                document.querySelectorAll('[data-customer-release-checkbox="' + formId + '"]').forEach(function (checkbox) {
                    checkbox.checked = false;
                });
            });
        });

        const stitchesField = document.getElementById('stitches');
        const workHoursField = document.getElementById('work_hours');
        const workMinutesField = document.getElementById('work_minutes');
        const amountField = document.getElementById('stamount');
        const tokenField = document.querySelector('input[name="_token"]');
        const statusField = document.getElementById('ddlStatus');
        const sendNotificationField = document.getElementById('send_customer_notification');
        const orderEmailGuidance = document.getElementById('order-email-guidance');

        function updateOrderEmailGuidance() {
            if (!orderEmailGuidance || !statusField) {
                return;
            }

            const customerEmail = orderEmailGuidance.dataset.customerEmail || '';
            const isAdminCreated = orderEmailGuidance.dataset.isAdminCreated === '1';
            const status = statusField.value;
            const notificationEnabled = sendNotificationField ? sendNotificationField.value === '1' : true;

            if (status !== 'done') {
                orderEmailGuidance.textContent = 'No customer email will be sent for this status.';
                return;
            }

            if (customerEmail === '') {
                orderEmailGuidance.textContent = 'This action would normally send a completion email, but no valid customer email is available on this order.';
                return;
            }

            if (isAdminCreated && !notificationEnabled) {
                orderEmailGuidance.textContent = 'Customer completion email is turned off for this save. No email will be sent.';
                return;
            }

            orderEmailGuidance.textContent = 'Saving as Vendor Complete will send a completion email to ' + customerEmail + '.';
        }

        function syncWorkTime() {
            if (!stitchesField || !workHoursField || !workMinutesField) {
                return;
            }

            const hours = (workHoursField.value || '').trim();
            const minutesRaw = (workMinutesField.value || '').trim();

            if (hours === '' && minutesRaw === '') {
                stitchesField.value = '';
                return;
            }

            const normalizedHours = hours === '' ? '0' : String(parseInt(hours, 10) || 0);
            const normalizedMinutes = minutesRaw === '' ? '0' : String(parseInt(minutesRaw, 10) || 0);
            const paddedMinutes = normalizedMinutes.padStart(2, '0');

            stitchesField.value = normalizedHours + ':' + paddedMinutes;
        }

        async function previewCompletionPrice() {
            const stitches = stitchesField ? stitchesField.value.trim() : '';

            if (stitches === '') {
                return;
            }

            try {
                const response = await fetch("{{ url('/v/order-detail/price-preview') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tokenField.value,
                    },
                    body: JSON.stringify({
                        order_id: {{ (int) $order->order_id }},
                        stitches: stitches,
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    return;
                }

                stitchesField.value = data.stitches ?? stitchesField.value;
                amountField.value = data.amount ?? amountField.value;

                if (workHoursField && workMinutesField && stitchesField.value.includes(':')) {
                    const [hoursPart, minutesPart] = stitchesField.value.split(':', 2);
                    workHoursField.value = hoursPart ?? '';
                    workMinutesField.value = minutesPart ?? '00';
                }
            } catch (error) {
                // Keep the form usable even if preview is unavailable.
            }
        }

        if (!stitchesField || !amountField || !tokenField) {
            updateOrderEmailGuidance();
            if (statusField) {
                statusField.addEventListener('change', updateOrderEmailGuidance);
            }
            if (sendNotificationField) {
                sendNotificationField.addEventListener('change', updateOrderEmailGuidance);
            }
            return;
        }

        if (workHoursField && workMinutesField) {
            const handleWorkTimePreview = function () {
                syncWorkTime();
                previewCompletionPrice();
            };

            workHoursField.addEventListener('blur', handleWorkTimePreview);
            workMinutesField.addEventListener('blur', handleWorkTimePreview);
            workHoursField.addEventListener('change', handleWorkTimePreview);
            workMinutesField.addEventListener('change', handleWorkTimePreview);
        } else {
            stitchesField.addEventListener('blur', previewCompletionPrice);
        }

        // If stitches are already filled on page load (e.g. team completed the order),
        // fire the price preview immediately so the amount field reflects the offer.
        if (stitchesField && stitchesField.value.trim() !== '') {
            if (workHoursField && workMinutesField) {
                syncWorkTime();
                previewCompletionPrice();
            } else {
                stitchesField.dispatchEvent(new Event('blur'));
            }
        }

        if (statusField) {
            statusField.addEventListener('change', updateOrderEmailGuidance);
        }
        if (sendNotificationField) {
            sendNotificationField.addEventListener('change', updateOrderEmailGuidance);
        }
        updateOrderEmailGuidance();
    });
    </script>
@endsection
