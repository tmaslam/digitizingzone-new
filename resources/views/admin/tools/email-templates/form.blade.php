@extends('layouts.admin')

@section('title', ($mode === 'create' ? 'Create Email Template' : 'Edit Email Template').' | 1Dollar Admin')
@section('page_heading', $mode === 'create' ? 'Create Email Template' : 'Edit Email Template')
@section('page_subheading', 'Save reusable subjects and message content for customer emails.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            @if (!empty($systemTemplates))
                <div class="alert" style="margin-bottom:18px;">
                    For built-in application emails, use one of these exact template names:
                    <strong>{{ collect($systemTemplates)->pluck('name')->implode(', ') }}</strong>.
                    To create an alternate selectable version for admin screens, use:
                    <strong>Base Template Name :: Variant Label</strong>
                    for example
                    <strong>Customer Order Completed :: Late Delivery</strong>.
                </div>
            @endif

            <form method="post" action="{{ $mode === 'create' ? url('/v/email-templates-create.php') : url('/v/email-templates/'.$template->id.'/edit') }}">
                @csrf
                <div class="toolbar">
                    <div class="field">
                        <label for="template_name">Template Name</label>
                        <input id="template_name" type="text" name="template_name" value="{{ old('template_name', $template->template_name) }}">
                    </div>
                    <div class="field">
                        <label for="subject">Subject</label>
                        <input id="subject" type="text" name="subject" value="{{ old('subject', $template->subject) }}">
                    </div>
                    <div class="field">
                        <label for="is_active">Status</label>
                        <select id="is_active" name="is_active">
                            <option value="1" @selected((string) old('is_active', $template->is_active ?? 1) === '1')>Active</option>
                            <option value="0" @selected((string) old('is_active', $template->is_active ?? 1) === '0')>Inactive</option>
                        </select>
                    </div>
                </div>

                @include('shared.rich-text-editor', [
                    'id' => 'email_template_body',
                    'name' => 'body',
                    'label' => 'Email Body',
                    'value' => $template->body,
                    'rows' => 16,
                    'placeholder' => 'Write the email content here. You can use formatting, links, and merge fields.',
                    'help' => 'Formatting is saved as HTML automatically. Merge fields like {{customer_name}} and {{review_url}} stay supported.',
                    'style' => 'margin-top:18px;',
                ])

                @if (!empty($tokenLabels))
                    <div class="card subcard" style="margin-top:18px;">
                        <div class="card-body">
                            <h4 style="margin:0 0 10px;">Supported Merge Fields</h4>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Token</th>
                                        <th>Meaning</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($tokenLabels as $token => $description)
                                        <tr>
                                            <td><code>{{ $token }}</code></td>
                                            <td>{{ $description }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                    <button type="submit">{{ $mode === 'create' ? 'Create Template' : 'Save Template' }}</button>
                    <a class="badge" href="{{ url('/v/email-templates.php') }}">Back to Templates</a>
                </div>
            </form>
        </div>
    </section>
@endsection
