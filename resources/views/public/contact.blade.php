@extends('public.layout')

@section('title', 'Contact Us | '.$siteContext->displayLabel())
@section('meta_description', 'Get in touch with '.$siteContext->displayLabel().' for quotes, support, and embroidery digitizing or vector art questions.')

@section('content')
    @php
        $contactFaqs = [
            'How quickly can you deliver my digitized file?' => 'Our standard turnaround time is 24 hours. Rush delivery options are available for urgent projects.',
            'What file formats do you provide?' => 'We provide major embroidery machine formats and common vector formats depending on the service requested.',
            'Do you offer free revisions?' => 'Minor revisions remain included, and the existing workflow for support and corrections is unchanged.',
            'How do I get a quote?' => 'Use the form on this page or the existing quote route and we will review the project details.',
            'What types of payment do you accept?' => 'We accept the payment methods already supported by the existing billing workflow, including card-based checkout and the currently configured payment providers.',
        ];
    @endphp

    <section class="page-header">
        <div class="container">
            <div>
                <span class="theme-badge">Contact Us</span>
                <h1>Contact <span>Us</span> &amp; Get a Quote</h1>
                <p>Have a question about our services or need a quote for your project? Fill out the form below and we’ll get back to you as quickly as possible.</p>
                <div class="theme-header-actions">
                    <a class="button primary" href="#contact-form">Request A Quote</a>
                    <a class="button secondary" href="tel:+12063126446">Call Us</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>Get In Touch</h2>
                    <p>We are available to help with quotes, order questions, billing, account help, and file support. For urgent requests, please call us directly.</p>

                    <div class="contact-methods">
                        <div class="contact-method">
                            <div class="contact-method-icon">📞</div>
                            <div>
                                <h3>Phone</h3>
                                <p><a href="tel:+12063126446">+1 (206) 312-6446</a><br>Mon-Fri 9AM-6PM PST</p>
                            </div>
                        </div>

                        <div class="contact-method">
                            <div class="contact-method-icon">✉️</div>
                            <div>
                                <h3>Email</h3>
                                <p><a href="mailto:{{ $siteContext->supportEmail }}">{{ $siteContext->supportEmail }}</a><br>We reply within 24 hours</p>
                            </div>
                        </div>

                        <div class="contact-method">
                            <div class="contact-method-icon">📍</div>
                            <div>
                                <h3>Address</h3>
                                <p>46494 Mission Blvd<br>Fremont, CA 94539</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <form method="post" action="/contact-us.php" class="contact-form" data-validate-form novalidate id="contact-form">
                        @csrf
                        <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
                        <h3 class="form-title">Request a Quote</h3>

                        @if (session('success'))
                            <div class="alert success">{{ session('success') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert">{{ $errors->first() }}</div>
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="contact-name">Full Name *</label>
                            <input id="contact-name" type="text" name="name" class="form-input" value="{{ old('name') }}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-email">Email Address *</label>
                            <input id="contact-email" type="email" name="email" class="form-input" value="{{ old('email') }}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-company">Company</label>
                            <input id="contact-company" type="text" name="company" class="form-input" value="{{ old('company') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-phone">Phone Number</label>
                            <input id="contact-phone" type="text" name="phone" class="form-input" value="{{ old('phone') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-subject">Subject *</label>
                            <input id="contact-subject" type="text" name="subject" class="form-input" value="{{ old('subject') }}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact-message">Project Details *</label>
                            <textarea id="contact-message" name="message" class="form-textarea" required>{{ old('message') }}</textarea>
                        </div>

                        @include('shared.turnstile')

                        <button type="submit" class="button primary button-block">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>Frequently Asked <span>Questions</span></h2>
            </div>

            <div class="marketing-faq-list faq-list-wide">
                @foreach ($contactFaqs as $question => $answer)
                    <details class="marketing-faq-item">
                        <summary>
                            <span class="faq-question">{{ $question }}</span>
                            <span class="faq-toggle-icon" aria-hidden="true">
                                <span class="faq-toggle-plus">+</span>
                                <span class="faq-toggle-minus">−</span>
                            </span>
                        </summary>
                        <div class="faq-answer">
                            <p>{{ $answer }}</p>
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endsection
