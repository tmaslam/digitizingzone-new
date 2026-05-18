@extends('layouts.customer')

@section('title', 'Secure Payment — '.$siteContext->displayLabel())
@section('hero_class', 'hero-compact')
@section('hero_title', 'Secure Payment')
@section('hero_text', $siteContext->displayLabel())

@section('content')
    @php
        $items = $checkoutItems ?? $transaction->items;
        $isOffer = ($itemCountLabel ?? 'Invoices') === 'Offer';
        $amount = number_format((float) $transaction->requested_amount, 2);
    @endphp

    <section class="content-card" style="padding: clamp(20px, 3vw, 32px);">

        <div class="payment-card-header">
            <div>
                <h3>Review &amp; Continue</h3>
                <p>Your payment request is already recorded. Confirm the details below before continuing to the payment page.</p>
            </div>
            <div class="payment-amount-col">
                <div class="payment-amount-label">Total Due</div>
                <div class="payment-amount-value">${{ $amount }}</div>
                <div class="payment-amount-note">{{ $providerLabel ?? 'Hosted Payment' }}</div>
            </div>
        </div>

        <div class="table-wrap responsive-stack" style="margin-bottom:20px;">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>{{ $isOffer ? 'Type' : 'Invoice' }}</th>
                        <th>{{ $isOffer ? 'Description' : 'Order' }}</th>
                        <th class="th-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item['invoice'] ?? ($item->billing_id ?: '—') }}</td>
                            <td>{{ $item['title'] ?? ($item->order?->design_name ?: 'Order #'.$item->order_id) }}</td>
                            <td class="td-right">${{ number_format((float) ($item['amount'] ?? $item->requested_amount), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="payment-ref-strip">
            <span class="payment-ref-label">Ref</span>
            <span class="payment-ref-value">{{ $transaction->merchant_reference }}</span>
        </div>

        @if (! empty($simulationMode))
            <div class="content-note" style="margin-bottom:20px;">
                <strong>Test payment mode is enabled.</strong>
                {{ $simulationMode['message'] ?? 'This checkout will follow the configured simulated outcome.' }}
            </div>
        @endif

        @if ($purchaseUrl)
            <form id="hosted-payment-form" method="post" action="{{ $purchaseUrl }}">
                <input type="hidden" name="sid" value="{{ $sellerId }}">
                <input type="hidden" name="cart_order_id" value="{{ $transaction->merchant_reference }}">
                <input type="hidden" name="total" value="{{ number_format((float) $transaction->requested_amount, 2, '.', '') }}">
                <input type="hidden" name="x_receipt_link_url" value="{{ $returnUrl }}">
            </form>
        @endif

        <div class="file-actions">
            @if (! empty($simulationMode))
                <a class="button" href="{{ $simulationMode['url'] }}">
                    {{ $simulationMode['label'] ?? ('Continue With Simulated '.($providerLabel ?? 'Payment')) }}
                </a>
            @elseif ($purchaseUrl)
                <button type="submit" class="button" form="hosted-payment-form">
                    Continue To {{ $providerLabel ?? 'Secure Payment' }}
                </button>
            @elseif (! empty($autoRedirectUrl))
                <a class="button" href="{{ $autoRedirectUrl }}">Continue To {{ $providerLabel ?? 'Secure Payment' }}</a>
            @endif
            <a class="button secondary" href="{{ $backUrl ?? '/view-billing.php' }}">{{ $backLabel ?? 'Back To Billing' }}</a>
        </div>

    </section>
@endsection
