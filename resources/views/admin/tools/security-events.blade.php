@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'created_at');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Security Events | Digitizing Zone Admin')
@section('page_heading', 'Security Events')
@section('page_subheading', 'Structured audit events for unauthorized access, failed bot checks, and suspicious file activity.')

@section('content')
    @if ($securityWatch['available'])
        <section class="stats">
            <article class="stat">
                <span class="muted">Action Required</span>
                <strong>{{ $securityWatch['actionable_events'] }}</strong>
                <div class="muted" style="margin-top:8px;">Warnings or higher in the last {{ $securityWatch['window_hours'] }} hours.</div>
            </article>
            <article class="stat">
                <span class="muted">Critical Events</span>
                <strong>{{ $securityWatch['critical_events'] }}</strong>
                <div class="muted" style="margin-top:8px;">Critical, alert, or emergency severity events.</div>
            </article>
            <article class="stat">
                <span class="muted">Failed Logins</span>
                <strong>{{ $securityWatch['failed_logins'] }}</strong>
                <div class="muted" style="margin-top:8px;">Failed or locked authentication attempts.</div>
            </article>
            <article class="stat">
                <span class="muted">Upload Rejections</span>
                <strong>{{ $securityWatch['upload_rejections'] }}</strong>
                <div class="muted" style="margin-top:8px;">Rejected file uploads across customer, admin, and team flows.</div>
            </article>
        </section>

        <section class="card">
            <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                <div>
                    <h3 style="margin-top:0;">Recent Alerts</h3>
                    <div style="display:grid;gap:10px;">
                        @if (collect($securityWatch['recent_events'])->isEmpty())
                            <div class="muted">No recent warning-level or higher events were found.</div>
                        @else
                        @foreach ($securityWatch['recent_events'] as $event)
                            <div style="padding:12px 14px;border-radius:16px;background:rgba(15,95,102,0.06);border:1px solid rgba(24,34,45,0.08);">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                    <strong>{{ $event->event_type }}</strong>
                                    <span class="badge">{{ strtoupper($event->severity) }}</span>
                                </div>
                                <div class="muted" style="margin-top:8px;">{{ $event->message }}</div>
                                <div class="muted" style="margin-top:8px;font-size:0.84rem;">{{ $event->created_at }} @if ($event->ip_address) · {{ $event->ip_address }} @endif</div>
                            </div>
                        @endforeach
                        @endif
                    </div>
                </div>

                <div>
                    <h3 style="margin-top:0;">Top Source IPs</h3>
                    <div style="display:grid;gap:10px;">
                        @if (collect($securityWatch['top_ips'])->isEmpty())
                            <div class="muted">No source IP concentration was detected in the current window.</div>
                        @else
                        @foreach ($securityWatch['top_ips'] as $ip)
                            <div style="padding:12px 14px;border-radius:16px;background:rgba(197,107,34,0.08);border:1px solid rgba(24,34,45,0.08);">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                                    <strong>{{ $ip->ip_address }}</strong>
                                    <span class="badge">{{ $ip->total_events }} events</span>
                                </div>
                                <div class="muted" style="margin-top:8px;">{{ $ip->actionable_events }} actionable · {{ $ip->critical_events }} critical</div>
                            </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/security-events.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtPortal">Portal</label>
                    <input id="txtPortal" type="text" name="txtPortal" value="{{ request('txtPortal') }}" placeholder="admin, team, customer">
                </div>
                <div class="field">
                    <label for="txtSeverity">Severity</label>
                    <input id="txtSeverity" type="text" name="txtSeverity" value="{{ request('txtSeverity') }}" placeholder="warning">
                </div>
                <div class="field">
                    <label for="txtEventType">Event Type</label>
                    <input id="txtEventType" type="text" name="txtEventType" value="{{ request('txtEventType') }}" placeholder="auth.login_failed">
                </div>
                <div class="field">
                    <label for="txtActor">Actor</label>
                    <input id="txtActor" type="text" name="txtActor" value="{{ request('txtActor') }}" placeholder="login or display name">
                </div>
                <div class="field">
                    <label for="txtUserIP">IP Address</label>
                    <input id="txtUserIP" type="text" name="txtUserIP" value="{{ request('txtUserIP') }}">
                </div>
                <div class="field">
                    <label for="txtPath">Path</label>
                    <input id="txtPath" type="text" name="txtPath" value="{{ request('txtPath') }}" placeholder="/dashboard.php">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            @include('shared.admin-report-export', [
                'copy' => 'Download the current security-events report.',
                'label' => 'Download Report',
                'show' => $events->count() > 0,
                'marginTop' => '0',
                'marginBottom' => '18px',
            ])
            <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'created_at', 'sort' => $nextDirection('created_at')]) }}">Time</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'severity', 'sort' => $nextDirection('severity')]) }}">Severity</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'portal', 'sort' => $nextDirection('portal')]) }}">Portal</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'event_type', 'sort' => $nextDirection('event_type')]) }}">Event</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'actor_login', 'sort' => $nextDirection('actor_login')]) }}">Actor</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'ip_address', 'sort' => $nextDirection('ip_address')]) }}">IP</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'request_path', 'sort' => $nextDirection('request_path')]) }}">Path</a></th>
                        <th>Message</th>
                        <th>Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($events)->isEmpty())
                        <tr><td colspan="9" class="muted">No security events found.</td></tr>
                    @else
                    @foreach ($events as $event)
                        <tr>
                            <td class="cell-nowrap">{{ $event->created_at }}</td>
                            <td class="cell-nowrap"><span class="badge">{{ strtoupper($event->severity) }}</span></td>
                            <td class="cell-nowrap">{{ $event->portal }}</td>
                            <td class="cell-wrap-md">{{ $event->event_type }}</td>
                            <td>
                                {{ $event->actor_login ?: '-' }}
                                @if ($event->actor_user_id)
                                    <div class="muted">ID {{ $event->actor_user_id }}</div>
                                @endif
                            </td>
                            <td class="cell-nowrap">{{ $event->ip_address }}</td>
                            <td class="cell-wrap-md">{{ $event->request_method }} {{ $event->request_path }}</td>
                            <td class="cell-wrap-lg">{{ $event->message }}</td>
                            <td>
                                @if (! empty($event->details_json))
                                    <details>
                                        <summary>View</summary>
                                        <pre style="white-space:pre-wrap; word-break:break-word; margin:10px 0 0;">{{ json_encode($event->details_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div style="margin-top:18px;">{{ $events->links() }}</div>
            @endif
        </div>
    </section>
@endsection
