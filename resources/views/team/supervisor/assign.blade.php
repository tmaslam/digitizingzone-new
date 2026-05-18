@extends('layouts.team')

@section('title', 'Assign Work #'.$order->order_id.' | 1Dollar Team Portal')
@section('page_heading', 'Assign Work #'.$order->order_id)
@section('page_subheading', 'Route work to yourself or a team member on your supervisor account.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Work Assignment</h3>
                    <p class="muted" style="margin:0;">{{ $order->design_name ?: 'Order '.$order->order_id }} | {{ $order->work_type_label }}</p>
                </div>
                <a class="badge" href="{{ $backUrl }}">Back</a>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Customer</span><strong>{{ $order->customer_name ?: '-' }}</strong></article>
                <article class="stat"><span class="muted">Status</span><strong>{{ $order->status ?: '-' }}</strong></article>
                <article class="stat"><span class="muted">Turnaround</span><strong>{{ $turnaround['label_with_timing'] }}</strong></article>
                <article class="stat"><span class="muted">Schedule</span><strong>{{ $turnaround['status_label'] }}</strong></article>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <tbody>
                    <tr><th>Order ID</th><td>{{ $order->order_id }}</td><th>Submitted</th><td>{{ $order->submit_date ?: '-' }}</td></tr>
                    <tr><th>Design Name</th><td>{{ $order->design_name ?: '-' }}</td><th>Format</th><td>{{ $order->format ?: '-' }}</td></tr>
                    <tr><th>Size</th><td>{{ trim(($order->width ?? '').' x '.($order->height ?? '').' '.($order->measurement ?? '')) ?: '-' }}</td><th>Colors</th><td>{{ $order->no_of_colors ?: '-' }}</td></tr>
                    <tr><th>Fabric Type</th><td>{{ $order->fabric_type ?: '-' }}</td><th>Current Assignee</th><td>{{ $order->assignee_name ?: '-' }}</td></tr>
                    <tr><th>Schedule Status</th><td>{{ $turnaround['status_label'] }}</td><th>Time Remaining</th><td>{{ $turnaround['remaining_label'] }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card subcard" style="margin-top:16px;">
                <div class="card-body">
                    <h4 style="margin:0 0 10px;">Source Artwork</h4>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>File</th>
                                <th>Source</th>
                                <th>Added</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if (collect($shareableAttachments)->isEmpty())
                                <tr><td colspan="3" class="muted">No source artwork is attached to this order yet.</td></tr>
                            @else
                            @foreach ($shareableAttachments as $attachment)
                                <tr>
                                    <td>{{ $attachment->file_name_with_order_id ?: $attachment->file_name }}</td>
                                    <td>{{ $attachment->file_source ?: '-' }}</td>
                                    <td>{{ $attachment->date_added ?: '-' }}</td>
                                </tr>
                            @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ url('/team/assign-order.php') }}" style="margin-top:18px;">
                @csrf
                <input type="hidden" name="design_id" value="{{ $order->order_id }}">
                <input type="hidden" name="page" value="{{ $page }}">

                <div class="toolbar">
                    <div class="field">
                        <label for="team">Assign To</label>
                        <select id="team" name="team">
                            @foreach ($assignableUsers as $member)
                                <option value="{{ $member->user_id }}" data-email="{{ $member->user_email }}" @selected((int) old('team', $order->assign_to) === (int) $member->user_id)>
                                    {{ $member->user_name }}{{ $member->is_supervisor ? ' (Supervisor)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="max-width:none;">
                        <label for="handoff_comment">Handoff Comment</label>
                        <textarea id="handoff_comment" name="handoff_comment" rows="5">{{ old('handoff_comment') }}</textarea>
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit">Save Assignment</button>
                    <span class="muted" style="align-self:center;">Email impact is shown below in Notification Trigger.</span>
                    <a class="badge" href="{{ $backUrl }}">Cancel</a>
                </div>

                <div class="card subcard" style="margin-top:16px;">
                    <div class="card-body">
                        <h4 style="margin:0 0 10px;">Notification Trigger</h4>
                        <div id="supervisor-assignment-email-guidance" class="muted">Saving this assignment will notify the selected team member when a valid email address is available.</div>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const teamField = document.getElementById('team');
        const guidance = document.getElementById('supervisor-assignment-email-guidance');

        if (!teamField || !guidance) {
            return;
        }

        const updateGuidance = function () {
            const selected = teamField.options[teamField.selectedIndex];
            const email = selected ? (selected.dataset.email || '').trim() : '';
            const label = selected ? selected.textContent.trim() : '';

            if (!selected) {
                guidance.textContent = 'No assignment email will be sent until you choose who should work on this item.';
                return;
            }

            if (email === '') {
                guidance.textContent = 'Saving this assignment will update the workflow, but no notification email will be sent because ' + label + ' does not have a valid email address.';
                return;
            }

            guidance.textContent = 'Saving this assignment will send the assignment email to ' + email + '.';
        };

        teamField.addEventListener('change', updateGuidance);
        updateGuidance();
    });
    </script>
@endsection
