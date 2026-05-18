@extends('layouts.admin')

@section('title', $mode === 'create' ? 'Create Site Offer' : 'Edit Site Offer')
@section('page_title', $mode === 'create' ? 'Create Site Offer' : 'Edit Site Offer')
@section('page_subtitle', 'Configure the site-scoped signup offer for the payment-based onboarding path.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>{{ $mode === 'create' ? 'New Welcome Offer' : 'Welcome Offer Details' }}</h3>
                <p>This offer stays brand-specific and controls the payment-based signup path for new customers.</p>
            </div>
            <a class="button secondary" href="{{ url('/v/site-offers.php') }}">Back To Offers</a>
        </div>

        <form method="post" action="{{ $mode === 'create' ? url('/v/site-offers-create.php') : url('/v/site-offers/'.$offer->id.'/edit') }}" class="stack">
            @csrf

            <div class="filter-grid">
                <label>
                    Site
                    <select name="site_id" required>
                        <option value="">Select Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id', $offer->site_id) === (string) $site->id)>{{ $site->brand_name ?: $site->name ?: $site->legacy_key }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Status
                    <select name="is_active" required>
                        <option value="1" @selected((string) old('is_active', $offer->is_active ?? 1) === '1')>Active</option>
                        <option value="0" @selected((string) old('is_active', $offer->is_active ?? 1) === '0')>Inactive</option>
                    </select>
                </label>
                <label>
                    Offer Name
                    <input type="text" name="promotion_name" value="{{ old('promotion_name', $offer->promotion_name) }}" required>
                </label>
                <label>
                    Offer Code
                    <input type="text" name="promotion_code" value="{{ old('promotion_code', $offer->promotion_code) }}">
                </label>
                <label>
                    Welcome Payment Amount
                    <input type="number" step="0.01" min="0" name="onboarding_payment_amount" value="{{ old('onboarding_payment_amount', number_format((float) ($offerConfig['payment_amount'] ?? 0), 2, '.', '')) }}" required>
                </label>
                <label>
                    Credit Amount To Store
                    <input type="number" step="0.01" min="0" name="credit_amount" value="{{ old('credit_amount', number_format((float) ($offerConfig['credit_amount'] ?? 0), 2, '.', '')) }}" required>
                </label>
                <label>
                    First Order Flat Price
                    <input type="number" step="0.01" min="0" name="first_order_flat_amount" value="{{ old('first_order_flat_amount', number_format((float) ($offerConfig['first_order_flat_amount'] ?? 0), 2, '.', '')) }}" required>
                </label>
                <label>
                    Free Under Stitches
                    <input type="number" step="1" min="0" name="first_order_free_under_stitches" value="{{ old('first_order_free_under_stitches', (int) ($offerConfig['first_order_free_under_stitches'] ?? 0)) }}">
                </label>
                <label>
                    Starts At
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $offer->starts_at ? date('Y-m-d\\TH:i', strtotime((string) $offer->starts_at)) : '') }}">
                </label>
                <label>
                    Ends At
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $offer->ends_at ? date('Y-m-d\\TH:i', strtotime((string) $offer->ends_at)) : '') }}">
                </label>
            </div>

            <label>
                Customer Headline
                <input type="text" name="headline" value="{{ old('headline', $offerConfig['headline'] ?? '') }}" required>
            </label>

            <label>
                Customer Summary
                <textarea name="summary" rows="4" required>{{ old('summary', $offerConfig['summary'] ?? '') }}</textarea>
            </label>

            <label>
                Verification Message
                <textarea name="verification_message" rows="3" required>{{ old('verification_message', $offerConfig['verification_message'] ?? '') }}</textarea>
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button class="button" type="submit">{{ $mode === 'create' ? 'Create Offer' : 'Save Offer' }}</button>
                <a class="button secondary" href="{{ url('/v/site-offers.php') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
