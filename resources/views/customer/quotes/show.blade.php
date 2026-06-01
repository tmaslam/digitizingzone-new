@extends('layouts.customer')

@section('title', 'Quote Detail - '.$order->order_id)
@section('hero_title', 'Quote Detail #'.$order->order_id)
@section('hero_text', 'Review your quote files, accept the price when it works for you, or reject it with a reason when it does not.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>{{ $order->design_name }}</h3>
                <p>Current status: <span class="status {{ $statusTone }}">{{ $statusLabel }}</span></p>
                <p>{{ $statusHint }}</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a class="button secondary" href="{{ $backLink['url'] }}">{{ $backLink['label'] }}</a>
                <form method="post" action="{{ url('/quotes/' . $order->order_id . '/delete') }}" onsubmit="return confirm('Delete this quote?');">
                    @csrf
                    <button type="submit" class="button danger">Delete Quote</button>
                </form>
            </div>
        </div>

        <ul class="order-meta-strip">
            <li><dt>Format</dt><dd>{{ $order->format ?: '-' }}</dd></li>
            <li><dt>Quoted Price</dt><dd>{{ ($latestQuoteNegotiation && (string) $latestQuoteNegotiation->status === 'accepted_by_admin' && $latestQuoteNegotiation->customer_target_amount !== null) ? number_format((float) $latestQuoteNegotiation->customer_target_amount, 2) : ($order->total_amount ?: $order->stitches_price ?: '0.00') }}</dd></li>
            <li><dt>Turnaround</dt><dd>{{ $order->turn_around_time ?: '-' }}</dd></li>
            <li><dt>Completion Date</dt><dd>{{ $order->completion_date ?: '-' }}</dd></li>
            <li><dt>Appliques</dt><dd>{{ $order->appliques ?: '-' }}</dd></li>
            <li><dt>Size</dt><dd>{{ trim(($order->width ?? '').' x '.($order->height ?? '').' '.($order->measurement ?? '')) ?: '-' }}</dd></li>
            <li><dt>Colors</dt><dd>{{ $order->no_of_colors ?: '-' }}</dd></li>
        </ul>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Quote Files</h3>
                <p>{{ $previewNotice }}</p>
            </div>
        </div>
        <div class="detail-grid">
            <article class="list-card">
                <div class="card-head">
                    <h4>Submitted Files</h4>
                </div>
                @if ($sourceAttachments->count())
                    <ul class="file-list">
                        @foreach ($sourceAttachments as $attachment)
                            @php
                                $sourceDisplayName = (string) ($attachment->file_name ?: $attachment->file_name_with_date);
                                $sourcePreviewKind = \App\Support\AttachmentPreview::kindForFileName($sourceDisplayName);
                                $sourcePreviewUrl = url('/preview.php?attachment_id='.$attachment->id);
                                $sourceDownloadUrl = url('/download.php?attachment_id='.$attachment->id);
                            @endphp
                            <li class="file-item">
                                <strong>{{ $sourceDisplayName }}</strong>
                                <div class="file-actions">
                                    <a class="button secondary" href="{{ $sourceDownloadUrl }}">Download</a>
                                    @if (\App\Support\CustomerAttachmentAccess::previewAllowed($order, $attachment) && $sourcePreviewKind)
                                        <button
                                            type="button"
                                            class="button secondary"
                                            data-preview-link
                                            data-preview-url="{{ $sourcePreviewUrl }}"
                                            data-preview-kind="{{ $sourcePreviewKind }}"
                                            data-preview-title="{{ $sourceDisplayName }}"
                                            data-preview-download="{{ $sourceDownloadUrl }}"
                                            data-preview-fallback="{{ $sourcePreviewUrl }}"
                                        >Preview</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty-state">No submitted files are attached to this quote.</div>
                @endif
            </article>

            <article class="list-card">
                <div class="card-head">
                    <h4>Released Files</h4>
                    <p>Use preview for images and PDF files, and download any released production files shown here.</p>
                </div>
                @if ($lockedReleasedCount > 0)
                    <div class="content-note" style="margin-bottom:14px;">
                        {{ $lockedReleasedCount }} production file{{ $lockedReleasedCount === 1 ? '' : 's' }} {{ $lockedReleasedCount === 1 ? 'requires' : 'require' }} payment before downloading. Complete payment to unlock.
                    </div>
                @endif
                @if ($releasedAttachments->count())
                    <ul class="file-list">
                        @foreach ($releasedAttachments as $attachment)
                            @php
                                $downloadAllowed = \App\Support\CustomerAttachmentAccess::attachmentAllowedForCustomer($order, $attachment);
                                $previewAllowed = \App\Support\CustomerAttachmentAccess::previewAllowed($order, $attachment);
                                $previewOnlyAccess = ! $releaseSummary['full_release_allowed'] && \App\Support\CustomerReleaseGate::isPreviewAttachment($attachment);
                                $releasedDisplayName = (string) ($attachment->file_name ?: $attachment->file_name_with_date);
                                $releasedPreviewKind = \App\Support\AttachmentPreview::kindForFileName($releasedDisplayName);
                                $releasedPreviewUrl = url('/preview.php?attachment_id='.$attachment->id);
                                $releasedDownloadUrl = url('/download.php?attachment_id='.$attachment->id);
                            @endphp
                            <li class="file-item">
                                <strong>{{ $releasedDisplayName }}</strong>
                                <div class="file-actions">
                                    @if ($downloadAllowed)
                                        <a class="button secondary" href="{{ $releasedDownloadUrl }}">{{ $previewOnlyAccess ? 'Download Preview' : 'Download' }}</a>
                                        @if ($previewAllowed && $releasedPreviewKind)
                                            <button
                                                type="button"
                                                class="button secondary"
                                                data-preview-link
                                                data-preview-url="{{ $releasedPreviewUrl }}"
                                                data-preview-kind="{{ $releasedPreviewKind }}"
                                                data-preview-title="{{ $releasedDisplayName }}"
                                                data-preview-download="{{ $releasedDownloadUrl }}"
                                                data-preview-fallback="{{ $releasedPreviewUrl }}"
                                            >Preview</button>
                                        @endif
                                        @if ($previewOnlyAccess)
                                            <span class="status success file-notice">Preview file available now</span>
                                        @endif
                                    @else
                                        <span class="status warning file-notice">Payment required to download</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty-state">No released files are available for this quote yet.</div>
                @endif
            </article>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Comments</h3>
                <p>Customer and shared internal comments remain available here while the quote is still in review.</p>
            </div>
        </div>

        <div class="detail-grid">
            <article class="detail-card">
                <div class="card-head">
                    <h4>Your Comments</h4>
                </div>
                @php
                    $submissionComments = collect([
                        trim((string) $order->comments1),
                        trim((string) $order->comments2),
                    ])->filter();
                @endphp
                @if ($submissionComments->isNotEmpty() || $customerComments->count())
                    <ul class="comment-list">
                        @foreach ($submissionComments as $comment)
                            <li class="comment-item">
                                <strong>{{ $order->submit_date ?: '-' }}</strong>
                                <p>{{ $comment }}</p>
                            </li>
                        @endforeach
                        @foreach ($customerComments as $comment)
                            <li class="comment-item">
                                <strong>{{ $comment->date_added ?: '-' }}</strong>
                                <p>{{ $comment->comments }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty-state">No customer comments were recorded for this quote.</div>
                @endif
            </article>

            <article class="detail-card">
                <div class="card-head">
                    <h4>Shared Comments</h4>
                </div>
                @if ($internalComments->count())
                    <ul class="comment-list">
                        @foreach ($internalComments as $comment)
                            <li class="comment-item">
                                <strong>{{ $comment->date_added ?: '-' }}</strong>
                                <p>{{ $comment->comments }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty-state">No shared internal comments are currently visible on this quote.</div>
                @endif
            </article>
        </div>
    </section>

    <section class="content-card single-column" id="quote-response">
        <div class="section-head">
            <div>
                <h3>Quote Decision</h3>
                <p>Use accept when the quoted price works for you. Reject only when you want to explain why the quote does not fit and optionally request a different price.</p>
            </div>
        </div>

        @if ($latestQuoteNegotiation && ($latestQuoteNegotiation->customer_target_amount !== null || trim((string) $latestQuoteNegotiation->customer_reason_text) !== '' || trim((string) $latestQuoteNegotiation->customer_reason_code) !== ''))
            <div class="content-note">
                <strong>Your Latest Quote Request</strong>
                @if ($latestQuoteNegotiation->customer_target_amount !== null)
                    Requested price: ${{ number_format((float) $latestQuoteNegotiation->customer_target_amount, 2) }}.
                @endif
                @if (trim((string) $latestQuoteNegotiation->customer_reason_text) !== '')
                    Your note: {{ $latestQuoteNegotiation->customer_reason_text }}
                @elseif (trim((string) $latestQuoteNegotiation->customer_reason_code) !== '')
                    Reason: {{ ucwords(str_replace('_', ' ', (string) $latestQuoteNegotiation->customer_reason_code)) }}.
                @endif
            </div>
        @endif

        @if ($latestQuoteNegotiation && $order->status === 'done' && in_array((string) $latestQuoteNegotiation->status, ['accepted_by_admin', 'counter_offered', 'request_declined'], true))
            <div class="content-note">
                <strong>
                    @if ($latestQuoteNegotiation->status === 'accepted_by_admin')
                        Requested Price Approved
                    @elseif ($latestQuoteNegotiation->status === 'counter_offered')
                        Revised Quote Available
                    @else
                        Quote Request Reviewed
                    @endif
                </strong>
                @if ($latestQuoteNegotiation->status === 'accepted_by_admin')
                    We approved your requested price and updated the quote for you.
                    @if ($latestQuoteNegotiation->customer_target_amount !== null)
                        Approved price: ${{ number_format((float) $latestQuoteNegotiation->customer_target_amount, 2) }}.
                    @endif
                @elseif ($latestQuoteNegotiation->status === 'counter_offered')
                    We reviewed your request and prepared a revised quote for you to review.
                    @if ($latestQuoteNegotiation->admin_counter_amount !== null)
                        Revised price: ${{ number_format((float) $latestQuoteNegotiation->admin_counter_amount, 2) }}.
                    @endif
                @else
                    We reviewed your request and kept the quote available for you to review.
                @endif
                @if (trim((string) $latestQuoteNegotiation->admin_note) !== '')
                    Admin note: {{ $latestQuoteNegotiation->admin_note }}
                @endif
            </div>
        @endif

        @if ($showQuoteAcceptAction)
            <form method="post" action="{{ url('/quotes/' . $order->order_id . '/switch-to-order') }}" class="form-grid">
                @csrf
                <label style="grid-column: 1 / -1;">
                    Response On Quote Comments
                    <textarea name="response_comment" placeholder="Optional response or instruction for the admin/team when this quote becomes an order."></textarea>
                </label>
                <div style="grid-column: 1 / -1; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <button type="submit">Accept Quote</button>
                    @if ($showQuoteRejectAction)
                        <button
                            type="button"
                            class="button secondary"
                            data-toggle-reject-quote
                            aria-expanded="{{ $errors->has('reason_code') || $errors->has('reason_text') || $errors->has('target_amount') ? 'true' : 'false' }}"
                        >Reject Quote</button>
                    @endif
                    <a class="button secondary" href="{{ url('/view-quotes.php') }}">Back To Quotes</a>
                </div>
            </form>
        @endif

        @if ($showQuoteRejectAction)
            <div
                class="content-note"
                data-reject-quote-panel
                @if (! ($errors->has('reason_code') || $errors->has('reason_text') || $errors->has('target_amount')))
                    hidden
                @endif
            >
                <strong>Reject Quote</strong>
                Tell us why this quote does not work for you and enter the target amount that would help you move forward.
            </div>

            <form
                method="post"
                action="{{ url('/quotes/' . $order->order_id . '/feedback') }}"
                class="form-grid"
                data-reject-quote-panel
                @if (! ($errors->has('reason_code') || $errors->has('reason_text') || $errors->has('target_amount')))
                    hidden
                @endif
            >
                @csrf
                <label>
                    Why Are You Rejecting It?
                    <select name="reason_code" required>
                        <option value="pricing_too_high" @selected(old('reason_code') === 'pricing_too_high')>Pricing is too high</option>
                        <option value="budget_limit" @selected(old('reason_code') === 'budget_limit')>I have a fixed budget</option>
                        <option value="turnaround_concern" @selected(old('reason_code') === 'turnaround_concern')>Turnaround and pricing do not fit</option>
                        <option value="other" @selected(old('reason_code') === 'other')>Other</option>
                    </select>
                </label>
                <label>
                    Target Amount
                    <input type="number" name="target_amount" min="0" step="0.01" placeholder="Enter your target amount" value="{{ old('target_amount') }}" required>
                </label>
                <label style="grid-column: 1 / -1;">
                    Details
                    <textarea name="reason_text" placeholder="Share what would help you move forward with this quote.">{{ old('reason_text') }}</textarea>
                </label>
                <div style="grid-column: 1 / -1; display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit">Send Quote Response</button>
                    <button type="button" class="button secondary" data-hide-reject-quote>Cancel</button>
                </div>
            </form>
        @endif

        @if ($showQuoteSwitchEarly)
            <div class="content-note" style="margin-bottom:16px;">
                <strong>Switch to Order</strong>
                This quote has not been completed by our team yet. If you switch it to an order now, it will be processed directly without a quoted price first.
            </div>
            <form method="post" action="{{ url('/quotes/' . $order->order_id . '/switch-to-order') }}" onsubmit="return confirm('This quote has not been completed yet. Are you sure you want to switch it directly to an order?');">
                @csrf
                <button type="submit" class="button secondary">Switch to Order</button>
            </form>
        @endif

        @if ($showQuoteFeedbackSent)
            <div class="content-note">
                <strong>Quote Response Sent</strong>
                Your rejection has been sent for review.
                @if ($latestQuoteNegotiation && $latestQuoteNegotiation->customer_target_amount !== null)
                    Requested price: ${{ number_format((float) $latestQuoteNegotiation->customer_target_amount, 2) }}.
                @endif
                The team will review your comments and get back to you.
            </div>
        @endif
    </section>

    @if ($showQuoteRejectAction)
        <script>
            (function () {
                var toggle = document.querySelector('[data-toggle-reject-quote]');
                var hideButton = document.querySelector('[data-hide-reject-quote]');
                var panels = document.querySelectorAll('[data-reject-quote-panel]');

                if (!toggle || !panels.length) {
                    return;
                }

                var setVisible = function (visible) {
                    panels.forEach(function (panel) {
                        panel.hidden = !visible;
                    });
                    toggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
                };

                toggle.addEventListener('click', function () {
                    var shouldShow = toggle.getAttribute('aria-expanded') !== 'true';
                    setVisible(shouldShow);
                });

                if (hideButton) {
                    hideButton.addEventListener('click', function () {
                        setVisible(false);
                    });
                }
            }());
        </script>
    @endif
@endsection
