<p style="margin-top:0;">Hello {{ $customer->display_name }},</p>
<p>Thank you for creating an account on {{ $siteContext->displayLabel() }}.</p>
@if (! empty($signupOffer))
    <p><strong>{{ $signupOffer['headline'] }}</strong><br>{{ $signupOffer['summary'] }}</p>
@endif
<p style="margin:26px 0;">
    <a href="{{ $activationUrl }}" style="display:inline-block;padding:12px 18px;border:1px solid #17212a;color:#17212a;text-decoration:none;font-weight:700;">Activate Account</a>
</p>
<p style="margin:0 0 18px;">
    If the button does not work, copy and paste this activation link into your browser:<br>
    <a href="{{ $activationUrl }}" style="color:#0d6ea3;word-break:break-all;">{{ $activationUrl }}</a>
</p>
<p>This activation link will expire at {{ $expiresAt->format('Y-m-d H:i') }}.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:14px 16px;background:#f4f6f8;border-left:3px solid #0f5f66;font-size:14px;color:#17212a;">
    <strong>Secure your account with two-factor authentication.</strong><br>
    Once you sign in, visit <strong>My Profile</strong> and enable two-factor authentication. Each time you sign in we will send a one-time code to this email address, keeping your account safe even if your password is ever compromised.
</td></tr>
</table>
<p>If you do not see future messages from us in your inbox, please check your spam or junk folder.</p>
<p>If you did not request this account, you can safely ignore this email.</p>
<p style="margin-bottom:0;">Need help? Contact <a href="mailto:{{ $siteContext->supportEmail }}">{{ $siteContext->supportEmail }}</a>.</p>
