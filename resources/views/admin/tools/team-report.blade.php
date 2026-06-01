@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'completion_date');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Team Report | Digitizing Zone Admin')
@section('page_heading', 'Team Report')
@section('page_subheading', 'Review completed work by team member and month.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/monthly-reports.php') }}" class="toolbar">
                <div class="field">
                    <label for="team">Select Team</label>
                    <select id="team" name="team">
                        <option value="">Not Selected Yet</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->user_id }}" @selected((string) request('team') === (string) $team->user_id)>{{ $team->user_name }}{{ $team->is_supervisor ? ' (Supervisor)' : ' (Team)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="month">Select Month</label>
                    <select id="month" name="month">
                        <option value="">Not Selected Yet</option>
                        @foreach ($months as $month)
                            @if ($month && $month !== '0000-00')
                                <option value="{{ $month }}" @selected(request('month') === $month)>{{ $month }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Search</button>
                </div>
            </form>
            @include('shared.admin-report-export', [
                'copy' => 'Download the current team report.',
                'label' => 'Download Report',
                'show' => $orders->count() > 0,
                'marginTop' => '14px',
                'marginBottom' => '0',
            ])
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Completed Orders</h3>
                    <p class="muted" style="margin:0;">{{ $summary['total_orders'] }} records match the current team/month filter.</p>
                </div>
                <span class="badge">team report</span>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Completed Designs</span><strong>{{ $summary['total_orders'] }}</strong></article>
                <article class="stat"><span class="muted">Supervisor Checked</span><strong>{{ $summary['supervisor_checked'] }}</strong></article>
                <article class="stat"><span class="muted">Total Amount</span><strong>{{ number_format((float) $summary['total_amount'], 2) }}</strong></article>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_id', 'sort' => $nextDirection('order_id')]) }}">Order ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_type', 'sort' => $nextDirection('order_type')]) }}">Design Type</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'design_name', 'sort' => $nextDirection('design_name')]) }}">Design Name</a></th>
                        <th>Assigned To</th>
                        <th>Supervisor Checked</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'stitches', 'sort' => $nextDirection('stitches')]) }}">No Of Stitches</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'total_amount', 'sort' => $nextDirection('total_amount')]) }}">Total Amount</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'completion_date', 'sort' => $nextDirection('completion_date')]) }}">Date Completion</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($orders)->isEmpty())
                        <tr><td colspan="8" class="muted">No team report rows found.</td></tr>
                    @else
                    @foreach ($orders as $order)
                        <tr>
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->work_type_label }}</td>
                            <td>{{ $order->design_name ?: '-' }}</td>
                            <td>{{ $order->assignee?->user_name ?: '-' }}</td>
                            <td>{{ $order->supervisor_checked_flag ? 'Yes' : 'No' }}</td>
                            <td>{{ $order->stitches ?: '-' }}</td>
                            <td>{{ is_numeric($order->total_amount) ? number_format((float) $order->total_amount, 2) : ($order->total_amount ?: '0.00') }}</td>
                            <td>{{ $order->completion_date ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
