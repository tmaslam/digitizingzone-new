@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'bill_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Payment Received Report | Digitizing Zone Admin')
@section('page_heading', 'Payment Received Report')
@section('page_subheading', 'Paid billing summary grouped by customer.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/payment-recieved-report.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtorderID">Order ID</label>
                    <input id="txtorderID" type="text" name="txtorderID" value="{{ request('txtorderID') }}">
                </div>
                <div class="field">
                    <label for="txtInvoiceNumber">Invoice Number</label>
                    <input id="txtInvoiceNumber" type="text" name="txtInvoiceNumber" value="{{ request('txtInvoiceNumber') }}">
                </div>
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
                <div class="field">
                    <label for="txtUserID">User ID</label>
                    <input id="txtUserID" type="text" name="txtUserID" value="{{ request('txtUserID') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            @include('shared.admin-report-export', [
                'copy' => 'Download the current received-payment report.',
                'label' => 'Download Report',
                'show' => $groups->count() > 0,
                'marginTop' => '0',
                'marginBottom' => '18px',
            ])
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Received Billing</h3>
                    <p class="muted" style="margin:0;">Visible amount across current rows: {{ number_format((float) $totalReceived, 2) }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span class="badge">paid invoices</span>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'bill_id', 'sort' => $nextDirection('bill_id')]) }}">Invoice Ref</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                        <th>Customer</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'total_design', 'sort' => $nextDirection('total_design')]) }}">Total Design</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'amount_total', 'sort' => $nextDirection('amount_total')]) }}">Amount</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($groups)->isEmpty())
                        <tr><td colspan="6" class="muted">No paid billing records were found.</td></tr>
                    @else
                    @foreach ($groups as $group)
                        @php $customer = $customers[$group->user_id] ?? null; @endphp
                        <tr>
                            <td><a class="badge" href="{{ url('/v/payment-recieved-detail.php?uid='.$group->user_id.'&source=payment-recieved-report') }}">View Orders</a></td>
                            <td>#{{ $group->bill_id }}</td>
                            <td>{{ $group->user_id }}</td>
                            <td>{{ $customer?->display_name ?: '-' }}</td>
                            <td>{{ $group->total_design }}</td>
                            <td>{{ number_format((float) $group->amount_total, 2) }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($groups->hasPages())
                <div style="margin-top:18px;">{{ $groups->links() }}</div>
            @endif
        </div>
    </section>
@endsection
