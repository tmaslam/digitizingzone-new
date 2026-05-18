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

    <!-- Upgrade Alert -->
    <div id="upgrade-alert" data-orders="{{ $metrics['orders'] }}" data-quotes="{{ $metrics['quotes'] }}" data-billing="{{ $metrics['billing_total'] }}" style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: 4px solid #f59e0b; color: #334155; padding: 18px 24px; border-radius: 14px; margin-top: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: flex-start; gap: 14px;">
                <span style="font-size: 24px; line-height: 1;">💡</span>
                <div>
                    <strong style="font-size: 1.05rem; display: block; margin-bottom: 4px; color: #0f172a;">Enjoy better features, faster service, and more discounted prices.</strong>
                    <span style="color: #64748b; font-size: 0.95rem;">Additionally, all of your existing orders will be migrated to your upgraded account.</span>
                </div>
            </div>
            <button type="button" id="btn-upgrade" class="button" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff; font-weight: 600; white-space: nowrap; padding: 10px 22px; border-radius: 10px; border: none; cursor: pointer;">Upgrade your account</button>
        </div>
    </div>

    <!-- Custom Modals -->
    <div id="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center;">
        
        <!-- Error Modal -->
        <div id="modal-error" style="display:none; background:#fff; border-radius:16px; padding:32px 28px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.25); text-align:center; animation:modalIn 0.3s ease;">
            <div style="width:56px; height:56px; background:#fee2e2; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h3 style="margin:0 0 10px; font-size:1.25rem; color:#111;">Account Upgrade Unavailable</h3>
            <p style="margin:0 0 24px; color:#64748b; line-height:1.6; font-size:0.95rem;">To switch your account, please ensure all outstanding billing is cleared first.</p>
            <button type="button" onclick="closeModal('modal-error')" style="background:#dc2626; color:#fff; border:none; padding:10px 28px; border-radius:10px; font-weight:600; cursor:pointer; font-size:0.95rem;">Got it</button>
        </div>

        <!-- Confirm Modal -->
        <div id="modal-confirm" style="display:none; background:#fff; border-radius:16px; padding:32px 28px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.25); text-align:center; animation:modalIn 0.3s ease;">
            <div id="confirm-content">
                <div style="width:56px; height:56px; background:#dbeafe; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <h3 style="margin:0 0 10px; font-size:1.25rem; color:#111;">Confirm Account Upgrade</h3>
                <p style="margin:0 0 24px; color:#64748b; line-height:1.6; font-size:0.95rem;">This action is irreversible. Your account will be upgraded, you will be logged out, and your legacy account will be blocked.</p>
                <div id="confirm-buttons" style="display:flex; gap:12px; justify-content:center;">
                    <button type="button" onclick="closeModal('modal-confirm')" style="background:#f3f4f6; color:#374151; border:none; padding:10px 24px; border-radius:10px; font-weight:600; cursor:pointer; font-size:0.95rem;">Cancel</button>
                    <button type="button" id="btn-confirm-upgrade" style="background:#2563eb; color:#fff; border:none; padding:10px 24px; border-radius:10px; font-weight:600; cursor:pointer; font-size:0.95rem;">Confirm Upgrade</button>
                </div>
            </div>
            <div id="confirm-loader" style="display:none; padding:20px 0;">
                <div style="width:40px; height:40px; border:3px solid #dbeafe; border-top-color:#2563eb; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 16px;"></div>
                <p style="color:#64748b; font-size:0.95rem; margin:0;">Processing your upgrade...</p>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalIn {
            from { opacity:0; transform:scale(0.92) translateY(10px); }
            to   { opacity:1; transform:scale(1) translateY(0); }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        function openModal(id) {
            document.getElementById('modal-overlay').style.display = 'flex';
            document.getElementById(id).style.display = 'block';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.getElementById('modal-overlay').style.display = 'none';
            // Reset confirm modal state
            document.getElementById('confirm-buttons').style.display = 'flex';
            document.getElementById('confirm-loader').style.display = 'none';
        }
        document.getElementById('modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal('modal-error');
                closeModal('modal-confirm');
            }
        });

        document.getElementById('btn-upgrade').addEventListener('click', function() {
            var alertBox = document.getElementById('upgrade-alert');
            var orders = parseInt(alertBox.dataset.orders) || 0;
            var quotes = parseInt(alertBox.dataset.quotes) || 0;
            var billing = parseFloat(alertBox.dataset.billing) || 0;

            if (orders === 0 && quotes === 0 && billing === 0) {
                openModal('modal-confirm');
            } else {
                openModal('modal-error');
            }
        });

        document.getElementById('btn-confirm-upgrade').addEventListener('click', function() {
            document.getElementById('confirm-buttons').style.display = 'none';
            document.getElementById('confirm-loader').style.display = 'block';

            var startTime = Date.now();

            fetch('/process-account-upgrade', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                var elapsed = Date.now() - startTime;
                var delay = Math.max(500 - elapsed, 0);
                setTimeout(function() {
                    window.location.href = data.redirect || 'https://1dollardigitizing.com/dashboard.php';
                }, delay);
            })
            .catch(function() {
                setTimeout(function() {
                    window.location.href = 'https://1dollardigitizing.com/dashboard.php';
                }, 500);
            });
        });
    </script>
@endsection
