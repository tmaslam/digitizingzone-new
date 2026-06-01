@extends('layouts.customer-guest')

@section('title', 'Verify Your Identity — '.$siteContext->displayLabel())

@section('content')
    <div class="container guest-shell">
        <div class="auth-layout auth-layout-solo">
            <section class="panel form-panel auth-panel">
                <h2>Check Your Email</h2>
                <p class="muted">We sent a 6-digit verification code to the email address on your account. Enter it below to complete sign-in.</p>

                @if ($errors->has('code') || $errors->has('auth'))
                    <div class="alert">{{ $errors->first('code') ?: $errors->first('auth') }}</div>
                @endif

                @if (session('success'))
                    <div class="alert success">{{ session('success') }}</div>
                @endif

                @if ($signupOffer)
                    <div class="info-note">
                        <strong>{{ $signupOffer['headline'] }}</strong><br>
                        {{ $signupOffer['summary'] }}
                    </div>
                @endif

                <form method="post" action="{{ route('customer.2fa.verify') }}" novalidate>
                    @csrf
                    <label class="form-field">
                        <span class="field-label">Verification Code</span>
                        <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                               maxlength="6" placeholder="000000" autocomplete="one-time-code" autofocus required
                               style="font-size:1.6rem;letter-spacing:0.3em;text-align:center;font-family:monospace;">
                        <span class="field-help">The code expires in 10 minutes and can only be used once.</span>
                    </label>

                    <label class="form-field" style="display:flex;gap:10px;align-items:flex-start;">
                        <input type="checkbox" name="trust_device" value="1" style="width:auto;min-height:0;margin-top:2px;">
                        <span>
                            <span class="field-label" style="display:block;">Trust this browser for 30 days</span>
                            <span class="field-help">If you choose this, we will not ask for a new verification code on this browser for the next 30 days.</span>
                        </span>
                    </label>

                    <div class="actions">
                        <button type="submit">Verify &amp; Sign In</button>
                    </div>
                </form>

                <p class="muted" style="margin-top:16px;">
                    <form method="post" action="{{ route('customer.2fa.resend') }}" style="display:inline;">
                        @csrf
                        <button type="submit" style="background:none;border:none;padding:0;color:var(--brand-dark);cursor:pointer;font-size:inherit;text-decoration:underline;">Resend code to my email</button>
                    </form><br>
                    <a href="{{ url('/login.php') }}">Back to sign in</a>
                </p>
            </section>
        </div>
    </div>
@endsection
