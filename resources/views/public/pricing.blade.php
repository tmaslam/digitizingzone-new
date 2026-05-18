@extends('public.layout')

@section('title', 'Our Prices | '.$siteContext->displayLabel())
@section('meta_description', 'Affordable embroidery digitizing pricing, rush turnaround options, and additional service details from '.$siteContext->displayLabel().'.')

@section('content')
    @php
        $quoteCtaUrl = request()->session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php';
        $plans = [
            [
                'title' => 'Standard',
                'turnaround' => '⏰ 24-Hour Turnaround',
                'amount' => '$1',
                'unit' => 'per 1,000 stitches',
                'minimums' => ['Minimum charge: $6.00', 'Vector: $6/hr'],
                'features' => ['Free quotes for all', 'Free edits included', '1 year design backup', 'No hidden fees', 'All machine formats', 'Email support'],
                'featured' => false,
            ],
            [
                'title' => 'Priority',
                'turnaround' => '⚡ 12-Hour Turnaround',
                'amount' => '$1.50',
                'unit' => 'per 1,000 stitches',
                'minimums' => ['Minimum charge: $9.00', 'Vector: $9/hr'],
                'features' => ['Free quotes for all', 'Free edits included', '1 year design backup', 'No hidden fees', 'All machine formats', 'Priority support'],
                'featured' => true,
            ],
            [
                'title' => 'Super Rush',
                'turnaround' => '🚀 6-Hour Turnaround',
                'amount' => '$2.00',
                'unit' => 'per 1,000 stitches',
                'minimums' => ['Minimum charge: $12.00', 'Vector: $12/hr'],
                'features' => ['Free quotes for all', 'Free edits included', '1 year design backup', 'No hidden fees', 'All machine formats', 'Priority phone support'],
                'featured' => false,
            ],
        ];
        $extras = [
            ['icon' => '💰', 'title' => 'Extra Setup', 'summary' => "Don't pay full fee. $5.00 only for the extra setup"],
            ['icon' => '📋', 'title' => 'Free Quotes', 'summary' => 'No obligation quotes for all projects'],
            ['icon' => '🔄', 'title' => 'Free Edits', 'summary' => 'Minor adjustments at no extra cost'],
            ['icon' => '💾', 'title' => '1 Year Backup', 'summary' => 'Your designs stored securely for 1 year'],
            ['icon' => '✓', 'title' => 'No Hidden Fees', 'summary' => 'Transparent pricing, no surprises'],
            ['icon' => '🎨', 'title' => '3D Puff', 'summary' => 'No extra charge'],
            ['icon' => '🔗', 'title' => 'Chain Stitch', 'summary' => '$1.5/1,000 stitches'],
            ['icon' => '📊', 'title' => 'Complexity Fee', 'summary' => 'No complexity fee'],
        ];
    @endphp

    <section class="page-header">
        <div class="container">
            <div>
                <h1>Simple, Transparent <span>Pricing</span></h1>
                <p>No hidden fees. No surprises. Just quality digitizing at affordable prices. Get exactly what you pay for with our straightforward pricing structure.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="pricing-grid">
                @foreach ($plans as $plan)
                    <div class="pricing-card {{ $plan['featured'] ? 'featured' : '' }}">
                        @if ($plan['featured'])
                            <span class="pricing-badge">Most Popular</span>
                        @endif
                        <h3>{{ $plan['title'] }}</h3>
                        <div class="turnaround-badge">{{ $plan['turnaround'] }}</div>
                        <div class="pricing-price">
                            <span class="amount">{{ $plan['amount'] }}</span>
                            <span class="unit">{{ $plan['unit'] }}</span>
                            @foreach ($plan['minimums'] as $minimum)
                                <span class="minimum">{{ $minimum }}</span>
                            @endforeach
                        </div>
                        <ul class="pricing-features">
                            @foreach ($plan['features'] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $quoteCtaUrl }}" class="button {{ $plan['featured'] ? 'primary' : 'secondary' }}">Get Started</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>Additional <span>Services</span></h2>
                <p>Extras to enhance your experience</p>
            </div>
            <div class="extras-grid">
                @foreach ($extras as $extra)
                    <div class="extra-card">
                        <div class="extra-icon">{{ $extra['icon'] }}</div>
                        <h4>{{ $extra['title'] }}</h4>
                        <p>{{ $extra['summary'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container public-center-wrap">
            <div class="template-cta-card">
                <h2>Not Sure What You Need?</h2>
                <p>Upload your design and we'll provide a detailed quote with recommendations. Free quotes for all!</p>
                <div class="theme-header-actions">
                    <a href="{{ $quoteCtaUrl }}" class="button secondary">Request Free Quote</a>
                </div>
            </div>
        </div>
    </section>
@endsection
