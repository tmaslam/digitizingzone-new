<?php

namespace App\Support;

class CustomerUploadPolicy
{
    private const CUSTOMER_SOURCE_MAX_SIZE_MB = 5;

    public static function customerSourceAcceptAttribute(): string
    {
        return UploadSecurity::acceptAttribute('source');
    }

    public static function customerProductionAcceptAttribute(): string
    {
        return UploadSecurity::acceptAttribute('production');
    }

    public static function customerSourceRulesSummary(): array
    {
        return [
            'max_size_mb' => self::CUSTOMER_SOURCE_MAX_SIZE_MB,
            'preview_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'source_allowed' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'svg', 'ai', 'eps', 'cdr', 'psd', 'dst', 'emb', 'pes', 'exp', 'jef', 'hus', 'vp3', 'xxx', 'dsb', 'dsz', 'tap', 'u01', 'cnd', 'pxt', 'pxf'],
            'production_allowed' => ['pdf', 'jpg', 'jpeg', 'png', 'ai', 'eps', 'svg', 'cdr', 'psd', 'dst', 'emb', 'pes', 'exp', 'jef', 'hus', 'vp3', 'xxx', 'dsb', 'dsz', 'tap', 'u01', 'cnd', 'pxt', 'pxf'],
        ];
    }

    public static function customerSourceMaxSizeKilobytes(): int
    {
        return self::CUSTOMER_SOURCE_MAX_SIZE_MB * 1024;
    }

    public static function customerSourceMaxSizeBytes(): int
    {
        return self::customerSourceMaxSizeKilobytes() * 1024;
    }
}
