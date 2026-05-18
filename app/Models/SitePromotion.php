<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SitePromotion extends Model
{
    protected $table = 'site_promotions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeSignupOffers($query)
    {
        return $query->where(function ($signupQuery) {
            $signupQuery
                ->where('work_type', 'signup')
                ->orWhere('discount_type', 'signup_offer');
        });
    }

    public function config(): array
    {
        $decoded = json_decode((string) ($this->config_json ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function isCurrent(?Carbon $at = null): bool
    {
        $at ??= now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && Carbon::parse((string) $this->starts_at)->gt($at)) {
            return false;
        }

        if ($this->ends_at && Carbon::parse((string) $this->ends_at)->lt($at)) {
            return false;
        }

        return true;
    }
}
