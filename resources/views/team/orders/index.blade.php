@extends('layouts.team')

@section('title', $pageTitle.' | 1Dollar Team Portal')
@section('page_heading', $pageTitle)
@section('page_subheading', $pageSummary)

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="stats">
                @foreach ($queueNavigation as $queue)
                    <a class="stat" href="{{ $queue['url'] }}" @if ($currentQueueKey === $queue['key']) style="border-color:rgba(30,106,87,0.36);background:rgba(223,241,234,0.72);" @endif>
                        <span class="muted">{{ $queue['label'] }}</span>
                        <strong>{{ $queue['count'] }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <h3 style="margin:0 0 6px;">{{ $pageTitle }}</h3>
                    <p class="muted" style="margin:0;">{{ $pageSummary }}</p>
                </div>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="badge">Download List</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Order ID</th>
                        @if ($teamUser->is_supervisor ?? false)
                            <th>Assigned To</th>
                        @endif
                        <th>TAT</th>
                        <th>Schedule</th>
                        <th>Time Left</th>
                        <th>Work Type</th>
                        @if ($showWorking)
                            <th>Accept</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @if ($orders->isEmpty())
                        <tr>
                            <td colspan="{{ ($showWorking ? 7 : 6) + (($teamUser->is_supervisor ?? false) ? 1 : 0) }}" class="muted">No records found.</td>
                        </tr>
                    @else
                    @foreach ($orders as $order)
                        @php
                            $scheduleTone = (string) ($order->turnaround_status_tone ?? '');
                            $scheduleBadgeStyle = match ($scheduleTone) {
                                'danger' => 'background:rgba(180,35,24,0.12);color:#b42318;border-color:rgba(180,35,24,0.18);',
                                'warning' => 'background:rgba(197,107,34,0.12);color:#9a5a16;border-color:rgba(197,107,34,0.18);',
                                default => 'background:rgba(34,139,94,0.12);color:#1f7a53;border-color:rgba(34,139,94,0.18);',
                            };
                            $remainingStyle = $scheduleTone === 'danger' ? 'color:#b42318;font-weight:700;' : '';
                        @endphp
                        <tr>
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ $detailUrl($order) }}">Open Detail</a>
                                    @if ($teamUser->is_supervisor ?? false)
                                        @php
                                            $page = $order->order_type === 'qquote' ? 'qquote' : (in_array($order->order_type, ['quote', 'digitzing', 'q-vector', 'qcolor'], true) ? 'quote' : (in_array($order->order_type, ['vector', 'color'], true) ? 'vector' : 'order'));
                                        @endphp
                                        <a class="badge" href="{{ url('/team/assign-order.php?design_id='.$order->order_id.'&page='.$page) }}">Assign</a>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $order->user_id }}-{{ $order->order_id }}</td>
                            @if ($teamUser->is_supervisor ?? false)
                                <td>{{ $order->assignee_name ?: '-' }}</td>
                            @endif
                            <td>{{ $order->turnaround_label ?: ($order->turn_around_time ?: '-') }}</td>
                            <td><span class="badge" style="{{ $scheduleBadgeStyle }}">{{ $order->turnaround_status_label ?: 'Schedule Unknown' }}</span></td>
                            <td style="{{ $remainingStyle }}">{{ $order->hours_left }}</td>
                            <td>{{ $order->work_type_label }}</td>
                            @if ($showWorking)
                                <td>
                                    @if ($allowStartWork)
                                        <form method="post" action="{{ url('/team/orders/'.$order->order_id.'/working') }}" style="display:flex;gap:8px;align-items:center;">
                                            @csrf
                                            <input type="hidden" name="queue" value="{{ $currentQueueKey }}">
                                            <button type="submit">Accept Job</button>
                                        </form>
                                    @else
                                        {{ $order->working ?: '-' }}
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            <div class="pagination-nav" style="margin-top:16px;">
                <div class="pagination-meta">
                    Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }}
                </div>
                {{ $orders->links() }}
            </div>
        </div>
    </section>
@endsection
