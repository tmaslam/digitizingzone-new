@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'Date_Added');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Login History | Digitizing Zone Admin')
@section('page_heading', 'Login History')
@section('page_subheading', 'Admin and user login events from the existing audit table, without exposing sensitive credentials.')

@section('content')
    <section class="card">
        <div class="card-body">
            <form method="get" action="{{ url('/v/login_history.php') }}" class="toolbar">
                <div class="field">
                    <label for="txtUserIP">IP Address</label>
                    <input id="txtUserIP" type="text" name="txtUserIP" value="{{ request('txtUserIP') }}">
                </div>
                <div class="field">
                    <label for="txtLoginName">Login Name</label>
                    <input id="txtLoginName" type="text" name="txtLoginName" value="{{ request('txtLoginName') }}">
                </div>
                <div class="field">
                    <label for="txtStatus">Reason</label>
                    <input id="txtStatus" type="text" name="txtStatus" value="{{ request('txtStatus') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            @include('shared.admin-report-export', [
                'copy' => 'Download the current login-history report.',
                'label' => 'Download Report',
                'show' => $history->count() > 0,
                'marginTop' => '0',
                'marginBottom' => '18px',
            ])
            <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'IP_Address', 'sort' => $nextDirection('IP_Address')]) }}">IP Address</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Login_Name', 'sort' => $nextDirection('Login_Name')]) }}">Login Name</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Date_Added', 'sort' => $nextDirection('Date_Added')]) }}">Date Added</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'Status', 'sort' => $nextDirection('Status')]) }}">Reason</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($history)->isEmpty())
                        <tr><td colspan="4" class="muted">No login history rows found.</td></tr>
                    @else
                    @foreach ($history as $entry)
                        <tr>
                            <td>{{ $entry->IP_Address }}</td>
                            <td>{{ $entry->Login_Name }}</td>
                            <td>{{ $entry->Date_Added }}</td>
                            <td>{{ $entry->Status }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($history->hasPages())
                <div style="margin-top:18px;">{{ $history->links() }}</div>
            @endif
        </div>
    </section>
@endsection
