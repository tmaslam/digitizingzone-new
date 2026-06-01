@extends('layouts.admin')

@php
    $currentColumn = request('column_name', $defaultColumn);
    $currentDirection = strtolower(request('sort', $defaultDirection));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
    $detailBack = $backContext ?? ($mode === 'received' ? 'payment-recieved' : 'all-payment-due');
    $filterAction = $filterAction ?? ($mode === 'due' ? url('/v/all-payment-due.php') : url('/v/payment-recieved.php'));
    $detailUrl = function ($billing) use ($detailBack) {
        $order = $billing->order;
        $orderId = (int) ($billing->order_id ?: 0);

        if ($order) {
            return match ((string) $order->order_type) {
                'qquote' => url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'),
                'quote', 'digitzing', 'qcolor' => url('/v/orders/'.$order->order_id.'/detail/quote?back='.rawurlencode($detailBack)),
                'vector', 'q-vector' => url('/v/orders/'.$order->order_id.'/detail/vector?back='.rawurlencode($detailBack)),
                default => url('/v/orders/'.$order->order_id.'/detail/order?back='.rawurlencode($detailBack)),
            };
        }

        if ($orderId > 0) {
            return url('/v/orders/'.$orderId.'/detail/order?back='.rawurlencode($detailBack));
        }

        if (! $billing->user_id) {
            return null;
        }

        return match ($detailBack) {
            'all-payment-due' => url('/v/payment-due-detail.php?uid='.$billing->user_id.'&source=all-payment-due'),
            'payment-recieved' => url('/v/payment-recieved-detail.php?uid='.$billing->user_id.'&source=payment-recieved'),
            'payment-due-report' => url('/v/payment-due-detail.php?uid='.$billing->user_id.'&source=payment-due-report'),
            'payment-recieved-report' => url('/v/payment-recieved-detail.php?uid='.$billing->user_id.'&source=payment-recieved-report'),
            default => null,
        };
    };
    $detailLabel = fn ($billing) => $billing->order ? 'Open Detail' : 'Open Billing';
@endphp

@section('title', $title.' | Digitizing Zone Admin')
@section('page_heading', $title)
@section('page_subheading', $subtitle)

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ $filterAction }}" class="toolbar">
                <div class="field">
                    <label for="txtorderID">Order ID</label>
                    <input id="txtorderID" type="text" name="txtorderID" value="{{ request('txtorderID') }}">
                </div>

                @if ($mode === 'due')
                    <div class="field">
                        <label for="txt_ordername">Design / Order Name</label>
                        <input id="txt_ordername" type="text" name="txt_ordername" value="{{ request('txt_ordername') }}">
                    </div>
                    <div class="field">
                        <label for="txt_amount">Amount</label>
                        <input id="txt_amount" type="text" name="txt_amount" value="{{ request('txt_amount') }}">
                    </div>
                    <div class="field">
                        <label for="txtFirstName">First Name</label>
                        <input id="txtFirstName" type="text" name="txtFirstName" value="{{ request('txtFirstName') }}">
                    </div>
                    <div class="field">
                        <label for="txtLastName">Last Name</label>
                        <input id="txtLastName" type="text" name="txtLastName" value="{{ request('txtLastName') }}">
                    </div>
                @else
                    <div class="field">
                        <label for="txt_transid">Transaction ID</label>
                        <input id="txt_transid" type="text" name="txt_transid" value="{{ request('txt_transid') }}">
                    </div>
                    <div class="field">
                        <label for="txt_ordername">Order Name</label>
                        <input id="txt_ordername" type="text" name="txt_ordername" value="{{ request('txt_ordername') }}">
                    </div>
                    <div class="field">
                        <label for="txt_customername">Customer Name</label>
                        <input id="txt_customername" type="text" name="txt_customername" value="{{ request('txt_customername') }}">
                    </div>
                @endif

                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Filter</button>
                </div>
            </form>
            @if ($billings->count() > 0)
                <div style="margin-top:14px;display:flex;justify-content:flex-start;">
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" style="display:flex;align-items:center;justify-content:center;width:min(100%, 320px);min-height:46px;padding:0 18px;border-radius:16px;background:#0f5f66;color:#fff;font-weight:700;text-decoration:none;">Download Report</a>
                </div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">{{ $title }}</h3>
                    <p class="muted" style="margin:0;">Showing {{ $billings->total() }} matching billing records.</p>
                </div>
                <span class="badge">{{ $mode === 'due' ? 'payment queue' : 'transactions' }}</span>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    @if ($mode === 'due')
                        <tr>
                            <th>Action</th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_id', 'sort' => $nextDirection('order_id')]) }}">Order ID</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'design_name', 'sort' => $nextDirection('design_name')]) }}">Design Name</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'customer_name', 'sort' => $nextDirection('customer_name')]) }}">Customer Name</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'amount', 'sort' => $nextDirection('amount')]) }}">Amount</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'approve_date', 'sort' => $nextDirection('approve_date')]) }}">Approve Date</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'website', 'sort' => $nextDirection('website')]) }}">Website</a></th>
                            <th>Delete</th>
                        </tr>
                    @else
                        <tr>
                            <th>Action</th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_id', 'sort' => $nextDirection('order_id')]) }}">Order ID</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'design_name', 'sort' => $nextDirection('design_name')]) }}">Design Name</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'customer_name', 'sort' => $nextDirection('customer_name')]) }}">Customer Name</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'amount', 'sort' => $nextDirection('amount')]) }}">Amount</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'trandtime', 'sort' => $nextDirection('trandtime')]) }}">Transaction Date</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'transid', 'sort' => $nextDirection('transid')]) }}">Transaction ID</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'website', 'sort' => $nextDirection('website')]) }}">Website</a></th>
                            <th>Delete</th>
                        </tr>
                    @endif
                    </thead>
                    <tbody>
                    @if (collect($billings)->isEmpty())
                        <tr>
                            <td colspan="{{ $mode === 'due' ? 8 : 8 }}" class="muted">No billing records match the current filters.</td>
                        </tr>
                    @else
                    @foreach ($billings as $billing)
                        <tr>
                            <td>
                                @if ($detailUrl($billing))
                                    <a class="badge" href="{{ $detailUrl($billing) }}">{{ $detailLabel($billing) }}</a>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>{{ $billing->order_id }}</td>
                            <td>{{ $billing->order?->design_name ?: '-' }}</td>
                            <td>{{ $billing->customer?->display_name ?: '-' }}</td>
                            @if ($mode === 'due')
                                <td>{{ is_numeric($billing->amount) ? number_format((float) $billing->amount, 2) : ($billing->amount ?: '0.00') }}</td>
                                <td>{{ $billing->approve_date ?: '-' }}</td>
                                <td>{{ $billing->website ?: '-' }}</td>
                            @else
                                <td>{{ is_numeric($billing->amount) ? number_format((float) $billing->amount, 2) : ($billing->amount ?: '0.00') }}</td>
                                <td>{{ $billing->trandtime ?: '-' }}</td>
                                <td>{{ $billing->transid ?: '-' }}</td>
                                <td>{{ $billing->website ?: '-' }}</td>
                            @endif
                            <td>
                                <form method="post" action="{{ url('/v/billing/'.$billing->bill_id.'/delete') }}" onsubmit="return confirm('Delete this billing record?');">
                                    @csrf
                                    @foreach (request()->query() as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="hidden" name="return_to" value="{{ $mode }}">
                                    <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($billings->hasPages())
                <div style="margin-top:18px;">
                    {{ $billings->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
