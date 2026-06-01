@extends('layouts.team')

@section('title', 'Quick Quote Detail #'.$order->order_id.' | Digitizing Zone Team Portal')
@section('page_heading', 'Quick Quote Detail #'.$order->order_id)
@section('page_subheading', 'Assigned quick quote files, comments, and completion.')

@section('content')
    @php
        $turnaround = \App\Support\TurnaroundTracking::summary($order);
        $isHourBasedCompletion = ! filled($order->sew_out);
        $completionValue = old('stitches', $order->stitches);
        $completionTimeValue = '';
    @endphp
    @if ($isHourBasedCompletion && preg_match('/^(\d{1,2})(?::(\d{2}))?$/', (string) $completionValue, $matches))
        @php
            $completionTimeValue = sprintf('%02d:%02d', (int) $matches[1], isset($matches[2]) ? (int) $matches[2] : 0);
        @endphp
    @endif
    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Core Details</h3>
                    <p class="muted" style="margin:0;">Review the quick quote information shared for this assignment.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ $backUrl }}">Back To Quick Quotes</a>
                    <a class="badge" href="{{ url('/team/team_get_design_info_file.php?design_id='.$order->order_id) }}">Download TXT File</a>
                    @if ($teamUser->is_supervisor ?? false)
                        <a class="badge" href="{{ url('/team/assign-order.php?design_id='.$order->order_id.'&page=qquote') }}">Assign Work</a>
                    @endif
                </div>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Status</span><strong>{{ $order->status ?: '-' }}</strong></article>
                @if ($teamUser->is_supervisor ?? false)
                    <article class="stat"><span class="muted">Assigned To</span><strong>{{ $order->assignee_name ?: '-' }}</strong></article>
                    <article class="stat"><span class="muted">Supervisor Review</span><strong>{{ $supervisorReviewComment?->date_modified ?: 'Pending' }}</strong></article>
                @endif
                <article class="stat"><span class="muted">Vendor Complete</span><strong>{{ $order->vender_complete_date ?: '-' }}</strong></article>
                <article class="stat"><span class="muted">Schedule</span><strong>{{ $turnaround['status_label'] }}</strong></article>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <tbody>
                    <tr><th>Submitted</th><td>{{ $order->submit_date ?: '-' }}</td><th>Completion Date</th><td>{{ $order->completion_date ?: '-' }}</td></tr>
                    <tr><th>Design Name</th><td>{{ $order->design_name ?: '-' }}</td><th>Format</th><td>{{ $order->format ?: '-' }}</td></tr>
                    <tr><th>Fabric Type</th><td>{{ $order->fabric_type ?: '-' }}</td><th>Sew Out Required</th><td>{{ $order->sew_out ?: '-' }}</td></tr>
                    <tr><th>Turnaround</th><td>{{ $turnaround['label_with_timing'] }}</td><th>Current Value</th><td>{{ $order->stitches ?: '-' }}</td></tr>
                    <tr><th>Schedule</th><td>{{ $turnaround['status_label'] }}</td><th>Time Remaining</th><td>{{ $turnaround['remaining_label'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @if (($teamUser->is_supervisor ?? false) && (int) $order->assign_to !== (int) $teamUser->user_id && $order->status === 'Ready')
        <section class="card">
            <div class="card-body">
                <h3 style="margin:0 0 6px;font-size:1.15rem;">Supervisor Review</h3>
                <p class="muted" style="margin:0 0 18px;">Record your verification note for this completed quick quote before admin final review.</p>

                <form method="post" action="{{ url('/team/review-order.php') }}" class="toolbar">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                    <div class="field" style="max-width:none;">
                        <label for="review_note">Review Note</label>
                        <textarea id="review_note" name="review_note" rows="4">{{ old('review_note', $supervisorReviewComment?->comments) }}</textarea>
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">{{ $supervisorReviewComment ? 'Update Review' : 'Mark Reviewed' }}</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Shared Instructions</h3>
            <div class="table-wrap" style="margin-top:14px;">
                <table>
                    <thead><tr><th>Comment</th></tr></thead>
                    <tbody>
                    @if (collect($sharedComments)->isEmpty())
                        <tr><td class="muted">No shared instructions are available.</td></tr>
                    @else
                    @foreach ($sharedComments as $comment)
                        <tr><td>{{ $comment->comments }}</td></tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Attachments</h3>
            <p class="muted" style="margin:0 0 18px;">Only files that admin has explicitly shared are available here, alongside your own uploaded work files.</p>

            <div class="card" style="margin-bottom:16px;">
                <div class="card-body">
                    <h4 style="margin:0 0 12px;">Shared Job Files</h4>
                    <div class="table-wrap">
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
                            @if (collect($sharedAttachments)->isEmpty())
                                <tr><td colspan="4" class="muted">No shared files are available.</td></tr>
                            @else
                            @foreach ($sharedAttachments as $attachment)
                                <tr>
                                    <td><a href="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}">{{ $attachment->file_name_with_order_id ?: $attachment->file_name }}</a></td>
                                    <td>{{ $attachment->file_source }}</td>
                                    <td>{{ $attachment->date_added ?: '-' }}</td>
                                    <td>
                                        <div class="action-row">
                                            @php
                                                $sharedDisplayName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name);
                                            @endphp
                                            @if (\App\Support\AttachmentPreview::isSupported($sharedDisplayName))
                                                <button
                                                    type="button"
                                                    class="badge"
                                                    data-preview-link
                                                    data-preview-url="{{ url('/team/quick-attachments/'.$attachment->id.'/preview/raw') }}"
                                                    data-preview-kind="{{ \App\Support\AttachmentPreview::kindForFileName($sharedDisplayName) }}"
                                                    data-preview-title="{{ $sharedDisplayName }}"
                                                    data-preview-download="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}"
                                                    data-preview-fallback="{{ url('/team/quick-attachments/'.$attachment->id.'/preview') }}"
                                                >Preview</button>
                                            @endif
                                            <a class="badge" href="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}">Download</a>
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

            <div class="card">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                        <h4 style="margin:0;">Team Uploaded Files</h4>
                        <form method="post" action="{{ url('/team/quick-order/upload') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            @csrf
                            <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                            <input type="file" name="files[]" multiple accept="{{ \App\Support\UploadSecurity::acceptAttribute('production') }}">
                            <button type="submit">Upload Files</button>
                        </form>
                    </div>

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
                            @if (collect($teamAttachments)->isEmpty())
                                <tr><td colspan="4" class="muted">No team files uploaded yet.</td></tr>
                            @else
                            @foreach ($teamAttachments as $attachment)
                                <tr>
                                    <td><a href="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}">{{ $attachment->file_name }}</a></td>
                                    <td>{{ $attachment->file_source }}</td>
                                    <td>{{ $attachment->date_added ?: '-' }}</td>
                                    <td>
                                        <div class="action-row">
                                            @php
                                                $teamDisplayName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name);
                                            @endphp
                                            @if (\App\Support\AttachmentPreview::isSupported($teamDisplayName))
                                                <button
                                                    type="button"
                                                    class="badge"
                                                    data-preview-link
                                                    data-preview-url="{{ url('/team/quick-attachments/'.$attachment->id.'/preview/raw') }}"
                                                    data-preview-kind="{{ \App\Support\AttachmentPreview::kindForFileName($teamDisplayName) }}"
                                                    data-preview-title="{{ $teamDisplayName }}"
                                                    data-preview-download="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}"
                                                    data-preview-fallback="{{ url('/team/quick-attachments/'.$attachment->id.'/preview') }}"
                                                >Preview</button>
                                            @endif
                                            <a class="badge" href="{{ url('/team/quick-attachments/'.$attachment->id.'/download') }}">Download</a>
                                            <form method="post" action="{{ url('/team/quick-attachments/'.$attachment->id.'/delete') }}" onsubmit="return confirm('Delete this attachment?');">
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
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Team Comments</h3>
            <div class="table-wrap" style="margin-top:14px;">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Comment</th>
                        <th>Date Added</th>
                        <th>Last Modified</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($teamComments)->isEmpty())
                        <tr><td colspan="4" class="muted">No comments from team members on this design till now.</td></tr>
                    @else
                    @foreach ($teamComments as $comment)
                        <tr>
                            <td style="white-space:nowrap;">
                        <a class="badge" href="{{ $selfUrl.'?edit_comment='.$comment->id }}">Edit</a>
                                <form method="post" action="{{ url('/team/quick-comments/'.$comment->id.'/delete') }}" style="display:inline-flex;margin-left:8px;" onsubmit="return confirm('Delete this comment?');">
                                    @csrf
                                    <button type="submit">Delete</button>
                                </form>
                            </td>
                            <td>{{ $comment->comments }}</td>
                            <td>{{ $comment->date_added ?: '-' }}</td>
                            <td>{{ $comment->date_modified ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            <form method="post" action="{{ url('/team/quick-order/comments') }}" style="margin-top:16px;">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                <input type="hidden" name="comment_id" value="{{ $editComment?->id }}">
                <div class="field" style="max-width:none;">
                    <label for="comments">{{ $editComment ? 'Edit Comment' : 'Add Comment' }}</label>
                    <textarea id="comments" name="comments" rows="6">{{ old('comments', $editComment?->comments) }}</textarea>
                </div>
                <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit">{{ $editComment ? 'Update Comment' : 'Add Comment' }}</button>
                    @if ($editComment)
                        <a class="badge" href="{{ $selfUrl }}">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Complete Quote</h3>
            <p class="muted" style="margin:0 0 18px;">Mark this quick quote complete and send it back to admin review.</p>

            <form method="post" action="{{ url('/team/quick-order/complete') }}" class="toolbar">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->order_id }}">

                @if (filled($order->sew_out))
                    <div class="field">
                        <label for="stitches">No. Of Stitches</label>
                        <input
                            id="stitches"
                            type="text"
                            name="stitches"
                            value="{{ $completionValue }}"
                            inputmode="decimal"
                            placeholder="Enter total stitches"
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <span class="muted">Enter the final stitch count used to complete this quote.</span>
                    </div>
                @else
                    @php
                        $normalizedCompletionHours = \App\Support\TeamPricing::normalizeHours((string) $completionValue);
                        [$completionHoursPart, $completionMinutesPart] = $normalizedCompletionHours
                            ? explode(':', $normalizedCompletionHours, 2)
                            : ['', '0'];
                    @endphp
                    <div class="field">
                        <label>Total Work Time</label>
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
                        <span class="muted">Enter the total time spent on this quote. Example: `8` hours and `30` minutes for an 8:30 quote.</span>
                    </div>
                @endif

                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Complete Order</button>
                </div>

                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <a class="badge" href="{{ $backUrl }}">Back To Quick Quotes</a>
                </div>

                <div class="field" style="min-width:100%;">
                    <label>Notification Trigger</label>
                    <div class="muted" style="padding:12px 14px;border:1px solid rgba(32,64,96,0.12);border-radius:14px;background:#f7fafc;">
                        @php
                            $adminAlertEmail = (string) config('mail.admin_alert_address', '');
                        @endphp
                        @if ($adminAlertEmail !== '')
                            Completing this quick quote sends the admin review email to {{ $adminAlertEmail }}. It does not send a customer email from this team screen.
                        @else
                            Completing this quick quote returns the work to admin review, but no admin alert email is configured right now.
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
