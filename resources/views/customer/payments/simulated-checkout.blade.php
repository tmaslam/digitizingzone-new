@extends('layouts.customer')

@section('title', 'Hosted Payment Simulation — '.$siteContext->displayLabel())
@section('hero_class', 'hero-compact')
@section('hero_title', 'Hosted Payment Simulation')
@section('hero_text', $siteContext->displayLabel())

@section('content')
    @php
        $amount = number_format((float) $transaction->requested_amount, 2);
        $isOffer = (string) $transaction->payment_scope === 'signup_offer';
    @endphp

    <section class="content-card" style="padding: clamp(20px, 3vw, 32px);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
            <div>
                <h3 style="margin:0 0 4px; font-size:1.15rem; letter-spacing:-0.02em;">Complete Hosted Checkout</h3>
                <p style="margin:0; color:var(--muted); font-size:0.88rem; max-width:640px;">
                    This local-only simulator stands in for the hosted 2Checkout screen so you can review the intermediate checkout HTML before the customer returns to the payment result page.
                </p>
            </div>
            <div style="text-align:right; flex-shrink:0;">
                <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:4px;">Configured Result</div>
                <div style="font-size:1rem; font-weight:800; letter-spacing:-0.02em; color:var(--ink);">{{ strtoupper($configuredOutcome) }}</div>
                <div style="font-size:0.78rem; color:var(--muted); margin-top:2px;">Total: ${{ $amount }}</div>
            </div>
        </div>

        <div class="content-note" style="margin-bottom:20px;">
            <strong>Simulation only.</strong>
            The real hosted payment logic is unchanged. This page exists only to mirror the customer’s “checkout complete” step on local before the existing success page renders.
        </div>

        <div class="table-wrap" style="margin-bottom:20px;">
            <table>
                <thead>
                    <tr>
                        <th>{{ $isOffer ? 'Type' : 'Invoice' }}</th>
                        <th>{{ $isOffer ? 'Description' : 'Order' }}</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($checkoutItems as $item)
                        <tr>
                            <td>{{ $item['invoice'] }}</td>
                            <td>{{ $item['title'] }}</td>
                            <td style="text-align:right;">${{ number_format((float) $item['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="display:flex; gap:24px; align-items:flex-start; padding:12px 16px; border-radius:12px; background:var(--surface-soft); border:1px solid var(--line); margin-bottom:20px; flex-wrap:wrap;">
            <div>
                <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:3px;">Reference</div>
                <div style="font-size:0.85rem; font-family:monospace; word-break:break-all;">{{ $transaction->merchant_reference }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:3px;">Provider</div>
                <div style="font-size:0.85rem;">{{ $providerLabel }}</div>
            </div>
        </div>

        <div style="display:grid; gap:12px;">
            <div class="actions">
                <a class="button" href="{{ $completeUrl }}">Simulate Completed Payment</a>
                <a class="button secondary" href="{{ $failUrl }}">Simulate Failed Payment</a>
                <a class="button ghost" href="{{ $backUrl }}">Back To Billing</a>
            </div>
            <p class="muted" style="margin:0; font-size:0.86rem;">
                Use the buttons above to continue into the existing payment status page with either a success or failed return.
            </p>
        </div>
    </section>
@endsection
