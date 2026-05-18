<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class AttachmentPreview
{
    public static function isSupported(string $fileName): bool
    {
        return self::kindForFileName($fileName) !== null;
    }

    public static function kindForFileName(string $fileName): ?string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true) => 'image',
            in_array($extension, ['txt', 'csv', 'log', 'json', 'xml', 'md'], true) => 'text',
            default => null,
        };
    }

    public static function inlineResponse(string $path, string $fileName): Response
    {
        $kind = self::kindForFileName($fileName);
        abort_unless($kind !== null, 404);
        abort_unless(is_file($path), 404);

        $headers = [
            'Content-Type' => self::contentType($fileName),
            'Content-Disposition' => 'inline; filename="'.self::safeFileName($fileName).'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data: blob:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        if ($kind === 'text') {
            return response(file_get_contents($path), 200, $headers);
        }

        return response()->file($path, $headers);
    }

    public static function textContents(string $path): string
    {
        abort_unless(is_file($path), 404);

        return (string) file_get_contents($path);
    }

    public static function contentType(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'csv' => 'text/csv; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'xml' => 'application/xml; charset=utf-8',
            'md', 'log', 'txt' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    private static function safeFileName(string $fileName): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?? 'preview-file';

        return $clean !== '' ? $clean : 'preview-file';
    }
}
