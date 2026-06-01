@extends('layouts.team')

@section('title', 'Review Queue | Digitizing Zone Team Portal')
@section('page_heading', 'Review Queue')
@section('page_subheading', 'Monitor completed team work and record supervisor verification before admin review.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/team/review-queue.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtUserID">Team Member</label>
                    <select id="txtUserID" name="txtUserID">
                        <option value="">All Team Members</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->user_id }}" @selected((string) request('txtUserID') === (string) $member->user_id)>{{ $member->user_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="txtOrderID">Order ID</label>
                    <input id="txtOrderID" type="text" name="txtOrderID" value="{{ request('txtOrderID') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Filter</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="action-col">Action</th>
                        <th>Order ID</th>
                        <th>Assigned To</th>
                        <th>Turnaround</th>
                        <th>Schedule</th>
                        <th>Time Left</th>
                        <th>Work Type</th>
                        <th>Design Name</th>
                        <th>Completed By Team</th>
                        <th>Supervisor Review</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if ($orders->isEmpty())
                        <tr><td colspan="10" class="muted">No completed team work is waiting in your review queue.</td></tr>
                    @else
                    @foreach ($orders as $order)
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
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ $detailUrl($order) }}">Open Detail</a>
                                    <form method="post" action="{{ url('/team/review-order.php') }}">
                                        @csrf
                                        <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                        <input type="text" name="review_note" value="{{ old('review_note', $review?->comments) }}" placeholder="Optional review note" style="max-width:220px;">
                                        <button type="submit">{{ $review ? 'Update Review' : 'Mark Reviewed' }}</button>
                                    </form>
                                </div>
                            </td>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->assignee_name ?: '-' }}</td>
                            <td>{{ $order->turnaround_label ?: ($order->turn_around_time ?: '-') }}</td>
                            <td>
                                <span class="badge" style="{{ $scheduleBadgeStyle }}">
                                    {{ $order->turnaround_status_label ?: 'Schedule Unknown' }}
                                </span>
                            </td>
                            <td style="{{ $remainingStyle }}">{{ $order->turnaround_remaining_label ?: '-' }}</td>
                            <td>{{ $order->work_type_label }}</td>
                            <td>{{ $order->design_name ?: '-' }}</td>
                            <td>{{ $order->vender_complete_date ?: '-' }}</td>
                            <td>{{ $review?->date_modified ?: 'Pending' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
