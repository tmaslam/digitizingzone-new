@extends('layouts.admin')

@section('title', 'Dashboard | Digitizing Zone Admin')
@section('page_heading', 'Dashboard')
@section('page_subheading', 'A cleaner control center for workload, approvals, payments, and account health.')

@section('content')
    @php
        $orderQueues = \App\Support\AdminOrderQueues::navigation($navCounts, 'orders');
        $quoteQueues = \App\Support\AdminOrderQueues::navigation($navCounts, 'quotes');
        $workflowCards = [
            ['label' => 'New Orders', 'count' => $navCounts['new_orders'], 'url' => \App\Support\AdminOrderQueues::url('new-orders')],
            ['label' => 'Designer Orders', 'count' => $navCounts['designer_orders'], 'url' => \App\Support\AdminOrderQueues::url('designer-orders')],
            ['label' => 'Designer Completed', 'count' => $navCounts['designer_completed_orders'], 'url' => \App\Support\AdminOrderQueues::url('designer-completed')],
            ['label' => 'Approval Waiting', 'count' => $navCounts['approval_waiting_orders'], 'url' => \App\Support\AdminOrderQueues::url('approval-waiting')],
            ['label' => 'Approved / Unpaid', 'count' => $navCounts['approved_orders'], 'url' => \App\Support\AdminOrderQueues::url('approved-orders')],
            ['label' => 'New Quotes', 'count' => $navCounts['new_quotes'], 'url' => \App\Support\AdminOrderQueues::url('new-quotes')],
            ['label' => 'Assigned Quotes', 'count' => $navCounts['assigned_quotes'], 'url' => \App\Support\AdminOrderQueues::url('assigned-quotes')],
            ['label' => 'Designer Completed Quotes', 'count' => $navCounts['designer_completed_quotes'], 'url' => \App\Support\AdminOrderQueues::url('designer-completed-quotes')],
            ['label' => 'Payment Due Report', 'count' => $navCounts['due_payments'], 'url' => url('/v/payment-due-report.php')],
        ];

        $quickActions = [
            ['label' => 'Open Due Payments', 'url' => url('/v/payment-due-report.php')],
            ['label' => 'Customer Payment Inventory', 'url' => url('/v/customer-payment-inventory.php')],
            ['label' => 'Manage Team Accounts', 'url' => url('/v/show-all-teams.php')],
            ['label' => 'Create Account', 'url' => url('/v/create-teams.php')],
            ['label' => 'Notify Customers', 'url' => url('/v/notify-customers.php')],
        ];
    @endphp

    <section class="stats">
        @foreach ($workflowCards as $card)
            <a class="stat-link" href="{{ $card['url'] }}">
                <article class="stat">
                    <span class="muted">{{ $card['label'] }}</span>
                    <strong>{{ $card['count'] }}</strong>
                </article>
            </a>
        @endforeach
    </section>

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Workflow Focus</h3>
                    <p class="section-copy">The busiest queues and the next actions admins usually need first.</p>
                </div>
            </div>

            <div class="stats workflow-focus-grid">
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('designer-completed') }}">
                    <article class="stat">
                        <span class="muted">Review Ready</span>
                        <strong>{{ $workflowFocus['review_ready'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Designer-completed orders and quotes waiting on admin review.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Designer Completed</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('approval-waiting') }}">
                    <article class="stat">
                        <span class="muted">Approval Waiting</span>
                        <strong>{{ $workflowFocus['approval_waiting'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Work sent out and waiting on customer response.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Customer Approval Waiting</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('new-orders') }}">
                    <article class="stat">
                        <span class="muted">New Orders</span>
                        <strong>{{ $navCounts['new_orders'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Unassigned new orders that need admin review and routing.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: New Orders</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('new-quotes') }}">
                    <article class="stat">
                        <span class="muted">New Quotes</span>
                        <strong>{{ $navCounts['new_quotes'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Unassigned new quotes that need admin review and routing.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: New Quotes</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('designer-orders') }}">
                    <article class="stat">
                        <span class="muted">Assigned Orders</span>
                        <strong>{{ $navCounts['designer_orders'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Orders currently sitting with production.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Designer Orders</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('assigned-quotes') }}">
                    <article class="stat">
                        <span class="muted">Assigned Quotes</span>
                        <strong>{{ $navCounts['assigned_quotes'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Codes and quotes currently sitting with production.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Assigned Quotes</div>
                    </article>
                </a>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Financial Snapshot</h3>
                    <p class="section-copy">Payment pressure and money movement at a glance.</p>
                </div>
                <a href="{{ url('/v/payment-due-report.php') }}" class="badge">Open Payment Reports</a>
            </div>

            <div class="stats">
                <a class="stat-link" href="{{ url('/v/payment-due-report.php') }}">
                    <article class="stat">
                        <span class="muted">Due Amount</span>
                        <strong>{{ number_format((float) $financialSnapshot['due_amount'], 2) }}</strong>
                        <div class="muted" style="margin-top:8px;">Across {{ $financialSnapshot['due_invoices'] }} unpaid approved invoice rows.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Payment Due Report</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/payment-recieved-report.php') }}">
                    <article class="stat">
                        <span class="muted">Received Amount</span>
                        <strong>{{ number_format((float) $financialSnapshot['received_amount'], 2) }}</strong>
                        <div class="muted" style="margin-top:8px;">Across {{ $financialSnapshot['received_invoices'] }} paid invoice rows.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Payment Received Report</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/customer-payment-inventory.php') }}">
                    <article class="stat">
                        <span class="muted">Available Customer Credit</span>
                        <strong>{{ $hasCreditLedger ? number_format((float) $financialSnapshot['customer_balance'], 2) : 'N/A' }}</strong>
                        <div class="muted" style="margin-top:8px;">
                            @if ($hasCreditLedger)
                                Across {{ $financialSnapshot['customers_with_credit'] }} active customer{{ $financialSnapshot['customers_with_credit'] === 1 ? '' : 's' }} with credit ready to apply to future invoices.
                            @else
                                Customer credit tracking is not available in this database.
                            @endif
                        </div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Customer Credit Inventory</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/payment-due-report.php') }}">
                    <article class="stat">
                        <span class="muted">Due Payment Queue</span>
                        <strong>{{ $navCounts['due_payments'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Approved billing entries still waiting to be settled.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Payment Due Report</div>
                    </article>
                </a>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Operations Snapshot</h3>
                    <p class="section-copy">Customer, team, and queue health in one place.</p>
                </div>
                <a href="{{ url('/v/show-all-teams.php') }}" class="badge">Open Account Management</a>
            </div>

            <div class="stats">
                <a class="stat-link" href="{{ url('/v/customer_list.php') }}">
                    <article class="stat">
                        <span class="muted">Active Customers</span>
                        <strong>{{ $operationsSnapshot['active_customers'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Current active customer accounts.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Customer List</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/block-customer_list.php') }}">
                    <article class="stat">
                        <span class="muted">Inactive Customers</span>
                        <strong>{{ $operationsSnapshot['blocked_customers'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Previously active customer accounts that are currently inactive or blocked.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Inactive Customers</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ url('/v/show-all-teams.php') }}">
                    <article class="stat">
                        <span class="muted">Team / Supervisors</span>
                        <strong>{{ $operationsSnapshot['team_accounts'] }} / {{ $operationsSnapshot['supervisors'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Active production accounts and supervisor accounts.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: Show All Team Accounts</div>
                    </article>
                </a>
                <a class="stat-link" href="{{ \App\Support\AdminOrderQueues::url('all-orders') }}">
                    <article class="stat">
                        <span class="muted">All Open Work</span>
                        <strong>{{ $operationsSnapshot['all_open_work'] }}</strong>
                        <div class="muted" style="margin-top:8px;">Active items still in the working pipeline.</div>
                        <div class="muted" style="margin-top:10px;font-weight:600;">View: All Orders</div>
                    </article>
                </a>
            </div>
        </div>
    </section>

    @if ($securityWatch['available'])
        <section class="card">
            <div class="card-body">
                <div class="section-head">
                    <div>
                        <h3>Security Watch</h3>
                        <p class="section-copy">A rolling {{ $securityWatch['window_hours'] }} hour view of suspicious activity, denied access, and risky upload attempts.</p>
                    </div>
                    <a href="{{ url('/v/security-events.php') }}" class="badge">Open Security Events</a>
                </div>

                <div class="stats">
                    <a class="stat-link" href="{{ url('/v/security-events.php?txtSeverity=warning') }}">
                        <article class="stat">
                            <span class="muted">Action Required</span>
                            <strong>{{ $securityWatch['actionable_events'] }}</strong>
                            <div class="muted" style="margin-top:8px;">Warnings or higher that deserve admin review.</div>
                        </article>
                    </a>
                    <a class="stat-link" href="{{ url('/v/security-events.php?txtEventType=auth.login') }}">
                        <article class="stat">
                            <span class="muted">Failed Logins</span>
                            <strong>{{ $securityWatch['failed_logins'] }}</strong>
                            <div class="muted" style="margin-top:8px;">Failed, blocked, rate-limited, or locked login attempts.</div>
                        </article>
                    </a>
                    <a class="stat-link" href="{{ url('/v/security-events.php?txtEventType=files.upload_rejected') }}">
                        <article class="stat">
                            <span class="muted">Upload Rejections</span>
                            <strong>{{ $securityWatch['upload_rejections'] }}</strong>
                            <div class="muted" style="margin-top:8px;">Rejected file uploads that may indicate risky or invalid input.</div>
                        </article>
                    </a>
                    <a class="stat-link" href="{{ url('/v/security-events.php?txtEventType=bot.turnstile_failed') }}">
                        <article class="stat">
                            <span class="muted">Bot Checks Failed</span>
                            <strong>{{ $securityWatch['turnstile_failures'] }}</strong>
                            <div class="muted" style="margin-top:8px;">Turnstile failures that may indicate scripted probing.</div>
                        </article>
                    </a>
                </div>

                <div style="display:grid;grid-template-columns:1.1fr 1fr;gap:18px;margin-top:18px;">
                    <article class="stat" style="min-height:100%;">
                        <span class="muted">Recent Alerts</span>
                        <div style="margin-top:12px;display:grid;gap:10px;">
                            @if (collect($securityWatch['recent_events'])->isEmpty())
                                <div class="muted">No warning-level or higher events were recorded in the last {{ $securityWatch['window_hours'] }} hours.</div>
                            @else
                            @foreach ($securityWatch['recent_events'] as $event)
                                <div style="padding:12px 14px;border-radius:16px;background:rgba(15,95,102,0.06);border:1px solid rgba(24,34,45,0.08);">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                        <strong style="font-size:0.95rem;">{{ $event->event_type }}</strong>
                                        <span class="badge">{{ strtoupper($event->severity) }}</span>
                                    </div>
                                    <div class="muted" style="margin-top:8px;">{{ $event->message }}</div>
                                    <div class="muted" style="margin-top:8px;font-size:0.84rem;">{{ $event->created_at }} @if ($event->ip_address) · {{ $event->ip_address }} @endif</div>
                                </div>
                            @endforeach
                            @endif
                        </div>
                    </article>

                    <article class="stat" style="min-height:100%;">
                        <span class="muted">Top Source IPs</span>
                        <div style="margin-top:12px;display:grid;gap:10px;">
                            @if (collect($securityWatch['top_ips'])->isEmpty())
                                <div class="muted">No source IPs have generated security events in the last {{ $securityWatch['window_hours'] }} hours.</div>
                            @else
                            @foreach ($securityWatch['top_ips'] as $ip)
                                <div style="padding:12px 14px;border-radius:16px;background:rgba(197,107,34,0.08);border:1px solid rgba(24,34,45,0.08);">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                                        <strong style="font-size:0.95rem;">{{ $ip->ip_address }}</strong>
                                        <span class="badge">{{ $ip->total_events }} events</span>
                                    </div>
                                    <div class="muted" style="margin-top:8px;">{{ $ip->actionable_events }} actionable · {{ $ip->critical_events }} critical</div>
                                </div>
                            @endforeach
                            @endif
                        </div>
                    </article>
                </div>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>Queue Shortcuts</h3>
                    <p class="section-copy">Jump straight into the live order and quote queues without hunting through the sidebar.</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                @foreach (array_merge($orderQueues, $quoteQueues) as $queueItem)
                    <a class="badge" style="justify-content:space-between;padding:14px 16px;border-radius:18px;" href="{{ $queueItem['url'] }}">
                        <span>{{ $queueItem['label'] === 'Approved Orders' ? 'Approved / Unpaid' : ($queueItem['label'] === 'Designer Completed Quotes' ? 'Designer Completed Quotes' : $queueItem['label']) }}</span>
                        <span>{{ $queueItem['count'] }}</span>
                    </a>
                @endforeach
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:14px;">
                @foreach ($quickActions as $action)
                    <a class="badge" style="justify-content:flex-start;padding:14px 16px;border-radius:18px;" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
