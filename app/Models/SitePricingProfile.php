<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePricingProfile extends Model
{
    protected $table = 'site_pricing_profiles';

    public $timestamps = false;

    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
