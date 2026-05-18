@extends('layouts.customer')

@section('title', 'Request an Edit - '.$order->order_id)
@section('hero_title', 'Request an Edit')
@section('hero_text', 'When completed work needs revision, explain what should change and attach any updated artwork or reference files.')

@section('content')
    <section class="content-card single-column">
        <div class="section-head">
            <div>
                <h3>Order #{{ $order->order_id }}: {{ $order->design_name }}</h3>
                <p>This request will move the order back into the internal workflow for review.</p>
            </div>
            <a class="button secondary" href="/view-order-detail.php?order_id={{ $order->order_id }}">Back to Order</a>
        </div>

        <form method="post" action="/disapprove-order.php?order_id={{ $order->order_id }}" enctype="multipart/form-data" class="form-grid">
            @csrf

            <label style="grid-column: 1 / -1;">
                Subject
                <input type="text" name="subject" value="{{ old('subject', $order->subject ?: $order->design_name) }}" required>
            </label>

            <label style="grid-column: 1 / -1;">
                What needs to change?
                <textarea name="comments" required placeholder="Describe the exact revision you want, including any issues with text, size, colors, or stitch direction.">{{ old('comments') }}</textarea>
            </label>

            <label style="grid-column: 1 / -1;">
                Updated Files or References
                <input
                    type="file"
                    name="source_files[]"
                    accept="{{ $sourceFileAccept }}"
                    multiple
                    data-customer-upload-input
                    data-max-file-size-bytes="{{ \App\Support\CustomerUploadPolicy::customerSourceMaxSizeBytes() }}"
                    data-max-file-size-mb="{{ \App\Support\CustomerUploadPolicy::customerSourceRulesSummary()['max_size_mb'] }}"
                >
                <span class="upload-error" data-upload-error hidden></span>
                <span class="muted">Upload replacement art, annotated previews, or sew-out images if they help explain the requested edit.</span>
            </label>

            <div style="grid-column: 1 / -1; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit" class="button danger">Send Edit Request</button>
                <a class="button secondary" href="/view-order-detail.php?order_id={{ $order->order_id }}">Cancel</a>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
