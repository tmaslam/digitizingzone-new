@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Blocked IPs | Digitizing Zone Admin')
@section('page_heading', 'Blocked IP List')
@section('page_subheading', 'Manage blocked IP addresses for admin access.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <form method="post" action="{{ url('/v/blocked-ip-list.php') }}" class="toolbar">
                @csrf
                <div class="field">
                    <label for="txtUserID">IP Address</label>
                    <input id="txtUserID" type="text" name="txtUserID" value="{{ old('txtUserID') }}">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Add IP Address</button>
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
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'ipaddress', 'sort' => $nextDirection('ipaddress')]) }}">IP Address</a></th>
                        <th>Delete</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($ips)->isEmpty())
                        <tr><td colspan="2" class="muted">No blocked IP addresses found.</td></tr>
                    @else
                    @foreach ($ips as $ip)
                        <tr>
                            <td>{{ $ip->ipaddress }}</td>
                            <td>
                                <form method="post" action="{{ url('/v/blocked-ip-list/'.$ip->id.'/delete') }}" onsubmit="return confirm('Delete this IP address?');">
                                    @csrf
                                    <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($ips->hasPages())
                <div style="margin-top:18px;">{{ $ips->links() }}</div>
            @endif
        </div>
    </section>
@endsection
