@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'bill_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Payment Due Report | 1Dollar Admin')
@section('page_heading', 'Payment Due Report')
@section('page_subheading', 'Review approved unpaid billing by customer.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/payment-due-report.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtorderID">Order ID</label>
                    <input id="txtorderID" type="text" name="txtorderID" value="{{ request('txtorderID') }}">
                </div>
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
                <div class="field">
                    <label for="txtInvoiceNumber">Invoice / Email / Login</label>
                    <input id="txtInvoiceNumber" type="text" name="txtInvoiceNumber" value="{{ request('txtInvoiceNumber') }}">
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
            @include('shared.admin-report-export', [
                'copy' => 'Download the current due-payment report.',
                'label' => 'Download Report',
                'show' => $groups->count() > 0,
                'marginTop' => '14px',
                'marginBottom' => '0',
            ])
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Outstanding Billing</h3>
                    <p class="muted" style="margin:0;">Total approved unpaid amount: {{ number_format((float) $totalApproved, 2) }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span class="badge">customer balances</span>
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
                        <th>Available Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($groups)->isEmpty())
                        <tr><td colspan="7" class="muted">No unpaid billing records were found.</td></tr>
                    @else
                    @foreach ($groups as $group)
                        @php $customer = $customers[$group->user_id] ?? null; @endphp
                        <tr>
                            <td><a class="badge" href="{{ url('/v/payment-due-detail.php?uid='.$group->user_id.'&source=payment-due-report') }}">View Orders</a></td>
                            <td>#{{ $group->bill_id }}</td>
                            <td>{{ $group->user_id }}</td>
                            <td>{{ $customer?->display_name ?: '-' }}</td>
                            <td>{{ $group->total_design }}</td>
                            <td>{{ number_format((float) $group->amount_total, 2) }}</td>
                            <td>{{ number_format((float) ($balancesByCustomer[$group->user_id] ?? 0), 2) }}</td>
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
