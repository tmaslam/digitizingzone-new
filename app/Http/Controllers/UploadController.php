<?php

namespace App\Http\Controllers;

use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    private const ALLOWED_PUBLIC_ROOTS = ['public', 'branding', 'site'];

    private const ALLOWED_PUBLIC_EXTENSIONS = [
        'css', 'json', 'png', 'gif', 'jpg', 'jpeg', 'webp', 'ico', 'svg', 'txt',
    ];

    public function show(string $path): Response
    {
        $normalized = $this->normalizePath($path);
        $file = SharedUploads::path($normalized);

        if (! is_file($file)) {
            SecurityAudit::record(null, 'files.public_asset_missing', 'A public upload asset was requested but no file existed.', [
                'requested_path' => $normalized,
            ], 'notice', [
                'portal' => 'public',
                'request_path' => '/uploads/'.$normalized,
                'request_method' => 'GET',
            ]);

            abort(404);
        }

        if (! $this->publicAssetAllowed($normalized)) {
            SecurityAudit::record(null, 'files.public_asset_denied', 'A non-public upload asset path was requested.', [
                'requested_path' => $normalized,
            ], 'warning', [
                'portal' => 'public',
                'request_path' => '/uploads/'.$normalized,
                'request_method' => 'GET',
            ]);

            abort(404);
        }

        return response(file_get_contents($file), 200, [
            'Content-Type' => $this->contentTypeFor($file),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function normalizePath(string $path): string
    {
        $path = ltrim(str_replace("\0", '', $path), '/');
        abort_if(str_contains($path, '..'), 404);

        return $path;
    }

    private function publicAssetAllowed(string $path): bool
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $root = strtolower((string) ($segments[0] ?? ''));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($root, self::ALLOWED_PUBLIC_ROOTS, true)) {
            return false;
        }

        return in_array($extension, self::ALLOWED_PUBLIC_EXTENSIONS, true);
    }

    private function contentTypeFor(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
            'html', 'htm' => 'text/html; charset=utf-8',
            default => mime_content_type($file) ?: 'application/octet-stream',
        };
    }
}
