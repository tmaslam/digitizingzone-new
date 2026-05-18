@extends('public.layout')

@section('title', 'Our Quality | '.$siteContext->displayLabel())
@section('meta_description', 'Learn how '.$siteContext->displayLabel().' approaches embroidery digitizing quality, turnaround time, pricing, and production-ready results.')

@section('content')
    @php
        $offerItems = [
            [
                'title' => 'Fast Turnaround',
                'body' => 'We work around the clock so most standard digitizing jobs can be delivered within 24 hours without slowing down communication or production support.',
            ],
            [
                'title' => 'Affordable Pricing',
                'body' => 'Our pricing stays simple and competitive, making it easier for customers to get dependable embroidery digitizing without overcomplicating the order process.',
            ],
            [
                'title' => 'Wide Format Support',
                'body' => 'We prepare files in the embroidery and artwork formats customers commonly need, so production stays smooth across different machine and print requirements.',
            ],
            [
                'title' => 'Convenient Payments',
                'body' => 'Customers can complete payments through secure hosted providers while keeping account billing, order release, and credits organized in one place.',
            ],
            [
                'title' => 'Production-Ready Quality',
                'body' => 'Every order is prepared with machine-friendly structure, practical stitch planning, and the kind of finish that helps real production run cleaner.',
            ],
        ];
    @endphp

    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">{{ $siteContext->displayLabel() }}</span>
                <h1>Our <span>Quality</span></h1>
                <p>Quality digitizing starts with experienced people, careful file preparation, and dependable turnaround for real embroidery production.</p>
                <div class="theme-header-actions">
                    <a class="button primary" href="/price-plan.php">View Pricing</a>
                    <a class="button secondary" href="/contact-us.php">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="split">
                    <div class="copy">
                        <div class="section-head quality-head">
                            <h2><span style="color:#169fe6;">Quality</span> Digitizing & Embroidery</h2>
                        </div>
                        <div class="quality-copy">
                            <p>Looking for professional embroidery digitizing or machine embroidery designs? 1 Dollar Digitizing is built to support customers who need practical service, strong communication, and files prepared for real production use.</p>
                            <p>With a team of skilled digitizers, embroiderers, and graphic designers backed by years of hands-on industry experience, we focus on delivering accurate results for logos, detailed artwork, rush jobs, and repeat production work.</p>
                            <p>We stay committed to fast service without losing sight of quality. That is why customers continue to rely on us for dependable turnaround, consistent file preparation, and support that stays available when orders need attention.</p>
                        </div>
                    </div>
                    <div class="quality-side-card">
                        <span class="quality-side-label">What Customers Expect</span>
                        <ul>
                            <li>Clean embroidery digitizing</li>
                            <li>Responsive turnaround</li>
                            <li>Affordable rates</li>
                            <li>Reliable production files</li>
                            <li>Support when revisions are needed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-card">
                <div class="section-head" style="margin-bottom:20px;">
                    <h2>We Offer</h2>
                    <p>The service experience is built around speed, clarity, and files that are ready to work.</p>
                </div>
                <div class="quality-offer-grid">
                    @foreach ($offerItems as $item)
                        <article class="quality-offer-card">
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
            <div class="section-card quality-cta-card">
                <div>
                    <h2>Ready To Send Your Design?</h2>
                    <p>Start with a quote, send your artwork, or contact us if you want help choosing the right service before placing an order.</p>
                </div>
                <div class="theme-header-actions" style="margin:0;">
                    <a class="button primary" href="/sign-up.php">Get Started</a>
                    <a class="button secondary" href="/formats.php">View Formats</a>
                </div>
            </div>
        </div>
    </section>

    <style>
        .quality-head {
            text-align: left;
            margin-bottom: 14px;
        }

        .quality-copy {
            display: grid;
            gap: 14px;
        }

        .quality-copy p {
            margin: 0;
            color: #526071;
            line-height: 1.78;
        }

        .quality-side-card {
            align-self: start;
            padding: 24px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(22, 159, 230, 0.08) 0%, rgba(255, 255, 255, 0.98) 100%);
            border: 1px solid rgba(22, 159, 230, 0.14);
            box-shadow: 0 18px 36px rgba(12, 48, 89, 0.08);
        }

        .quality-side-label {
            display: inline-block;
            margin-bottom: 14px;
            color: #0f6d9f;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .quality-side-card ul {
            margin: 0;
            padding-left: 18px;
            color: #3c4c5e;
            line-height: 1.75;
        }

        .quality-offer-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .quality-offer-card {
            padding: 24px;
            border-radius: 22px;
            background: #fff;
            border: 1px solid rgba(22, 159, 230, 0.12);
            box-shadow: 0 16px 30px rgba(12, 48, 89, 0.08);
        }

        .quality-offer-card h3 {
            margin: 0 0 10px;
            color: #182a3e;
            font-size: 1.08rem;
        }

        .quality-offer-card p {
            margin: 0;
            color: #526071;
            line-height: 1.72;
        }

        .quality-cta-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .quality-cta-card h2 {
            margin: 0 0 10px;
        }

        .quality-cta-card p {
            margin: 0;
            color: #526071;
            line-height: 1.72;
            max-width: 720px;
        }

        @media (max-width: 960px) {
            .quality-offer-grid {
                grid-template-columns: 1fr 1fr;
            }

            .quality-cta-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 720px) {
            .quality-offer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection
