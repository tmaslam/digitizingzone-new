@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'user_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Inactive Customers | Digitizing Zone Admin')
@section('page_heading', 'Inactive Customers')
@section('page_subheading', 'Previously active customer accounts that are currently inactive or blocked. Pending signup approvals are managed separately.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/block-customer_list.php') }}" class="toolbar">
                <div class="field"><label>User ID</label><input type="text" name="txtUserID" value="{{ request('txtUserID') }}"></div>
                <div class="field"><label>Username</label><input type="text" name="txtUserName" value="{{ request('txtUserName') }}"></div>
                <div class="field"><label>Email</label><input type="text" name="txtEmail" value="{{ request('txtEmail') }}"></div>
                <div class="field" style="min-width:auto;"><label>&nbsp;</label><button type="submit">Search</button></div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="action-col">Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_name', 'sort' => $nextDirection('user_name')]) }}">Username</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_email', 'sort' => $nextDirection('user_email')]) }}">Email</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_country', 'sort' => $nextDirection('user_country')]) }}">Country</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'is_active', 'sort' => $nextDirection('is_active')]) }}">Status</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'userip_addrs', 'sort' => $nextDirection('userip_addrs')]) }}">IP Address</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'date_added', 'sort' => $nextDirection('date_added')]) }}">Date Added</a></th>
                        <th>Delete</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($customers)->isEmpty())
                        <tr><td colspan="9" class="muted">No inactive customers found.</td></tr>
                    @else
                    @foreach ($customers as $customer)
                        <tr>
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ url('/v/customer-detail.php?uid='.$customer->user_id) }}">View</a>
                                    <a class="badge" href="{{ url('/v/edit-customer-detail.php?uid='.$customer->user_id) }}">Edit</a>
                                    <form method="post" action="{{ url('/v/block-customer_list/'.$customer->user_id.'/unblock') }}" onsubmit="return confirm('Unblock this customer?');">
                                        @csrf
                                        @foreach (request()->query() as $key => $value)
                                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                        @endforeach
                                        <button type="submit">Unblock</button>
                                    </form>
                                </div>
                            </td>
                            <td>{{ $customer->user_id }}</td>
                            <td>{{ $customer->user_name }}</td>
                            <td>{{ $customer->user_email }}</td>
                            <td>{{ $customer->user_country }}</td>
                            <td>{{ (int) $customer->is_active === 1 ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $customer->userip_addrs }}</td>
                            <td>{{ $customer->date_added }}</td>
                            <td>
                                <form method="post" action="{{ url('/v/block-customer_list/'.$customer->user_id.'/delete') }}" onsubmit="return confirm('Delete this customer?');">
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
                <div style="margin-top:18px;">{{ $customers->links() }}</div>
            @endif
        </div>
    </section>
@endsection
