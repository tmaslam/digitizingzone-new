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
@endsection
