@extends('layouts.admin')

@section('title', 'Change Password | Digitizing Zone Admin')
@section('page_heading', 'Change Password')
@section('page_subheading', 'Update your own admin login password.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <form method="post" action="{{ url('/v/change-password.php') }}" class="toolbar">
                @csrf
                <div class="field">
                    <label>Admin User</label>
                    <input type="text" value="{{ $adminUser->user_name }}" readonly>
                </div>
                <div class="field">
                    <label for="txtPassword">New Password</label>
                    <input id="txtPassword" type="password" name="txtPassword" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="txtCPassword">Confirm Password</label>
                    <input id="txtCPassword" type="password" name="txtCPassword" autocomplete="new-password" required>
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Save Password</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card" style="margin-top:24px;">
        <div class="card-body">
            <h3 style="margin-top:0;">Two-Factor Authentication</h3>
            <p style="color:#6b7280;">When enabled, you will be asked to enter a one-time code emailed to your registered address each time you sign in.</p>

            <div style="display:flex; align-items:center; gap:12px; margin:16px 0;">
                <strong>Status:</strong>
                @if ((int) ($adminUser->two_factor_enabled ?? 0) === 1)
                    <span style="color:#1d6f46; font-weight:700;">Enabled</span>
                @else
                    <span style="color:#c56b22; font-weight:700;">Disabled</span>
                @endif
            </div>

            @if ((int) ($adminUser->two_factor_enabled ?? 0) === 1)
                <form method="post" action="{{ url('/v/change-password/2fa') }}" onsubmit="return confirm('Are you sure you want to disable two-factor authentication? Your account will be less secure.');">
                    @csrf
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="secondary">Disable Two-Factor Authentication</button>
                </form>
            @else
                <form method="post" action="{{ url('/v/change-password/2fa') }}">
                    @csrf
                    <input type="hidden" name="action" value="enable">
                    <button type="submit">Enable Two-Factor Authentication</button>
                </form>
            @endif
        </div>
    </section>
@endsection
