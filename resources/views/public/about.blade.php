@extends('public.layout')

@section('title', 'About Us | Embroidery Digitizing Since 2005 | '.$siteContext->displayLabel())
@section('meta_description', 'About 1DollarDigitizing — professional embroidery digitizing since 2005. Over 1 million designs for 10,000+ customers worldwide. Quality, speed and affordable $1 pricing.')

@section('content')
    @php
        $legacyAssetBase = rtrim(request()->getSchemeAndHttpHost(), '/');
        $stats = [
            ['number' => '2005', 'label' => 'Founded'],
            ['number' => '10K+', 'label' => 'Happy Customers'],
            ['number' => '1M+', 'label' => 'Designs Completed'],
        ];
        $values = [
            [
                'icon' => '✓',
                'title' => 'Quality First',
                'body' => 'Every design is meticulously crafted to ensure clean stitches and optimal results on your machines.',
            ],
            [
                'icon' => '⚡',
                'title' => 'Speed & Reliability',
                'body' => '24-hour standard turnaround with consistent, dependable delivery you can count on.',
            ],
            [
                'icon' => '💰',
                'title' => 'Transparent Pricing',
                'body' => 'No hidden fees or surprises. Just straightforward pricing that respects your budget.',
            ],
            [
                'icon' => '🤝',
                'title' => 'Customer Partnership',
                'body' => 'We view every client as a partner, working together to achieve the best possible results.',
            ],
        ];
    @endphp

    <section class="page-header">
        <div class="container">
            <div class="about-page-header">
                <h1>About <span>1 Dollar Digitizing</span></h1>
                <p>Your trusted partner for premium embroidery digitizing and vector art services since 2005.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="service-showcase-grid service-showcase-grid-split">
                    <div class="service-showcase-card">
                        <img src="{{ $legacyAssetBase }}/images/1dollar-Digitizing.webp" alt="About 1 Dollar Digitizing">
                    </div>
                    <div class="service-showcase-copy">
                        <h2>Our <span>Story</span></h2>
                        <p>Founded in 2005, 1 Dollar Digitizing has grown from a small digitizing shop to a trusted partner for thousands of apparel decorators, screen printers, and promotional product businesses across the United States.</p>
                        <p>Our mission is simple: deliver production-ready embroidery files and crisp vector graphics at prices that make sense for your business. We combine decades of expertise with cutting-edge software to ensure every design meets the highest standards of quality.</p>
                        <p>Over the years, we've digitized over 1 Million designs for more than 10,000 satisfied customers. From small shops to large commercial operations, we treat every project with the same attention to detail and commitment to excellence.</p>
                    </div>
                </div>
                <div class="stats-grid stats-grid-spaced">
                    @foreach ($stats as $stat)
                        <div class="stat-card">
                            <div class="stat-number">{{ $stat['number'] }}</div>
                            <div class="stat-label">{{ $stat['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="section-header">
                    <h2>Our Core <span>Values</span></h2>
                    <p>The principles that guide everything we do</p>
                </div>
                <div class="features-grid">
                    @foreach ($values as $value)
                        <article class="feature-item">
                            <div class="feature-icon">{{ $value['icon'] }}</div>
                            <h3>{{ $value['title'] }}</h3>
                            <p>{{ $value['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card public-card-center">
                <div class="section-header section-header-tight">
                    <h2>Our <span>Location</span></h2>
                    <p>Based in Fremont, California, serving customers nationwide</p>
                </div>
                <h3 class="location-title">1 Dollar Digitizing</h3>
                <p class="location-copy">46494 Mission Blvd<br>Fremont, CA 94539<br>United States</p>
                <p class="location-line"><a href="tel:+12063126446" class="inline-link">+1 (206) 312-6446</a></p>
                @if ($siteContext->supportEmail)
                    <p><a href="mailto:{{ $siteContext->supportEmail }}" class="inline-link">{{ $siteContext->supportEmail }}</a></p>
                @endif
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="template-cta-card">
                <h2>Ready to Work With Us?</h2>
                <p>Experience the difference that quality digitizing can make for your business.</p>
                <div class="theme-header-actions">
                    <a class="button secondary" href="{{ session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php' }}">Get Your Free Quote</a>
                </div>
            </div>
        </div>
    </section>
@endsection
