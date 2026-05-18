@php
    $exportCopy = $copy ?? 'Download the current report.';
    $exportMarginTop = $marginTop ?? '18px';
    $exportMarginBottom = $marginBottom ?? '18px';
    $exportLabel = $label ?? 'Download Report';
    $showExport = $show ?? true;
@endphp

@if ($showExport)
    <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;margin-top:{{ $exportMarginTop }};margin-bottom:{{ $exportMarginBottom }};padding:14px 16px;border-radius:18px;background:rgba(15,95,102,0.06);border:1px solid rgba(15,95,102,0.12);">
        <div>
            <strong style="display:block;">Report Actions</strong>
            <span class="muted">{{ $exportCopy }}</span>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" style="display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:999px;background:#0f5f66;color:#fff;font-weight:700;text-decoration:none;">{{ $exportLabel }}</a>
    </div>
@endif
