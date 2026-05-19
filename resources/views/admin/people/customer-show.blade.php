@extends('layouts.admin')

@section('title', 'Customer #'.$customer->user_id.' | 1Dollar Admin')
@section('page_heading', 'Customer Detail #'.$customer->user_id)
@section('page_subheading', 'Review customer account details, pricing, and approval limits.')

@section('content')
    @php $source = request('source'); @endphp
    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">{{ $customer->display_name }}</h3>
                    <p class="muted" style="margin:0;">Customer account, contact info, pricing, and approval limits.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ $source === 'customer-approvals' ? url('/v/customer-approvals.php') : url('/v/customer_list.php') }}">Back to {{ $source === 'customer-approvals' ? 'Customer Approvals' : 'Customers' }}</a>
                    <a class="badge" href="{{ url('/v/edit-customer-detail.php?uid='.$customer->user_id.($source ? '&source='.rawurlencode($source) : '')) }}">Edit Customer</a>
                    <form method="post" action="{{ url('/v/simulate-login/'.$customer->user_id) }}" onsubmit="return confirm('Start a simulated customer session for support?');">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                        <button type="submit">Simulate Login</button>
                    </form>
                    @if (trim((string) ($customer->user_term ?? '')) === 'upgraded')
                    <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/reverse-upgrade') }}" onsubmit="return confirm('Reverse upgrade for this customer? They will be able to place new orders again.');">
                        @csrf
                        <button style="background: linear-gradient(135deg, #2563eb, #1d4ed8);" type="submit">Reverse Upgrade</button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <tbody>
                    <tr><th>User ID</th><td>{{ $customer->user_id }}</td><th>Username</th><td>{{ $customer->user_name ?: '-' }}</td></tr>
                    <tr><th>Email</th><td>{{ $customer->user_email ?: '-' }}</td><th>Status</th><td>{{ (int) $customer->is_active === 1 ? 'Active' : 'Blocked' }}</td></tr>
                    <tr><th>First Name</th><td>{{ $customer->first_name ?: '-' }}</td><th>Last Name</th><td>{{ $customer->last_name ?: '-' }}</td></tr>
                    <tr><th>Company</th><td>{{ $customer->company ?: '-' }}</td><th>Company Type</th><td>{{ $customer->company_type ?: '-' }}</td></tr>
                    <tr><th>Address</th><td>{{ $customer->company_address ?: '-' }}</td><th>Zip Code</th><td>{{ $customer->zip_code ?: '-' }}</td></tr>
                    <tr><th>City</th><td>{{ $customer->user_city ?: '-' }}</td><th>Country</th><td>{{ $customer->user_country ?: '-' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $customer->user_phone ?: '-' }}</td><th>Fax</th><td>{{ $customer->user_fax ?: '-' }}</td></tr>
                    <tr><th>Contact Person</th><td>{{ $customer->contact_person ?: '-' }}</td><th>Last IP</th><td>{{ $customer->userip_addrs ?: '-' }}</td></tr>
                    <tr><th>Standard Customer Rate</th><td>{{ $customer->normal_fee ?: '-' }}</td><th>Express / Normal Rate</th><td>{{ $customer->middle_fee ?: '-' }}</td></tr>
                    <tr><th>Priority Customer Rate</th><td>{{ $customer->urgent_fee ?: '-' }}</td><th>Super Rush Customer Rate</th><td>{{ $customer->super_fee ?: '-' }}</td></tr>
                    <tr><th>Pending Orders Limit</th><td>{{ $customer->customer_pending_order_limit ?: '-' }}</td><th>Credit Limit</th><td>{{ $customer->customer_approval_limit ?: '-' }}</td></tr>
                    <tr><th>Single Order Price Limit</th><td>{{ $customer->single_approval_limit ?: '-' }}</td><th>Advance Deposit</th><td>{{ $customer->topup ?: '-' }}</td></tr>
                    <tr><th>Payment Terms</th><td>{{ $customer->payment_terms ?: '-' }}</td><th>Date Added</th><td>{{ $customer->date_added ?: '-' }}</td></tr>
                    <tr><th>Max Number of Stitches Override</th><td colspan="3">{{ $customer->max_num_stiches ?: '-' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div style="margin-bottom:16px;">
                <h3 style="margin:0 0 6px;font-size:1.15rem;">Reset Customer Password</h3>
                <p class="muted" style="margin:0;">Set a new password for this customer account. Share the new password with the customer directly after resetting.</p>
            </div>

            @if (session('success'))
                <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
            @endif

            <form method="post" action="{{ url('/v/customers/'.$customer->user_id.'/reset-password') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                @csrf
                <input type="hidden" name="source" value="{{ $source ?? '' }}">
                <div style="flex:1;min-width:200px;max-width:320px;">
                    <label style="display:block;font-size:0.84rem;font-weight:700;margin-bottom:6px;">New Password</label>
                    <input type="text" name="new_password" required minlength="6" maxlength="100" placeholder="Enter new password" style="width:100%;">
                </div>
                <button type="submit" onclick="return confirm('Reset the password for {{ addslashes($customer->display_name) }}?')">Reset Password</button>
            </form>
            @error('new_password')
                <div style="margin-top:8px;color:#9d2d17;font-size:0.88rem;">{{ $message }}</div>
            @enderror
        </div>
    </section>
@endsection
