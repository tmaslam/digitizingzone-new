@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'user_id');
    $currentDirection = strtolower(request('sort', 'asc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
    $selectedRecipients = array_map('strval', old('recipients', []));
@endphp

@section('title', 'Notify Customers | 1Dollar Admin')
@section('page_heading', 'Notify Customers')
@section('page_subheading', 'Send email to active customers on the selected website.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    @if ($hasEmailTemplates)
        <section class="card">
            <div class="card-body">
                <form method="get" action="{{ url('/v/notify-customers.php') }}" class="toolbar">
                    <div class="field">
                        <label for="website">Website</label>
                        <select id="website" name="website">
                            @foreach ($sites as $site)
                                <option value="{{ $site['legacy_key'] }}" @selected($selectedWebsite === $site['legacy_key'])>{{ $site['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="search">Search Active Customers</label>
                        <input id="search" type="text" name="search" value="{{ $searchTerm }}" placeholder="User ID, email, username, or name">
                    </div>
                    <div class="field">
                        <label for="template_id">Email Template</label>
                        <select id="template_id" name="template_id">
                            <option value="">Start with a blank email</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected((string) request('template_id') === (string) $template->id)>{{ $template->template_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Load Template</button>
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <a class="badge" href="{{ url('/v/email-templates.php') }}">Manage Templates</a>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <section class="card">
            <div class="card-body">
                <form method="post" action="{{ url('/v/notify-customers.php') }}">
                    @csrf
                    <input type="hidden" name="website" value="{{ $selectedWebsite }}">
                    <input type="hidden" name="template_id" value="{{ $selectedTemplate?->id }}">
                    <div class="alert" style="margin-bottom:16px;">
                        This screen sends email to every selected recipient when you click <strong>Send Email To Selected Customers</strong>.
                    </div>
                <div class="field">
                    <label for="subject">Subject</label>
                    <input id="subject" type="text" name="subject" value="{{ old('subject', $selectedTemplate?->subject) }}">
                </div>
                @if ($selectedTemplate)
                    @include('shared.rich-text-editor', [
                        'id' => 'notify_body',
                        'name' => 'body',
                        'label' => 'Email Body',
                        'value' => old('body', $selectedTemplate->body),
                        'rows' => 10,
                        'placeholder' => '',
                        'style' => 'margin-top:16px;',
                    ])
                @else
                    <div class="field" style="margin-top:16px;">
                        <label for="body">Email Body</label>
                        <textarea id="body" name="body" rows="10">{{ old('body') }}</textarea>
                    </div>
                @endif
                <div class="table-wrap" style="margin-top:18px;">
                    <div class="action-row" style="padding: 0 0 12px;">
                        <button type="button" class="badge" id="select-all-customers">Select All</button>
                        <button type="button" class="badge badge-muted" id="clear-all-customers">Clear All</button>
                    </div>
                    @if ($customers->isEmpty())
                        <div class="muted" style="padding:12px 0 4px;">
                            No active customers matched this website and search filter.
                        </div>
                    @endif
                    <table>
                        <thead>
                        <tr>
                            <th style="width: 96px;">
                                Select
                            </th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_name', 'sort' => $nextDirection('user_name')]) }}">Username</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_id', 'sort' => $nextDirection('user_id')]) }}">User ID</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'first_name', 'sort' => $nextDirection('first_name')]) }}">Name</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'user_email', 'sort' => $nextDirection('user_email')]) }}">Email</a></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="recipients[]"
                                        value="{{ $customer->user_id }}"
                                        data-recipient-checkbox
                                        {{ in_array((string) $customer->user_id, $selectedRecipients, true) ? 'checked' : '' }}
                                    >
                                </td>
                                <td>{{ $customer->user_name ?: '-' }}</td>
                                <td>{{ $customer->user_id }}</td>
                                <td>{{ trim(($customer->first_name ?: '').' '.($customer->last_name ?: '')) ?: '-' }}</td>
                                <td>{{ $customer->user_email }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:18px;">
                    <button type="submit">Send Email To Selected Customers</button>
                </div>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkboxes = Array.from(document.querySelectorAll('[data-recipient-checkbox]'));
            const selectAllButton = document.getElementById('select-all-customers');
            const clearAllButton = document.getElementById('clear-all-customers');

            selectAllButton?.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
            });

            clearAllButton?.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            });
        });
    </script>
@endsection
