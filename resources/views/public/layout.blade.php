<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $legacyAssetBase = rtrim(request()->getSchemeAndHttpHost(), '/');
        $seoTitle = html_entity_decode(trim($__env->yieldContent('title', $siteContext->displayLabel())), ENT_QUOTES, 'UTF-8');
        $seoDescription = trim(preg_replace('/\s+/', ' ', strip_tags($__env->yieldContent('meta_description', 'Professional embroidery digitizing service starting at $1. Custom logo digitizing, 3D puff, applique, vector art. All file formats, 12-hour turnaround, free revisions.'))));
        $seoCanonical = trim($__env->yieldContent('canonical', url()->current()));
        $seoRobots = trim($__env->yieldContent('meta_robots', 'index,follow,max-image-preview:large'));
        $seoImage = trim($__env->yieldContent('meta_image', $legacyAssetBase.'/images/logo.webp'));
        $seoType = trim($__env->yieldContent('meta_og_type', 'website'));
        $seoTwitterCard = trim($__env->yieldContent('twitter_card', 'summary_large_image'));
        $siteBaseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
        $supportEmail = $siteContext->supportEmail !== '' ? $siteContext->supportEmail : (string) config('mail.admin_alert_address', '');

        if ($seoImage !== '' && ! \Illuminate\Support\Str::startsWith($seoImage, ['http://', 'https://'])) {
            $seoImage = url($seoImage);
        }

        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $siteBaseUrl.'/#organization',
            'name' => $siteContext->displayLabel(),
            'url' => $siteBaseUrl.'/',
            'logo' => $legacyAssetBase.'/images/logo.png',
        ];

        if ($supportEmail !== '') {
            $organizationSchema['email'] = $supportEmail;
            $organizationSchema['contactPoint'] = [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $supportEmail,
            ]];
        }

        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $siteBaseUrl.'/#website',
            'url' => $siteBaseUrl.'/',
            'name' => $siteContext->displayLabel(),
            'publisher' => ['@id' => $siteBaseUrl.'/#organization'],
        ];

        $pageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => $seoCanonical,
            'name' => $seoTitle,
            'description' => $seoDescription,
            'isPartOf' => ['@id' => $siteBaseUrl.'/#website'],
        ];

        $publicMenu = [
            ['label' => 'Home', 'href' => url('/')],
            ['label' => 'Services', 'href' => url('/our-services.php')],
            ['label' => 'Pricing', 'href' => url('/price-plan.php')],
            ['label' => 'Work Process', 'href' => url('/work-process.php')],
            ['label' => 'About Us', 'href' => url('/about-us.php')],
            ['label' => 'Contact', 'href' => url('/contact-us.php')],
        ];
        $serviceLinks = [
            ['label' => 'Embroidery Digitizing', 'href' => url('/embroidery-digitizing.php')],
            ['label' => '3D / Puff Embroidery', 'href' => url('/3d-puff-embroidery-digitizing.php')],
            ['label' => 'Applique Embroidery', 'href' => url('/applique-embroidery-digitizing.php')],
            ['label' => 'Chain Stitch Embroidery', 'href' => url('/chain-stitch-embroidery-digitizing.php')],
            ['label' => 'Photo Digitizing', 'href' => url('/photo-digitizing.php')],
            ['label' => 'Vector Art', 'href' => url('/vector-art.php')],
        ];
        $companyLinks = [
            ['label' => 'About Us', 'href' => url('/about-us.php')],
            ['label' => 'Pricing', 'href' => url('/price-plan.php')],
            ['label' => 'Contact', 'href' => url('/contact-us.php')],
        ];
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="{{ $seoType }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:site_name" content="{{ $siteContext->displayLabel() }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="{{ $seoTwitterCard }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <script type="application/ld+json">@json($organizationSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)</script>
    <script type="application/ld+json">@json($websiteSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)</script>
    <script type="application/ld+json">@json($pageSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)</script>
    @hasSection('structured_data')
        <script type="application/ld+json">{!! trim($__env->yieldContent('structured_data')) !!}</script>
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>:root { --accent-soft: #fdf0f0; --accent-dark: #b01f1f; }</style>
    <link rel="stylesheet" href="{{ url('/css/front-theme-overrides.css') }}">
    <style>
        body.front-theme.public-theme *,
        body.front-theme.public-theme *::before,
        body.front-theme.public-theme *::after {
            box-sizing: border-box;
        }

        body.front-theme.public-theme .container {
            width: min(1220px, calc(100% - 28px)) !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        body.front-theme.public-theme .marketing-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid rgba(203, 213, 225, 0.72);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        body.front-theme.public-theme .marketing-header-shell {
            min-height: 82px;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            grid-template-areas: "brand nav actions";
            align-items: center;
            gap: 18px;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        body.front-theme.public-theme .marketing-brand {
            grid-area: brand;
            display: inline-flex;
            align-items: center;
            min-width: 0;
        }

        body.front-theme.public-theme .marketing-brand img {
            height: 84px;
            width: auto;
            max-width: 100%;
            display: block;
        }

        body.front-theme.public-theme .marketing-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #d62b2b, #b01f1f);
            color: #ffffff;
            font-family: "Inter", "Segoe UI", sans-serif;
            font-size: 0.92rem;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(214, 43, 43, 0.24);
        }

        body.front-theme.public-theme .marketing-nav {
            grid-area: nav;
            display: flex;
            align-items: center;
            min-width: 0;
            margin-left: 24px;
        }

        body.front-theme.public-theme .marketing-nav-list {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            width: 100%;
            min-width: 0;
            margin: 0;
            padding: 0.4rem;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.92));
            border: 1px solid rgba(203, 213, 225, 0.8);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 10px 26px rgba(15, 23, 42, 0.05);
        }

        body.front-theme.public-theme .marketing-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.72rem 1rem;
            border-radius: 999px;
            color: #334155;
            text-decoration: none;
            font-family: "Inter", "Segoe UI", sans-serif;
            font-size: 0.94rem;
            font-weight: 700;
            line-height: 1;
            transition: color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }

        body.front-theme.public-theme .marketing-nav-link:hover,
        body.front-theme.public-theme .marketing-nav-link.active {
            background: #ffffff;
            color: #b01f1f;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        }

        body.front-theme.public-theme .marketing-actions {
            grid-area: actions;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            justify-content: flex-end;
            margin-left: 68px;
            position: relative;
            top: -10px;
        }

        body.front-theme.public-theme .marketing-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            min-height: 44px !important;
            padding: 0 18px !important;
            border-radius: 10px !important;
            font-size: 0.94rem !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            white-space: nowrap;
        }

        @media (max-width: 900px) {
            body.front-theme.public-theme .marketing-header-shell {
                grid-template-columns: minmax(0, 1fr) auto;
                grid-template-areas:
                    "brand toggle"
                    "actions actions"
                    "nav nav";
                gap: 12px;
                min-height: auto;
            }

            body.front-theme.public-theme .marketing-brand {
                grid-area: brand;
            }

            body.front-theme.public-theme .marketing-brand img {
                max-width: 150px;
                height: auto;
            }

            body.front-theme.public-theme .marketing-toggle {
                grid-area: toggle;
                display: inline-flex;
                justify-self: end;
            }

            body.front-theme.public-theme .marketing-actions {
                grid-area: actions;
                margin-left: 0;
                position: static;
                top: auto;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                width: 100%;
                gap: 10px;
            }

            body.front-theme.public-theme .marketing-action-button {
                min-height: 42px !important;
                width: 100%;
                justify-content: center;
                white-space: normal;
                padding: 10px 12px !important;
            }

            body.front-theme.public-theme .marketing-nav {
                grid-area: nav;
                margin-left: 0;
                display: none;
                width: 100%;
            }

            body.front-theme.public-theme .marketing-nav.open {
                display: block;
            }

            body.front-theme.public-theme .marketing-nav-list {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
                padding: 1rem;
                border-radius: 22px;
            }

            body.front-theme.public-theme .marketing-nav-link {
                width: 100%;
                justify-content: flex-start;
                text-align: left;
                white-space: normal;
            }
        }

        @media (max-width: 640px) {
            body.front-theme.public-theme .marketing-header-shell {
                gap: 8px;
            }

            body.front-theme.public-theme .marketing-brand img {
                max-width: 122px;
            }

            body.front-theme.public-theme .marketing-actions {
                grid-template-columns: 1fr;
            }

            body.front-theme.public-theme .marketing-toggle {
                padding-left: 14px;
                padding-right: 14px;
            }
        }
    </style>
