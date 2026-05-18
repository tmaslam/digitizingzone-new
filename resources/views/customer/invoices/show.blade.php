@extends('layouts.customer')

@section('title', 'Invoice Detail - '.$transactionId)
@section('hero_title', 'Invoice Detail')
@section('hero_text', 'Review the exact orders included in this paid transaction without exposing data from any other site account.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Transaction ID: {{ $transactionId }}</h3>
                <p>Payment date: {{ $invoiceDate ?: '-' }}</p>
            </div>
            <div class="invoice-detail-actions">
                <a class="button secondary" href="/view-invoices.php">Back to Invoices</a>
                <a class="button secondary" href="/view-invoice-detail.php?transid={{ urlencode($transactionId) }}&download=pdf">Download Invoice</a>
                <span class="status success">Total ${{ number_format($invoiceTotal, 2) }}</span>
            </div>
        </div>

        <div class="table-wrap responsive-stack">
            <table class="responsive-table">
                <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Design Name</th>
                    <th>Completion Date</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($invoiceItems as $billing)
                    <tr>
                        <td>
                            @if ($billing->order)
                                <a href="/view-order-detail.php?order_id={{ $billing->order->order_id }}&origin=invoices">{{ $billing->order->order_id }}</a>
                            @else
                                {{ $billing->order_id }}
                            @endif
                        </td>
                        <td>{{ $billing->order?->design_name ?: 'Order #'.$billing->order_id }}</td>
                        <td>{{ $billing->order?->completion_date ?: $billing->trandtime ?: '-' }}</td>
                        <td>${{ number_format((float) preg_replace('/[^0-9.\-]/', '', (string) ($billing->amount ?: $billing->order?->total_amount)), 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
