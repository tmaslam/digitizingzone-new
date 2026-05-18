@extends('public.layout')

@section('title', 'Work Process - '.$siteContext->displayLabel())
@section('meta_description', 'See how the digitizing workflow moves from registration and quote through quality assurance, preview review, payment, and final file release.')

@section('content')
    @php
        $steps = [
            ['title' => 'Request a Quote', 'body' => 'Fill out our simple contact form or email us your design. Include details about size, fabric type, and any special requirements.'],
            ['title' => 'Review Your Quote', 'body' => 'We\'ll analyze your design and send you a detailed quote within 24 hours, including price and estimated turnaround time.'],
            ['title' => 'We Digitize', 'body' => 'Once approved, our expert digitizers get to work. We optimize every stitch for your specific machine and fabric.'],
            ['title' => 'Preview & Delivery', 'body' => 'Review a preview of your design before final delivery. We provide all major file formats ready for production.'],
        ];
        $expectations = [
            ['title' => '1. Submit Your Design', 'body' => 'Upload your artwork through our contact form or email it directly. We accept all common formats including JPG, PNG, PDF, AI, and EPS. Include details about your intended use, fabric type, and any size requirements.'],
            ['title' => '2. Receive Your Quote', 'body' => 'Within 24 hours, you\'ll receive a detailed quote based on stitch count, complexity, and turnaround time. Our pricing is transparent - $1 per 1,000 stitches for standard digitizing with a $6 minimum.'],
            ['title' => '3. Approval & Payment', 'body' => 'Once you approve the quote, simply submit payment to begin work. We accept all major credit cards and PayPal. Rush orders can be accommodated for an additional fee.'],
            ['title' => '4. Production & Review', 'body' => 'Our digitizers create your file, optimizing stitch paths and density for your specific machine and fabric. We send a preview for your approval before finalizing.'],
            ['title' => '5. Final Delivery', 'body' => 'Receive your production-ready files in your preferred format. We keep backups of all designs for future orders and provide free minor edits if needed.'],
        ];
        $benefits = [
            ['icon' => '⚡', 'title' => 'Fast Turnaround', 'body' => '24-hour standard delivery with rush options available when a deadline matters.'],
            ['icon' => '👁️', 'title' => 'Preview Before Paying', 'body' => 'See your digitized design before final delivery to ensure it meets your expectations.'],
            ['icon' => '🔄', 'title' => 'Free Revisions', 'body' => 'Minor adjustments are included at no extra charge. We want you to be 100% satisfied.'],
            ['icon' => '💾', 'title' => 'Secure Backups', 'body' => 'We store your files securely for easy reordering and future modifications.'],
        ];
    @endphp

    <section class="page-header work-process-hero-flat">
        <div class="container">
            <div class="work-process-page-header">
                <h1>How Our Custom <span>Digitizing Process</span> Works</h1>
                <p>From your initial quote request to the final file delivery, we've streamlined our process to make getting quality embroidery digitizing as simple as possible.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="section-head">
                    <div>
                        <h2>Step By Step</h2>
                        <p>A simple overview of how your artwork moves from submission to approval and final delivery.</p>
                    </div>
                </div>

                <div class="timeline-step-grid work-process-step-grid">
                    @foreach ($steps as $index => $step)
                        <article class="timeline-step-card">
                            <span class="timeline-step-number">{{ $index + 1 }}</span>
                            <h3>{{ $step['title'] }}</h3>
                            <p>{{ $step['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="section-header">
                    <h2>What to <span>Expect</span></h2>
                    <p>A detailed look at each step of our process</p>
                </div>
                <div class="about-reason-grid">
                    @foreach ($expectations as $item)
                        <article class="about-reason-card">
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="section-header">
                    <h2>Why Our Process <span>Works</span></h2>
                </div>
                <div class="features-grid">
                    @foreach ($benefits as $benefit)
                        <article class="feature-item">
                            <div class="feature-icon">{{ $benefit['icon'] }}</div>
                            <h3>{{ $benefit['title'] }}</h3>
                            <p>{{ $benefit['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="template-cta-card">
                <h2>Ready to Start Your Project?</h2>
                <p>Get started with a free quote. We'll guide you through every step of the process.</p>
                <div class="theme-header-actions">
                    <a class="button secondary" href="{{ session()->has('customer_user_id') ? '/quote.php' : '/sign-up.php' }}">Request Your Free Quote</a>
                </div>
            </div>
        </div>
    </section>
@endsection
