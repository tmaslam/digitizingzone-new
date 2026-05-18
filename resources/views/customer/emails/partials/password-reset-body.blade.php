<p style="margin-top:0;">Hello {{ $customer->display_name }},</p>
<p>We received a request to reset the password for your account on {{ $siteContext->displayLabel() }}.</p>
<p style="margin:26px 0;">
    <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 18px;border:1px solid #17212a;color:#17212a;text-decoration:none;font-weight:700;">Reset Password</a>
</p>
<p>This link will expire at {{ $expiresAt->format('Y-m-d H:i') }}.</p>
<p>If you did not request this change, you can safely ignore this message.</p>
<p style="margin-bottom:0;">Need help? Contact <a href="mailto:{{ $siteContext->supportEmail }}">{{ $siteContext->supportEmail }}</a>.</p>
