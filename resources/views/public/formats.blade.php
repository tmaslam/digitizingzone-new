@extends('public.layout')

@section('title', 'Supported Formats | '.$siteContext->displayLabel())
@section('meta_description', 'Review the embroidery digitizing and vector art formats supported by '.$siteContext->displayLabel().'.')

@section('content')
    @php
        $legacyAssetBase = rtrim(request()->getSchemeAndHttpHost(), '/');
    @endphp

    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">{{ $siteContext->displayLabel() }}</span>
                <h1>Supported <span>Embroidery And Vector Formats</span></h1>
                <p>Review the machine embroidery and vector file formats we support for artwork preparation, production, and delivery.</p>
                <div class="theme-header-actions">
                    <a class="button primary" href="{{ url('/sign-up.php') }}">Get Quote</a>
                    <a class="button secondary" href="{{ url('/contact-us.php') }}">Ask About A Format</a>
                </div>
                <div class="formats-jump-nav" aria-label="Format sections">
                    <a href="#embroidery-formats">Machine Embroidery</a>
                    <a href="#vector-formats">Vector File</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="embroidery-formats">
        <div class="container">
            <div class="section-card">
                <div class="section-head legacy-underline" style="text-align:left;margin-bottom:14px;">
                    <h2>Machine Embroidery Formats</h2>
                </div>
                <div class="copy">
                    <p>Most to all embroidery software support different source format files. We at <strong>1Dollor</strong> have made most of the <strong>Machine Embroidery Formats</strong>, including both industry standards and others, available for our customers.</p>
                    <div class="service-offers-block" style="margin-top:18px;">
                        <h3>Our Supported Machine Embroidery Formats are as Follows</h3>
                    </div>
                </div>
                <div class="media-frame format-chart-frame">
                    <img src="{{ $legacyAssetBase }}/images/Digitizing-Formats.png" alt="Machine embroidery formats">
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="vector-formats">
        <div class="container">
            <div class="section-card">
                <div class="section-head legacy-underline" style="text-align:left;margin-bottom:14px;">
                    <h2>Vector File</h2>
                </div>
                <div class="copy">
                    <p>Vector file formats are industry standards worldwide and can be converted to any format one wants form the source file.</p>
                    <div class="service-offers-block" style="margin-top:18px;">
                        <h3>Vector File Formats and Extensions we Offer are as Follows</h3>
                    </div>
                </div>
                <div class="media-frame format-chart-frame">
                    <img src="{{ $legacyAssetBase }}/images/Vector-File-Formats.jpg" alt="Vector file formats">
                </div>
            </div>
        </div>
    </section>

    <style>
        .format-chart-frame {
            margin-top: 20px;
            padding: 14px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 18px 38px rgba(12, 48, 89, 0.10);
        }

        .format-chart-frame img {
            display: block;
            width: 100%;
            height: auto;
            max-height: none;
            object-fit: contain;
        }

        .formats-jump-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin: 20px 0 0;
        }

        .formats-jump-nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(214, 43, 43, 0.10) 0%, rgba(214, 43, 43, 0.04) 100%);
            border: 1px solid rgba(214, 43, 43, 0.16);
            color: #b01f1f;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(12, 48, 89, 0.07);
        }

        .formats-jump-nav a:hover {
            background: #d62b2b;
            color: #fff;
        }
    </style>
@endsection
