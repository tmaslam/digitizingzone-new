@extends('layouts.admin')

@section('title', 'Quick Quote #'.$order->order_id.' | 1Dollar Admin')
@section('page_heading', 'Quick Quote Detail #'.$order->order_id)
@section('page_subheading', 'Review quote details, files, comments, and pricing.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Core Details</h3>
                    <p class="muted" style="margin:0;">Quick-quote customer, design, dates, pricing, and attachments.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ url('/v/ordersquick.php?page=Quick%20Quotes%20List') }}">Back to Quick Quotes</a>
                </div>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Status</span><strong style="font-size:1.25rem;">{{ $order->status ?: '-' }}</strong></article>
                <article class="stat">
                    <span class="muted">Order Type</span>
                    <strong style="font-size:1.1rem;">{{ $order->order_type ?: '-' }}</strong>
                    <span class="muted" style="display:block;margin-top:4px;">{{ $order->flow_context_label }} / {{ $order->work_type_label }}</span>
                </article>
                <article class="stat"><span class="muted">Customer</span><strong style="font-size:1.25rem;">{{ $quoteCustomer->customer_name ?? '-' }}</strong></article>
                <article class="stat"><span class="muted">Amount</span><strong style="font-size:1.25rem;">{{ $order->total_amount ?: '0.00' }}</strong></article>
                <article class="stat"><span class="muted">Email</span><strong style="font-size:1.05rem;">{{ $quoteCustomer->customer_email ?? '-' }}</strong></article>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <tbody>
                    <tr><th>Submitted</th><td>{{ $order->submit_date ?: '-' }}</td><th>Completed</th><td>{{ $order->completion_date ?: '-' }}</td></tr>
                    <tr><th>Assigned Date</th><td>{{ $order->assigned_date ?: '-' }}</td><th>Vendor Complete</th><td>{{ $order->vender_complete_date ?: '-' }}</td></tr>
                    <tr><th>Design Name</th><td>{{ $order->design_name ?: '-' }}</td><th>Format</th><td>{{ $order->format ?: '-' }}</td></tr>
                    <tr><th>Turnaround</th><td>{{ $order->turn_around_time ?: '-' }}</td><th>Hours / Stitches</th><td>{{ $order->stitches ?: '-' }}</td></tr>
                    <tr><th>Flow</th><td>Quick Quote</td><th>Work Type</th><td>{{ $order->work_type_label }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Attachments</h3>
            <p class="muted" style="margin:0 0 18px;">Files for this quick quote are grouped by source.</p>

            @php
                $groups = [
                    'complaint' => 'Customer Complaint Attachments',
                    'order' => 'Quote Image Attachments',
                ];
            @endphp

            @foreach ($groups as $key => $label)
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-body">
                        <h4 style="margin:0;">{{ $label }}</h4>
                        <div class="table-wrap" style="margin-top:14px;">
                            <table>
                                <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Source</th>
                                    <th>Added</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if (collect($attachmentGroups[$key])->isEmpty())
                                    <tr><td colspan="4" class="muted">No attachments in this section.</td></tr>
                                @else
                                @foreach ($attachmentGroups[$key] as $attachment)
                                    <tr>
                                        <td><a href="{{ url('/v/quick-attachments/'.$attachment->id.'/download') }}">{{ $attachment->file_name_with_order_id ?: $attachment->file_name }}</a></td>
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
                                                        data-preview-url="{{ url('/v/quick-attachments/'.$attachment->id.'/preview/raw') }}"
                                                        data-preview-download="{{ url('/v/quick-attachments/'.$attachment->id.'/download') }}"
                                                        data-preview-title="{{ $previewName }}"
                                                        data-preview-fallback="{{ url('/v/quick-attachments/'.$attachment->id.'/preview') }}"
                                                    >
                                                        Preview
                                                    </button>
                                                @endif
                                                <form method="post" action="{{ url('/v/quick-attachments/'.$attachment->id.'/delete') }}" onsubmit="return confirm('Delete this attachment?');">
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
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Comments</h3>
            <p class="muted" style="margin:0 0 18px;">Customer, admin, and team comments against this quick quote.</p>

            @php
                $commentSections = [
                    'customerComments' => ['label' => 'Customer Comments', 'items' => $customerComments, 'newSource' => null],
                    'adminComments' => ['label' => 'Admin / Customer Comments', 'items' => $adminComments, 'newSource' => 'customer'],
                    'teamComments' => ['label' => 'Team Comments', 'items' => $teamComments, 'newSource' => 'team'],
                ];
            @endphp

            @foreach ($commentSections as $section)
                <div class="card" style="margin-bottom:16px;">
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
                                            <form method="post" action="{{ url('/v/quick-comments/'.$comment->id.'/delete') }}" onsubmit="return confirm('Delete this comment?');">
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
                            <form method="post" action="{{ url('/v/quick-order/comments') }}" style="margin-top:16px;">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                <input type="hidden" name="comment_source" value="{{ $section['newSource'] }}">
                                <div class="field">
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

    @if ($canCompleteQuote)
        <section class="card">
            <div class="card-body">
                <h3 style="margin:0 0 6px;font-size:1.15rem;">Complete the Quick Quote</h3>
                <p class="muted" style="margin:0 0 18px;">Update stitches, amount, and final status for this quick quote. Admin can step in here for ready work or already assigned team work.</p>

                <form method="post" action="{{ url('/v/quick-order/complete') }}" class="toolbar">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->order_id }}">

                    <div class="field">
                        <label for="stitches">No. Of Stitches</label>
                        <input id="stitches" type="text" name="stitches" value="{{ $order->stitches }}" inputmode="decimal">
                    </div>

                    <div class="field">
                        <label for="stamount">Amount</label>
                        <input id="stamount" type="text" name="stamount" value="{{ $order->total_amount !== 'first order is free' ? $order->stitches_price : $order->total_amount }}">
                    </div>

                    <div class="field">
                        <label for="ddlStatus">Order Status</label>
                        <select id="ddlStatus" name="ddlStatus">
                            <option value="done">Vendor Complete</option>
                            <option value="Disapproved">Disapproved</option>
                        </select>
                    </div>

                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Save Completion</button>
                        <span class="muted" style="display:block;margin-top:8px;">Check the Email Trigger box below before saving.</span>
                    </div>

                    <div class="field" style="min-width:100%;">
                        <label>Email Trigger</label>
                        <div
                            id="quick-quote-email-guidance"
                            class="muted"
                            data-customer-email="{{ $quoteCustomer->customer_email ?? '' }}"
                            style="padding:12px 14px;border:1px solid rgba(32,64,96,0.12);border-radius:14px;background:#f7fafc;"
                        ></div>
                    </div>

                    <div class="field" style="min-width:100%;">
                        <label for="customer_email_template_id">Customer Email Template</label>
                        <select id="customer_email_template_id" name="customer_email_template_id">
                            <option value="">Use Standard Template</option>
                            @foreach ($completionEmailTemplateOptions as $templateOption)
                                <option value="{{ $templateOption['id'] }}" @selected((string) old('customer_email_template_id') === (string) $templateOption['id'])>{{ $templateOption['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="muted" style="display:block;margin-top:8px;">Only quick quote completion templates are shown here.</span>
                    </div>
                </form>

                <p class="muted" style="margin:14px 0 0;">
                    Saving as Disapproved does not send any customer, team, or supervisor notification email.
                </p>
            </div>
        </section>
    @endif
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusField = document.getElementById('ddlStatus');
        const guidance = document.getElementById('quick-quote-email-guidance');

        function updateQuickQuoteEmailGuidance() {
            if (!statusField || !guidance) {
                return;
            }

            const customerEmail = guidance.dataset.customerEmail || '';

            if (statusField.value !== 'done') {
                guidance.textContent = 'No customer email will be sent for this status.';
                return;
            }

            if (customerEmail === '') {
                guidance.textContent = 'This action would normally send a completion email, but no valid quick-quote customer email is available.';
                return;
            }

            guidance.textContent = 'Saving as Vendor Complete will send the quick-quote completion email to ' + customerEmail + '.';
        }

        if (statusField) {
            statusField.addEventListener('change', updateQuickQuoteEmailGuidance);
        }

        updateQuickQuoteEmailGuidance();
    });
    </script>
@endsection
