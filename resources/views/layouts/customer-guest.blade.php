<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteContext->displayLabel())</title>
    <link rel="icon" type="image/png" href="{{ url('images/logo.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    @php
        $legacyAssetBase = rtrim(request()->getSchemeAndHttpHost(), '/');
        $publicMenu = [
            ['label' => 'Home', 'href' => url('/?page=home')],
            ['label' => 'Services', 'href' => url('/?page=services'), 'children' => [
                ['label' => 'Embroidery Digitizing', 'href' => url('/?page=svc-embroidery')],
                ['label' => '3D / Puff Embroidery', 'href' => url('/?page=svc-3d')],
                ['label' => 'Applique Embroidery', 'href' => url('/?page=svc-applique')],
                ['label' => 'Chain Stitch Embroidery', 'href' => url('/?page=svc-chain')],
                ['label' => 'Photo Digitizing', 'href' => url('/?page=svc-photo')],
                ['label' => 'Vector Art', 'href' => url('/?page=svc-vector')],
            ]],
            ['label' => 'Pricing', 'href' => url('/?page=pricing')],
            ['label' => 'Formats', 'href' => url('/?page=formats')],
            ['label' => 'How It Works', 'href' => url('/?page=how-it-works')],
            ['label' => 'About', 'href' => url('/?page=about')],
            ['label' => 'Contact', 'href' => url('/?page=contact')],
        ];
        $serviceLinks = [
            ['label' => 'Embroidery Digitizing', 'href' => url('/?page=svc-embroidery')],
            ['label' => '3D / Puff Embroidery', 'href' => url('/?page=svc-3d')],
            ['label' => 'Applique Embroidery', 'href' => url('/?page=svc-applique')],
            ['label' => 'Chain Stitch Embroidery', 'href' => url('/?page=svc-chain')],
            ['label' => 'Photo Digitizing', 'href' => url('/?page=svc-photo')],
            ['label' => 'Vector Art', 'href' => url('/?page=svc-vector')],
        ];
        $companyLinks = [
            ['label' => 'About Us', 'href' => url('/?page=about')],
            ['label' => 'Our Quality', 'href' => url('/our-quality.php')],
            ['label' => 'Contact Us', 'href' => url('/?page=contact')],
        ];
        $resourceLinks = [
            ['label' => 'Our Prices', 'href' => url('/?page=pricing')],
            ['label' => 'Formats', 'href' => url('/?page=formats')],
            ['label' => 'Privacy Policy', 'href' => url('/privacy-policy.php')],
            ['label' => 'Terms and Conditions', 'href' => url('/terms.php')],
        ];
    @endphp
    <style>
        :root {
            color-scheme: light;
            --page-bg: #f4f4f4;
            --surface: #ffffff;
            --surface-soft: #f8fbfd;
            --ink: #1e293b;
            --muted: #5e6772;
            --brand: #d62b2b;
            --brand-dark: #b01f1f;
            --line: #e2e8f0;
            --shadow: 0 18px 38px rgba(17, 31, 45, 0.12);
            --footer: #0e1e35;
            --max: 1180px;
            --danger: #b8504d;
            --success: #2d7b53;
            --gold: #f5a623;
            --gold-light: #fff8ec;
            --navy: #0e1e35;
            --red-light: #fdf0f0;
            --accent-soft: #fdf0f0;
            --accent-dark: #b01f1f;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: var(--ink);
            background: #f8fafc;
            line-height: 1.6;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: min(var(--max), calc(100% - 28px));
            margin: 0 auto;
        }

        .site-frame {
            width: min(100%, 1280px);
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 10px 32px rgba(15, 23, 42, 0.08);
        }


        .site-header {
            position: relative;
            z-index: 50;
            background: #ffffff;
            border-bottom: 3px solid var(--gold);
        }

        .nav-shell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 76px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
        }

        .brand img {
            height: 48px;
            width: auto;
            max-width: 48vw;
        }

        .nav-toggle {
            display: none;
            border: 1px solid rgba(255,255,255,0.45);
            background: rgba(255,255,255,0.12);
            color: #fff;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-links a {
            padding: 7px 12px;
            font-size: 13px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--navy);
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--brand);
            background: var(--red-light);
        }

        .nav-drop { position: relative; cursor: pointer; }
        .nav-drop:hover .dropdown { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown { position: absolute; top: calc(100% + 8px); left: 0; background: #fff; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 16px 48px rgba(0,0,0,.12); min-width: 220px; padding: 8px; opacity: 0; pointer-events: none; transform: translateY(-6px); transition: all .22s; z-index: 999; }
        .dropdown a { display: block; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: var(--ink); font-weight: 500; white-space: nowrap; font-family: 'Montserrat', sans-serif; }
        .dropdown a:hover { background: var(--red-light); color: var(--brand); }
        @media (max-width: 860px) {
            .nav-drop { width: 100%; }
            .dropdown { position: static; opacity: 1; pointer-events: all; transform: none; box-shadow: none; border: none; padding: 4px 0 4px 16px; min-width: auto; }
            .dropdown a { padding: 8px 12px; font-size: 13px; }
        }

        .page-content {
            padding: 40px 0 56px;
        }

        .guest-shell {
            width: min(1120px, 100%);
            margin: 0 auto;
        }

        .panel {
            border-radius: 24px;
            background: #fff;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .intro-panel {
            padding: clamp(28px, 4vw, 42px);
            color: #fff;
            background:
                linear-gradient(rgba(0, 0, 0, 0.48), rgba(0, 0, 0, 0.48)),
                url('{{ $legacyAssetBase }}/images/1dollar-digitizing-banner.webp') center/cover no-repeat;
        }

        .intro-panel span {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            font-size: 0.76rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .intro-panel h1 {
            margin: 18px 0 10px;
            font-size: clamp(2rem, 4.6vw, 3.5rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .intro-panel p {
            margin: 0;
            color: rgba(255,255,255,0.88);
            line-height: 1.8;
        }

        .intro-stack {
            display: grid;
            gap: 12px;
            margin-top: 24px;
        }

        .intro-card {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.16);
        }

        .form-panel {
            padding: clamp(24px, 3vw, 36px);
        }

        .form-panel.auth-panel {
            border-top: 5px solid var(--gold);
        }

        .form-panel h2 {
            margin: 0 0 10px;
            font-size: 1.9rem;
            letter-spacing: -0.04em;
        }

        .muted {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.7;
        }

        .alert {
            margin-bottom: 16px;
            padding: 13px 15px;
            border-radius: 16px;
            border: 1px solid rgba(184,80,77,0.2);
            background: rgba(184,80,77,0.10);
            color: #7c2f2d;
        }

        .alert.success {
            background: rgba(45,123,83,0.10);
            color: #1d5639;
            border-color: rgba(45,123,83,0.18);
        }

        form {
            display: grid;
            gap: 16px;
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        label {
            display: grid;
            gap: 8px;
            font-weight: 700;
        }

        .form-field {
            display: grid;
            gap: 8px;
            position: relative;
        }

        .field-label {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 700;
            color: var(--ink);
        }

        .field-meta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .field-meta.required {
            min-height: auto;
            padding: 0;
            background: transparent;
            color: #d43f3a;
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0;
        }

        .field-meta.optional {
            display: none;
        }

        .form-section {
            display: grid;
            gap: 14px;
            margin-top: 6px;
            padding-top: 10px;
        }

        .form-section + .form-section {
            border-top: 1px solid var(--line);
            padding-top: 20px;
            margin-top: 4px;
        }

        .section-heading {
            display: grid;
            gap: 4px;
        }

        .section-heading h3 {
            margin: 0;
            font-size: 1rem;
            letter-spacing: -0.02em;
            color: var(--ink);
        }

        .section-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .field-help {
            margin-top: -2px;
            min-height: 20px;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .quick-picks {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .country-results {
            display: grid;
            gap: 6px;
            max-height: 240px;
            overflow-y: auto;
            padding: 10px;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 40;
            border: 1px solid rgba(13, 110, 163, 0.14);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 16px 32px rgba(17, 31, 45, 0.08);
        }

        .country-results[hidden] {
            display: none;
        }

        .country-result {
            min-height: auto;
            width: 100%;
            justify-content: flex-start;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid transparent;
            background: #fff;
            color: var(--ink);
            font-weight: 600;
            text-align: left;
            box-shadow: none;
        }

        .country-result:hover,
        .country-result:focus,
        .country-result.is-selected {
            background: rgba(214, 43, 43, 0.10);
            border-color: rgba(214, 43, 43, 0.18);
        }

        .quick-pick {
            min-height: 36px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(13, 110, 163, 0.18);
            background: rgba(214, 43, 43, 0.08);
            color: var(--brand-dark);
            font-size: 0.84rem;
            font-weight: 700;
            line-height: 1.2;
            box-shadow: none;
        }

        .quick-pick:hover,
        .quick-pick:focus {
            background: rgba(214, 43, 43, 0.14);
        }

        .field-error {
            min-height: 18px;
            color: var(--danger);
            font-size: 0.86rem;
            line-height: 1.4;
        }

        input, select, textarea {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 2px solid #8fa3b5;
            background: #fff;
            color: var(--ink);
            font: inherit;
            box-shadow: inset 0 1px 2px rgba(17, 31, 45, 0.04);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        textarea { min-height: 110px; resize: vertical; }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(245, 166, 35, 0.16);
        }

        input.is-invalid,
        select.is-invalid,
        textarea.is-invalid,
        .field-check.is-invalid,
        .radio-group.is-invalid {
            border-color: rgba(184, 80, 77, 0.65) !important;
            box-shadow: 0 0 0 4px rgba(184, 80, 77, 0.10);
        }

        .radio-group {
            display: grid;
            gap: 10px;
            padding: 4px;
            border-radius: 18px;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .radio-option {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,0.88);
        }

        .radio-option input { width: auto; min-height: auto; margin-top: 4px; }

        .field-check,
        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,0.88);
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .field-check input,
        .terms-row input {
            width: auto;
            min-height: auto;
            margin-top: 4px;
        }

        .field-check-copy,
        .terms-copy {
            display: grid;
            gap: 6px;
        }

        .terms-copy a {
            color: var(--brand-dark);
            font-weight: 700;
        }

        .terms-line {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 6px;
            font-weight: 600;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .terms-line {
                display: inline;
                white-space: normal;
            }
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-note {
            margin-bottom: 14px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(214, 43, 43, 0.16);
            background: rgba(214, 43, 43, 0.06);
            color: #355061;
        }

        .info-note strong {
            color: var(--brand-dark);
        }

        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 16px;
            border: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: white;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .button.secondary {
            background: white;
            color: var(--brand-dark);
            border: 1px solid var(--line);
        }

        .footer {
            margin-top: 48px;
            background: var(--footer);
            color: rgba(255, 255, 255, 0.78);
            padding: 44px 0 18px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr repeat(3, 1fr);
            gap: 24px;
        }

        .footer-card {
            padding: 22px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-logo {
            width: auto;
            height: 40px;
            max-width: 100%;
            margin-bottom: 16px;
        }

        .footer-intro {
            margin: 0;
            color: #f8fafc;
        }

        .footer h3 {
            margin-top: 0;
            margin-bottom: 14px;
            color: #fff;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .footer-link {
            color: #ffffff;
            font-weight: 600;
        }

        .footer-link:hover {
            color: #f5a623;
        }

        .footer-contact {
            display: grid;
            gap: 14px;
        }

        .footer-contact-item {
            display: grid;
            gap: 4px;
        }

        .footer-contact-item span {
            color: #cbd5e1;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .footer-bottom {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.12);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.92rem;
            color: #e2e8f0;
        }

        @media (max-width: 980px) {
            .guest-shell,
            .footer-grid,
            .grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .nav-toggle { display: inline-flex; }
            .nav-links {
                display: none;
                width: 100%;
                padding: 8px 0 16px;
            }
            .nav-links.open { display: flex; }
            .nav-links a {
                padding: 12px 14px;
            }
        }
        .topbar { background: var(--brand); padding: 8px 0; }
        .topbar .inner { max-width: 1260px; margin: 0 auto; padding: 0 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; }
        .tleft, .tright { display: flex; align-items: center; gap: 16px; font-size: 12.5px; color: #ffffff; }
        .topbar a { color: rgba(255,255,255,0.85); transition: color .2s; }
        .topbar a:hover { color: #ffffff; }
        .tb-sep { opacity: .25; }
        .socials { display: flex; gap: 7px; }
        .socials a { width: 26px; height: 26px; border-radius: 50%; border: 1px solid rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; font-size: 12px; transition: all .2s; color: #94a3b8; text-decoration: none; }
        .socials a:hover { border-color: var(--gold); color: var(--gold); }
    </style>
    <link rel="stylesheet" href="{{ url('/css/front-theme-overrides.css') }}">
    <style>
        body.front-theme.customer-guest-theme {
            background: #f8fafc !important;
        }
        body.front-theme.customer-guest-theme .site-frame {
            background: #ffffff !important;
            box-shadow: 0 10px 32px rgba(15, 23, 42, 0.08) !important;
        }
        body.front-theme.customer-guest-theme .top-bar,
        body.front-theme.customer-guest-theme .topbar {
            background: linear-gradient(135deg, #d62b2b, #b01f1f) !important;
            color: #ffffff !important;
        }
        body.front-theme.customer-guest-theme .site-header {
            border-bottom: 3px solid #f5a623 !important;
            background: rgba(255, 255, 255, 0.97) !important;
        }
        body.front-theme.customer-guest-theme .nav-links a:hover,
        body.front-theme.customer-guest-theme .nav-links a.active {
            color: #d62b2b !important;
        }
        body.front-theme.customer-guest-theme .form-panel.auth-panel {
            border-top: 5px solid #f5a623 !important;
        }
        body.front-theme.customer-guest-theme button,
        body.front-theme.customer-guest-theme .button {
            background: linear-gradient(135deg, #d62b2b, #b01f1f) !important;
        }
        body.front-theme.customer-guest-theme .button.secondary {
            background: #ffffff !important;
            color: #b01f1f !important;
            border: 1px solid #e2e8f0 !important;
        }
        body.front-theme.customer-guest-theme input:focus,
        body.front-theme.customer-guest-theme select:focus,
        body.front-theme.customer-guest-theme textarea:focus {
            border-color: #f5a623 !important;
            box-shadow: 0 0 0 4px rgba(245, 166, 35, 0.16) !important;
        }
        body.front-theme.customer-guest-theme .footer {
            background: #0e1e35 !important;
        }
        body.front-theme.customer-guest-theme .footer-link:hover {
            color: #f5a623 !important;
        }
        body.front-theme.customer-guest-theme .country-result:hover,
        body.front-theme.customer-guest-theme .country-result:focus,
        body.front-theme.customer-guest-theme .country-result.is-selected {
            background: rgba(214, 43, 43, 0.10) !important;
            border-color: rgba(214, 43, 43, 0.18) !important;
        }
        body.front-theme.customer-guest-theme .quick-pick {
            border-color: rgba(176, 31, 31, 0.18) !important;
            background: rgba(214, 43, 43, 0.08) !important;
            color: #b01f1f !important;
        }
        body.front-theme.customer-guest-theme .quick-pick:hover,
        body.front-theme.customer-guest-theme .quick-pick:focus {
            background: rgba(214, 43, 43, 0.14) !important;
        }
        body.front-theme.customer-guest-theme .info-note {
            border-color: rgba(245, 166, 35, 0.16) !important;
            background: rgba(245, 166, 35, 0.06) !important;
            color: #5e4b2a !important;
        }
        body.front-theme.customer-guest-theme .info-note strong {
            color: #c8840a !important;
        }
        body.front-theme.customer-guest-theme .terms-copy a {
            color: #b01f1f !important;
        }
    </style>
</head>
<body class="front-theme customer-guest-theme">
    <div class="site-frame">
        <div class="topbar">
            <div class="inner">
                <div class="tleft">
                    <span>📞 <a href="tel:+12063126446">+1 206-312-6446</a></span>
                    <span class="tb-sep">|</span>
                    <span>✉ <a href="mailto:info@digitizingzone.com">info@digitizingzone.com</a></span>
                    <span class="tb-sep topbar-addr">|</span>
                    <span class="topbar-addr">🇺🇸 US · 🇬🇧 UK · 🇵🇰 Pakistan — Global Operations</span>
                </div>
                <div class="tright">
                    <span>24/7 Support</span>
                    <span class="tb-sep">|</span>
                    <div class="socials">
                        <a href="https://facebook.com" target="_blank">f</a>
                        <a href="https://linkedin.com" target="_blank">in</a>
                        <a href="https://twitter.com" target="_blank">tw</a>
                        <a href="https://instagram.com" target="_blank">ig</a>
                    </div>
                </div>
            </div>
        </div>

        <header class="site-header">
            <div class="container nav-shell">
                <a class="brand" href="{{ url('/') }}">
                    <img src="{{ url('images/logo.png') }}" alt="Digitizing Zone">
                </a>

                <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="public-navigation">Menu</button>

                <nav class="nav-links" id="public-navigation">
                    @foreach ($publicMenu as $item)
                        @php
                            $active = request()->path() === ltrim($item['href'], '/') || ($item['href'] === '/' && request()->path() === '/');
                            $childActive = !empty($item['children']) && collect($item['children'])->contains(fn ($child) => request()->path() === ltrim($child['href'], '/'));
                        @endphp
                        @if (!empty($item['children']))
                            <div class="nav-drop">
                                <a class="{{ $active || $childActive ? 'active' : '' }}">{{ $item['label'] }} ▾</a>
                                <div class="dropdown">
                                    @foreach ($item['children'] as $child)
                                        @php
                                            $childActiveLink = request()->path() === ltrim($child['href'], '/');
                                        @endphp
                                        <a class="{{ $childActiveLink ? 'active' : '' }}" href="{{ $child['href'] }}">{{ $child['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a class="{{ $active ? 'active' : '' }}" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="page-content">
            @yield('content')
        </main>

        <footer class="footer">
            <div class="container footer-grid">
                <div class="footer-card">
                    <img class="footer-logo" src="{{ url('images/logo.png') }}" alt="Digitizing Zone footer logo">
                    <p class="footer-intro">Digitizing Zone is a world-leading embroidery digitizing company with over 20 years of excellence. We deliver the highest quality digitized designs at unbeatable rates, with offices in the USA, UK, and Pakistan — serving clients globally 24/7.</p>
                </div>
                <div class="footer-card">
                    <h3>Company</h3>
                    <ul>
                        @foreach ($companyLinks as $item)
                            <li><a class="footer-link" href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="footer-card">
                    <h3>Resources</h3>
                    <ul>
                        @foreach ($resourceLinks as $item)
                            <li><a class="footer-link" href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="footer-card">
                    <h3>Contact &amp; Access</h3>
                    <div class="footer-contact">
                        <div class="footer-contact-item">
                            <span>Email</span>
                            <a class="footer-link" href="mailto:{{ $siteContext->supportEmail }}">{{ $siteContext->supportEmail }}</a>
                        </div>
                        <div class="footer-contact-item">
                            <span>Customer Login</span>
                            <a class="footer-link" href="{{ url('/login.php') }}">Sign In To Your Account</a>
                        </div>
                        <div class="footer-contact-item">
                            <span>Need Help?</span>
                            <a class="footer-link" href="{{ url('/contact-us.php') }}">Contact Our Team</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container footer-bottom">
                <span>Copyrights &copy; 2010-{{ date('Y') }} All Rights Reserved by digitizingzone.com</span>
                <span>Custom embroidery digitizing and vector art services.</span>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.querySelector('[data-nav-toggle]');
            var navigation = document.getElementById('public-navigation');

            if (!toggle || !navigation) {
                return;
            }

            toggle.addEventListener('click', function () {
                var isOpen = navigation.classList.toggle('open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            document.querySelectorAll('form[data-validate-form]').forEach(function (form) {
                var controls = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function (control) {
                    return control.type !== 'hidden' && control.type !== 'submit' && control.type !== 'button' && control.type !== 'reset';
                });

                var radioNames = {};

                function fieldContainer(control) {
                    return control.closest('[data-form-field]') || control.closest('label') || control.parentElement;
                }

                function fieldErrorNode(control) {
                    var container = fieldContainer(control);

                    return container ? container.querySelector('[data-field-error]') : null;
                }

                function syncMatchValidity(control) {
                    var otherName = control.getAttribute('data-match');

                    if (!otherName) {
                        syncCountryValidity(control);
                        return;
                    }

                    var other = form.querySelector('[name="' + otherName + '"]');

                    if (!other) {
                        control.setCustomValidity('');
                        return;
                    }

                    if (control.value !== '' && other.value !== '' && control.value !== other.value) {
                        control.setCustomValidity(control.getAttribute('data-match-message') || 'This field must match.');
                    } else {
                        control.setCustomValidity('');
                    }

                    syncCountryValidity(control);
                }

                function syncCountryValidity(control) {
                    if (!control.hasAttribute('data-country-strict')) {
                        return;
                    }

                    var options = [];

                    try {
                        options = JSON.parse(control.getAttribute('data-country-options') || '[]');
                    } catch (error) {
                        options = [];
                    }

                    var value = (control.value || '').trim();

                    if (value === '' || options.indexOf(value) !== -1) {
                        control.setCustomValidity('');
                    } else {
                        control.setCustomValidity('Please choose a country from the suggested list.');
                    }
                }

                function renderError(control, isValid, message) {
                    var container = fieldContainer(control);
                    var error = fieldErrorNode(control);

                    control.classList.toggle('is-invalid', !isValid);
                    control.setAttribute('aria-invalid', isValid ? 'false' : 'true');

                    if (container && (control.type === 'checkbox' || control.type === 'radio')) {
                        container.classList.toggle('is-invalid', !isValid);
                    }

                    if (error) {
                        error.textContent = isValid ? '' : message;
                    }
                }

                function validateRadio(control) {
                    if (radioNames[control.name]) {
                        return radioNames[control.name];
                    }

                    var group = Array.prototype.slice.call(form.querySelectorAll('input[type="radio"][name="' + control.name + '"]'));
                    var required = group.some(function (item) { return item.required; });
                    var valid = !required || group.some(function (item) { return item.checked; });
                    var message = valid ? '' : (control.getAttribute('data-group-error') || 'Please select an option.');

                    group.forEach(function (item) {
                        renderError(item, valid, message);
                    });

                    radioNames[control.name] = valid;

                    return valid;
                }

                function validateControl(control) {
                    if (control.disabled) {
                        return true;
                    }

                    if (control.type === 'radio') {
                        return validateRadio(control);
                    }

                    syncMatchValidity(control);

                    var valid = control.checkValidity();
                    renderError(control, valid, valid ? '' : control.validationMessage);

                    return valid;
                }

                controls.forEach(function (control) {
                    control.addEventListener('blur', function () {
                        radioNames = {};
                        validateControl(control);
                    });

                    control.addEventListener('input', function () {
                        radioNames = {};
                        if (control.classList.contains('is-invalid') || control.getAttribute('aria-invalid') === 'true') {
                            validateControl(control);
                        } else if (control.hasAttribute('data-match')) {
                            validateControl(control);
                        }
                    });

                    control.addEventListener('change', function () {
                        radioNames = {};
                        validateControl(control);
                    });
                });

                form.addEventListener('submit', function (event) {
                    radioNames = {};
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

            document.querySelectorAll('[data-country-pick]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var field = document.querySelector('[data-country-input]');

                    if (!field) {
                        return;
                    }

                    field.value = button.getAttribute('data-country-pick') || '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    field.focus();
                });
            });

            document.querySelectorAll('[data-country-input]').forEach(function (field) {
                var results = field.parentElement ? field.parentElement.querySelector('[data-country-results]') : null;
                var options = [];

                try {
                    options = JSON.parse(field.getAttribute('data-country-options') || '[]');
                } catch (error) {
                    options = [];
                }

                function renderCountryOptions(term) {
                    if (!results) {
                        return;
                    }

                    var query = (term || '').trim().toLowerCase();
                    var hasFocus = document.activeElement === field;

                    if (!hasFocus) {
                        results.hidden = true;
                        return;
                    }

                    var startsWith = options.filter(function (country) {
                        return query === '' || country.toLowerCase().indexOf(query) === 0;
                    });
                    var includes = options.filter(function (country) {
                        return query !== '' && country.toLowerCase().indexOf(query) > 0;
                    });
                    var matches = startsWith.concat(includes);

                    if (!matches.length) {
                        results.innerHTML = '';
                        results.hidden = true;
                        return;
                    }

                    results.innerHTML = matches.map(function (country) {
                        var selected = field.value === country ? ' is-selected' : '';
                        return '<button type="button" class="country-result' + selected + '" data-country-value="' + country.replace(/"/g, '&quot;') + '">' + country + '</button>';
                    }).join('');
                    results.hidden = false;
                }

                field.addEventListener('focus', function () {
                    renderCountryOptions('');
                });

                field.addEventListener('input', function () {
                    renderCountryOptions(field.value);
                });

                field.addEventListener('keydown', function (e) {
                    if (e.key === 'Tab' || e.key === 'Escape') {
                        if (results) {
                            results.hidden = true;
                        }
                    }
                });

                field.addEventListener('blur', function () {
                    window.setTimeout(function () {
                        if (results) {
                            results.hidden = true;
                        }
                    }, 140);
                });

                if (!results) {
                    return;
                }

                results.addEventListener('click', function (event) {
                    var option = event.target.closest('[data-country-value]');

                    if (!option) {
                        return;
                    }

                    field.value = option.getAttribute('data-country-value') || '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    results.hidden = true;
                    field.focus();
                });

                results.hidden = true;
            });
        });
    </script>
</body>
</html>
