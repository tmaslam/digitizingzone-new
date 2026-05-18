@extends('layouts.customer')

@php
    $isCreate = $mode === 'create';
    $showVectorFields = ($flow['work_type'] ?? '') === 'vector';
    $commentsValue = old('comments', $order?->comments1 ?? '');
@endphp

@section('title', ($pageTitle ?? 'Customer Form').' - '.$siteContext->displayLabel())
@section('hero_title', $pageTitle ?? 'Customer Submission')
@section('hero_text', $isCreate
    ? 'Complete the order details below and upload your files.'
    : 'Update your order details and files below.')

@section('content')
    @if (!empty($placement['warning']))
        <section class="content-card">
            <div class="alert {{ $placement['can_place'] ? 'alert-success' : 'alert-error' }}">
                {{ $placement['warning'] }}
            </div>
        </section>
    @endif

    @if (trim((string) ($customer->user_term ?? '')) === 'upgraded')
        <section class="content-card">
            <div class="section-head">
                <div>
                    <h3>Order Entry Unavailable</h3>
                    <p>Your account has been upgraded and new submissions are handled on the new portal.</p>
                </div>
            </div>
            <div class="alert alert-error">
                Your account has been upgraded. You can no longer place new orders or quotes on the legacy portal, but you can still download your previously paid orders.
            </div>
            <div style="margin-top: 18px;">
                <a class="button secondary" href="/view-archive-orders.php">View Paid Orders</a>
            </div>
        </section>
    @else
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>{{ $isCreate ? 'Order Details' : 'Edit Order' }}</h3>
                <p>Fill in the required fields and upload your files.</p>
            </div>
        </div>

        <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            <input type="hidden" name="sew_out" value="{{ old('sew_out', $order?->sew_out ?: 'no') }}">

            <label>
                <span>Design Name <span class="required-mark">*</span></span>
                <input type="text" name="design_name" value="{{ old('design_name', $order?->design_name) }}" required>
            </label>

            <label>
                <span>Format <span class="required-mark">*</span></span>
                <select name="format" required>
                    <option value="">Please Select</option>
                    @foreach ($formatOptions as $option)
                        <option value="{{ $option }}" @selected(old('format', $order?->format ?? ($preferredFormat ?? '')) === $option)>{{ strtoupper($option) }}</option>
                    @endforeach
                </select>
            </label>

            @if (! $showVectorFields)
                <label class="field-stack">
                    <span>Fabric/Garment Type <span class="required-mark">*</span></span>
                    <select name="fabric_type" required>
                        @foreach ($fabricTypeOptions as $option)
                            <option value="{{ $option }}" @selected(old('fabric_type', $order?->fabric_type ?? 'Pique Polo') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <span class="field-help">Choose the garment or fabric so production follows the right setup.</span>
                </label>

                <div class="dimension-field">
                    <span>Design Size <span class="required-mark">*</span></span>
                    <div class="dimension-inputs">
                        <input type="number" name="width" min="0" step="0.01" value="{{ old('width', $order?->width) }}" placeholder="Width" required>
                        <span class="dimension-divider">X</span>
                        <input type="number" name="height" min="0" step="0.01" value="{{ old('height', $order?->height) }}" placeholder="Height" required>
                    </div>
                    <span class="field-help">add "0" for proportional dimension.</span>
                </div>

                <label>
                    <span>Measurement <span class="required-mark">*</span></span>
                    <select name="measurement" required>
                        @foreach ($measurementOptions as $option)
                            <option value="{{ $option }}" @selected(old('measurement', $order?->measurement ?? 'Inches') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="paired-field">
                    <span>Colors</span>
                    <div class="paired-field-inputs">
                        <input type="number" name="no_of_colors" min="0" step="1" value="{{ old('no_of_colors', $order?->no_of_colors) }}" placeholder="No. of Colors">
                        <input type="text" name="color_names" value="{{ old('color_names', $order?->color_names) }}" placeholder="Color Names">
                    </div>
                </div>

                <label>
                    Appliques
                    <span class="actions" style="margin-top:0;">
                        @foreach (['no' => 'No', 'yes' => 'Yes'] as $value => $label)
                            <label style="display:inline-flex; align-items:center; gap:8px; font-weight:600; font-size:0.92rem;">
                                <input type="radio" name="appliques" value="{{ $value }}" @checked(old('appliques', $order?->appliques ?? 'no') === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </span>
                </label>

                <div class="paired-field" data-applique-details>
                    <span>Applique Details</span>
                    <div class="paired-field-inputs">
                        <input type="number" name="no_of_appliques" min="0" step="1" value="{{ old('no_of_appliques', $order?->no_of_appliques) }}" placeholder="No. of Appliques" data-applique-input>
                        <input type="text" name="applique_colors" value="{{ old('applique_colors', $order?->applique_colors) }}" placeholder="Applique Colors" data-applique-input>
                    </div>
                </div>
            @endif

            <label>
                <span>Turnaround Time <span class="required-mark">*</span></span>
                <select name="turn_around_time" required>
                    @foreach ($turnaroundOptions as $option)
                        <option value="{{ $option }}" @selected(old('turn_around_time', $order?->turn_around_time ?? 'Standard') === $option)>{{ $turnaroundOptionLabels[$option] ?? \App\Support\TurnaroundTracking::labelWithTiming($option) }}</option>
                    @endforeach
                </select>
                <span class="field-help">
                    Turnaround pricing is shown here automatically using your current account or site pricing rules.
                </span>
            </label>

            <label style="grid-column: 1 / -1;">
                <span>Instructions</span>
                <textarea name="comments">{{ $commentsValue }}</textarea>
            </label>

            <label style="grid-column: 1 / -1;">
                <span>Upload Files @if ($mode === 'create')<span class="required-mark">*</span>@endif</span>
                <input
                    type="file"
                    name="source_files[]"
                    accept="{{ $sourceFileAccept }}"
                    multiple
                    @if ($mode === 'create') required @endif
                    data-customer-upload-input
                    data-max-file-size-bytes="{{ \App\Support\CustomerUploadPolicy::customerSourceMaxSizeBytes() }}"
                    data-max-file-size-mb="{{ \App\Support\CustomerUploadPolicy::customerSourceRulesSummary()['max_size_mb'] }}"
                >
                <span class="upload-error" data-upload-error hidden></span>
            </label>

            <div class="upload-guidance">
                <strong>File Upload:</strong> Please upload a clear artwork file. We accept common image, vector, and embroidery formats, and each file must be under {{ \App\Support\CustomerUploadPolicy::customerSourceRulesSummary()['max_size_mb'] }} MB.
            </div>

            @if (!empty($existingAttachments) && $existingAttachments->count())
                <div style="grid-column: 1 / -1;">
                    <div class="card-head" style="border: 1px solid var(--line); border-radius: 18px 18px 0 0;">
                        <h4>Existing Files</h4>
                        <p>Earlier uploads remain attached to this order.</p>
                    </div>
                    <div class="list-card" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                        <ul class="file-list">
                            @foreach ($existingAttachments as $attachment)
                                <li class="file-item">
                                    <strong>{{ $attachment->file_name ?: $attachment->file_name_with_date }}</strong>
                                    <div class="muted">{{ $attachment->file_source }} | {{ $attachment->date_added ?: '-' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div style="grid-column: 1 / -1; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit">{{ $submitLabel }}</button>
                <a class="button secondary" href="{{ ($flow['flow_context'] ?? 'order') === 'code' ? '/view-quotes.php' : '/view-orders.php' }}">Cancel</a>
            </div>
        </form>
    </section>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const appliqueRadios = Array.from(document.querySelectorAll('input[name="appliques"]'));
            const appliqueDetails = document.querySelector('[data-applique-details]');
            const appliqueInputs = Array.from(document.querySelectorAll('[data-applique-input]'));

            const syncAppliqueDetails = () => {
                if (!appliqueDetails || !appliqueRadios.length) {
                    return;
                }

                const selected = appliqueRadios.find((radio) => radio.checked);
                const enabled = selected?.value === 'yes';

                appliqueDetails.hidden = !enabled;
                appliqueInputs.forEach((input) => {
                    input.disabled = !enabled;
                    if (!enabled) {
                        input.value = '';
                    }
                });
            };

            appliqueRadios.forEach((radio) => {
                radio.addEventListener('change', syncAppliqueDetails);
            });
            syncAppliqueDetails();

            document.querySelectorAll('[data-customer-upload-input]').forEach((input) => {
                const errorTarget = input.closest('label')?.querySelector('[data-upload-error]');
                const maxBytes = Number(input.dataset.maxFileSizeBytes || 0);
                const maxMb = input.dataset.maxFileSizeMb || '5';

                input.addEventListener('change', () => {
                    const files = Array.from(input.files || []);
                    const invalid = files.find((file) => file.size > maxBytes);

                    if (!invalid) {
                        if (errorTarget) {
                            errorTarget.hidden = true;
                            errorTarget.textContent = '';
                        }
                        return;
                    }

                    input.value = '';

                    if (errorTarget) {
                        errorTarget.hidden = false;
                        errorTarget.textContent = `"${invalid.name}" is larger than ${maxMb} MB.`;
                    }
                });
            });
        });
    </script>
@endsection
