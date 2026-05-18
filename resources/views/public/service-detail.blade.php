@extends('public.layout')

@php
    $serviceStructuredData = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $service['title'],
        'description' => $service['meta_description'],
        'url' => url('/'.$service['slug'].'.php'),
        'provider' => [
            '@type' => 'Organization',
            'name' => $siteContext->displayLabel(),
            'url' => url('/'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@section('title', $service['title'].' | '.$siteContext->displayLabel())
@section('meta_description', $service['meta_description'])
@section('meta_image', $service['image'])
@section('structured_data')
{!! $serviceStructuredData !!}
@endsection

@section('content')
    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">{{ $siteContext->displayLabel() }}</span>
                <h1>{{ $service['title'] }}</h1>
                <p>{{ $service['meta_description'] }}</p>
                <div class="theme-header-actions">
                    <a class="button primary" href="{{ session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php' }}">Get Quote</a>
                    <a class="button secondary" href="/contact-us.php">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($service['banner_image'] ?? null))
        <section class="banner-section">
            <div class="container">
                <img class="banner-image" src="{{ $service['banner_image'] }}" alt="{{ $service['page_heading'] ?? $service['title'] }}">
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <div class="section-card service-page-copy">
                <div class="section-head">
                    <div>
                        <h2>{{ $service['page_heading'] ?? $service['title'] }}</h2>
                        <p>{{ $service['meta_description'] }}</p>
                    </div>
                </div>

                @foreach ($service['paragraphs'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach

                @if (! empty($service['service_offers'] ?? []))
                    <div class="service-offers-block">
                        <h3>{{ $service['service_offers_title'] ?? 'Services offered' }}</h3>
                        <ul class="service-offers-list">
                            @foreach ($service['service_offers'] as $offer)
                                <li><strong>{{ $offer }}</strong></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($service['content_blocks'] ?? []))
                    @foreach ($service['content_blocks'] as $block)
                        <div class="service-offers-block">
                            @if (! empty($block['title'] ?? null))
                                <h3>{{ $block['title'] }}</h3>
                            @endif
                            @foreach (($block['paragraphs'] ?? []) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                            @if (! empty($block['list'] ?? []))
                                <ul class="service-offers-list">
                                    @foreach ($block['list'] as $item)
                                        <li><strong>{{ $item }}</strong></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @endif

                <div class="theme-header-actions content-actions">
                    <a class="button primary" href="{{ session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php' }}">Get Quote</a>
                    <a class="button secondary" href="/contact-us.php">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($service['gallery_images'] ?? []))
        <section class="section">
            <div class="container">
                <div class="service-gallery-grid {{ (int) ($service['gallery_columns'] ?? 2) === 3 ? 'service-gallery-grid-3' : '' }}">
                    @foreach ($service['gallery_images'] as $image)
                        <div class="service-gallery-frame">
                            <img src="{{ $image }}" alt="{{ $service['title'] }}">
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($service['sections'] ?? []))
        @foreach ($service['sections'] as $section)
            <section class="section">
                <div class="container">
                    <div class="section-card">
                        <div class="service-showcase-grid service-showcase-grid-split">
                            <div class="service-showcase-card">
                                <img src="{{ $section['image'] }}" alt="{{ $section['title'] }}">
                            </div>
                            <div class="service-showcase-copy">
                                <h3>{{ $section['title'] }}</h3>
                                @foreach ($section['paragraphs'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endforeach
    @endif

    @if (empty($service['hide_highlights'] ?? false) && ! empty($service['highlights'] ?? []))
        <section class="section">
            <div class="container">
                <div class="section-card">
                    <div class="section-head">
                        <div>
                            <h2>{{ $service['highlights_title'] ?? 'Highlights' }}</h2>
                        </div>
                    </div>
                    <div class="marketing-feature-grid">
                        @foreach ($service['highlights'] as $highlight)
                            <article class="marketing-feature-card">
                                <span>{{ $service['title'] }}</span>
                                <h3>{{ $highlight }}</h3>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <div class="template-cta-card">
                <span class="theme-badge">Need A Quote?</span>
                <h2>Send Your Artwork When You Are Ready</h2>
                <p>Send us your design and we will review the artwork, confirm the turnaround, and provide a quote for this service.</p>
                <div class="theme-header-actions">
                    <a class="button primary" href="{{ session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php' }}">Get Quote</a>
                    <a class="button secondary" href="/contact-us.php">Ask A Question</a>
                </div>
            </div>
        </div>
    </section>
@endsection
