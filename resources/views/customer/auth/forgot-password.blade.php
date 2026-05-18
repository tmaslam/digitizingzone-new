@extends('layouts.customer-guest')

@section('title', $siteContext->displayLabel().' Password Help')

@section('content')
    <div class="container guest-shell" style="grid-template-columns:minmax(0,0.9fr) minmax(0,0.8fr);">
        <section class="panel intro-panel">
            <span>{{ $siteContext->displayLabel() }}</span>
            <h1>Password help</h1>
            <p>Enter the email, alternate email, or username for your customer account and we will send a reset link if we find a matching account for this website.</p>
            <div class="intro-stack">
                <div class="intro-card">
                    If the reset email does not show up in your inbox, please check spam or junk.
                </div>
            </div>
        </section>

        <section class="panel form-panel">
            <h2>Send reset link</h2>
            <p class="muted">Use the same details you normally use to log in.</p>

            @if (session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="/forget-password.php" data-validate-form novalidate>
                @csrf
                <label class="form-field" data-form-field>
                    <span class="field-label">Email or User Name <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="text" name="identity" value="{{ old('identity') }}" autocomplete="username" required>
                    <span class="field-help">Enter the email, alternate email, or username linked to your customer account.</span>
                    <span class="field-error" data-field-error aria-live="polite"></span>
                </label>

                @include('shared.turnstile')

                <div class="actions">
                    <button type="submit">Send Reset Link</button>
                    <a class="button secondary" href="/login.php">Back to Login</a>
                </div>
            </form>
        </section>
    </div>
@endsection
