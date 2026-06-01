@extends('layouts.team')

@section('title', ($mode === 'create' ? 'Create Team Login' : 'Edit Team Login').' | Digitizing Zone Team Portal')
@section('page_heading', $mode === 'create' ? 'Create Team Login' : 'Edit Team Login #'.$member->user_id)
@section('page_subheading', 'Manage login details for team members on your supervisor account.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="post" action="{{ url('/team/create-team.php') }}" class="toolbar">
                @csrf
                @if ($member->exists)
                    <input type="hidden" name="user_id" value="{{ $member->user_id }}">
                @endif

                <div class="field">
                    <label for="txtTeamName">Team Name</label>
                    <input id="txtTeamName" type="text" name="txtTeamName" value="{{ old('txtTeamName', $member->user_name) }}">
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
                    <input id="txtEmail" type="email" name="txtEmail" value="{{ old('txtEmail', $member->user_email) }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">{{ $mode === 'create' ? 'Create Team Login' : 'Save Team Login' }}</button>
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <a class="badge" href="{{ url('/team/manage-team.php') }}">Back To Team</a>
                </div>
            </form>
        </div>
    </section>
@endsection
