@extends('layouts.customer-guest')

@section('title', $siteContext->displayLabel().' Activation')

@section('content')
    <div class="container guest-shell" style="grid-template-columns:minmax(0,0.9fr) minmax(0,0.8fr);">
        <section class="panel intro-panel">
            <span>{{ $siteContext->displayLabel() }}</span>
            <h1>{{ ($activated ?? false) ? 'Verification complete' : 'Activation update' }}</h1>
            <p>{{ $message ?? 'Your customer account has been updated.' }}</p>
        </section>

        <section class="panel form-panel">
            <h2>Activation complete</h2>
            <p class="muted">{{ $message ?? ('Your customer account for '.$siteContext->displayLabel().' is now active.') }}</p>
            <div class="actions">
                <a class="button" href="{{ $nextStepUrl ?? '/login.php' }}">{{ $nextStepLabel ?? 'Go to Login' }}</a>
            </div>
        </section>
    </div>
@endsection
