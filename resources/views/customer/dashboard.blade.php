@extends('layouts.customer')

@section('title', 'Dashboard - '.$siteContext->displayLabel())
@section('hero_class', 'hero-compact dashboard-hero')
@section('hero_title', 'Dashboard')
@section('hero_text', 'Track your orders, quotes, billing, downloads, and account details in one streamlined workspace.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Dashboard</h3>
                <p>View your orders, quotes, billing, and account details in one place.</p>
            </div>
        </div>

        <div class="portal-stat-grid" style="margin-top:18px;">
            <a class="metric-link" href="/view-orders.php">
                <article class="portal-stat">
                    <span>My Orders</span>
                    <strong>{{ $metrics['orders'] }}</strong>
                </article>
            </a>
            <a class="metric-link" href="/view-quotes.php">
                <article class="portal-stat">
                    <span>My Quotes</span>
                    <strong>{{ $metrics['quotes'] }}</strong>
                </article>
            </a>
            <a class="metric-link" href="/view-billing.php">
                <article class="portal-stat">
                    <span>Payment Due</span>
                    <strong>${{ number_format($metrics['billing_total'], 2) }}</strong>
                </article>
            </a>
            <a class="metric-link" href="/view-archive-orders.php">
                <article class="portal-stat">
                    <span>Paid Orders</span>
                    <strong>{{ $metrics['paid'] }}</strong>
                </article>
            </a>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Quick Actions</h3>
                <p>Jump into the task you need most without hunting through the portal.</p>
            </div>
        </div>

        <div class="action-grid">
            <a class="action-card" href="/new-order.php">
                <span>Digitizing</span>
                <strong>Place New Order</strong>
                <p>Upload artwork and start a standard digitizing request.</p>
            </a>
            <a class="action-card" href="/quote.php">
                <span>Quote</span>
                <strong>Digitizing Quote</strong>
                <p>Ask for digitizing pricing first before placing a new order.</p>
            </a>
            <a class="action-card" href="/vector-order.php">
                <span>Vector</span>
                <strong>Place Vector Order</strong>
                <p>Start a vector-only job with the existing vector order flow.</p>
            </a>
            <a class="action-card" href="/vector-quote.php">
                <span>Vector Quote</span>
                <strong>Request Vector Quote</strong>
                <p>Ask for vector pricing first before placing a vector order.</p>
            </a>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Recent Activity</h3>
                <p>Pick up where you left off without scanning every list page.</p>
            </div>
        </div>

        <div class="workspace-grid">
            <div class="activity-card">
                <span class="activity-kicker">Latest Orders</span>
                <div class="activity-list" style="margin-top:12px;">
                    @if ($recentOrders->isEmpty())
                        <div class="empty-state">No active orders are open right now.</div>
                    @else
                        @foreach ($recentOrders as $order)
                            <div class="activity-item">
                                <div class="activity-meta">
                                    <strong><a class="inline-link" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=orders">{{ $order->design_name ?: 'Order #'.$order->order_id }}</a></strong>
                                    <span class="status {{ \App\Support\CustomerWorkflowStatus::tone($order) }}">{{ \App\Support\CustomerWorkflowStatus::label($order) }}</span>
                                </div>
                                <div class="file-actions">
                                    <a class="button secondary" href="/view-order-detail.php?order_id={{ $order->order_id }}&origin=orders">Open Order</a>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="activity-card">
                <span class="activity-kicker">Quotes & Billing</span>
                <div class="activity-list" style="margin-top:12px;">
                    @if ($recentQuotes->isEmpty())
                        <div class="activity-item">
                            <strong>No open quotes</strong>
                            <p>You can request pricing first whenever you need a review before ordering.</p>
                        </div>
                    @else
                        @foreach ($recentQuotes as $quote)
                            <div class="activity-item">
                                <div class="activity-meta">
                                    <strong><a class="inline-link" href="/view-quote-detail.php?order_id={{ $quote->order_id }}&origin=quotes">{{ $quote->design_name ?: 'Quote #'.$quote->order_id }}</a></strong>
                                    <span class="status {{ \App\Support\CustomerWorkflowStatus::tone($quote, true) }}">{{ \App\Support\CustomerWorkflowStatus::label($quote, true) }}</span>
                                </div>
                                <div class="file-actions">
                                    <a class="button secondary" href="/view-quote-detail.php?order_id={{ $quote->order_id }}&origin=quotes">Open Quote</a>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if ($recentBilling->isNotEmpty())
                        <div class="activity-item">
                            <div class="activity-meta">
                                <strong>${{ number_format($metrics['billing_total'], 2) }} outstanding</strong>
                                <span class="status warning">{{ $metrics['billing_count'] }} due</span>
                            </div>
                            <div class="file-actions">
                                <a class="button secondary" href="/view-billing.php">Open Billing</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Account Details</h3>
                <p>Important account information remains visible without changing any billing or approval rules.</p>
            </div>
        </div>

        @if (!empty($placement['warning']))
            <div class="alert {{ $placement['can_place'] ? 'alert-success' : 'alert-error' }}" style="margin-bottom:16px;">
                {{ $placement['warning'] }}
            </div>
        @endif

        <div class="info-grid">
            <div class="info-card">
                <span>Available Balance</span>
                <strong>${{ number_format($metrics['available_balance'], 2) }}</strong>
                <p style="margin:8px 0 0; color:#64748b; font-size:0.9rem;">Payments and usable credit currently available on the account.</p>
            </div>
            <div class="info-card">
                <span>Admin Deposit</span>
                <strong>${{ number_format($metrics['deposit_balance'], 2) }}</strong>
                <p style="margin:8px 0 0; color:#64748b; font-size:0.9rem;">Amount manually added by admin as an advance deposit on the account.</p>
            </div>
            <div class="info-card">
                <span>Credit Limit</span>
                <strong>${{ number_format($metrics['credit_limit'], 2) }}</strong>
            </div>
            <div class="info-card">
                <span>Single Order Limit</span>
                <strong>${{ number_format($metrics['single_order_limit'], 2) }}</strong>
            </div>
        </div>
    </section>
@endsection
