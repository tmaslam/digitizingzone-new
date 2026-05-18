@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'user_id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Team Accounts | 1Dollar Admin')
@section('page_heading', 'Team Accounts')
@section('page_subheading', 'Manage team and supervisor accounts. Use the Status filter to view locked accounts and unlock them.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/show-all-teams.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtUserID">User ID</label>
                    <input id="txtUserID" type="text" name="txtUserID" value="{{ request('txtUserID') }}">
                </div>
                <div class="field">
                    <label for="txtUserName">Team Name</label>
                    <input id="txtUserName" type="text" name="txtUserName" value="{{ request('txtUserName') }}">
                </div>
                <div class="field">
                    <label for="account_type">Account Type</label>
                    <select id="account_type" name="account_type">
                        <option value="">All Types</option>
                        <option value="team" @selected(request('account_type') === 'team')>Team</option>
                        <option value="supervisor" @selected(request('account_type') === 'supervisor')>Supervisor</option>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
                        <option value="locked" @selected(request('status') === 'locked')>Locked</option>
                        <option value="all" @selected(request('status') === 'all')>All</option>
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
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">Team And Supervisor Accounts</h3>
                    <p class="muted" style="margin:0;">Showing {{ $teams->total() }} {{ request('status', 'active') === 'locked' ? 'locked' : (request('status') === 'all' ? '' : 'active') }} team portal user{{ $teams->total() === 1 ? '' : 's' }}.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span class="badge">team management</span>
                    <a class="badge" href="{{ url('/v/create-teams.php') }}">Create Account</a>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th class="action-col">Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_name', 'sort' => $nextDirection('user_name')]) }}">Team Name</a></th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Password Status</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'date_added', 'sort' => $nextDirection('date_added')]) }}">Creation Date</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($teams)->isEmpty())
                        <tr>
                            <td colspan="7" class="muted">No team accounts were found.</td>
                        </tr>
                    @else
                    @foreach ($teams as $team)
                        <tr>
                            <td class="action-col">
                                <div class="action-row">
                                    <a class="badge" href="{{ url('/v/create-teams.php?user_id='.$team->user_id) }}">Edit</a>
                                    @if ((int) $team->is_active === 1)
                                        <form method="post" action="{{ url('/v/simulate-login/'.$team->user_id) }}" onsubmit="return confirm('Start a simulated team session for support?');">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <button type="submit">Simulate Login</button>
                                        </form>
                                        <form method="post" action="{{ url('/v/teams/'.$team->user_id.'/disable') }}" onsubmit="return confirm('Remove this team account?');">
                                            @csrf
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Remove</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ url('/v/teams/'.$team->user_id.'/unlock') }}" onsubmit="return confirm('Unlock this team account and restore access?');">
                                            @csrf
                                            @foreach (request()->query() as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit" style="background:linear-gradient(135deg,#1f7a53,#145c3c);">Unlock</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td>#{{ $team->user_id }}</td>
                            <td>{{ $team->user_name ?: '-' }}</td>
                            <td>{{ $team->role_label }}</td>
                            <td>
                                @if ((int) $team->is_active === 1)
                                    <span class="badge" style="background:rgba(34,139,94,0.12);color:#1f7a53;border-color:rgba(34,139,94,0.24);">Active</span>
                                @else
                                    <span class="badge" style="background:rgba(180,35,24,0.12);color:#b42318;border-color:rgba(180,35,24,0.18);">Locked</span>
                                @endif
                            </td>
                            <td>{{ $team->password_storage_label }}</td>
                            <td>{{ $team->date_added ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($teams->hasPages())
                <div style="margin-top:18px;">
                    {{ $teams->links() }}
                </div>
            @endif
        </div>
    </section>
@endsection
