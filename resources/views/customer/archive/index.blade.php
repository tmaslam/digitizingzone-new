@extends('layouts.customer')

@section('title', 'Paid Orders - '.$siteContext->displayLabel())
@section('hero_title', 'Paid Orders')
@section('hero_text', 'Review completed paid orders and reopen the details whenever you need them.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Paid Orders</h3>
                <p>Orders appear here after payment is recorded or a no-charge order is approved.</p>
            </div>
            <a class="button ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Download Paid Orders</a>
        </div>

        <form method="get" action="/view-archive-orders.php" class="filter-bar">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Order ID or design name"
                style="flex:1; min-width:180px;"
            >
            <button type="submit">Search</button>
            @if ($search !== '')
                <a class="button secondary" href="/view-archive-orders.php">Clear</a>
            @endif
        </form>

        @if ($orders->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    @php
                        $sortLink = fn($col) => '/view-archive-orders.php?'.http_build_query(array_merge(request()->query(), [
                            'sort' => $col,
                            'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                            'page' => 1,
                        ]));
                        $sortIcon = fn($col) => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
                    @endphp
                    <tr>
                        <th><a href="{{ $sortLink('order_id') }}">Order ID{!! $sortIcon('order_id') !!}</a></th>
                        <th><a href="{{ $sortLink('design_name') }}">Design Name{!! $sortIcon('design_name') !!}</a></th>
                        <th><a href="{{ $sortLink('completion_date') }}">Completion Date{!! $sortIcon('completion_date') !!}</a></th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->design_name }}</td>
                            <td>{{ $order->completion_date ?: '-' }}</td>
                            <td><a class="button secondary" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=archive">View Detail</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $orders->links() }}
            </div>
        @else
            <div class="empty-state">
                @if ($search !== '' || ! $isDefaultRange)
                    No paid orders found matching your search.
                @else
                    No paid orders are currently available.
                @endif
            </div>
        @endif
    </section>
@endsection
