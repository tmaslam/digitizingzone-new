@extends('public.layout')

@section('title', 'Payment Options - '.$siteContext->displayLabel())
@section('meta_description', 'Review the secure payment options, billing workflow, and supported customer payment paths for '.$siteContext->displayLabel().'.')

@section('content')
    @php
        $legacyAssetBase = rtrim(request()->getSchemeAndHttpHost(), '/');
        $paymentPoints = [
            ['title' => 'Multiple Payment Methods', 'body' => 'We accept multiple forms and modes of payment suited to customer needs so the checkout step does not become a blocker.'],
            ['title' => 'Invoice-Based Billing', 'body' => 'Customers can review approved invoices and pay against real billing records rather than relying on disconnected manual steps.'],
            ['title' => 'Extra Payment Protection', 'body' => 'If extra money is paid, the system can keep it as customer credit so it is not lost and can be used for future work where allowed.'],
            ['title' => 'Support-Friendly Review', 'body' => 'Admin and billing teams still have visibility into payment history, due amounts, and customer balance without splitting into separate systems.'],
        ];
    @endphp

    <section class="hero" style="--hero-image: url('{{ $legacyAssetBase }}/images/Payment-Options.jpg');">
        <div class="container">
            <span class="eyebrow">Payment Options</span>
            <div class="hero-grid">
                <div>
                    <h1>Secure payment handling with the same customer simplicity</h1>
                    <p>We accept multiple payment options to make checkout easier for customers while keeping invoice control, payment verification, and final file release in the right order.</p>
                    <div class="hero-actions">
                        <a class="button primary" href="{{ url('/view-billing.php') }}">View Billing</a>
                        <a class="button secondary" href="{{ url('/contact-us.php') }}">Billing Support</a>
                    </div>
                </div>
                <div class="hero-card">
                    <h2>Important billing note</h2>
                    <p>We do not store customer card details in the application. Hosted checkout and verified payment callbacks are used so invoices are marked properly and extra payments can move into customer credit.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-head">
                <h2>How Payments Are Handled</h2>
                <p>Payment is kept flexible for customers and controlled for the business, so billing remains accurate and easier to support.</p>
            </div>

            <div class="grid-2">
                @foreach ($paymentPoints as $point)
                    <article class="card">
                        <div class="card-body">
                            <span>Payment</span>
                            <h3>{{ $point['title'] }}</h3>
                            <p>{{ $point['body'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
