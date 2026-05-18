@extends('layouts.customer')

@section('title', 'Advance Orders - '.$siteContext->displayLabel())
@section('hero_title', 'Advance Orders')
@section('hero_text', 'Completed prepaid work stays visible in a separate list so customers can quickly return to their advance-paid jobs and files.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Completed Advance Orders</h3>
                <p>These are paid orders that were flagged as advance-paid and already completed.</p>
            </div>
        </div>

        @if ($orders->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>View</th>
                        <th>Order ID</th>
                        <th>Design Name</th>
                        <th>Completion Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td><a class="button secondary" href="/view-orderpaid-details.php?order_id={{ $order->order_id }}">View</a></td>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->design_name ?: 'Order #'.$order->order_id }}</td>
                            <td>{{ $order->completion_date ?: '-' }}</td>
                            <td><span class="status success">{{ $order->status ?: 'done' }}</span></td>
                            <td><span class="status success">Paid</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $orders->links() }}
            </div>
        @else
            <div class="empty-state">No completed advance-paid orders are currently available for this site account.</div>
        @endif
    </section>
@endsection
