@extends('layouts.customer')

@php
    $emailAlreadyVerified = ! empty($claim->verified_at);
    $singlePaymentProvider = count($paymentProviders ?? []) === 1 ? $paymentProviders[0] : null;
    $statusLabel = $emailAlreadyVerified ? 'Email Verified' : 'Verification Required';
    $statusClass = $emailAlreadyVerified ? 'success' : 'warning';
    $heroTitle = $emailAlreadyVerified ? 'Secure Your Welcome Offer' : 'Verify And Secure Your Account';
    $heroText = $emailAlreadyVerified
        ? 'Your email is already verified. Complete the secure onboarding payment to unlock the first-order offer on this website.'
        : 'Verify your email address, complete the secure $1 onboarding payment, and unlock the first-order offer on this website.';
    $summaryText = $emailAlreadyVerified
        ? 'Your email is already verified. Complete the secure welcome payment to finish activating this customer account.'
        : ($offer['summary'] ?? 'Complete the secure welcome payment to finish activating this customer account.');
@endphp

@section('title', 'Welcome Offer - '.$siteContext->displayLabel())
@section('hero_title', $heroTitle)
@section('hero_text', $heroText)

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>{{ $offer['headline'] ?? 'New member welcome offer' }}</h3>
                <p>{{ $summaryText }}</p>
            </div>
            <span class="status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <div class="info-grid">
            <div class="info-card"><span>Welcome Payment</span><strong>${{ number_format((float) $claim->required_payment_amount, 2) }}</strong></div>
            @if ((float) $claim->credit_amount > 0)
                <div class="info-card"><span>Stored Credit</span><strong>${{ number_format((float) $claim->credit_amount, 2) }}</strong></div>
            @endif
            @if (($offer['first_order_free_under_stitches'] ?? 0) > 0)
                <div class="info-card"><span>First Order Benefit</span><strong>Free under {{ number_format((int) $offer['first_order_free_under_stitches']) }} stitches</strong></div>
            @else
                <div class="info-card"><span>First Order Price</span><strong>${{ number_format((float) ($claim->first_order_flat_amount ?: 0), 2) }}</strong></div>
            @endif
            <div class="info-card"><span>Website</span><strong>{{ $siteContext->displayLabel() }}</strong></div>
        </div>

        @unless ($emailAlreadyVerified)
            <div class="content-note">
                <strong>Why this step exists:</strong>
                This helps us confirm that we are dealing with a real customer, reduces spam submissions, and keeps the website safer for legitimate customers.
            </div>
        @endunless

        <div class="content-note">
            <strong>Secure card handling:</strong>
            Card details stay with the hosted payment provider. This website only stores the verified payment result and your transaction reference.
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start;">
            <form method="post" action="/member-offer.php/pay" class="stack" id="signup-offer-payment-form">
                @csrf
                @if ($singlePaymentProvider)
                    <input type="hidden" name="provider" value="{{ $singlePaymentProvider['key'] }}">
                @endif
                @include('customer.payments.provider-buttons', [
                    'paymentProviders' => $paymentProviders,
                    'buttonPrefix' => 'Continue With',
                ])
            </form>
            <a class="button secondary" href="/logout.php">Log Out</a>
        </div>
    </section>

    @if ($singlePaymentProvider)
        <script>
            window.setTimeout(function () {
                var form = document.getElementById('signup-offer-payment-form');
                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            }, 250);
        </script>
    @endif
@endsection
