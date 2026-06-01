@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'user_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Customers | Digitizing Zone Admin')
@section('page_heading', 'Customers')
@section('page_subheading', 'Manage active customer accounts and account details.')

@section('content')
    <style>
        .customer-actions-cell {
            min-width: 360px;
        }

        .customer-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .customer-actions form {
            margin: 0;
        }

        .customer-actions .badge,
        .customer-actions button {
            white-space: nowrap;
        }

        .customer-actions .badge {
            padding: 8px 10px;
            font-size: 0.78rem;
        }

        .customer-actions button {
            padding: 8px 12px;
            font-size: 0.78rem;
            line-height: 1.1;
            border-radius: 999px;
        }

        .customer-actions .simulate-login-button {
            background: linear-gradient(135deg, #1b8d5a, #146845);
        }

        .customer-actions .block-button {
            background: linear-gradient(135deg, #c56b22, #8c4f18);
        }
    </style>

    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/customer_list.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtUserID">User ID</label>
                    <input id="txtUserID" type="text" name="txtUserID" value="{{ request('txtUserID') }}">
                </div>
                <div class="field">
                    <label for="txtUserName">Username</label>
                    <input id="txtUserName" type="text" name="txtUserName" value="{{ request('txtUserName') }}">
                </div>
                <div class="field">
                    <label for="txtEmail">Email</label>
                    <input id="txtEmail" type="text" name="txtEmail" value="{{ request('txtEmail') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Filter</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Customer Directory</h3>
                    <p class="muted" style="margin:0;">Showing {{ $customers->total() }} active customer accounts.</p>
                </div>
                <span class="badge">customer management</span>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th class="action-col">Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_name', 'sort' => $nextDirection('user_name')]) }}">Username</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_email', 'sort' => $nextDirection('user_email')]) }}">Email</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_country', 'sort' => $nextDirection('user_country')]) }}">Country</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'userip_addrs', 'sort' => $nextDirection('userip_addrs')]) }}">IP Address</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'date_added', 'sort' => $nextDirection('date_added')]) }}">Date Added</a></th>
                        <th>Delete</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($customers)->isEmpty())
                        <tr>
                            <td colspan="8" class="muted">No active customers match the current filters.</td>
                        </tr>
                    @else
                    @foreach ($customers as $customer)
                        <tr>
                            <td class="action-col customer-actions-cell">
                                <div class="action-row customer-actions">
                                    <a class="badge" href="{{ url('/v/customer-detail.php?uid='.$customer->user_id) }}">View</a>
                                    <a class="badge" href="{{ url('/v/edit-customer-detail.php?uid='.$customer->user_id) }}">Edit</a>
                                    <form method="post" action="{{ url('/v/simulate-login/'.$customer->user_id) }}" onsubmit="return confirm('Start a simulated customer session for support?');">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <button class="simulate-login-button" type="submit">Simulate Login</button>
                                    </form>
                                    <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/block') }}" onsubmit="return confirm('Block this customer?');">
                                        @csrf
                                        @foreach (request()->query() as $key => $value)
                                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                        @endforeach
                                        <button class="block-button" type="submit">Block</button>
                                    </form>

                                </div>
                            </td>
                            <td class="cell-nowrap">{{ $customer->user_id }}</td>
                            <td class="cell-wrap-md">{{ $customer->user_name ?: '-' }}</td>
                            <td class="cell-wrap-lg">{{ $customer->user_email ?: '-' }}</td>
                            <td class="cell-wrap-md">{{ $customer->user_country ?: '-' }}</td>
                            <td class="cell-nowrap">{{ $customer->userip_addrs ?: '-' }}</td>
                            <td class="cell-nowrap">{{ $customer->date_added ?: '-' }}</td>
                            <td>
                                <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/delete') }}" onsubmit="return confirm('Delete this customer?');">
                                    @csrf
                                    @foreach (request()->query() as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div style="margin-top:18px;">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