</head>
<body class="front-theme public-theme">
    <div class="site-frame">
        <div class="top-bar">
            <div class="container topbar-inner">
                <span class="template-topbar-message">
                    Trusted Since 2005 | Custom Embroidery Digitizing &amp; Vector Art
                    <span class="template-topbar-separator">—</span>
                    <a href="tel:+12063126446">Call Us: +1 (206) 312-6446</a>
                </span>
            </div>
        </div>

        <header class="marketing-header">
            <div class="container marketing-header-shell">
                <a href="{{ url('/') }}" class="marketing-brand">
                    <img class="site-logo" src="{{ url('images/logo.png') }}" alt="Digitizing Zone">
                </a>

                <button class="marketing-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="public-navigation">Menu</button>

                <div class="marketing-actions">
                    @if (session()->has('customer_user_id'))
                        <a class="button secondary marketing-action-button" href="{{ url('/dashboard.php') }}">Dashboard</a>
                        <a class="button secondary marketing-action-button" href="{{ url('/logout.php') }}">Logout</a>
                        <a class="button primary marketing-action-button" href="{{ url('/quote.php') }}">Get Quote</a>
                    @else
                        <a class="button secondary marketing-action-button" href="{{ url('/login.php') }}">Login</a>
                        <a class="button secondary marketing-action-button" href="{{ url('/sign-up.php') }}">Sign Up</a>
                        <a class="button primary marketing-action-button" href="{{ url('/sign-up.php') }}">Get Quote</a>
                    @endif
                </div>

                <nav class="marketing-nav" id="public-navigation">
                    <div class="marketing-nav-list">
                        @foreach ($publicMenu as $item)
                            @php
                                $currentPath = request()->path();
                                $active = $currentPath === ltrim($item['href'], '/') || ($item['href'] === '/' && ($currentPath === '/' || $currentPath === ''));
                            @endphp
                            <a class="marketing-nav-link {{ $active ? 'active' : '' }}" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </nav>
            </div>
         </header>

        <main class="page-content">
            @yield('content')
        </main>

        <footer class="footer">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-brand-block">
                        <img class="footer-logo" src="{{ url('images/logo.png') }}" alt="Digitizing Zone">
                        <p>Professional embroidery digitizing services at affordable prices. Quality you can count on.</p>
                        <div class="footer-brand-pills">
                            <span>24 Hour Standard Turnaround</span>
                            <span>$1 per 1K Stitches</span>
                            <span>All Major Formats</span>
                        </div>
                    </div>

                    <div class="footer-column">
                        <h4>Services</h4>
                        <ul class="footer-links">
                            @foreach ($serviceLinks as $item)
                                <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h4>Company</h4>
                        <ul class="footer-links">
                            @foreach ($companyLinks as $item)
                                <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="footer-column footer-contact-block">
                        <h4>Contact</h4>
                        <ul class="footer-links">
                            <li><a href="tel:+12063126446">+1 (206) 312-6446</a></li>
                            <li>46494 Mission Blvd<br>Fremont, CA 94539</li>
                            @if ($supportEmail !== '')
                                <li><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
                            @endif
                        </ul>
                        <div class="footer-cta-group">
                            <a class="button secondary footer-button" href="{{ url('/contact-us.php') }}">Contact Us</a>
                            <a class="button primary footer-button" href="{{ session()->has('customer_user_id') ? url('/quote.php') : url('/sign-up.php') }}">Get Quote</a>
                        </div>
                    </div>
                </div>
            </div>

            <div aria-hidden="true" style="height:40px;"></div>
            <div class="footer-bottom-wrap">
                <div class="container">
                    <div class="footer-bottom" style="margin-top:0;padding-top:0;">
                        <p>&copy; {{ date('Y') }} Digitizing Zone. All rights reserved.</p>
                        <div class="footer-bottom-links">
                            <a href="{{ url('/privacy-policy.php') }}">Privacy Policy</a>
                            <a href="{{ url('/terms.php') }}">Terms</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.querySelector('[data-nav-toggle]');
            var navigation = document.getElementById('public-navigation');

            if (toggle && navigation) {
                toggle.addEventListener('click', function () {
                    var isOpen = navigation.classList.toggle('open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            }

            document.querySelectorAll('form[data-validate-form]').forEach(function (form) {
                var controls = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function (control) {
                    return control.type !== 'hidden' && control.type !== 'submit' && control.type !== 'button' && control.type !== 'reset';
                });

                function fieldContainer(control) {
                    return control.closest('[data-form-field]') || control.closest('label') || control.parentElement;
                }

                function fieldErrorNode(control) {
                    var container = fieldContainer(control);
                    return container ? container.querySelector('[data-field-error]') : null;
                }

                function renderError(control, isValid, message) {
                    var error = fieldErrorNode(control);
                    control.classList.toggle('is-invalid', !isValid);
                    control.setAttribute('aria-invalid', isValid ? 'false' : 'true');

                    if (error) {
                        error.textContent = isValid ? '' : message;
                    }
                }

                function validateControl(control) {
                    if (control.disabled) {
                        return true;
                    }

                    var valid = control.checkValidity();
                    renderError(control, valid, valid ? '' : control.validationMessage);
                    return valid;
                }

                controls.forEach(function (control) {
                    control.addEventListener('blur', function () {
                        validateControl(control);
                    });

                    control.addEventListener('input', function () {
                        if (control.classList.contains('is-invalid') || control.getAttribute('aria-invalid') === 'true') {
                            validateControl(control);
                        }
                    });
                });

                form.addEventListener('submit', function (event) {
                    var firstInvalid = null;

                    controls.forEach(function (control) {
                        if (!validateControl(control) && !firstInvalid) {
                            firstInvalid = control;
                        }
                    });

                    if (firstInvalid) {
                        event.preventDefault();
                        firstInvalid.focus();
                    }
                });
            });
        });
    </script>
</body>
</html>
