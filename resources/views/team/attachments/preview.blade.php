@extends('layouts.team')

@section('title', 'File Preview | Digitizing Zone Team Portal')
@section('page_heading', 'File Preview')
@section('page_subheading', 'Preview supported files without downloading them first.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="section-head">
                <div>
                    <h3>{{ $displayName }}</h3>
                    <p class="section-copy">Preview type: {{ ucfirst($previewKind) }}</p>
                </div>
                <div class="action-row">
                    <a class="badge" href="{{ $backUrl }}">Back</a>
                    <a class="badge" href="{{ $downloadUrl }}">Download</a>
                </div>
            </div>

            @if ($previewKind === 'image')
                <div style="display:flex;justify-content:center;padding:12px;border:1px solid rgba(24,34,45,0.1);border-radius:20px;background:rgba(255,255,255,0.72);">
                    <img src="{{ $rawUrl }}" alt="{{ $displayName }}" style="max-width:100%;height:auto;border-radius:16px;">
                </div>
            @elseif ($previewKind === 'pdf')
                <div style="border:1px solid rgba(24,34,45,0.1);border-radius:20px;overflow:hidden;background:#f3f5f7;">
                    <iframe
                        src="{{ $rawUrl }}"
                        title="{{ $displayName }}"
                        style="width:100%;min-height:70vh;border:0;display:block;"
                    ></iframe>
                </div>
            @else
                <div style="border:1px solid rgba(24,34,45,0.1);border-radius:20px;background:#0f1720;color:#eff5fb;padding:18px;overflow:auto;">
                    <pre style="margin:0;white-space:pre-wrap;word-break:break-word;font:0.95rem/1.6 'SFMono-Regular',Consolas,monospace;">{{ $textContent }}</pre>
                </div>
            @endif
        </div>
    </section>
@endsection
