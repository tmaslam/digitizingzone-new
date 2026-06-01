@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'updated_at');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Email Templates | Digitizing Zone Admin')
@section('page_heading', 'Email Templates')
@section('page_subheading', 'Create and manage reusable email subjects and message content.')

@section('content')
    @unless ($hasTemplates)
        <div class="alert">Email templates are not available until the `email_templates` table is created.</div>
    @else
        <section class="card">
            <div class="card-body">
                <form method="get" action="{{ url('/v/email-templates.php') }}" class="toolbar">
                    <div class="field">
                        <label for="template_name">Template Name</label>
                        <input id="template_name" type="text" name="template_name" value="{{ request('template_name') }}">
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Filter</button>
                    </div>
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <a class="badge" href="{{ url('/v/email-templates-create.php') }}">Create Template</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:1.15rem;">Saved Templates</h3>
                        <p class="muted" style="margin:0;">Showing {{ $paginator?->total() ?? 0 }} saved email templates.</p>
                    </div>
                    <span class="badge">email library</span>
                </div>

                @if (!empty($systemTemplates))
                    <div class="alert" style="margin-top:18px;">
                        Use the exact system template names below when you want to control built-in application emails:
                        {{ collect($systemTemplates)->pluck('name')->implode(', ') }}.
                        To create extra options for admin send screens, save variants like:
                        <strong>Customer Quote Completed :: Price Courtesy</strong>.
                    </div>
                @endif

                <div class="table-wrap" style="margin-top:18px;">
                    <table>
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'template_name', 'sort' => $nextDirection('template_name')]) }}">Name</a></th>
                            <th>Used For</th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'subject', 'sort' => $nextDirection('subject')]) }}">Subject</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'is_active', 'sort' => $nextDirection('is_active')]) }}">Status</a></th>
                            <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'updated_at', 'sort' => $nextDirection('updated_at')]) }}">Updated</a></th>
                            <th>Delete</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if (collect($templates)->isEmpty())
                            <tr><td colspan="7" class="muted">No email templates found.</td></tr>
                        @else
                        @foreach ($templates as $template)
                            <tr>
                                <td><a class="badge" href="{{ url('/v/email-templates/'.$template->id.'/edit') }}">Edit</a></td>
                                <td>{{ $template->template_name }}</td>
                                <td>{{ \App\Support\SystemEmailTemplates::usageForTemplateName($template->template_name) ?: 'Custom library template' }}</td>
                                <td>{{ $template->subject }}</td>
                                <td>{{ (int) $template->is_active === 1 ? 'Active' : 'Inactive' }}</td>
                                <td>{{ $template->updated_at ?: '-' }}</td>
                                <td>
                                    <form method="post" action="{{ url('/v/email-templates/'.$template->id.'/delete') }}" onsubmit="return confirm('Delete this email template?');">
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

                @if ($paginator && $paginator->hasPages())
                    <div style="margin-top:18px;">{{ $paginator->links() }}</div>
                @endif
            </div>
        </section>
    @endunless
@endsection
