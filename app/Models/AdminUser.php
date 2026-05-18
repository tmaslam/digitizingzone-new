<?php

namespace App\Models;

use App\Support\LegacyDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AdminUser extends Model
{
    public const TYPE_CUSTOMER = 1;
    public const TYPE_TEAM = 2;
    public const TYPE_ADMIN = 3;
    public const TYPE_SUPERVISOR = 4;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $guarded = [];

    protected static array $columnPresence = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : (string) $this->user_name;
    }

    public function getHasSecurePasswordAttribute(): bool
    {
        return trim((string) ($this->password_hash ?? '')) !== '';
    }

    public function getPasswordStorageLabelAttribute(): string
    {
        if ($this->has_secure_password) {
            return 'Secure';
        }

        return trim((string) ($this->user_password ?? '')) !== '' ? 'Legacy upgrade pending' : 'Password not set';
    }

    public function scopeAdmins($query)
    {
        return $query->where('usre_type_id', self::TYPE_ADMIN);
    }

    public function scopeTeams($query)
    {
        return $query->where('usre_type_id', self::TYPE_TEAM);
    }

    public function scopeSupervisors($query)
    {
        return $query->where('usre_type_id', self::TYPE_SUPERVISOR);
    }

    public function scopeTeamPortalUsers($query)
    {
        return $query->whereIn('usre_type_id', [self::TYPE_TEAM, self::TYPE_SUPERVISOR]);
    }

    public function scopeCustomers($query)
    {
        return $query->where('usre_type_id', self::TYPE_CUSTOMER);
    }

    public function scopePendingAdminApproval($query)
    {
        return $query
            ->customers()
            ->active()
            ->where('is_active', 0)
            ->where('user_term', 'dc')
            ->where('exist_customer', 'pending_admin_approval');
    }

    public function scopeBlockedCustomerAccounts($query)
    {
        return $query
            ->customers()
            ->active()
            ->where('is_active', 0)
            ->where(function ($subQuery) {
                // Previously approved customers who were blocked
                $subQuery->where(function ($approved) {
                    $approved->where('exist_customer', '1');
                    if (self::hasColumnCached('real_user')) {
                        $approved->where('real_user', '1');
                    }
                })
                // Pre-approval accounts rejected/blocked before activation
                ->orWhere('user_term', 'blocked');
            });
    }

    protected static function hasColumnCached(string $column): bool
    {
        $cacheKey = static::class.':'.$column;

        if (! array_key_exists($cacheKey, self::$columnPresence)) {
            self::$columnPresence[$cacheKey] = Schema::hasColumn((new static)->getTable(), $column);
        }

        return self::$columnPresence[$cacheKey];
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

    public function scopeActive($query)
    {
        return $query->where(function ($activeQuery) {
            $activeQuery
                ->whereNull('end_date')
                ->orWhereRaw("CAST(end_date AS CHAR) = ''")
                ->orWhereRaw("CAST(end_date AS CHAR) = '0000-00-00'")
                ->orWhereRaw("CAST(end_date AS CHAR) = '0000-00-00 00:00:00'");
        });
    }

    public static function activeFirstOrderSql(): string
    {
        return "
            CASE
                WHEN end_date IS NULL
                    OR CAST(end_date AS CHAR) = ''
                    OR CAST(end_date AS CHAR) = '0000-00-00'
                    OR CAST(end_date AS CHAR) = '0000-00-00 00:00:00'
                THEN 0
                ELSE 1
            END
        ";
    }

    public function getIsSupervisorAttribute(): bool
    {
        return (int) $this->usre_type_id === self::TYPE_SUPERVISOR;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ((int) $this->usre_type_id) {
            self::TYPE_SUPERVISOR => 'Supervisor',
            self::TYPE_ADMIN => 'Admin',
            self::TYPE_TEAM => 'Team',
            default => 'User',
        };
    }

    public function getIsInternalAttribute(): bool
    {
        return in_array((int) $this->usre_type_id, [self::TYPE_TEAM, self::TYPE_ADMIN, self::TYPE_SUPERVISOR], true);
    }

    public function getDateAddedAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
