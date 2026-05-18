<?php

namespace App\Models;

use App\Support\LegacyDate;
use App\Support\LegacyQuerySupport;
use App\Support\OrderWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    protected $table = 'orders';

    protected $primaryKey = 'order_id';

    public $timestamps = false;

    protected $guarded = [];

    private static array $columnPresence = [];

    public static function writablePayload(array $payload): array
    {
        $instance = new static;
        $table = $instance->getTable();

        return array_filter(
            $payload,
            function (string $column) use ($table): bool {
                $cacheKey = $table.':'.$column;

                if (! array_key_exists($cacheKey, self::$columnPresence)) {
                    self::$columnPresence[$cacheKey] = Schema::hasColumn($table, $column);
                }

                return self::$columnPresence[$cacheKey];
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function customer()
    {
        return $this->belongsTo(AdminUser::class, 'user_id', 'user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(AdminUser::class, 'assign_to', 'user_id');
    }

    public function scopeActive($query)
    {
        return LegacyQuerySupport::applyActiveEndDate($query, $this->getTable());
    }

    public function scopeOrderManagement($query)
    {
        return $query->whereIn('order_type', ['order', 'vector', 'color']);
    }

    public function scopeQuoteManagement($query)
    {
        return $query->whereIn('order_type', OrderWorkflow::quoteManagementTypes());
    }

    public function scopeAssigned($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->whereNotNull('assign_to')
                ->where('assign_to', '!=', 0)
                ->where('assign_to', '!=', '');
        });
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

    public function scopeUnassigned($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->whereNull('assign_to')
                ->orWhere('assign_to', 0)
                ->orWhere('assign_to', '');
        });
    }

    public function getLegacyTypeLabelAttribute(): string
    {
        return $this->work_type_label;
    }

    public function getWorkTypeLabelAttribute(): string
    {
        return OrderWorkflow::workTypeLabel($this);
    }

    public function getFlowContextLabelAttribute(): string
    {
        return OrderWorkflow::flowContextLabel($this);
    }

    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->display_name ?? '-';
    }

    public function getAssigneeNameAttribute(): string
    {
        return $this->assignee?->user_name ?? '-';
    }

    public function getSubmitDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }

    public function getCompletionDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }

    public function getAssignedDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }

    public function getVenderCompleteDateAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
