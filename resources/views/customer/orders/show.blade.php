@extends('layouts.customer')

@section('title', 'Order Detail - '.$order->order_id)
@section('hero_title', 'Order Detail #'.$order->order_id)
@section('hero_text', 'Review your order files, pricing, and comments in one place.')

@section('content')
    <section class="content-card stack order-detail-summary">
        <div class="section-head">
            <div>
                <h3>{{ $order->design_name }}</h3>
                <p>Current status: <span class="status {{ $statusTone }}">{{ $statusLabel }}</span></p>
                <p>{{ $statusHint }}</p>
            </div>
            <div class="order-detail-actions">
                <a class="button secondary" href="{{ $backLink['url'] }}">{{ $backLink['label'] }}</a>
                @if (! in_array((string) $order->status, ['done', 'approved'], true))
                    <a class="button secondary" href="/edit-order.php?order_id={{ $order->order_id }}">Edit Order</a>
                @endif
                @if ($showOrderCancelAction)
                    <form method="post" action="/orders/{{ $order->order_id }}/cancel" onsubmit="return confirm('Cancel this order?');">
                        @csrf
                        <button type="submit" class="button danger">Cancel Order</button>
                    </form>
                @endif
            </div>
        </div>

        <ul class="order-meta-strip">
            <li><dt>Format</dt><dd>{{ $order->format ?: '-' }}</dd></li>
            <li><dt>Design Cost</dt><dd>{{ $order->total_amount ?: $order->stitches_price ?: '0.00' }}</dd></li>
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
                <h3>Files</h3>
                <p>{{ $previewNotice }}</p>
            </div>
        </div>

        <div class="detail-grid">
            <article class="list-card">
                <div class="card-head">
                    <h4>Submitted Source Files</h4>
                    <p>These remain available to the customer account on this site.</p>
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
                    <div class="empty-state">No submitted source files are attached to this order.</div>
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
                                @if ($attachment->date_added)
                                    <div class="muted" style="font-size:0.82rem;margin-top:3px;">Released {{ $attachment->date_added }}</div>
                                @endif
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
                    <div class="empty-state">No released files are available yet for this order.</div>
                @endif
            </article>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Comments</h3>
                <p>Customer and internal comments remain visible only inside this site account.</p>
            </div>
        </div>

        <div class="detail-grid">
            <article class="detail-card">
                <div class="card-head">
                    <h4>Your Comments</h4>
                </div>
                @if ($customerComments->count())
                    <ul class="comment-list">
                        @foreach ($customerComments as $comment)
                            <li class="comment-item">
                                <strong>{{ $comment->date_added ?: '-' }}</strong>
                                <p>{{ $comment->comments }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty-state">No customer comments were recorded for this order.</div>
                @endif
            </article>
            <article class="detail-card">
                <div class="card-head">
                    <h4>{{ $siteContext->displayLabel() }} Comments</h4>
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
                    <div class="empty-state">No internal comments are currently shared on this order.</div>
                @endif
            </article>
        </div>
    </section>

    @if ($showApproveAction)
        <section class="content-card single-column">
            <div class="section-head">
                <div>
                    <h3>Customer Approval</h3>
                    <p>Completed orders still need your approval before they move to billing or archive.</p>
                </div>
            </div>
            <form method="post" action="/orders/{{ $order->order_id }}/approve">
                @csrf
                <button type="submit">{{ trim(strtolower((string) $order->total_amount)) === 'first order is free' || (float) preg_replace('/[^0-9.\-]/', '', (string) $order->total_amount) <= 0 ? 'Approve Order' : 'Send to Billing' }}</button>
            </form>
            <div style="margin-top: 12px;">
                <a class="button danger" href="/disapprove-order.php?order_id={{ $order->order_id }}">Request an Edit</a>
            </div>
        </section>
    @endif
@endsection
