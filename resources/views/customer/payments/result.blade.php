@extends('layouts.customer')

@section('title', 'Payment Status — '.$siteContext->displayLabel())
@section('hero_class', 'hero-compact')
@section('hero_title', 'Payment Status')
@section('hero_text', $siteContext->displayLabel())

@section('content')
    @php
        $ok = (bool) $result['ok'];
        $isOffer = $transaction->payment_scope === 'signup_offer';
    @endphp

    <section class="content-card" style="padding: clamp(20px, 3vw, 32px);">

        <div class="payment-status-banner">
            <div class="payment-status-main">
                <div class="payment-status-icon {{ $ok ? 'ok' : 'fail' }}">
                    {{ $ok ? '✓' : '!' }}
                </div>
                <div>
                    <h3>{{ $ok ? 'Payment Confirmed' : 'Payment Needs Attention' }}</h3>
                    <p>{{ $result['message'] }}</p>
                </div>
            </div>
            <div class="payment-amount-col">
                <div class="payment-amount-label">Amount</div>
                <div class="payment-amount-value">${{ number_format((float) $transaction->requested_amount, 2) }}</div>
                @if ($transaction->confirmed_amount && (float) $transaction->confirmed_amount !== (float) $transaction->requested_amount)
                    <div class="payment-amount-note">Confirmed: ${{ number_format((float) $transaction->confirmed_amount, 2) }}</div>
                @endif
            </div>
        </div>

        <div class="table-wrap responsive-stack" style="margin-bottom:20px;">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>{{ $isOffer ? 'Type' : 'Invoice' }}</th>
                        <th>{{ $isOffer ? 'Description' : 'Order' }}</th>
                        <th>Status</th>
                        <th class="th-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($transaction->items->isEmpty() && $isOffer)
                        <tr>
                            <td>Offer</td>
                            <td>New member welcome offer</td>
                            <td><span class="status {{ $ok ? 'success' : 'warning' }}">{{ $ok ? 'paid' : 'pending' }}</span></td>
                            <td class="td-right">${{ number_format((float) $transaction->requested_amount, 2) }}</td>
                        </tr>
                    @else
                        @foreach ($transaction->items as $item)
                            <tr>
                                <td>{{ $item->billing_id ?: '—' }}</td>
                                <td>{{ $item->order?->design_name ?: 'Order #'.$item->order_id }}</td>
                                <td><span class="status {{ $item->status === 'paid' ? 'success' : 'warning' }}">{{ $item->status }}</span></td>
                                <td class="td-right">${{ number_format((float) $item->requested_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="payment-detail-strip">
            <div class="payment-detail-item">
                <div class="di-label">Reference</div>
                <div class="di-value mono">{{ $transaction->merchant_reference }}</div>
            </div>
            <div class="payment-detail-item">
                <div class="di-label">Provider</div>
                <div class="di-value">{{ \App\Support\HostedPaymentProviders::label((string) $transaction->provider) }}</div>
            </div>
            @if ($transaction->provider_transaction_id)
                <div class="payment-detail-item">
                    <div class="di-label">Provider Ref</div>
                    <div class="di-value mono">{{ $transaction->provider_transaction_id }}</div>
                </div>
            @endif
        </div>

        <div class="file-actions">
            <a class="button" href="{{ $isOffer ? '/dashboard.php' : '/view-billing.php' }}">
                {{ $isOffer ? 'Go To Dashboard' : 'Back To Billing' }}
            </a>
            @if (! $isOffer)
                <a class="button secondary" href="/dashboard.php">Dashboard</a>
            @endif
        </div>

    </section>
@endsection
