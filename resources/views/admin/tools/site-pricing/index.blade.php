@extends('layouts.admin')

@section('title', 'Site Pricing')
@section('page_title', 'Site Pricing')
@section('page_subtitle', 'Manage the default pricing rules for each website. Customer pricing fields only override these defaults when the customer has special pricing.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>Pricing Profiles</h3>
                <p>Keep the default formula at the site level, then use customer rates only when a customer really has a special deal.</p>
            </div>
            <a class="button" href="{{ url('/v/site-pricing-create.php') }}">New Pricing Profile</a>
        </div>

        <form method="get" action="{{ url('/v/site-pricing.php') }}" class="filter-grid">
            <label>
                Site
                <select name="site_id">
                    <option value="">All Sites</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) request('site_id') === (string) $site->id)>{{ $site->brand_name ?: $site->name ?: $site->legacy_key }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Work Type
                <select name="work_type">
                    <option value="">All Work Types</option>
                    @foreach ($workTypes as $key => $label)
                        <option value="{{ $key }}" @selected((string) request('work_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <button class="button" type="submit">Filter</button>
                <a class="button secondary" href="{{ url('/v/site-pricing.php') }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Profile</th>
                        <th>Work Type</th>
                        <th>Turnaround</th>
                        <th>Formula</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if (collect($profiles)->isEmpty())
                        <tr>
                            <td colspan="7">No site pricing profiles have been configured yet.</td>
                        </tr>
                    @else
                    @foreach ($profiles as $profile)
                        <tr>
                            <td>{{ $profile->site?->brand_name ?: $profile->site?->name ?: '-' }}</td>
                            <td>
                                <strong>{{ $profile->profile_name }}</strong><br>
                                <span class="muted">
                                    @if ((float) ($profile->minimum_charge ?: 0) > 0)
                                        Min ${{ number_format((float) $profile->minimum_charge, 2) }}
                                    @endif
                                    @if ((float) ($profile->included_units ?: 0) > 0)
                                        @if ((float) ($profile->minimum_charge ?: 0) > 0) · @endif
                                        Cap {{ rtrim(rtrim(number_format((float) $profile->included_units, 2, '.', ''), '0'), '.') }}
                                    @endif
                                </span>
                            </td>
                            <td>{{ \App\Support\SitePricing::workTypeLabel((string) $profile->work_type) }}</td>
                            <td>{{ \App\Support\SitePricing::turnaroundLabel((string) ($profile->turnaround_code ?: 'standard')) }}</td>
                            <td>
                                @if ($profile->pricing_mode === 'per_thousand' || $profile->pricing_mode === 'customer_rate')
                                    ${{ number_format((float) ($profile->per_thousand_rate ?: 0), 2) }} / 1,000
                                @else
                                    ${{ number_format((float) ($profile->fixed_price ?: $profile->overage_rate ?: 0), 2) }} fixed/hourly
                                @endif
                            </td>
                            <td>{{ (int) $profile->is_active === 1 ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <a class="badge" href="{{ url('/v/site-pricing/'.$profile->id.'/edit') }}">Edit</a>
                                    <form method="post" action="{{ url('/v/site-pricing/'.$profile->id.'/delete') }}" onsubmit="return confirm('Delete this pricing profile?');">
                                        @csrf
                                        <button class="badge" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        {{ $profiles->links() }}
    </section>
@endsection
