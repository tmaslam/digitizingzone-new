<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PortalMailer
{
    public static function sendHtml(string $to, string $subject, string $html): bool
    {
        $recipients = self::normalizeRecipients($to);

        if ($recipients === []) {
            return false;
        }

        try {
            Mail::send([], [], function ($message) use ($recipients, $subject, $html) {
                $message->to($recipients)
                    ->subject($subject);

                self::applySender($message);
                $message->html(self::prepareHtml($subject, $html));
            });

            return true;
        } catch (\Throwable $exception) {
            Log::error('Portal mail sendHtml failed.', [
                'to' => $recipients,
                'subject' => $subject,
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public static function sendText(string $to, string $subject, string $body): bool
    {
        $recipients = self::normalizeRecipients($to);

        if ($recipients === []) {
            return false;
        }

        try {
            Mail::raw($body, function ($message) use ($recipients, $subject) {
                $message->to($recipients)
                    ->subject($subject);

                self::applySender($message);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::error('Portal mail sendText failed.', [
                'to' => $recipients,
                'subject' => $subject,
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public static function normalizeRecipient(?string $to): ?string
    {
        $recipient = trim((string) $to);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $recipient;
    }

    public static function normalizeRecipients(?string $to): array
    {
        $parts = preg_split('/[;,]+/', (string) $to) ?: [];
        $recipients = [];

        foreach ($parts as $part) {
            $recipient = self::normalizeRecipient($part);

            if ($recipient !== null) {
                $recipients[] = strtolower($recipient);
            }
        }

        return array_values(array_unique($recipients));
    }

    private static function applySender($message): void
    {
        $fromAddress = self::resolveFromAddress();
        $fromName = self::resolveFromName();
        $smtpMailbox = self::normalizeRecipient((string) config('mail.mailers.smtp.username'));

        if ($fromAddress !== null) {
            $message->from($fromAddress, $fromName);
            $message->replyTo($fromAddress, $fromName);
        }

        if (
            $smtpMailbox !== null
            && ($fromAddress === null || strtolower($smtpMailbox) !== strtolower($fromAddress))
            && method_exists($message, 'sender')
        ) {
            $message->sender($smtpMailbox, $fromName);
        }
    }

    private static function resolveFromAddress(): ?string
    {
        $candidates = [
            (string) config('mail.site_from.address', ''),
            (string) config('mail.from.address', ''),
            (string) config('mail.mailers.smtp.username', ''),
        ];

        foreach ($candidates as $candidate) {
            $recipient = self::normalizeRecipient($candidate);

            if ($recipient !== null && $recipient !== 'hello@example.com') {
                return $recipient;
            }
        }

        return null;
    }

    private static function resolveFromName(): string
    {
        $candidates = [
            trim((string) config('mail.site_from.name', '')),
            trim((string) config('mail.from.name', '')),
            trim((string) config('app.name', '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate !== 'Laravel') {
                return $candidate;
            }
        }

        return 'Admin Portal';
    }

    private static function prepareHtml(string $subject, string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            $html = '<p></p>';
        }

        if (self::containsHtmlShell($html)) {
            return self::normalizeHtmlDocument($html);
        }

        return view('customer.emails.layout', [
            'title' => $subject !== '' ? $subject : self::resolveFromName(),
            'siteLabel' => self::resolveFromName(),
            'supportEmail' => self::resolveFromAddress(),
            'content' => $html,
        ])->render();
    }

    private static function containsHtmlShell(string $html): bool
    {
        return stripos($html, '<html') !== false
            || stripos($html, '<body') !== false
            || stripos($html, '<!doctype') !== false;
    }

    private static function normalizeHtmlDocument(string $html): string
    {
        $css = self::emailBaseCss();

        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $css.'</head>', $html, 1) ?? $html;
        } elseif (preg_match('/<head\b[^>]*>/i', $html) === 1) {
            $html = preg_replace('/<head\b[^>]*>/i', '$0'.$css, $html, 1) ?? $html;
        } else {
            $html = $css.$html;
        }

        $bodyStyle = 'margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#17212a;';

        if (preg_match('/<body\b[^>]*>/i', $html) === 1) {
            return preg_replace_callback('/<body\b([^>]*)>/i', function (array $matches) use ($bodyStyle) {
                $attributes = $matches[1] ?? '';

                if (preg_match('/style\s*=\s*([\'"])(.*?)\1/i', $attributes, $styleMatch) === 1) {
                    $existing = trim((string) $styleMatch[2]);
                    $merged = rtrim($existing, ';');
                    $merged = $merged !== '' ? $merged.'; '.$bodyStyle : $bodyStyle;

                    $attributes = preg_replace(
                        '/style\s*=\s*([\'"])(.*?)\1/i',
                        'style="'.$merged.'"',
                        $attributes,
                        1
                    ) ?? $attributes;
                } else {
                    $attributes .= ' style="'.$bodyStyle.'"';
                }

                return '<body'.$attributes.'>';
            }, $html, 1) ?? $html;
        }

        return $html;
    }

    private static function emailBaseCss(): string
    {
        return <<<HTML
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
img {
    max-width: 100%;
}
</style>
HTML;
    }
}
