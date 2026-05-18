@extends('layouts.admin')

@section('title', 'Assign Order '.$order->order_id.' | 1Dollar Admin')
@section('page_heading', 'Assign Order '.$order->order_id)
@section('page_subheading', 'Assign work, share files, and send notes to the team or supervisor.')

@section('content')
    @php
        $customerCommentModeValue = old('customer_comment_mode', $customerCommentMode);
        $sharedCustomerCommentValue = old('shared_customer_comment', $existingSharedCustomerText !== '' ? $existingSharedCustomerText : $customerSubmissionText);
        $handoffCommentValue = old('handoff_comment', $existingHandoffText);
    @endphp

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <form method="post" action="{{ url('/v/assign-order.php') }}">
                @csrf
                <input type="hidden" name="design_id" value="{{ $order->order_id }}">
                <input type="hidden" name="page" value="{{ $page }}">
                <input type="hidden" name="status" value="{{ $order->status }}">
                <input type="hidden" name="back" value="{{ $backQueue }}">

                <div class="toolbar">
                    <div class="field">
                        <label>Assign To</label>
                        <select name="team">
                            <option value="">Not Selected Yet</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->user_id }}" data-email="{{ $team->user_email }}" @selected((string) old('team', $order->assign_to) === (string) $team->user_id)>{{ $team->user_name }}{{ $team->is_supervisor ? ' (Supervisor)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field" style="min-width:280px;">
                        <label>Handoff Comment</label>
                        <textarea name="handoff_comment" rows="4">{{ $handoffCommentValue }}</textarea>
                    </div>

                    <div class="field" style="min-width:260px;">
                        <label>Customer Note Sharing</label>
                        <select name="customer_comment_mode">
                            <option value="original" @selected($customerCommentModeValue === 'original')>Send Original Customer Notes</option>
                            <option value="edited" @selected($customerCommentModeValue === 'edited')>Edit Before Sharing</option>
                            <option value="none" @selected($customerCommentModeValue === 'none')>Do Not Share Customer Notes</option>
                        </select>
                    </div>
                </div>

                <div class="card" style="margin-top:18px;">
                    <div class="card-body">
                        <h3 style="margin:0 0 12px;font-size:1.05rem;">Share Attachments With Team</h3>
                        <p class="muted" style="margin:0 0 14px;">Select only the source files you want the assigned user to see.</p>
                        <div class="action-row" style="padding: 0 0 12px;">
                            <button type="button" class="badge" id="select-all-attachments">Select All</button>
                            <button type="button" class="badge badge-muted" id="clear-all-attachments">Clear All</button>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>File</th>
                                    <th>Source</th>
                                    <th>Added</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if (collect($shareableAttachments)->isEmpty())
                                    <tr><td colspan="4" class="muted">No source attachments are available to share.</td></tr>
                                @else
                                @foreach ($shareableAttachments as $attachment)
                                    <tr>
                                        <td><input type="checkbox" name="attachment_ids[]" value="{{ $attachment->id }}" data-attachment-checkbox @checked(in_array((int) $attachment->id, $defaultSelectedAttachmentIds, true))></td>
                                        <td>{{ $attachment->file_name_with_order_id ?: $attachment->file_name }}</td>
                                        <td>{{ $attachment->file_source }}</td>
                                        <td>{{ $attachment->date_added ?: '-' }}</td>
                                    </tr>
                                @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px;padding:14px 16px;border:1px solid rgba(24, 34, 45, 0.1);border-radius:18px;background:rgba(255,255,255,0.62);">
                    <div>
                        <strong style="display:block;">Quick Assign</strong>
                        <span class="muted">Assign the order here, or continue below if you want to review notes and shared files first.</span>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit">Assign To Team / Supervisor</button>
                        <a class="badge" href="{{ url('/v/orders/'.$order->order_id.'/detail/'.$page.'?back='.rawurlencode($backQueue)) }}">Back to Order Detail</a>
                    </div>
                </div>

                <div class="card subcard" style="margin-top:18px;">
                    <div class="card-body">
                        <h4 style="margin:0 0 10px;">Notification Trigger</h4>
                        <div id="assignment-email-guidance" class="muted">Saving this assignment will notify the selected team user by email when a valid login email is available.</div>
                    </div>
                </div>

                <div class="card" style="margin-top:18px;">
                    <div class="card-body">
                        <h3 style="margin:0 0 6px;font-size:1.05rem;">Order Snapshot</h3>
                        <p class="muted" style="margin:0 0 14px;">Current assignee, status, and customer before reassignment.</p>
                        <div class="stats">
                            <article class="stat"><span class="muted">Status</span><strong style="font-size:1.15rem;">{{ $order->status ?: '-' }}</strong></article>
                            <article class="stat"><span class="muted">Assigned To</span><strong style="font-size:1.15rem;">{{ $order->assignee_name }}</strong></article>
                            <article class="stat"><span class="muted">Customer</span><strong style="font-size:1.15rem;">{{ $order->customer_name }}</strong></article>
                            <article class="stat"><span class="muted">Page</span><strong style="font-size:1.15rem;">{{ strtoupper($page) }}</strong></article>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:18px;">
                    <div class="card-body">
                        <h3 style="margin:0 0 12px;font-size:1.05rem;">Customer Submission Review</h3>
                        <div class="field" style="min-width:100%;">
                            <label>Original Customer Notes</label>
                            <textarea rows="6" readonly>{{ $customerSubmissionText !== '' ? $customerSubmissionText : 'No customer note text was found for this order.' }}</textarea>
                        </div>
                        <div class="field" style="min-width:100%;margin-top:14px;">
                            <label>Customer Notes To Share Downstream</label>
                            <textarea name="shared_customer_comment" rows="6">{{ $sharedCustomerCommentValue }}</textarea>
                        </div>
                        <p class="muted" style="margin:12px 0 0;">Only the shared text above is sent downstream. Raw customer notes are not exposed automatically.</p>
                    </div>
                </div>

                <div class="card" style="margin-top:18px;">
                    <div class="card-body">
                        <h3 style="margin:0 0 12px;font-size:1.05rem;">Existing Handoff Notes</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Comment</th>
                                    <th>Updated</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if (collect($handoffComments)->isEmpty())
                                    <tr><td colspan="2" class="muted">No handoff notes yet.</td></tr>
                                @else
                                @foreach ($handoffComments as $comment)
                                    <tr>
                                        <td>{{ $comment->comments }}</td>
                                        <td>{{ $comment->date_modified ?: $comment->date_added ?: '-' }}</td>
                                    </tr>
                                @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                    <button type="submit">Save Assignment</button>
                    <span class="muted" style="align-self:center;">Email impact is shown above in Notification Trigger.</span>
                    <a class="badge" href="{{ url('/v/orders/'.$order->order_id.'/detail/'.$page.'?back='.rawurlencode($backQueue)) }}">Back to Order Detail</a>
                </div>
            </form>
        </div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const teamField = document.querySelector('select[name="team"]');
        const guidance = document.getElementById('assignment-email-guidance');
        const attachmentCheckboxes = Array.from(document.querySelectorAll('[data-attachment-checkbox]'));
        const selectAllAttachments = document.getElementById('select-all-attachments');
        const clearAllAttachments = document.getElementById('clear-all-attachments');

        if (!teamField || !guidance) {
            return;
        }

        const updateGuidance = function () {
            const selected = teamField.options[teamField.selectedIndex];
            const email = selected ? (selected.dataset.email || '').trim() : '';
            const label = selected ? selected.textContent.trim() : '';

            if (!selected || teamField.value === '') {
                guidance.textContent = 'No assignment email will be sent until you choose a team member or supervisor.';
                return;
            }

            if (email === '') {
                guidance.textContent = 'Saving this assignment will update the workflow, but no assignment email will be sent because ' + label + ' does not have a valid email address.';
                return;
            }

            guidance.textContent = 'Saving this assignment will send the assignment email to ' + email + '.';
        };

        teamField.addEventListener('change', updateGuidance);
        updateGuidance();

        selectAllAttachments?.addEventListener('click', function () {
            attachmentCheckboxes.forEach((checkbox) => {
                checkbox.checked = true;
            });
        });

        clearAllAttachments?.addEventListener('click', function () {
            attachmentCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
        });
    });
    </script>
@endsection
