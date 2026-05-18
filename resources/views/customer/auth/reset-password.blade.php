@extends('layouts.customer-guest')

@section('title', $siteContext->displayLabel().' Reset Password')

@section('content')
    <div class="container guest-shell" style="grid-template-columns:minmax(0,0.9fr) minmax(0,0.8fr);">
        <section class="panel intro-panel">
            <span>{{ $siteContext->displayLabel() }}</span>
            <h1>Reset password</h1>
            <p>Create a new password for your customer account on this website.</p>
        </section>

        <section class="panel form-panel">
            <h2>Choose a new password</h2>
            <p class="muted">Once saved, the old reset link will no longer work.</p>

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            @if (! $valid)
                <div class="alert">This password reset link is invalid or has expired.</div>
                <div class="actions">
                    <a class="button" href="/forget-password.php">Request a New Link</a>
                </div>
            @else
                <form method="post" action="/reset-password.php" data-validate-form novalidate>
                    @csrf
                    <input type="hidden" name="selector" value="{{ $selector }}">
                    <input type="hidden" name="token" value="{{ $token }}">

                    <label class="form-field" data-form-field>
                        <span class="field-label">New Password <span class="field-meta required" aria-hidden="true">*</span></span>
                        <input type="password" name="password" autocomplete="new-password" minlength="6" required>
                        <span class="field-help">Use at least 6 characters.</span>
                        <span class="field-error" data-field-error aria-live="polite"></span>
                    </label>

                    <label class="form-field" data-form-field>
                        <span class="field-label">Confirm New Password <span class="field-meta required" aria-hidden="true">*</span></span>
                        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="6" required data-match="password" data-match-message="The confirm password must match the password.">
                        <span class="field-error" data-field-error aria-live="polite"></span>
                    </label>

                    <div class="actions">
                        <button type="submit">Save Password</button>
                        <a class="button secondary" href="/login.php">Back to Login</a>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection
