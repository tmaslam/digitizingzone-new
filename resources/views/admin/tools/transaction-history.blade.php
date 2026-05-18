@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'Effective_Date');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Transaction History | 1Dollar Admin')
@section('page_heading', 'Transaction History')
@section('page_subheading', 'Manual payment ledger and outstanding balances.')

@section('content')
    @unless ($hasPaymentsTable)
        <div class="alert">The `customerpayments` table was not found in this database, so transaction history and manual payment entry are unavailable here.</div>
    @endunless

    <section class="card">
        <div class="card-body">
            <div class="stats">
                <a class="stat-link" href="{{ url('/v/payment-due-report.php') }}">
                    <article class="stat">
                        <span class="muted">Outstanding Due</span>
                        <strong style="font-size:1.25rem;">{{ $hasPaymentsTable ? number_format((float) $totalDue, 2).' USD' : 'N/A' }}</strong>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/transaction-history.php#ledger-records') }}">
                    <article class="stat">
                        <span class="muted">Manual Ledger Total</span>
                        <strong style="font-size:1.25rem;">{{ $hasPaymentsTable ? number_format((float) $totalAmount, 2).' USD' : 'N/A' }}</strong>
                    </article>
                </a>
                <article class="stat"><span class="muted">Actions</span><strong style="font-size:1rem;"><a href="{{ url('/v/pay-now.php') }}">New Payment</a></strong></article>
            </div>
            @if ($hasCustomerBalanceTable)
                <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ url('/v/customer-payment-inventory.php') }}">Open Customer Payment Inventory</a>
                </div>
            @endif
        </div>
    </section>

    @if ($hasPaymentsTable)
        <section class="card">
            <div class="card-body">
                <form method="get" action="{{ url('/v/transaction-history.php') }}" class="toolbar" style="margin-bottom:18px;">
                    <div class="field">
                        <label for="txtUserID">User ID</label>
                        <input id="txtUserID" type="text" name="txtUserID" value="{{ request('txtUserID') }}">
                    </div>
                    <div class="field">
                        <label for="txtSeqNo">Reference Number</label>
                        <input id="txtSeqNo" type="text" name="txtSeqNo" value="{{ request('txtSeqNo') }}">
                    </div>
                    <div class="field">
                        <label for="txtTransID">Transaction ID</label>
                        <input id="txtTransID" type="text" name="txtTransID" value="{{ request('txtTransID') }}">
                    </div>
                    <div class="field">
                        <label for="txtSource">Payment Source</label>
                        <input id="txtSource" type="text" name="txtSource" value="{{ request('txtSource') }}">
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Filter</button>
                    </div>
                </form>

                <div id="ledger-records" style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:18px;">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:1.15rem;">Ledger Records</h3>
                        <p class="muted" style="margin:0;">Manual payment entries for the current business ledger.</p>
                    </div>
                    <span class="badge">single-site ledger</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Customer</th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Seq_No', 'sort' => $nextDirection('Seq_No')]) }}">Reference Number</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Payment_Amount', 'sort' => $nextDirection('Payment_Amount')]) }}">Payment Amount</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Payment_Source', 'sort' => $nextDirection('Payment_Source')]) }}">Payment Source</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Transaction_ID', 'sort' => $nextDirection('Transaction_ID')]) }}">Transaction ID</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Effective_Date', 'sort' => $nextDirection('Effective_Date')]) }}">Transaction Date</a></th>
                        </tr>
                        </thead>
                        <tbody>
                        @if (collect($records)->isEmpty())
                            <tr><td colspan="7" class="muted">No transaction rows found.</td></tr>
                        @else
                        @foreach ($records as $record)
                            @php
                                $linkedCustomer = $linkedCustomers->get('customerpayments:'.$record->Seq_No);
                            @endphp
                            <tr>
                                <td><a class="badge" href="{{ url('/v/pay-now.php?id='.$record->Seq_No) }}">Edit</a></td>
                                <td>
                                    @if ($linkedCustomer)
                                        <div>{{ $linkedCustomer->display_name }}</div>
                                        <div class="muted" style="font-size:0.85rem;">User ID: {{ $linkedCustomer->user_id }}</div>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $record->Seq_No }}</td>
                                <td>{{ $record->Payment_Amount }} USD</td>
                                <td>{{ $record->Payment_Source }}</td>
                                <td>{{ $record->Transaction_ID }}</td>
                                <td>{{ $record->Effective_Date }}</td>
                            </tr>
                        @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>

                @if ($paginator && $paginator->hasPages())
                    <div style="margin-top:18px;">{{ $paginator->links() }}</div>
                @endif
            </div>
        </section>
    @endif
@endsection
