@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'order_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', $pageTitle.' | Digitizing Zone Admin')
@section('page_heading', $pageTitle)
@section('page_subheading', str_contains(strtolower($pageTitle), 'quote') ? 'Review quotes by queue, search quickly, and move work forward without jumping between duplicate screens.' : 'Review orders by queue, search quickly, and move work forward without jumping between duplicate screens.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>{{ $pageTitle }}</h3>
                    <p class="section-copy">{{ $queueMeta['summary'] }} Showing {{ $orders->total() }} matching records.</p>
                </div>
                <span class="badge">{{ $queueMeta['chip'] }}</span>
            </div>

            <form method="get" action="{{ $currentQueueUrl }}" class="toolbar">
                <div class="field">
                    <label for="queue_summary">Queue</label>
                    <input id="queue_summary" type="text" value="{{ $pageTitle }}" readonly>
                </div>
                <div class="field">
                    <label for="txt_orderid">Order ID</label>
                    <input id="txt_orderid" type="text" name="txt_orderid" value="{{ request('txt_orderid') }}">
                </div>
                <div class="field">
                    <label for="txt_designname">Design Name</label>
                    <input id="txt_designname" type="text" name="txt_designname" value="{{ request('txt_designname') }}">
                </div>
                <div class="field">
                    <label for="txt_custname">Customer</label>
                    <input id="txt_custname" type="text" name="txt_custname" value="{{ request('txt_custname') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Filter</button>
                </div>
            </form>

            @if ($orders->count() > 0)
                <div style="margin-top:14px;display:flex;justify-content:flex-start;">
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:999px;background:#0f5f66;color:#fff;font-weight:700;text-decoration:none;">Download List</a>
                </div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_id', 'sort' => $nextDirection('order_id')]) }}">Order ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_type', 'sort' => $nextDirection('order_type')]) }}">Work Type</a></th>
                        <th>Customer</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'design_name', 'sort' => $nextDirection('design_name')]) }}">Design Name</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'assign_to', 'sort' => $nextDirection('assign_to')]) }}">Assigned To</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'status', 'sort' => $nextDirection('status')]) }}">Status</a></th>
                        <th>Turnaround</th>
                        <th>Schedule</th>
                        <th>Payment</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'total_amount', 'sort' => $nextDirection('total_amount')]) }}">Amount</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'submit_date', 'sort' => $nextDirection('submit_date')]) }}">Submitted</a></th>
                        <th class="action-col">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($orders)->isEmpty())
                        <tr>
                            <td colspan="12"><div class="empty-state">No records found for this category and filter combination.</div></td>
                        </tr>
                    @else
                    @foreach ($orders as $order)
                        @php
                            $detailUrl = match ((string) $order->order_type) {
                                'qquote' => url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'),
                                'quote', 'digitzing', 'qcolor' => url('/v/orders/'.$order->order_id.'/detail/quote?back='.rawurlencode($queueKey)),
                                'vector', 'q-vector' => url('/v/orders/'.$order->order_id.'/detail/vector?back='.rawurlencode($queueKey)),
                                default => url('/v/orders/'.$order->order_id.'/detail/order?back='.rawurlencode($queueKey)),
                            };
                        @endphp
                        <tr>
                            <td><a href="{{ $detailUrl }}" style="font-weight:700;">{{ $order->order_id }}</a></td>
                            <td>{{ $order->work_type_label }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ $order->design_name ?: '-' }}</td>
                            <td>{{ $order->assignee_name }}</td>
                            <td><span class="badge">{{ $order->status ?: '-' }}</span></td>
                            <td>{{ $order->turnaround_label ?: ($order->turn_around_time ?: '-') }}</td>
                            <td>
                                <div style="display:grid;gap:6px;">
                                    <span class="badge" style="{{ ($order->turnaround_status_tone ?? '') === 'danger' ? 'background:rgba(180,35,24,0.12);color:#b42318;border-color:rgba(180,35,24,0.18);' : (($order->turnaround_status_tone ?? '') === 'warning' ? 'background:rgba(197,107,34,0.12);color:#9a5a16;border-color:rgba(197,107,34,0.18);' : 'background:rgba(34,139,94,0.12);color:#1f7a53;border-color:rgba(34,139,94,0.18);') }}">
                                        {{ $order->turnaround_status_label ?: 'Schedule Unknown' }}
                                    </span>
                                    <span class="muted" style="font-size:0.82rem;">{{ $order->turnaround_remaining_label ?: '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="{{ $order->customer_paid_flag ? 'background:rgba(34,139,94,0.14);color:#1f7a53;border-color:rgba(34,139,94,0.24);' : '' }}">
                                    {{ $order->customer_paid_flag ? 'Paid' : 'Unpaid' }}
                                </span>
                            </td>
                            <td>{{ $order->total_amount ?: '0.00' }}</td>
                            <td>{{ $order->submit_date ?: '-' }}</td>
                            <td class="action-col">
                                @php
                                    $canConvertQuote = in_array((string) $order->order_type, ['quote', 'digitzing'], true) && is_null($order->end_date);
                                @endphp
                                <div class="action-row">
                                    @if ($order->can_mark_paid_flag)
                                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/mark-paid') }}" onsubmit="return confirm('Mark this order as paid?');">
                                            @csrf
                                            <input type="hidden" name="queue" value="{{ $queueKey }}">
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <label for="transaction_id_{{ $order->order_id }}" class="sr-only">Transaction ID</label>
                                            <input
                                                id="transaction_id_{{ $order->order_id }}"
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
                                    @endif
                                    @if ($order->can_approve_flag)
                                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/approve') }}" onsubmit="return confirm('Approve this order?');">
                                            @csrf
                                            <input type="hidden" name="queue" value="{{ $queueKey }}">
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <label for="approved_amount_{{ $order->order_id }}" class="sr-only">Approval Amount</label>
                                            <input
                                                id="approved_amount_{{ $order->order_id }}"
                                                type="number"
                                                name="approved_amount"
                                                step="0.01"
                                                min="0"
                                                value="{{ old('approved_amount', $order->total_amount ?: $order->stitches_price ?: '0.00') }}"
                                                style="width:120px;"
                                                placeholder="Approval Amount"
                                                title="Approval Amount"
                                                aria-label="Approval amount"
                                            >
                                            <button type="submit">Approve (No Email)</button>
                                        </form>
                                    @endif
                                    @if ($canConvertQuote && $queueKey === 'completed-quotes')
                                        <form method="post" action="{{ url('/v/order-detail/convert-quote') }}" onsubmit="return confirm('Convert this completed quote to an order?');">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                                            <input type="hidden" name="back" value="{{ $queueKey }}">
                                            <button type="submit">Convert To Order</button>
                                        </form>
                                    @endif
                                    @if ($order->can_delete_flag)
                                        <form method="post" action="{{ url('/v/orders/'.$order->order_id.'/delete') }}" onsubmit="return confirm('Delete this new order?');">
                                            @csrf
                                            <input type="hidden" name="queue" value="{{ $queueKey }}">
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div>
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
