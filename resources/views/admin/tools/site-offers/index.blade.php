@extends('layouts.admin')

@section('title', 'Site Offers')
@section('page_title', 'Site Offers')
@section('page_subtitle', 'Manage the payment-based signup offer for each site.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>New Member Offers</h3>
                <p>These offers stay site-specific, so each brand can control the $1 onboarding path separately from manual admin approval.</p>
            </div>
            <a class="button" href="{{ url('/v/site-offers-create.php') }}">Create Offer</a>
        </div>

        <form method="get" class="filter-grid">
            <label>
                Site
                <select name="site_id">
                    <option value="">All Sites</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) request('site_id') === (string) $site->id)>{{ $site->brand_name ?: $site->name ?: $site->legacy_key }}</option>
                    @endforeach
                </select>
            </label>
            <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <button type="submit" class="button secondary">Filter</button>
                <a class="button secondary" href="{{ url('/v/site-offers.php') }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Offer</th>
                    <th>Site</th>
                    <th>Welcome Payment</th>
                    <th>First Order Benefit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @if (collect($offers)->isEmpty())
                    <tr>
                        <td colspan="6">No site offers have been configured yet.</td>
                    </tr>
                @else
                @foreach ($offers as $offer)
                    @php
                        $summary = \App\Support\SignupOfferService::offerSummary($offer);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $offer->promotion_name }}</strong><br>
                            <span class="table-subtext">{{ $summary['headline'] ?? '-' }}</span>
                        </td>
                        <td>{{ $offer->site?->brand_name ?: $offer->site?->name ?: '-' }}</td>
                        <td>${{ number_format((float) ($summary['payment_amount'] ?? 0), 2) }}</td>
                        <td>
                            @if (($summary['first_order_free_under_stitches'] ?? 0) > 0)
                                Free under {{ number_format((int) $summary['first_order_free_under_stitches']) }} stitches
                            @else
                                ${{ number_format((float) ($summary['first_order_flat_amount'] ?? 0), 2) }}
                            @endif
                        </td>
                        <td><span class="status {{ $offer->is_active ? 'success' : 'warning' }}">{{ $offer->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <a class="button secondary" href="{{ url('/v/site-offers/'.$offer->id.'/claims') }}">Claims</a>
                                <a class="button secondary" href="{{ url('/v/site-offers/'.$offer->id.'/edit') }}">Edit</a>
                                <form method="post" action="{{ url('/v/site-offers/'.$offer->id.'/delete') }}" onsubmit="return confirm('Delete this site offer?');" style="margin:0;">
                                    @csrf
                                    <button class="button secondary" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                @endif
                </tbody>
            </table>
        </div>

        {{ $offers->links() }}
    </section>
@endsection
