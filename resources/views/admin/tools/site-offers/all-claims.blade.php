@extends('layouts.admin')

@section('title', 'Offer Claims Report')
@section('page_title', 'Offer Claims Report')
@section('page_subtitle', 'All signup offer claims across every offer — payment status, first-order redemption, and stitch outcome.')

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>All Claims</h3>
                <p>Every account that signed up under a welcome offer, with payment and first-order outcome.</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="get" class="filter-grid">
            <label>
                Offer
                <select name="offer_id">
                    <option value="">All Offers</option>
                    @foreach ($offers as $offer)
                        <option value="{{ $offer->id }}" @selected((string) request('offer_id') === (string) $offer->id)>
                            {{ $offer->promotion_name }}{{ $offer->promotion_code ? ' ('.$offer->promotion_code.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending_verification" @selected(request('status') === 'pending_verification')>Pending Verification</option>
                    <option value="pending_payment"      @selected(request('status') === 'pending_payment')>Pending Payment</option>
                    <option value="paid"                 @selected(request('status') === 'paid')>Paid</option>
                    <option value="redeemed"             @selected(request('status') === 'redeemed')>Redeemed</option>
                </select>
            </label>
            <label>
                Signed Up From
                <input type="date" name="date_from" value="{{ request('date_from') }}">
            </label>
            <label>
                Signed Up To
                <input type="date" name="date_to" value="{{ request('date_to') }}">
            </label>
            <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <button type="submit" class="button secondary">Filter</button>
                <a class="button secondary" href="{{ url('/v/offer-claims.php') }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Offer</th>
                        <th>Signed Up</th>
                        <th>Status</th>
                        <th>Paid At</th>
                        <th>First Order</th>
                        <th>Stitches</th>
                        <th>Redeemed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($claims as $claim)
                        @php
                            $config    = \App\Support\SignupOfferService::offerSummary($claim->promotion);
                            $threshold = (int) ($config['first_order_free_under_stitches'] ?? 0);
                            $stitches  = $claim->redeemedOrder ? (float) trim((string) ($claim->redeemedOrder->stitches ?? '')) : null;
                            $stResult  = null;
                            if ($threshold > 0 && $stitches !== null && $stitches > 0) {
                                $stResult = $stitches <= $threshold ? 'under' : 'over';
                            }
                        @endphp
                        <tr>
                            <td>
                                @if ($claim->customer)
                                    <strong>{{ $claim->customer->display_name ?: $claim->customer->user_name }}</strong><br>
                                    <span class="table-subtext">{{ $claim->customer->user_email }}</span>
                                @else
                                    <span class="table-subtext">User #{{ $claim->user_id }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($claim->promotion)
                                    <a href="{{ url('/v/site-offers/'.$claim->site_promotion_id.'/claims') }}">
                                        {{ $claim->promotion->promotion_name }}
                                    </a>
                                    @if ($claim->promotion->promotion_code)
                                        <br><span class="table-subtext">{{ $claim->promotion->promotion_code }}</span>
                                    @endif
                                @else
                                    <span class="table-subtext">#{{ $claim->site_promotion_id }}</span>
                                @endif
                            </td>
                            <td>{{ $claim->created_at ? \Carbon\Carbon::parse($claim->created_at)->format('d M Y') : '—' }}</td>
                            <td>
                                @php
                                    $statusClass = match((string) $claim->status) {
                                        'paid', 'redeemed' => 'success',
                                        'pending_payment'  => 'warning',
                                        default            => '',
                                    };
                                @endphp
                                <span class="status {{ $statusClass }}">{{ str_replace('_', ' ', $claim->status) }}</span>
                            </td>
                            <td>{{ $claim->paid_at ? \Carbon\Carbon::parse($claim->paid_at)->format('d M Y') : '—' }}</td>
                            <td>
                                @if ($claim->redeemedOrder)
                                    <a href="{{ url('/v/order-detail.php?order_id='.$claim->redeemed_order_id) }}">
                                        #{{ $claim->redeemed_order_id }}
                                    </a><br>
                                    <span class="table-subtext">{{ \Illuminate\Support\Str::limit($claim->redeemedOrder->design_name, 35) }}</span>
                                @elseif ($claim->redeemed_order_id)
                                    #{{ $claim->redeemed_order_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($stitches !== null && $stitches > 0)
                                    {{ number_format((int) $stitches) }}
                                    @if ($stResult === 'under')
                                        <br><span class="status success">Under — free</span>
                                    @elseif ($stResult === 'over')
                                        <br><span class="status warning">Over by {{ number_format((int) round($stitches - $threshold)) }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $claim->redeemed_at ? \Carbon\Carbon::parse($claim->redeemed_at)->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No claims found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $claims->links() }}
    </section>
@endsection
