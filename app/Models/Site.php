<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';

    public $timestamps = false;

    protected $guarded = [];

    public function domains()
    {
        return $this->hasMany(SiteDomain::class, 'site_id');
    }

    public function pricingProfiles()
    {
        return $this->hasMany(SitePricingProfile::class, 'site_id');
    }

    public function promotions()
    {
        return $this->hasMany(SitePromotion::class, 'site_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', 1);
    }

    public function scopeLegacyKey($query, string $legacyKey)
    {
        return $query->where('legacy_key', $legacyKey);
    }
}
