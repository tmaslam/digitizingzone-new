@extends('layouts.admin')

@php
    $label = $accountType === 'supervisor' ? 'Supervisor' : 'Team';
@endphp

@section('title', ($mode === 'create' ? 'Create '.$label : 'Edit '.$label).' | Digitizing Zone Admin')
@section('page_heading', $mode === 'create' ? 'Create '.$label : 'Edit '.$label.' #'.$team->user_id)
@section('page_subheading', $accountType === 'supervisor'
    ? 'Create or update supervisor login details.'
    : 'Create or update team account details.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>{{ $mode === 'create' ? 'Create '.$label : 'Edit '.$label }}</h3>
                    <p class="section-copy">Save login and contact details for this {{ strtolower($label) }} account.</p>
                </div>
                <a class="badge" href="{{ url('/v/show-all-teams.php') }}">Back to Teams</a>
            </div>
            <form method="post" action="{{ url('/v/create-teams.php') }}" class="toolbar">
                @csrf
                @if ($team->exists)
                    <input type="hidden" name="user_id" value="{{ $team->user_id }}">
                @endif
                <div class="field">
                    <label for="account_type">Account Type</label>
                    <select id="account_type" name="account_type">
                        <option value="team" @selected(old('account_type', $accountType) === 'team')>Team</option>
                        <option value="supervisor" @selected(old('account_type', $accountType) === 'supervisor')>Supervisor</option>
                    </select>
                </div>

                <div class="field">
                    <label for="txtTeamName">{{ $accountType === 'supervisor' ? 'Supervisor Name' : 'Team Name' }}</label>
                    <input id="txtTeamName" type="text" name="txtTeamName" value="{{ old('txtTeamName', $team->user_name) }}">
                </div>
                <div class="field">
                    <label for="txtPassword">Password</label>
                    <input id="txtPassword" type="password" name="txtPassword" value="{{ old('txtPassword') }}" autocomplete="new-password" placeholder="{{ $mode === 'create' ? 'Create a password' : 'Leave blank to keep current password' }}">
                </div>
                <div class="field">
                    <label for="txtCPassword">Confirm Password</label>
                    <input id="txtCPassword" type="password" name="txtCPassword" value="{{ old('txtCPassword') }}" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="txtEmail">Email</label>
                    <input id="txtEmail" type="email" name="txtEmail" value="{{ old('txtEmail', $team->user_email) }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">{{ $mode === 'create' ? 'Create '.$label : 'Save '.$label }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
