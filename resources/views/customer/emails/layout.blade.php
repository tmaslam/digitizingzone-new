<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $title ?? '' }}</title>
<style>
body,
table,
tbody,
tr,
td,
th,
div,
p,
span,
a,
li,
ol,
ul,
h1,
h2,
h3,
h4,
h5,
h6 {
    font-family: Arial, Helvetica, sans-serif !important;
    color: #17212a;
}
a {
    color: #0d6ea3;
}
a[style*="background"],
a[style*="background:"],
.button-link {
    color: #ffffff !important;
}
pre,
code {
    font-family: 'Courier New', Courier, monospace !important;
}
table {
    border-collapse: collapse;
}
</style>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#17212a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f4f6f8;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;border-collapse:collapse;">
                    <tr>
                        <td style="background:#17212a;padding:22px 28px;">
                            <div style="font-size:20px;font-weight:700;line-height:1.2;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">{{ $siteLabel ?? 'Digitizing Zone' }}</div>
                            <div style="margin-top:6px;font-size:13px;color:#d6e0ea;font-family:Arial,Helvetica,sans-serif;">{{ $title }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;line-height:1.65;font-size:14px;color:#17212a;background:#ffffff;border:1px solid #d9dee5;border-top:0;font-family:Arial,Helvetica,sans-serif;">
                            {!! $content !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 28px;font-size:12px;color:#6b7785;background:#f9f9f9;border:1px solid #d9dee5;border-top:0;font-family:Arial,Helvetica,sans-serif;">
                            {{ $siteLabel ?? 'Digitizing Zone' }}
                            @if (! empty($supportEmail))
                                &bull; Questions? <a href="mailto:{{ $supportEmail }}" style="color:#0d6ea3;font-family:Arial,Helvetica,sans-serif;">{{ $supportEmail }}</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
