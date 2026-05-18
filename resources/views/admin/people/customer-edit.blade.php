@extends('layouts.admin')

@section('title', 'Edit Customer #'.$customer->user_id.' | 1Dollar Admin')
@section('page_heading', 'Edit Customer #'.$customer->user_id)
@section('page_subheading', 'Update customer account details, pricing, and approval limits.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    @php $source = request('source', old('source')); @endphp
    <section class="card">
        <div class="card-body">
            <form method="post" action="{{ url('/v/edit-customer-detail.php') }}">
                @csrf
                <input type="hidden" name="uid" value="{{ $customer->user_id }}">
                <input type="hidden" name="source" value="{{ $source }}">

                <div class="toolbar">
                    <div class="field"><label>User Name</label><input type="text" name="user_name" value="{{ old('user_name', $customer->user_name) }}"></div>
                    <div class="field"><label>Password</label><input type="password" name="txtPassword" value="{{ old('txtPassword') }}" autocomplete="new-password" placeholder="Leave blank to keep current password"></div>
                    <div class="field"><label>First Name</label><input type="text" name="txtFirstName" value="{{ old('txtFirstName', $customer->first_name) }}"></div>
                    <div class="field"><label>Last Name</label><input type="text" name="txtLastName" value="{{ old('txtLastName', $customer->last_name) }}"></div>
                    <div class="field"><label>Company</label><input type="text" name="txtCompany" value="{{ old('txtCompany', $customer->company) }}"></div>
                    <div class="field">
                        <label>Company Type</label>
                        <select name="selCompanyTypes">
                            <option value="">Please Select</option>
                            @foreach ($companyTypes as $type)
                                <option value="{{ $type }}" @selected(old('selCompanyTypes', $customer->company_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Email</label><input type="text" name="txtEmail" value="{{ old('txtEmail', $customer->user_email) }}"></div>
                    <div class="field"><label>Address</label><input type="text" name="txtCompanyAddress" value="{{ old('txtCompanyAddress', $customer->company_address) }}"></div>
                    <div class="field"><label>Zip Code</label><input type="text" name="txtZipCode" value="{{ old('txtZipCode', $customer->zip_code) }}"></div>
                    <div class="field"><label>City</label><input type="text" name="txtCity" value="{{ old('txtCity', $customer->user_city) }}"></div>
                    <div class="field">
                        <label>Country</label>
                        <select name="selCountry">
                            <option value="">Please Select</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country }}" @selected(old('selCountry', $customer->user_country) === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Phone</label><input type="text" name="txtTelephone" value="{{ old('txtTelephone', $customer->user_phone) }}"></div>
                    <div class="field"><label>Fax</label><input type="text" name="txtFax" value="{{ old('txtFax', $customer->user_fax) }}"></div>
                    <div class="field"><label>Contact Person</label><input type="text" name="txtContactPerson" value="{{ old('txtContactPerson', $customer->contact_person) }}"></div>
                    <div class="field"><label>Signup IP Address</label><input type="text" name="txtSignupIp" value="{{ old('txtSignupIp', $customer->userip_addrs) }}" placeholder="Leave blank to clear"></div>
                    <div class="field"><label>Standard Customer Rate</label><input type="text" name="normal_fee" value="{{ old('normal_fee', $customer->normal_fee) }}" placeholder="Blank falls back to site pricing"></div>
                    <div class="field"><label>Express / Normal Customer Rate</label><input type="text" name="middle_fee" value="{{ old('middle_fee', $customer->middle_fee) }}" placeholder="Blank falls back to standard/site pricing"></div>
                    <div class="field"><label>Priority Customer Rate</label><input type="text" name="urgent_fee" value="{{ old('urgent_fee', $customer->urgent_fee) }}" placeholder="Blank falls back to site pricing"></div>
                    <div class="field"><label>Super Rush Customer Rate</label><input type="text" name="super_fee" value="{{ old('super_fee', $customer->super_fee) }}" placeholder="Blank falls back to site pricing"></div>
                    <div class="field"><label>Pending Orders Limit</label><input type="text" name="customer_pending_order_limit" value="{{ old('customer_pending_order_limit', $customer->customer_pending_order_limit) }}"></div>
                    <div class="field"><label>Credit Limit</label><input type="text" name="customer_approval_limit" value="{{ old('customer_approval_limit', $customer->customer_approval_limit) }}"></div>
                    <div class="field"><label>Single Order Price Limit</label><input type="text" name="single_approval_limit" value="{{ old('single_approval_limit', $customer->single_approval_limit) }}"></div>
                    <div class="field"><label>Advance Deposit</label><input type="text" name="topup" value="{{ old('topup', $customer->topup) }}"></div>
                    <div class="field">
                        <label>Payment Terms</label>
                        <select name="payment_terms">
                            @for ($days = 7; $days <= 56; $days += 7)
                                <option value="{{ $days }}" @selected((string) old('payment_terms', $customer->payment_terms) === (string) $days)>{{ $days }} Days</option>
                            @endfor
                        </select>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select name="is_active">
                            <option value="1" @selected((string) old('is_active', $customer->is_active) === '1')>Active</option>
                            <option value="0" @selected((string) old('is_active', $customer->is_active) === '0')>Blocked</option>
                        </select>
                    </div>
                    <div class="field"><label>Max Number of Stitches Override</label><input type="text" name="max_num_stiches" value="{{ old('max_num_stiches', $customer->max_num_stiches) }}" placeholder="Blank uses site pricing"></div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                    <button type="submit">Save Customer</button>
                    <a class="badge" href="{{ url('/v/customer-detail.php?uid='.$customer->user_id.($source ? '&source='.rawurlencode($source) : '')) }}">Cancel</a>
                </div>
            </form>
        </div>
    </section>
@endsection
