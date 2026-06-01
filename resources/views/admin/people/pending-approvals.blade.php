@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'user_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Customer Approvals | Digitizing Zone Admin')
@section('page_heading', 'Customer Approvals')
@section('page_subheading', 'Review signup accounts that are waiting for email verification, admin approval, or the customer welcome-payment step.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/customer-approvals.php') }}" class="toolbar">
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
                <div class="field">
                    <label for="approval_state">State</label>
                    <select id="approval_state" name="approval_state">
                        @foreach ($approvalStateOptions as $value => $label)
                            <option value="{{ $value }}" @selected($approvalState === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
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
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Pending Customer Approvals</h3>
                    <p class="muted" style="margin:0;">Showing {{ $customers->total() }} signup accounts across email verification, admin review, and customer payment states.</p>
                </div>
                <span class="badge">approval queue</span>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th class="action-col">Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_name', 'sort' => $nextDirection('user_name')]) }}">Username</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_email', 'sort' => $nextDirection('user_email')]) }}">Email</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'website', 'sort' => $nextDirection('website')]) }}">Website</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_country', 'sort' => $nextDirection('user_country')]) }}">Country</a></th>
                        <th>State</th>
                        <th>Signup Path</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'date_added', 'sort' => $nextDirection('date_added')]) }}">Date Added</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($customers)->isEmpty())
                        <tr>
                            <td colspan="9" class="muted">No signup accounts are waiting in the selected approval state.</td>
                        </tr>
                    @else
                    @foreach ($customers as $customer)
                        <tr>
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ url('/v/customer-detail.php?uid='.$customer->user_id.'&source=customer-approvals') }}">View</a>
                                    <a class="badge" href="{{ url('/v/edit-customer-detail.php?uid='.$customer->user_id.'&source=customer-approvals') }}">Edit</a>
                                    @if ($customer->approval_state === 'pending_verification')
                                        <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/verify-email') }}" onsubmit="return confirm('Mark this customer email as verified?');">
                                            @csrf
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit">Verify Email</button>
                                        </form>
                                    @elseif ($customer->approval_state === 'pending_welcome_payment')
                                        <span class="badge">Waiting On $1 Payment</span>
                                    @else
                                        <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/approve') }}" onsubmit="return confirm('Approve this customer account?');">
                                            @csrf
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit">Approve</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/block') }}" onsubmit="return confirm('Block this customer account? They will not be able to log in.');">
                                        @csrf
                                        <input type="hidden" name="return_to" value="customer-approvals">
                                        @foreach (request()->query() as $key => $value)
                                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                        @endforeach
                                        <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Block</button>
                                    </form>
                                </div>
                            </td>
                            <td>{{ $customer->user_id }}</td>
                            <td>{{ $customer->user_name ?: '-' }}</td>
                            <td>{{ $customer->user_email ?: '-' }}</td>
                            <td>{{ $customer->website ?: '-' }}</td>
                            <td>{{ $customer->user_country ?: '-' }}</td>
                            <td>{{ $customer->approval_state_label }}</td>
                            <td>{{ $customer->signup_path_label }}</td>
                            <td>{{ $customer->date_added ?: '-' }}</td>
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
