@extends('layouts.customer')

@section('title', 'My Profile - '.$siteContext->displayLabel())
@section('hero_title', 'My Profile')
@section('hero_text', 'Keep your contact details current so quotes, orders, invoices, and delivery updates reach the right person every time.')

@section('content')
    <section class="content-card">
        <div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="metric">
                <span>Available Balance</span>
                <strong>${{ number_format($accountSummary['available_balance'], 2) }}</strong>
                <p>Payments and usable account credit.</p>
            </div>
            <div class="metric">
                <span>Admin Deposit</span>
                <strong>${{ number_format($accountSummary['deposit_balance'], 2) }}</strong>
                <p>Manual deposit added by admin to your profile.</p>
            </div>
            <div class="metric">
                <span>Credit Limit</span>
                <strong>${{ number_format($accountSummary['credit_limit'], 2) }}</strong>
            </div>
            <div class="metric">
                <span>Single Order Limit</span>
                <strong>${{ number_format($accountSummary['single_order_limit'], 2) }}</strong>
            </div>
            <div class="metric">
                <span>Payment Terms</span>
                <strong>{{ $accountSummary['payment_terms'] ? $accountSummary['payment_terms'].' Days' : 'Standard' }}</strong>
            </div>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Account Information</h3>
                <p>Email changes are handled by support so your account stays secure.</p>
            </div>
        </div>

        <form method="post" action="{{ url('/my-profile.php') }}" class="stack" data-form-validation novalidate>
            @csrf
            <div class="form-grid">
                <label>
                    <span class="field-label">Email Address</span>
                    <input type="email" value="{{ $customer->user_email }}" disabled>
                    <span class="field-help">For security, email changes are handled by support.</span>
                </label>
                <label>
                    <span class="field-label">User Name</span>
                    <input type="text" value="{{ $customer->user_name }}" disabled>
                    <span class="field-help">Your login name stays tied to this account.</span>
                </label>
                <label>
                    <span class="field-label">First Name <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" autocomplete="given-name" required maxlength="100">
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Last Name <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" autocomplete="family-name" required maxlength="100">
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Company</span>
                    <input type="text" name="company" value="{{ old('company', $customer->company) }}" autocomplete="organization" maxlength="150">
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Company Type</span>
                    <select name="company_type">
                        <option value="">Please Select</option>
                        @foreach ($companyTypes as $type)
                            <option value="{{ $type }}" @selected(old('company_type', $customer->company_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-field-error></span>
                </label>
                <label style="grid-column: 1 / -1;">
                    <span class="field-label">Address</span>
                    <textarea name="company_address" autocomplete="street-address" maxlength="500">{{ old('company_address', $customer->company_address) }}</textarea>
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Zip Code</span>
                    <input type="text" name="zip_code" value="{{ old('zip_code', $customer->zip_code) }}" autocomplete="postal-code" maxlength="30">
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">City</span>
                    <input type="text" name="user_city" value="{{ old('user_city', $customer->user_city) }}" autocomplete="address-level2" maxlength="120">
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Country <span class="field-meta required" aria-hidden="true">*</span></span>
                    <select name="user_country" autocomplete="country-name" required>
                        <option value="">Please Select</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country }}" @selected(old('user_country', $customer->user_country) === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Phone <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="text" name="user_phone" value="{{ old('user_phone', $customer->user_phone) }}" autocomplete="tel" inputmode="tel" required maxlength="50">
                    <span class="field-error" data-field-error></span>
                </label>
            </div>
            <div>
                <button type="submit">Save Profile</button>
            </div>
        </form>
    </section>

    <section class="content-card single-column">
        <div class="section-head">
            <div>
                <h3>Two-Factor Authentication</h3>
                <p>When enabled, you will be asked to enter a one-time code emailed to your registered address each time you sign in. This adds a second layer of protection to your account.</p>
            </div>
            @if ((int) ($customer->two_factor_enabled ?? 0) === 1)
                <span class="status success" style="align-self:flex-start;">Enabled</span>
            @else
                <span class="status warning" style="align-self:flex-start;">Disabled</span>
            @endif
        </div>

        @if (session('success') && str_contains(session('success'), 'two-factor'))
            <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
        @endif

        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            @if ((int) ($customer->two_factor_enabled ?? 0) === 1)
                <form method="post" action="{{ url('/my-profile/2fa') }}" onsubmit="return confirm('Are you sure you want to disable two-factor authentication? Your account will be less secure.');">
                    @csrf
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="secondary">Disable Two-Factor Authentication</button>
                </form>
            @else
                <form method="post" action="{{ url('/my-profile/2fa') }}">
                    @csrf
                    <input type="hidden" name="action" value="enable">
                    <button type="submit">Enable Two-Factor Authentication</button>
                </form>
            @endif
        </div>
    </section>

    <section class="content-card single-column">
        <div class="section-head">
            <div>
                <h3>Change Password</h3>
                <p>Use your current password to set a new one for your account.</p>
            </div>
        </div>

        <form method="post" action="{{ url('/my-profile/password') }}" class="stack" data-form-validation novalidate>
            @csrf
            <div class="form-grid">
                <label>
                    <span class="field-label">Current Password <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="password" name="current_password" autocomplete="current-password" required>
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">New Password <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="password" name="new_password" autocomplete="new-password" minlength="6" required>
                    <span class="field-help">Use at least 6 characters for this account password.</span>
                    <span class="field-error" data-field-error></span>
                </label>
                <label>
                    <span class="field-label">Confirm New Password <span class="field-meta required" aria-hidden="true">*</span></span>
                    <input type="password" name="new_password_confirmation" autocomplete="new-password" minlength="6" required data-match="new_password" data-match-message="The confirm password must match the new password.">
                    <span class="field-help">&nbsp;</span>
                    <span class="field-error" data-field-error></span>
                </label>
            </div>
            <div>
                <button type="submit" class="secondary">Update Password</button>
            </div>
        </form>
    </section>
@endsection
