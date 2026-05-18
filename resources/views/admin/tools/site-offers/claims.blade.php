@extends('layouts.admin')

@section('title', 'Offer Claims — '.$offer->promotion_name)
@section('page_title', 'Offer Claims')
@section('page_subtitle', $offer->promotion_name.' · '.($offer->site?->brand_name ?: $offer->site?->name ?: 'All Sites'))

@section('content')
    <section class="content-card stack">
        <div class="section-head">
            <div>
                <h3>Claim Records</h3>
                <p>
                    Every account that signed up under this offer, along with their payment status and first-order outcome.
                    @if ($threshold > 0)
                        Stitch threshold for this offer: <strong>{{ number_format($threshold) }}</strong>.
                    @endif
                </p>
            </div>
            <a class="button secondary" href="{{ url('/v/site-offers.php') }}">← Back</a>
        </div>

        {{-- Filter --}}
        <form method="get" class="filter-grid">
            <label>
                Status
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending"  @selected(request('status') === 'pending')>Pending</option>
                    <option value="paid"     @selected(request('status') === 'paid')>Paid</option>
                    <option value="expired"  @selected(request('status') === 'expired')>Expired</option>
                </select>
            </label>
            <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <button type="submit" class="button secondary">Filter</button>
                <a class="button secondary" href="{{ url('/v/site-offers/'.$offer->id.'/claims') }}">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Signed Up</th>
                        <th>Claim Status</th>
                        <th>Paid At</th>
                        <th>Order</th>
                        <th>Stitches</th>
                        @if ($threshold > 0)
                            <th>vs {{ number_format($threshold) }} Threshold</th>
                        @endif
                        <th>Redeemed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($claims as $claim)
                        @php
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
                            <td>{{ $claim->created_at ? \Carbon\Carbon::parse($claim->created_at)->format('d M Y') : '—' }}</td>
                            <td>
                                <span class="status {{ $claim->status === 'paid' ? 'success' : ($claim->status === 'expired' ? 'warning' : '') }}">
                                    {{ $claim->status }}
                                </span>
                            </td>
                            <td>{{ $claim->paid_at ? \Carbon\Carbon::parse($claim->paid_at)->format('d M Y') : '—' }}</td>
                            <td>
                                @if ($claim->redeemedOrder)
                                    <a href="{{ url('/v/order-detail.php?order_id='.$claim->redeemed_order_id) }}">
                                        #{{ $claim->redeemed_order_id }}
                                    </a><br>
                                    <span class="table-subtext">{{ \Illuminate\Support\Str::limit($claim->redeemedOrder->design_name, 40) }}</span>
                                @elseif ($claim->redeemed_order_id)
                                    #{{ $claim->redeemed_order_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($stitches !== null && $stitches > 0)
                                    {{ number_format((int) $stitches) }}
                                @else
                                    —
                                @endif
                            </td>
                            @if ($threshold > 0)
                                <td>
                                    @if ($stResult === 'under')
                                        <span class="status success">Under — free</span>
                                    @elseif ($stResult === 'over')
                                        @php $excess = (int) round($stitches - $threshold); @endphp
                                        <span class="status warning">Over by {{ number_format($excess) }}</span>
                                    @else
                                        <span class="table-subtext">—</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $claim->redeemed_at ? \Carbon\Carbon::parse($claim->redeemed_at)->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $threshold > 0 ? 8 : 7 }}">No claims found for this offer.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $claims->links() }}
    </section>
@endsection
