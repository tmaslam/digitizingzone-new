@extends('public.layout')

@section('title', 'Custom Embroidery Digitizing & Vector Art | '.$siteContext->displayLabel())
@section('meta_description', 'Professional embroidery digitizing services since 2005. $1 per 1000 stitches. 24-hour turnaround for all major machine formats.')
@section('canonical', url('/'))
@section('meta_image', url('/images/1dollar-Digitizing.webp'))

@section('content')
    @php
        $ctaUrl = session()->has('customer_user_id') ? url('/quote.php') : url('/sign-up.php');
        $heroFeatures = [
            ['icon' => '💰', 'title' => '$1.00', 'subtitle' => 'per 1k stitches'],
            ['icon' => '⏰', 'title' => '24h', 'subtitle' => 'Standard Turnaround'],
            ['icon' => '✓', 'title' => '100%', 'subtitle' => 'Satisfaction Guaranteed'],
        ];
        $stats = [
            ['number' => '2005', 'label' => 'Founded'],
            ['number' => '10K+', 'label' => 'Happy Customers'],
            ['number' => '1M+', 'label' => 'Designs Completed'],
        ];
        $services = [
            ['title' => 'Custom Embroidery Digitizing', 'summary' => 'Clean, production-ready embroidery files built for smoother runs, fewer trims, and reliable results on your machines.', 'image' => url('/images/Embroidery-Digitizings.webp'), 'href' => '/embroidery-digitizing.php', 'price' => '$1 / 1k stitches', 'image_fit' => 'contain'],
            ['title' => '3D Puff Embroidery', 'summary' => 'Specialized puff digitizing for structured caps and raised lettering that holds shape and stitches cleanly.', 'image' => url('/images/3D-puff.webp'), 'href' => '/3d-puff-embroidery-digitizing.php', 'price' => 'Cap embroidery', 'image_fit' => 'contain'],
            ['title' => 'Applique & Chain Stitch', 'summary' => 'Applique and decorative stitch files planned for accurate placement, secure tackdowns, and clean finishing.', 'image' => url('/images/Applique-Embroidery-Digitizing.webp'), 'href' => '/applique-embroidery-digitizing.php', 'price' => 'Specialty stitching', 'image_fit' => 'contain'],
            ['title' => 'Photo Digitizing', 'summary' => 'Photo-based embroidery converted into stitchable artwork with cleaner shapes, smarter density, and readable detail.', 'image' => url('/images/Photo-Digitizing.webp'), 'href' => '/photo-digitizing.php', 'price' => 'Portrait embroidery', 'image_fit' => 'contain', 'image_class' => 'service-card-image-photo'],
            ['title' => 'Vector Art Services', 'summary' => 'Accurate vector redraws for logos, artwork, and print production with clean paths and scalable output files.', 'image' => url('/images/Vector-Art.webp'), 'href' => '/vector-art.php', 'price' => '$6 / hour', 'image_fit' => 'contain'],
            ['title' => 'Chain Stitch Embroidery', 'summary' => 'Chain stitch digitizing for vintage, western, and specialty embroidery looks with smooth decorative flow.', 'image' => url('/images/Chain-Stitch-Embroidery-Digitizing.webp'), 'href' => '/chain-stitch-embroidery-digitizing.php', 'price' => 'Vintage embroidery', 'image_fit' => 'contain'],
        ];
        $features = [
            ['icon' => '💰', 'title' => 'Affordable & Transparent Pricing', 'summary' => '$1 per 1,000 stitches. No hidden fees.'],
            ['icon' => '⚡', 'title' => 'Speed on Your Schedule', 'summary' => '24-hour standard delivery. Rush options available.'],
            ['icon' => '✓', 'title' => 'Quality Guaranteed', 'summary' => 'Free edits if you are not satisfied. We stand behind our work.'],
            ['icon' => '🎨', 'title' => 'All Formats', 'summary' => 'We support all major embroidery machine formats. DST, PES, EXP, and more.'],
        ];
        $testimonials = [
            ['initials' => 'MR', 'avatar_class' => 'avatar-blue', 'name' => 'Mike Rodriguez', 'role' => 'Owner, Hill Country Embroidery', 'quote' => 'Been using these guys for my shop for about 3 years now. Turnaround is consistently fast and the files run clean on our Tajima machines.'],
            ['initials' => 'SL', 'avatar_class' => 'avatar-amber', 'name' => 'Sarah Lin', 'role' => 'Production Manager, Pacific Promotions', 'quote' => 'Switched from a local digitizer who kept missing deadlines. These folks hit the 24-hour mark every single time. Pricing is straightforward too.'],
            ['initials' => 'DW', 'avatar_class' => 'avatar-green', 'name' => 'Dave Williams', 'role' => 'Owner, Team Spirit Apparel, Ohio', 'quote' => 'The $1 first design deal got me to try them. Quality was good so I stuck around. Now they are doing all our youth sports league orders.'],
            ['initials' => 'JK', 'avatar_class' => 'avatar-rose', 'name' => 'James Kim', 'role' => 'Head Digitizer, Atlanta Custom Caps', 'quote' => 'We do a lot of 3D puff hats. The puff files from here stitch out clean without thread breaks and the turnaround stays dependable.'],
            ['initials' => 'AP', 'avatar_class' => 'avatar-violet', 'name' => 'Amanda Perez', 'role' => 'Home-Based Business, Florida', 'quote' => 'I was skeptical about the pricing but the files sewed perfectly. Customer service actually answers the phone too.'],
            ['initials' => 'TB', 'avatar_class' => 'avatar-teal', 'name' => 'Tom Benson', 'role' => 'Owner, Wild West Wear, Montana', 'quote' => 'These guys are the most consistent we have used. The chain stitch work on our western shirts comes out great every time.'],
        ];
        $faqs = [
            ['question' => 'How quickly can you deliver my digitized file?', 'answer' => "Our standard turnaround time is 24 hours. For rush orders, we offer 12-hour and 6-hour delivery options. You'll receive your digitized file via email, ready to load directly into your embroidery machine."],
            ['question' => 'What file formats do you provide?', 'answer' => "We provide all major embroidery machine formats including DST, PES, EXP, JEF, VP3, XXX, and more. Just let us know what machine you're using, and we'll deliver the correct format. For vector art, we provide AI, EPS, PDF, and SVG files."],
            ['question' => 'How much does embroidery digitizing cost?', 'answer' => 'Our pricing starts at just $1 per 1,000 stitches with a $6 minimum per design. 3D puff embroidery is included at no extra charge. Complex designs with high stitch counts or special requirements may cost more. Get a free quote by uploading your design.'],
            ['question' => "What if I'm not satisfied with the digitized file?", 'answer' => "We offer free minor revisions on all projects. If the file doesn't stitch out correctly due to our digitizing, we'll fix it at no charge. Your satisfaction is our priority - we stand behind our work 100%."],
            ['question' => 'Do you offer a first-time customer discount?', 'answer' => "Yes! First-time customers get their first design digitized for just $1.00 (for hat or left chest logos, up to 10,000 stitches). It's our way of letting you try our service risk-free. No coupon code needed - just mention it when you submit your quote request."],
            ['question' => 'How do I send you my design?', 'answer' => 'You can upload your design directly through our quote request form. We accept JPG, PNG, and PDF files. You can also email your design to support@digitizingzone.com. For best results, please send high-resolution images (300 DPI or higher).'],
            ['question' => 'Can you digitize photos for embroidery?', 'answer' => 'Absolutely! We specialize in photo digitizing, converting photographs into stitchable embroidery designs. This is perfect for memorial patches, pet portraits, and custom gifts. Photo digitizing requires special techniques and may take slightly longer than standard logo digitizing.'],
            ['question' => 'What payment methods do you accept?', 'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express, Discover), PayPal, and bank transfers for larger orders. Payment is due upon approval of the quote, before we begin the digitizing work.'],
        ];
    @endphp

    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <div class="hero-badge">Founded in 2005 • Trusted by 10,000+ Businesses</div>
                    <h1>Flawless Custom Embroidery Digitizing &amp; Vector Art Services</h1>
                    <p>Welcome to {{ $siteContext->displayLabel() }}! Since 2005, we have been the trusted partner for apparel decorators, screen printers, and promotional product businesses. We combine experience with practical production workflows to deliver machine-ready files and crisp vector graphics.</p>

                    <div class="hero-buttons">
                        <a href="{{ $ctaUrl }}" class="button primary">Get Your Free Quote</a>
                        <a href="{{ url('/our-services.php') }}" class="button secondary">Explore Our Services</a>
                    </div>

                    <div class="hero-features">
                        @foreach ($heroFeatures as $feature)
                            <div class="hero-feature">
                                <div class="hero-feature-icon">{{ $feature['icon'] }}</div>
                                <div class="hero-feature-text">{{ $feature['title'] }}<span>{{ $feature['subtitle'] }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="hero-image">
                    <div class="hero-visual-card">
                        <img src="{{ url('/images/1dollar-Digitizing.webp') }}" alt="Embroidery Digitizing Sample">
                        <div class="hero-floating-card hero-floating-card-primary">
                            <strong>24-Hour Delivery</strong>
                            <span>Production-ready files for working shops</span>
                        </div>
                        <div class="hero-floating-card hero-floating-card-secondary">
                            <strong>$1 / 1K</strong>
                            <span>Transparent embroidery digitizing pricing</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                @foreach ($stats as $stat)
                    <div class="stat-card">
                        <div class="stat-number">{{ $stat['number'] }}</div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" id="services">
        <div class="container">
            <div class="section-header home-services-header">
                <h2>Our Core <span>Digitizing &amp; Vector Services</span></h2>
                <p>We offer a comprehensive range of embroidery digitizing and vector art services to meet all your needs.</p>
            </div>

            <div class="services-grid">
                @foreach ($services as $service)
                    <article class="service-card">
                        <img
                            class="{{ trim((($service['image_fit'] ?? '') === 'contain' ? 'service-card-image-contain ' : '').($service['image_class'] ?? '')) }}"
                            src="{{ $service['image'] }}"
                            alt="{{ $service['title'] }}"
                            loading="lazy"
                            @if (($service['image_class'] ?? '') === 'service-card-image-photo')
                                style="height:auto;width:100%;object-fit:contain;object-position:center top;padding:0;background:#ffffff;"
                            @endif
                        >
                        <div class="service-card-content">
                            <h3>{{ $service['title'] }}</h3>
                            <p>{{ $service['summary'] }}</p>
                            <span class="price-tag">{{ $service['price'] }}</span>
                            <a href="{{ $service['href'] }}" class="inline-link">Learn more →</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section-surface">
        <div class="container">
            <div class="section-header home-benefits-header">
                <h2>Why {{ $siteContext->displayLabel() }} is Your <span>Best Choice</span></h2>
                <p>We pride ourselves on delivering exceptional quality and service to all our customers.</p>
            </div>

            <div class="features-grid">
                @foreach ($features as $feature)
                    <div class="feature-item">
                        <div class="feature-icon">{{ $feature['icon'] }}</div>
                        <h3>{{ $feature['title'] }}</h3>
                        <p>{{ $feature['summary'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section-surface home-testimonials-section">
        <div class="container">
            <div class="section-header home-testimonials-header">
                <h2>What Our <span>Customers</span> Say</h2>
                <p>Real feedback from businesses who trust us with their embroidery digitizing needs.</p>
            </div>

            <div class="marketing-testimonial-rows">
                @foreach (array_chunk($testimonials, 3) as $testimonialRow)
                    <div class="marketing-testimonials">
                        @foreach ($testimonialRow as $testimonial)
                            <div class="marketing-testimonial-card">
                                <div class="marketing-stars">★★★★★</div>
                                <p>"{{ $testimonial['quote'] }}"</p>
                                <div class="marketing-testimonial-person">
                                    <div class="marketing-testimonial-avatar {{ $testimonial['avatar_class'] ?? '' }}">{{ $testimonial['initials'] }}</div>
                                    <div class="marketing-testimonial-meta">
                                        <strong>{{ $testimonial['name'] }}</strong>
                                        <span>{{ $testimonial['role'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section home-faq-section">
        <div class="container">
            <div class="section-header home-faq-header">
                <h2>Frequently Asked <span>Questions</span></h2>
                <p>Got questions? We have answers. If you do not see what you need, contact us.</p>
            </div>

            <div class="marketing-faq-list faq-list-home home-faq-list">
                @foreach ($faqs as $faq)
                    <details class="marketing-faq-item">
                        <summary>
                            <span class="faq-question">{{ $faq['question'] }}</span>
                            <span class="faq-toggle-icon" aria-hidden="true">
                                <span class="faq-toggle-plus">+</span>
                                <span class="faq-toggle-minus">−</span>
                            </span>
                        </summary>
                        <div class="faq-answer">
                            <p>{{ $faq['answer'] }}</p>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    @if (! session()->has('customer_user_id') && ! empty($signupOfferSummary))
        @php
            $offerHeadline = trim((string) ($signupOfferSummary['headline'] ?? 'New member welcome offer'));
            $offerSummary = trim((string) ($signupOfferSummary['summary'] ?? 'Verify your email, complete the welcome payment, and unlock the first-order offer.'));
            $offerVerification = trim((string) ($signupOfferSummary['verification_message'] ?? 'Check your inbox and spam or junk folder for the verification email after signup.'));
            $offerPaymentAmount = (float) ($signupOfferSummary['payment_amount'] ?? 0);
            $offerFreeUnder = (int) ($signupOfferSummary['first_order_free_under_stitches'] ?? 0);
            $offerFlatAmount = (float) ($signupOfferSummary['first_order_flat_amount'] ?? 0);
            $offerCode = trim((string) ($signupOfferSummary['offer_code'] ?? ''));
            $offerStorageKey = 'site-welcome-offer:'.$siteContext->legacyKey;
        @endphp

        <section class="welcome-offer-modal is-hidden" data-welcome-offer hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="welcome-offer-title">
            <div class="welcome-offer-backdrop" data-welcome-offer-close></div>
            <div class="welcome-offer-panel">
                <button type="button" class="welcome-offer-close" data-welcome-offer-close aria-label="Close welcome offer">×</button>
                <span class="welcome-offer-eyebrow">New Member Offer</span>
                <h3 id="welcome-offer-title">{{ $offerHeadline }}</h3>
                <p class="welcome-offer-summary">{{ $offerSummary }}</p>

                <div class="welcome-offer-metrics">
                    @if ($offerPaymentAmount > 0)
                        <div class="welcome-offer-metric">
                            <span>Welcome Payment</span>
                            <strong>${{ number_format($offerPaymentAmount, 2) }}</strong>
                        </div>
                    @endif
                    @if ($offerFreeUnder > 0)
                        <div class="welcome-offer-metric">
                            <span>First Order Benefit</span>
                            <strong>Free under {{ number_format($offerFreeUnder) }} stitches</strong>
                        </div>
                    @elseif ($offerFlatAmount > 0)
                        <div class="welcome-offer-metric">
                            <span>First Order Price</span>
                            <strong>${{ number_format($offerFlatAmount, 2) }}</strong>
                        </div>
                    @endif
                    @if ($offerCode !== '')
                        <div class="welcome-offer-metric">
                            <span>Offer Code</span>
                            <strong>{{ $offerCode }}</strong>
                        </div>
                    @endif
                </div>

                <div class="welcome-offer-note">
                    <strong>How it works:</strong>
                    <span>{{ $offerVerification }}</span>
                </div>

                <div class="welcome-offer-actions">
                    <a class="button primary" href="{{ url('/sign-up.php') }}">Claim This Offer</a>
                    <button type="button" class="button secondary" data-welcome-offer-close>Maybe Later</button>
                </div>
            </div>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.querySelector('[data-welcome-offer]');
                if (!modal) {
                    return;
                }

                var storageKey = @json($offerStorageKey);
                var closeTargets = modal.querySelectorAll('[data-welcome-offer-close]');

                function closeOffer(persist) {
                    modal.hidden = true;
                    modal.classList.add('is-hidden');
                    modal.classList.remove('open');
                    modal.setAttribute('aria-hidden', 'true');

                    if (persist) {
                        try {
                            window.localStorage.setItem(storageKey, 'dismissed');
                        } catch (error) {
                        }
                    }
                }

                function openOffer() {
                    modal.hidden = false;
                    modal.classList.remove('is-hidden');
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }

                try {
                    if (window.localStorage.getItem(storageKey) === 'dismissed') {
                        return;
                    }
                } catch (error) {
                }

                window.setTimeout(openOffer, 900);

                closeTargets.forEach(function (target) {
                    target.addEventListener('click', function (event) {
                        event.preventDefault();
                        closeOffer(true);
                    });
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.classList.contains('open')) {
                        closeOffer(true);
                    }
                });
            });
        </script>
    @endif
@endsection
