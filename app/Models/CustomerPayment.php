<?php

namespace App\Models;

use App\Support\LegacyDate;
use App\Support\LegacyQuerySupport;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    protected $table = 'customerpayments';

    protected $primaryKey = 'Seq_No';

    public $timestamps = false;

    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function scopeActive($query)
    {
        return LegacyQuerySupport::applyActiveEndDate($query, $this->getTable(), 'End_Date');
    }

    public function scopeForWebsite($query, ?string $website)
    {
        if (! $website) {
            return $query;
        }

        $website = trim((string) $website);
        $primaryWebsite = (string) config('sites.primary_legacy_key', '1dollar');

        return $query->where(function ($siteQuery) use ($website, $primaryWebsite) {
            $siteQuery->where('Website', $website);

            if (strcasecmp($website, $primaryWebsite) === 0) {
                $siteQuery
                    ->orWhereNull('Website')
                    ->orWhere('Website', '');
            }
        });
    }

    public function getEffectiveDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
