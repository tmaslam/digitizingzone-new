@extends('layouts.team')

@section('title', 'Team Member Detail | Digitizing Zone Team Portal')
@section('page_heading', 'Team Member Detail')
@section('page_subheading', 'See current assignments, active work, and completed items for this team member.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">{{ $member->display_name }}</h3>
                    <p class="muted" style="margin:0;">{{ $member->user_name }}{{ $member->user_email ? ' | '.$member->user_email : '' }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ url('/team/manage-team.php') }}">Back to Team</a>
                    <a class="badge" href="{{ url('/team/create-team.php?user_id='.$member->user_id) }}">Edit Login</a>
                </div>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Active Assignments</span><strong>{{ $stats['active'] }}</strong></article>
                <article class="stat"><span class="muted">Working Now</span><strong>{{ $stats['working'] }}</strong></article>
                <article class="stat"><span class="muted">Ready For Admin</span><strong>{{ $stats['ready'] }}</strong></article>
                <article class="stat"><span class="muted">Verified By Supervisor</span><strong>{{ $stats['verified'] }}</strong></article>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Current Work</h3>
            <p class="muted" style="margin:0 0 18px;">Items actively assigned to this team member, including disapproved work sent back for changes.</p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Order ID</th>
                        <th>Status</th>
                        <th>Work Type</th>
                        <th>Design Name</th>
                        <th>Turnaround</th>
                        <th>Schedule</th>
                        <th>Time Left</th>
                        <th>Working</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if ($activeOrders->isEmpty())
                        <tr><td colspan="9" class="muted">No current assignments found for this team member.</td></tr>
                    @else
                    @foreach ($activeOrders as $order)
                        @php
                            $scheduleTone = (string) ($order->turnaround_status_tone ?? '');
                            $scheduleBadgeStyle = match ($scheduleTone) {
                                'danger' => 'background:rgba(180,35,24,0.12);color:#b42318;border-color:rgba(180,35,24,0.18);',
                                'warning' => 'background:rgba(197,107,34,0.12);color:#9a5a16;border-color:rgba(197,107,34,0.18);',
                                default => 'background:rgba(34,139,94,0.12);color:#1f7a53;border-color:rgba(34,139,94,0.18);',
                            };
                            $remainingStyle = $scheduleTone === 'danger' ? 'color:#b42318;font-weight:700;' : '';
                        @endphp
                        <tr>
                            <td><a class="badge" href="{{ $detailUrl($order) }}">Open Detail</a></td>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->status ?: '-' }}</td>
                            <td>{{ $order->work_type_label }}</td>
                            <td>{{ $order->design_name ?: '-' }}</td>
                            <td>{{ $order->turnaround_label ?: ($order->turn_around_time ?: '-') }}</td>
                            <td><span class="badge" style="{{ $scheduleBadgeStyle }}">{{ $order->turnaround_status_label ?: 'Schedule Unknown' }}</span></td>
                            <td style="{{ $remainingStyle }}">{{ $order->turnaround_remaining_label ?: '-' }}</td>
                            <td>{{ $order->working ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Completed By Team Member</h3>
            <p class="muted" style="margin:0 0 18px;">Items this team member completed and sent back for admin review.</p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Order ID</th>
                        <th>Work Type</th>
                        <th>Design Name</th>
                        <th>Turnaround</th>
                        <th>Schedule</th>
                        <th>Time Left</th>
                        <th>Vendor Complete</th>
                        <th>Supervisor Review</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if ($readyOrders->isEmpty())
                        <tr><td colspan="9" class="muted">No completed items are waiting from this team member.</td></tr>
                    @else
                    @foreach ($readyOrders as $order)
                        @php
                            $review = $reviewComments[$order->order_id] ?? null;
                            $scheduleTone = (string) ($order->turnaround_status_tone ?? '');
                            $scheduleBadgeStyle = match ($scheduleTone) {
                                'danger' => 'background:rgba(180,35,24,0.12);color:#b42318;border-color:rgba(180,35,24,0.18);',
                                'warning' => 'background:rgba(197,107,34,0.12);color:#9a5a16;border-color:rgba(197,107,34,0.18);',
                                default => 'background:rgba(34,139,94,0.12);color:#1f7a53;border-color:rgba(34,139,94,0.18);',
                            };
                            $remainingStyle = $scheduleTone === 'danger' ? 'color:#b42318;font-weight:700;' : '';
                        @endphp
                        <tr>
                            <td><a class="badge" href="{{ $detailUrl($order) }}">Open Detail</a></td>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->work_type_label }}</td>
                            <td>{{ $order->design_name ?: '-' }}</td>
                            <td>{{ $order->turnaround_label ?: ($order->turn_around_time ?: '-') }}</td>
                            <td><span class="badge" style="{{ $scheduleBadgeStyle }}">{{ $order->turnaround_status_label ?: 'Schedule Unknown' }}</span></td>
                            <td style="{{ $remainingStyle }}">{{ $order->turnaround_remaining_label ?: '-' }}</td>
                            <td>{{ $order->vender_complete_date ?: '-' }}</td>
                            <td>{{ $review?->date_modified ?: ($review ? 'Reviewed' : 'Pending') }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
