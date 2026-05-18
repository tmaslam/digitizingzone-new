<?php

namespace App\Support;

use DateTimeInterface;
use InvalidArgumentException;
use Illuminate\Http\UploadedFile;

class SharedUploads
{
    private const DEFAULT_FOLDERS = [
        'admin',
        'order',
        'order_final',
        'quotes',
        'scanned',
        'sewout',
        'team',
    ];

    public static function root(): string
    {
        $configured = trim((string) config('app.shared_uploads_path', ''));

        if ($configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return rtrim(dirname(base_path()).DIRECTORY_SEPARATOR.'upload', DIRECTORY_SEPARATOR);
    }

    public static function parent(): string
    {
        return dirname(self::root());
    }

    public static function folder(string $folder): string
    {
        $path = self::root().DIRECTORY_SEPARATOR.self::cleanRelativePath($folder);
        self::ensureDirectory($path);

        return $path;
    }

    public static function path(string $relativePath = ''): string
    {
        self::ensureReady();

        $relativePath = self::cleanRelativePath($relativePath);

        if ($relativePath === '') {
            return self::root();
        }

        return self::root().DIRECTORY_SEPARATOR.$relativePath;
    }

    public static function storeUploadedFile(
        UploadedFile $file,
        string $folder,
        string $fileName,
        ?DateTimeInterface $storedAt = null
    ): string {
        $relativePath = self::monthlyRelativePath($fileName, $storedAt);
        $absolutePath = self::path($folder.DIRECTORY_SEPARATOR.$relativePath);
        self::ensureParentDirectory($absolutePath);

        $file->move(dirname($absolutePath), basename($absolutePath));

        return $relativePath;
    }

    public static function monthlyRelativePath(string $fileName, ?DateTimeInterface $storedAt = null): string
    {
        $fileName = self::cleanRelativePath($fileName);

        return self::monthBucket($storedAt).DIRECTORY_SEPARATOR.$fileName;
    }

    public static function ensureParentDirectory(string $absolutePath): string
    {
        self::ensureDirectory(dirname($absolutePath));

        return $absolutePath;
    }

    public static function firstExistingPath(string $fileName, string $primaryFolder, array $fallbackFolders = []): string
    {
        $fileName = self::cleanRelativePath($fileName);
        $folders = array_values(array_unique(array_filter([
            $primaryFolder,
            ...$fallbackFolders,
        ], static fn (mixed $folder): bool => is_string($folder) && trim($folder) !== '')));

        foreach ($folders as $folder) {
            $candidate = self::path($folder.DIRECTORY_SEPARATOR.$fileName);

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return self::path($primaryFolder.DIRECTORY_SEPARATOR.$fileName);
    }

    public static function ensureReady(): void
    {
        self::ensureDirectory(self::root());

        foreach (self::DEFAULT_FOLDERS as $folder) {
            self::ensureDirectory(self::root().DIRECTORY_SEPARATOR.$folder);
        }
    }

    private static function monthBucket(?DateTimeInterface $storedAt = null): string
    {
        return ($storedAt ?? now())->format('Y-m');
    }

    private static function cleanRelativePath(string $path): string
    {
        $path = str_replace(["\0", '\\'], ['', '/'], trim($path));
        $path = preg_replace('#^/+#', '', $path) ?? '';
        $path = preg_replace('#^uploads/#i', '', $path) ?? '';

        if ($path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Invalid upload path segment.');
            }
        }

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    private static function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new InvalidArgumentException('Unable to prepare upload directory: '.$path);
        }
    }
}
