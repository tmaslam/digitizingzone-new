@extends('layouts.customer')

@section('title', 'My Billing - '.$siteContext->displayLabel())
@section('hero_title', 'Billing')
@section('hero_text', 'See what is due, use available balance, and complete payments in one place.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Billing Overview</h3>
                <p>Your current outstanding balance and account credit at a glance.</p>
            </div>
        </div>
        <div class="portal-stat-grid">
            <div class="portal-stat">
                <span>Outstanding Total</span>
                <strong>${{ number_format($outstandingTotal, 2) }}</strong>
            </div>
            <div class="portal-stat">
                <span>Available Balance</span>
                <strong>${{ number_format($availableBalance, 2) }}</strong>
            </div>
            <div class="portal-stat">
                <span>Admin Deposit</span>
                <strong>${{ number_format($depositBalance, 2) }}</strong>
            </div>
            <div class="portal-stat">
                <span>Open Invoices</span>
                <strong>{{ $billingSummary['invoice_count'] }}</strong>
            </div>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Outstanding Invoices</h3>
                <p>Pay invoices one at a time or together. The largest current invoice is ${{ number_format($billingSummary['largest_invoice'], 2) }}.</p>
            </div>
            @if ($billingRows->count())
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <form method="post" action="{{ url('/view-billing.php/pay-with-deposit') }}">
                        @csrf
                        <button type="submit" class="button" style="background: linear-gradient(135deg, #d62b2b, #b01f1f); color: #fff; font-weight: 600; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer;">Pay via Credit Account</button>
                    </form>
                    <form method="post" action="{{ url('/view-billing.php/pay-all') }}">
                        @csrf
                        @include('customer.payments.provider-buttons', [
                            'paymentProviders' => $paymentProviders,
                            'buttonPrefix' => 'Pay All With',
                        ])
                    </form>
                </div>
            @endif
        </div>

        @if ($billingRows->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Order</th>
                        <th>Approved</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($billingRows as $billing)
                        <tr>
                            <td data-label="Invoice">INV-{{ $billing->bill_id }}</td>
                            <td data-label="Order">
                                @if ($billing->order)
                                    <a href="{{ url('/view-order-detail.php') }}?order_id={{ $billing->order->order_id }}&origin=billing">Order #{{ $billing->order->order_id }} - {{ $billing->order->design_name ?: 'Design' }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td data-label="Approved">{{ $billing->approve_date ?: '-' }}</td>
                            <td data-label="Amount">${{ number_format((float) preg_replace('/[^0-9.\-]/', '', (string) $billing->amount), 2) }}</td>
                            <td data-label="Status"><span class="status warning">Payment Due</span></td>
                            <td data-label="Action">
                                <form method="post" action="{{ url('/view-billing.php/' . $billing->bill_id . '/pay') }}">
                                    @csrf
                                    @include('customer.payments.provider-buttons', [
                                        'paymentProviders' => $paymentProviders,
                                        'buttonPrefix' => 'Pay With',
                                    ])
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $billingRows->links() }}
            </div>
        @else
            <div class="empty-state">No unpaid invoices are currently open.</div>
        @endif
    </section>
@endsection
