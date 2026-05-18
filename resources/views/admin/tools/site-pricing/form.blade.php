@extends('layouts.admin')

@section('title', $mode === 'create' ? 'Create Site Pricing Profile' : 'Edit Site Pricing Profile')
@section('page_title', $mode === 'create' ? 'Create Site Pricing Profile' : 'Edit Site Pricing Profile')
@section('page_subtitle', 'Define the default site-level pricing. Customer pricing fields should only be used when that customer truly needs a special rate.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>{{ $mode === 'create' ? 'New Pricing Profile' : 'Pricing Profile Details' }}</h3>
                <p>Keep this simple: set the site default once, then let customer pricing fields override it only when required.</p>
            </div>
            <a class="button secondary" href="{{ url('/v/site-pricing.php') }}">Back To Pricing</a>
        </div>

        <form method="post" action="{{ $mode === 'create' ? url('/v/site-pricing-create.php') : url('/v/site-pricing/'.$profile->id.'/edit') }}" class="stack">
            @csrf

            <div class="filter-grid">
                <label>
                    Site
                    <select name="site_id" required>
                        <option value="">Select Site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id', $profile->site_id) === (string) $site->id)>{{ $site->brand_name ?: $site->name ?: $site->legacy_key }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Status
                    <select name="is_active" required>
                        <option value="1" @selected((string) old('is_active', $profile->is_active ?? 1) === '1')>Active</option>
                        <option value="0" @selected((string) old('is_active', $profile->is_active ?? 1) === '0')>Inactive</option>
                    </select>
                </label>
                <label>
                    Profile Name
                    <input type="text" name="profile_name" value="{{ old('profile_name', $profile->profile_name) }}" required>
                </label>
                <label>
                    Work Type
                    <select name="work_type" required>
                        @foreach ($workTypes as $key => $label)
                            <option value="{{ $key }}" @selected((string) old('work_type', $profile->work_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Turnaround
                    <select name="turnaround_code">
                        @foreach ($turnaroundCodes as $key => $label)
                            <option value="{{ $key }}" @selected((string) old('turnaround_code', $profile->turnaround_code ?: 'standard') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Pricing Mode
                    <select name="pricing_mode" required>
                        @foreach ($pricingModes as $key => $label)
                            <option value="{{ $key }}" @selected((string) old('pricing_mode', $profile->pricing_mode) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Fixed Price / Hourly Rate
                    <input type="number" step="0.01" min="0" name="fixed_price" value="{{ old('fixed_price', $profile->fixed_price) }}">
                </label>
                <label>
                    Per 1,000 Rate
                    <input type="number" step="0.0001" min="0" name="per_thousand_rate" value="{{ old('per_thousand_rate', $profile->per_thousand_rate) }}">
                </label>
                <label>
                    Minimum Charge
                    <input type="number" step="0.01" min="0" name="minimum_charge" value="{{ old('minimum_charge', $profile->minimum_charge) }}">
                </label>
                <label>
                    Included Units / Cap
                    <input type="number" step="0.01" min="0" name="included_units" value="{{ old('included_units', $profile->included_units) }}">
                </label>
                <label>
                    Overage / Hourly Rate
                    <input type="number" step="0.0001" min="0" name="overage_rate" value="{{ old('overage_rate', $profile->overage_rate) }}">
                </label>
                <label>
                    Package Name
                    <input type="text" name="package_name" value="{{ old('package_name', $profile->package_name) }}">
                </label>
            </div>

            <p class="muted" style="margin:0;">
                `Per 1,000 Rate` and `Minimum Charge` are most useful for digitizing. `Fixed Price / Hourly Rate` and `Overage / Hourly Rate` are the practical defaults for vector work.
            </p>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button class="button" type="submit">{{ $mode === 'create' ? 'Create Profile' : 'Save Profile' }}</button>
                <a class="button secondary" href="{{ url('/v/site-pricing.php') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
