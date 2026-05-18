<?php

namespace App\Support;

class UploadFileName
{
    public static function sanitize(string $fileName, int $maxLength = 120): string
    {
        // Keep customer-visible punctuation like apostrophes and commas,
        // while stripping path separators and filesystem-reserved characters.
        $clean = preg_replace('/[\\\\\\/:*?"<>|\\x00-\\x1F\\x7F]+/u', '', $fileName) ?? $fileName;
        $clean = preg_replace('/\\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        if ($clean === '') {
            return '';
        }

        return mb_substr($clean, 0, $maxLength);
    }
}
