<?php

namespace App\Models;

use App\Support\LegacyDate;
use App\Support\LegacyQuerySupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Billing extends Model
{
    protected $table = 'billing';

    protected $primaryKey = 'bill_id';

    public $timestamps = false;

    protected $guarded = [];

    public static function writablePayload(array $payload): array
    {
        if (! Schema::hasTable('billing')) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip(Schema::getColumnListing('billing')));
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function scopeActive($query)
    {
        return LegacyQuerySupport::applyActiveEndDate($query, $this->getTable());
    }

    public function customer()
    {
        return $this->belongsTo(AdminUser::class, 'user_id', 'user_id');
    }

    public function scopeForWebsite($query, ?string $website)
    {
        if (! $website) {
            return $query;
        }

        $website = trim((string) $website);
        $primaryWebsite = (string) config('sites.primary_legacy_key', '1dollar');

        return $query->where(function ($siteQuery) use ($website, $primaryWebsite) {
            $siteQuery->where('website', $website);

            if (strcasecmp($website, $primaryWebsite) === 0) {
                $siteQuery
                    ->orWhereNull('website')
                    ->orWhere('website', '');
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function getApproveDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }

    public function getTrandtimeAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
