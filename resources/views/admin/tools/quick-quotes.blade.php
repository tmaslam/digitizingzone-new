@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'order_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', $pageTitle.' | Digitizing Zone Admin')
@section('page_heading', $pageTitle)
@section('page_subheading', 'Review and manage quick quote requests.')

@section('content')
    @unless ($hasQuickQuotes)
        <div class="alert">Quick quote records are not available in this database.</div>
    @else
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <section class="card">
            <div class="card-body">
                <form method="get" action="{{ url('/v/ordersquick.php') }}" class="toolbar">
                    <input type="hidden" name="page" value="{{ $pageTitle }}">
                    <div class="field"><label>Order ID</label><input type="text" name="txt_orderid" value="{{ request('txt_orderid') }}"></div>
                    <div class="field"><label>Customer</label><input type="text" name="txt_custname" value="{{ request('txt_custname') }}"></div>
                    <div class="field" style="min-width:auto;"><label>&nbsp;</label><button type="submit">Filter</button></div>
                </form>
                @if ($quickQuotes->count() > 0)
                    <div style="margin-top:14px;display:flex;justify-content:flex-start;">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:999px;background:#0f5f66;color:#fff;font-weight:700;text-decoration:none;">Download List</a>
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-body">
                <form method="post" action="{{ url('/v/ordersquick-delete') }}">
                    @csrf
                    <input type="hidden" name="page" value="{{ $pageTitle }}">
                    <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                        <button type="button" class="badge" id="select-all-quick-quotes">Select All</button>
                        <button type="button" class="badge badge-muted" id="clear-all-quick-quotes">Clear All</button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Action</th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_id', 'sort' => $nextDirection('order_id')]) }}">Order ID</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'customer_name', 'sort' => $nextDirection('customer_name')]) }}">Customer</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'turn_around_time', 'sort' => $nextDirection('turn_around_time')]) }}">Turnaround</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'submit_date', 'sort' => $nextDirection('submit_date')]) }}">Submit Date</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'completion_date', 'sort' => $nextDirection('completion_date')]) }}">Completion Date</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'status', 'sort' => $nextDirection('status')]) }}">Status</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'assign_name', 'sort' => $nextDirection('assign_name')]) }}">Assigned To</a></th>
                                <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'order_type', 'sort' => $nextDirection('order_type')]) }}">Type</a></th>
                                <th>Delete</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if (collect($quickQuotes)->isEmpty())
                                <tr><td colspan="10" class="muted">No quick quote rows found.</td></tr>
                            @else
                            @foreach ($quickQuotes as $quote)
                                <tr>
                                    <td><a class="badge" href="{{ url('/v/view-quick-order-detail.php?oid='.$quote->order_id.'&page='.(($quote->order_type ?: 'qquote'))) }}">Open Detail</a></td>
                                    <td>{{ $quote->order_id }}</td>
                                    <td>{{ $quote->customer_name }}</td>
                                    <td>{{ $quote->turn_around_time }}</td>
                                    <td>{{ \App\Support\LegacyDate::display($quote->submit_date) }}</td>
                                    <td>{{ \App\Support\LegacyDate::display($quote->completion_date) }}</td>
                                    <td>{{ $quote->status }}</td>
                                    <td>{{ $quote->assign_name }}</td>
                                    <td>{{ $quote->order_type }}</td>
                                    <td><input type="checkbox" name="order_ids[]" value="{{ $quote->order_id }}" data-quick-quote-checkbox></td>
                                </tr>
                            @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" onclick="return confirm('Delete selected quick quote records?');">Delete Selected</button>
                        <a class="badge" href="{{ url('/v/ordersquick.php?page=Quick%20Quotes%20List') }}">Open Quotes</a>
                        <a class="badge" href="{{ url('/v/ordersquick.php?page=Completed%20Quick%20Quotes') }}">Completed Quotes</a>
                    </div>
                </form>

                @if ($paginator && $paginator->hasPages())
                    <div style="margin-top:18px;">{{ $paginator->links() }}</div>
                @endif
            </div>
        </section>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = Array.from(document.querySelectorAll('[data-quick-quote-checkbox]'));
            const selectAll = document.getElementById('select-all-quick-quotes');
            const clearAll = document.getElementById('clear-all-quick-quotes');

            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = true;
                    });
                });
            }

            if (clearAll) {
                clearAll.addEventListener('click', function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = false;
                    });
                });
            }
        });
        </script>
    @endunless
@endsection
