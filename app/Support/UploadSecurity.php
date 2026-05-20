<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class UploadSecurity
{
    public const MAX_CUSTOMER_UPLOAD_FILES = 10;

    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'sql', 'sh', 'bash', 'zsh', 'py', 'pl', 'cgi',
        'js', 'jar', 'war', 'exe', 'dll', 'bat', 'cmd',
        'com', 'msi', 'apk', 'app', 'vb', 'vbs', 'ps1',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz',
    ];

    private const SOURCE_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'svg',
        'ai', 'eps', 'cdr', 'psd',
        'dst', 'emb', 'pes', 'exp', 'jef', 'hus', 'vp3', 'xxx', 'dsb', 'dsz', 'tap', 'u01', 'cnd', 'pxt', 'pxf',
    ];

    private const PRODUCTION_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png',
        'ai', 'eps', 'svg', 'cdr', 'psd',
        'dst', 'emb', 'pes', 'exp', 'jef', 'hus', 'vp3', 'xxx', 'dsb', 'dsz', 'tap', 'u01', 'cnd', 'pxt', 'pxf',
    ];

    private const DANGEROUS_MIME_FRAGMENTS = [
        'php',
        'x-httpd-php',
        'x-php',
        'x-sh',
        'x-shellscript',
        'x-msdownload',
        'x-dosexec',
        'x-executable',
        'x-bat',
        'javascript',
        'ecmascript',
        'x-sql',
    ];

    private const SVG_SIGNATURE_MARKERS = [
        '<script',
        'onload=',
        'onerror=',
        'javascript:',
        '<foreignobject',
    ];

    public static function assertAllowedFiles(array $files, string $profile): ?string
    {
        if (count(array_filter($files, fn ($file) => $file instanceof UploadedFile)) > self::MAX_CUSTOMER_UPLOAD_FILES) {
            return 'You can upload up to '.self::MAX_CUSTOMER_UPLOAD_FILES.' files at one time.';
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $message = self::validationMessage($file, $profile);
            if ($message !== null) {
                return $message;
            }
        }

        return null;
    }

    public static function acceptAttribute(string $profile): string
    {
        $extensions = $profile === 'production'
            ? self::PRODUCTION_EXTENSIONS
            : self::SOURCE_EXTENSIONS;

        return collect($extensions)
            ->map(fn (string $extension) => '.'.$extension)
            ->implode(',');
    }

    private static function validationMessage(UploadedFile $file, string $profile): ?string
    {
        $clientName = trim((string) $file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());
        $realPath = $file->getRealPath() ?: '';

        if ($clientName === '' || strlen($clientName) > 180) {
            return 'Each uploaded file must have a valid file name.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $clientName)) {
            return 'A selected file name contains unsupported characters.';
        }

        if ($extension === '') {
            return 'Each uploaded file must have a valid file extension.';
        }

        $looksLikeHttpUpload = $realPath === '' || is_uploaded_file($realPath) || app()->runningUnitTests();

        if (! $file->isValid() || ! $file->isFile() || ! $looksLikeHttpUpload) {
            return 'One of the selected files could not be uploaded securely.';
        }

        if ($file->getSize() <= 0) {
            return 'Empty files are not allowed.';
        }

        // Check every segment of the filename, not just the final extension.
        // This blocks double-extension attacks such as shell.php.jpg.
        $nameParts = array_slice(explode('.', strtolower($clientName)), 1);
        foreach ($nameParts as $part) {
            if (in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                return 'Programming files, SQL files, and archive uploads are not allowed.';
            }
        }

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return 'Programming files, SQL files, and archive uploads are not allowed.';
        }

        foreach (self::DANGEROUS_MIME_FRAGMENTS as $fragment) {
            if ($mimeType !== '' && str_contains($mimeType, $fragment)) {
                return 'This file type is not allowed.';
            }
        }

        $allowedExtensions = $profile === 'production'
            ? self::PRODUCTION_EXTENSIONS
            : self::SOURCE_EXTENSIONS;

        if (! in_array($extension, $allowedExtensions, true)) {
            return $profile === 'production'
                ? 'Only approved production formats and PDF/image preview files are allowed.'
                : 'Only approved source artwork, digitizing, vector, and PDF files are allowed.';
        }

        if ($extension === 'svg' && $realPath !== '' && self::svgContainsActiveContent($realPath)) {
            return 'SVG files with scripts or active content are not allowed.';
        }

        return null;
    }

    private static function svgContainsActiveContent(string $path): bool
    {
        $contents = @file_get_contents($path, false, null, 0, 32768);
        if (! is_string($contents) || $contents === '') {
            return true;
        }

        $haystack = strtolower($contents);

        foreach (self::SVG_SIGNATURE_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }
}
