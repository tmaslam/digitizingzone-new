@extends('layouts.customer')

@section('title', 'My Orders - '.$siteContext->displayLabel())
@section('hero_title', 'My Orders')
@section('hero_text', 'Track active orders, open design details, and follow each job through completion.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Active Orders</h3>
                <p>Keep track of your current jobs here while completed paid work moves to the archive.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="button ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Download Order List</a>
                <a class="button secondary" href="/new-order.php">Start Digitizing Order</a>
                <a class="button secondary" href="/vector-order.php">Start Vector Order</a>
            </div>
        </div>

        <div class="summary-grid" style="margin-bottom:16px;">
            <div class="action-card">
                <span>Total Active</span>
                <strong>{{ $orderSummary['total'] }}</strong>
                <p>All open orders that are still moving through the workflow.</p>
            </div>
            <div class="action-card">
                <span>Action Needed</span>
                <strong>{{ $orderSummary['action_needed'] }}</strong>
                <p>Orders waiting for your approval or already back in revision.</p>
            </div>
            <div class="action-card">
                <span>In Production</span>
                <strong>{{ $orderSummary['in_production'] }}</strong>
                <p>Orders currently being worked on by production.</p>
            </div>
        </div>

        @if ($orders->count())
            <div class="table-wrap responsive-stack" id="orders-table">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Design Name</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td data-label="Order ID"><a class="inline-link" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=orders">{{ $order->order_id }}</a></td>
                            <td data-label="Design Name">
                                <strong><a class="inline-link" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=orders">{{ $order->design_name }}</a></strong>
                                <span class="table-note">{{ \App\Support\CustomerWorkflowStatus::actionHint($order) }}</span>
                            </td>
                            <td data-label="Submitted">{{ $order->submit_date ?: '-' }}</td>
                            <td data-label="Status">
                                <span class="status {{ \App\Support\CustomerWorkflowStatus::tone($order) }}">
                                    {{ \App\Support\CustomerWorkflowStatus::label($order) }}
                                </span>
                            </td>
                            <td class="action-cell" data-label="Action">
                                <div class="action-group">
                                    <a class="button secondary" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=orders">View Detail</a>
                                    @if ($order->can_customer_cancel_flag ?? false)
                                        <form method="post" action="/orders/{{ $order->order_id }}/cancel" onsubmit="return confirm('Cancel this order?');">
                                            @csrf
                                            <button type="submit" class="button danger">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $orders->links() }}
            </div>
        @else
            <div class="empty-state">No active orders were found. Start a new digitizing or vector order whenever you are ready.</div>
        @endif
    </section>
@endsection
