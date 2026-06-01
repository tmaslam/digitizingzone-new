@extends('public.layout')

@section('title', 'Embroidery Digitizing Services | From $1 | '.$siteContext->displayLabel())
@section('meta_description', 'Professional embroidery digitizing services from $1 — logo digitizing, 3D puff, applique, chain stitch, vector art conversion and more. All file formats, free revisions.')

@section('content')
    @php
        $serviceCards = [
            [
                'title' => 'Custom <span>Embroidery Digitizing</span>',
                'summary' => 'Production-ready machine embroidery files tailored to your design and fabric type. We optimize stitch counts and paths for the best results.',
                'features' => [
                    'Optimized for your specific machine',
                    'Multiple format support (DST, PES, EXP, etc.)',
                    'Fast 24-hour turnaround',
                    'Free minor edits included',
                ],
                'price' => 'Starting at $1 per 1,000 stitches',
                'image' => url('/images/Embroidery-Digitizings.webp'),
                'href' => url('/embroidery-digitizing.php'),
            ],
            [
                'title' => '<span>3D Puff</span> Embroidery',
                'summary' => 'Add dimension and impact with expertly digitized 3D puff embroidery designs. Perfect for caps, jackets, and bold logos.',
                'features' => [
                    'Optimal foam integration',
                    'Clean, defined edges',
                    'Great for caps and outerwear',
                    'Enhanced visual impact',
                ],
                'price' => 'Starting at $3 per 1,000 stitches',
                'image' => url('/images/3D-puff.webp'),
                'href' => url('/3d-puff-embroidery-digitizing.php'),
            ],
            [
                'title' => '<span>Applique & Chain Stitch</span>',
                'summary' => 'Beautiful applique designs that combine fabric pieces with embroidery for unique, textured results. Chain stitch for vintage aesthetics.',
                'features' => [
                    'Precision placement stitches',
                    'Multiple fabric options',
                    'Cost-effective for large designs',
                    'Unique texture effects',
                ],
                'price' => 'Starting at $2 per 1,000 stitches',
                'image' => url('/images/Applique-Embroidery-Digitizing.webp'),
                'href' => url('/applique-embroidery-digitizing.php'),
            ],
            [
                'title' => '<span>Photo</span> Digitizing',
                'summary' => 'Transform photographs into embroidery-ready designs. Perfect for portraits, memorials, and special keepsakes.',
                'features' => [
                    'Detailed portrait conversion',
                    'Memorial and tribute designs',
                    'High stitch density for detail',
                    'Custom sizing available',
                ],
                'price' => 'Custom Quote Required',
                'image' => url('/images/Photo-Digitizing.webp'),
                'href' => url('/photo-digitizing.php'),
            ],
            [
                'title' => '<span>Vector Art</span> Services',
                'summary' => 'Professional vector conversion for logos and graphics. Convert raster images to scalable vector formats for any application.',
                'features' => [
                    'AI, EPS, PDF, SVG formats',
                    'Logo redraws and cleanup',
                    'Print-ready graphics',
                    'Unlimited scalability',
                ],
                'price' => '$6 per hour',
                'image' => url('/images/Vector-Art.webp'),
                'href' => url('/vector-art.php'),
            ],
            [
                'title' => '<span>Chain Stitch</span> Embroidery',
                'summary' => 'Classic chain stitch digitizing for vintage-style designs. Perfect for decorative applications and retro aesthetics.',
                'features' => [
                    'Authentic vintage look',
                    'Decorative applications',
                    'Unique texture effects',
                    'Traditional craftsmanship',
                ],
                'price' => 'Starting at $1.50 per 1,000 stitches',
                'image' => url('/images/Chain-Stitch-Embroidery-Digitizing.webp'),
                'href' => url('/chain-stitch-embroidery-digitizing.php'),
            ],
        ];
    @endphp

    <section class="page-header services-hero-flat">
        <div class="container">
            <div class="services-page-header">
                <h1>Professional <span>Embroidery Digitizing</span> Services &mdash; Starting at $1</h1>
                <p>We offer a comprehensive range of embroidery digitizing and vector art services tailored to meet the needs of apparel decorators, screen printers, and promotional product businesses.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="services-grid">
                @foreach ($serviceCards as $service)
                    <div class="service-card">
                        <img src="{{ $service['image'] }}" alt="{{ strip_tags($service['title']) }}">
                        <div class="service-card-content">
                            <h3>{!! $service['title'] !!}</h3>
                            <p>{{ $service['summary'] }}</p>
                            <ul class="service-features">
                                @foreach ($service['features'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            <span class="price-tag">{{ $service['price'] }}</span>
                            <a class="inline-link" href="{{ $service['href'] }}">Learn more →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container public-center-wrap">
            <div class="template-cta-card">
                <h2>Need a Custom Quote?</h2>
                <p>Upload your design and we&apos;ll provide a detailed quote within hours.</p>
                <div class="theme-header-actions">
                    <a href="{{ url('/contact-us.php') }}" class="button secondary">Get Your Free Quote</a>
                </div>
            </div>
        </div>
    </section>
@endsection
