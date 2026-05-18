@extends('public.layout')

@section('title', 'Privacy Policy | '.$siteContext->displayLabel())
@section('meta_description', 'Read the privacy policy for '.$siteContext->displayLabel().'.')

@section('content')
    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">{{ $siteContext->displayLabel() }}</span>
                <h1>Privacy <span>Policy</span></h1>
                <p>Learn how information is handled when you use this website, create an account, request quotes, place orders, upload files, and contact support.</p>
                <div class="theme-header-actions">
                    <a class="button secondary" href="{{ url('/contact-us.php') }}">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="card">
                <div class="card-body prose professional-prose">
                    <h2>Information We Collect</h2>
                    <p>We collect the information you provide when you register, request a quote, place an order, upload files, contact support, or make a payment. This may include your name, company name, email address, phone number, billing information, uploaded artwork, and account activity.</p>

                    <h2>How We Use Your Information</h2>
                    <p>Your information is used to operate this website, provide embroidery digitizing and vector services, manage customer support, process payments through hosted providers, deliver completed files, and protect the platform from fraud, abuse, and unauthorized activity.</p>

                    <h2>Payments</h2>
                    <p>Payments are processed through hosted third-party providers such as Stripe Checkout and 2Checkout. This website does not store full credit card details on its own servers.</p>

                    <h2>Files And Orders</h2>
                    <p>Files you upload are used to prepare quotes, complete orders, provide previews, and deliver finished work according to your account and payment status. Access to downloadable production files may be restricted until payment or account rules allow release.</p>

                    <h2>Security And Monitoring</h2>
                    <p>We use security tools such as verification emails, rate limiting, activity logging, login protections, and bot checks to reduce spam, detect suspicious activity, and protect customer and internal accounts.</p>

                    <h2>Cookies And Sessions</h2>
                    <p>This website uses cookies and session storage to keep you signed in, protect forms, remember trusted devices when you choose remember-me, and improve account security.</p>

                    <h2>Support And Communication</h2>
                    <p>If you contact us, the message and related account details may be stored so we can respond, follow up on your request, and maintain an accurate service history for your account.</p>

                    <h2>Your Choices</h2>
                    <p>You may contact us if you need help updating your account details or if you have questions about how your information is handled on this website.</p>
                </div>
            </div>
        </div>
    </section>

    <style>
        .professional-prose h2 {
            color: #182a3e;
            margin-top: 0;
            font-size: 1.16rem;
        }

        .professional-prose p {
            color: #526071;
            line-height: 1.78;
        }

        .professional-prose {
            display: grid;
            gap: 10px;
        }
    </style>
@endsection
