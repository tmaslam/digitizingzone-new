@extends('public.layout')

@section('title', 'Terms and Conditions | '.$siteContext->displayLabel())
@section('meta_description', 'Read the terms and conditions for '.$siteContext->displayLabel().'.')

@section('content')
    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">{{ $siteContext->displayLabel() }}</span>
                <h1>Terms And <span>Conditions</span></h1>
                <p>These terms apply to customer accounts, quote requests, order submissions, payments, revisions, downloads, and general use of this website.</p>
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
                    <h2>Use Of This Website</h2>
                    <p>By creating an account, submitting artwork, requesting quotes, or placing orders, you agree to use this website lawfully and provide accurate information for your account and work requests.</p>

                    <h2>Quotes And Orders</h2>
                    <p>Quotes and turnaround times are based on the details, files, and instructions you provide. If files, sizes, or requirements change, the quoted price or delivery estimate may also change.</p>

                    <h2>Payments</h2>
                    <p>Payments must be made through the approved payment options shown on this website. Order release, invoice status, and account balance usage may depend on successful payment verification.</p>

                    <h2>Files And Delivery</h2>
                    <p>Preview images or proof files may be available before full release, but production-ready files may remain unavailable until the payment or approval requirements for the order are satisfied.</p>

                    <h2>Revisions And Approval</h2>
                    <p>Customers should review previews and completed work carefully. Revision requests, approval windows, and delivery timing may vary depending on the service requested and the stage of the order.</p>

                    <h2>Account Responsibility</h2>
                    <p>You are responsible for keeping your login details secure and for activity performed through your account. Please contact support immediately if you suspect unauthorized access.</p>

                    <h2>Prohibited Use</h2>
                    <p>You may not use this website for spam, malicious uploads, unauthorized access attempts, or unlawful content. Accounts or requests associated with abuse may be limited, blocked, or removed.</p>

                    <h2>Service Changes</h2>
                    <p>We may update workflows, pricing configuration, security requirements, and website functionality when needed to improve service quality, protect accounts, or maintain platform stability.</p>
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
