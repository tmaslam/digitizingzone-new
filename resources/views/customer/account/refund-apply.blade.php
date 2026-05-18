@extends('layouts.customer')

@section('title', ($pageTitle ?? 'Apply Refund').' - '.$siteContext->displayLabel())
@section('hero_title', $pageTitle ?? 'Apply Refund')
@section('hero_text', 'Send a refund or billing review request to the admin team without leaving your site-specific customer account.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Refund Request</h3>
                <p>Use this form if you need the billing team to review a refund request for your current site account.</p>
            </div>
        </div>

        <form method="post" action="/refund-apply.php" class="form-grid">
            @csrf

            <label style="grid-column: 1 / -1;">
                Reason
                <textarea name="comments" placeholder="Tell us why you are requesting a refund." required>{{ old('comments') }}</textarea>
            </label>

            <div style="grid-column: 1 / -1; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit">Submit Refund Request</button>
                <a class="button secondary" href="/dashboard.php">Back To Dashboard</a>
            </div>
        </form>
    </section>
@endsection
