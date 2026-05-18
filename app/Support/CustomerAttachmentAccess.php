<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class CustomerAttachmentAccess
{
    public const SOURCE_FILE_SOURCES = ['order', 'edit order', 'vector', 'color', 'quote', 'edit quote'];

    public const RELEASED_FILE_SOURCES = ['scanned', 'sewout'];

    public static function sourceAttachments(Order $order): Collection
    {
        return self::uniqueDisplayAttachments(Attachment::query()
            ->where('order_id', $order->order_id)
            ->whereIn('file_source', self::SOURCE_FILE_SOURCES)
            ->orderBy('id')
            ->get());
    }

    public static function releasedAttachments(Order $order): Collection
    {
        if (! static::releaseFilesVisibleForCustomer($order)) {
            return new Collection();
        }

        return self::uniqueDisplayAttachments(Attachment::query()
            ->where('order_id', $order->order_id)
            ->whereIn('file_source', self::RELEASED_FILE_SOURCES)
            ->orderByRaw("CASE WHEN file_source = 'scanned' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get());
    }

    public static function findDownload(
        AdminUser $customer,
        int $attachmentId = 0,
        string $fileParam = ''
    ): ?array {
        $userId = (int) $customer->user_id;
        $attachment = null;
        $order = null;

        if ($attachmentId > 0) {
            $attachment = Attachment::query()->find($attachmentId);
        }

        if (! $attachment && trim($fileParam) !== '') {
            [$fileName, $sources] = self::sourcesForLegacyFileParam($fileParam);

            if ($fileName !== '' && $sources !== []) {
                $attachment = Attachment::query()
                    ->where('file_name_with_date', $fileName)
                    ->whereIn('file_source', $sources)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if (! $attachment) {
            return null;
        }

        $order = Order::query()
            ->active()
            ->where('order_id', $attachment->order_id)
            ->where('user_id', $userId)
            ->first();

        if (! $order) {
            return null;
        }

        if (
            $customer->site_id !== null
            && $order->site_id !== null
            && (int) $customer->site_id !== (int) $order->site_id
        ) {
            return null;
        }

        if (! static::attachmentAllowedForCustomer($order, $attachment)) {
            return null;
        }

        $fullPath = static::absolutePath($attachment);

        if (! is_file($fullPath)) {
            return null;
        }

        return [
            'order' => $order,
            'attachment' => $attachment,
            'full_path' => $fullPath,
        ];
    }

    public static function attachmentAllowedForCustomer(Order $order, Attachment $attachment): bool
    {
        if (in_array((string) $attachment->file_source, self::RELEASED_FILE_SOURCES, true)
            && ! static::releaseFilesVisibleForCustomer($order)) {
            return false;
        }

        return CustomerReleaseGate::attachmentAllowedForCustomer($order, $attachment)
            || in_array((string) $attachment->file_source, self::SOURCE_FILE_SOURCES, true);
    }

    public static function absolutePath(Attachment $attachment): string
    {
        $folder = match ((string) $attachment->file_source) {
            'quote', 'edit quote' => 'quotes',
            'scanned' => 'scanned',
            'sewout' => 'sewout',
            default => 'order',
        };
        $fallbackFolders = in_array((string) $attachment->file_source, ['order', 'edit order', 'vector', 'color'], true)
            ? ['quotes']
            : [];

        return SharedUploads::firstExistingPath((string) $attachment->file_name_with_date, $folder, $fallbackFolders);
    }

    public static function previewAllowed(Order $order, Attachment $attachment): bool
    {
        return static::attachmentAllowedForCustomer($order, $attachment)
            && AttachmentPreview::isSupported((string) ($attachment->file_name ?: $attachment->file_name_with_date));
    }

    private static function sourcesForLegacyFileParam(string $fileParam): array
    {
        $fileParam = str_replace('RCHARBYDZONE42', '&', $fileParam);
        $fileParam = ltrim(str_replace('\\', '/', trim($fileParam)), '/');
        $segments = explode('/', $fileParam);
        $folder = strtolower((string) ($segments[1] ?? $segments[0] ?? ''));
        $fileName = basename($fileParam);

        $sources = match ($folder) {
            'order' => ['order', 'edit order', 'vector', 'color'],
            'quotes' => ['quote', 'edit quote'],
            'sewout' => ['sewout'],
            'scanned' => ['scanned'],
            default => [],
        };

        return [$fileName, $sources];
    }

    public static function releaseFilesVisibleForCustomer(Order $order): bool
    {
        return in_array((string) $order->status, ['done', 'approved'], true);
    }

    private static function uniqueDisplayAttachments(Collection $attachments): Collection
    {
        return $attachments
            ->reverse()
            ->unique(function (Attachment $attachment) {
                return strtolower(trim((string) ($attachment->file_name ?: $attachment->file_name_with_date)));
            })
            ->sortBy('id')
            ->values();
    }
}
