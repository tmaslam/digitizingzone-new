@extends('layouts.team')

@section('title', 'Manage Team | Digitizing Zone Team Portal')
@section('page_heading', 'Manage Team')
@section('page_subheading', 'Create team logins and manage your assigned team members.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/team/manage-team.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtUserID">User ID</label>
                    <input id="txtUserID" type="text" name="txtUserID" value="{{ request('txtUserID') }}">
                </div>
                <div class="field">
                    <label for="txtUserName">Team Name</label>
                    <input id="txtUserName" type="text" name="txtUserName" value="{{ request('txtUserName') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Filter</button>
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <a class="badge" href="{{ url('/team/create-team.php') }}">Create Team Login</a>
                </div>
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
                        <th>User ID</th>
                        <th>Team Name</th>
                        <th>Email</th>
                        <th>Active</th>
                        <th>Working</th>
                        <th>Ready</th>
                        <th>Verified</th>
                        <th>Date Added</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($members)->isEmpty())
                        <tr><td colspan="9" class="muted">No team members are linked to your supervisor account yet.</td></tr>
                    @else
                    @foreach ($members as $member)
                        @php $stats = $memberStats[$member->user_id] ?? ['active' => 0, 'working' => 0, 'ready' => 0, 'verified' => 0]; @endphp
                        <tr>
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ url('/team/team-member-detail.php?user_id='.$member->user_id) }}">View Work</a>
                                    <a class="badge" href="{{ url('/team/create-team.php?user_id='.$member->user_id) }}">Edit</a>
                                </div>
                            </td>
                            <td>#{{ $member->user_id }}</td>
                            <td>{{ $member->user_name ?: '-' }}</td>
                            <td>{{ $member->user_email ?: '-' }}</td>
                            <td>{{ $stats['active'] }}</td>
                            <td>{{ $stats['working'] }}</td>
                            <td>{{ $stats['ready'] }}</td>
                            <td>{{ $stats['verified'] }}</td>
                            <td>{{ $member->date_added ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
