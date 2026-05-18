<p style="margin-top:0;">Hello {{ $teamName }},</p>
<p>{{ $message }}</p>
<p><strong>Order ID:</strong> {{ $orderId }}</p>
<p><strong>Queue:</strong> {{ $queueLabel }}</p>
<p style="margin:24px 0;">
    <a href="{{ $detailUrl }}" class="button-link" style="display:inline-block;padding:12px 20px;background:#0f5f66;color:#ffffff !important;border-radius:8px;text-decoration:none;font-weight:700;">Open Assigned {{ $itemLabel }}</a>
</p>
<p style="margin:0 0 18px;">
    If the button does not work, open this link in your browser:<br>
    <a href="{{ $detailUrl }}" style="word-break:break-all;">{{ $detailUrl }}</a>
</p>
<p style="margin-bottom:0;">You can also log in at <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>.</p>
