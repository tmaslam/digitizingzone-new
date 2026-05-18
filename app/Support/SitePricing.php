<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Site;
use App\Models\SitePricingProfile;
use Illuminate\Support\Facades\Schema;

class SitePricing
{
    public static function embroidery(
        ?AdminUser $customer,
        ?string $website,
        ?int $siteId,
        string $turnaroundTime,
        string $stitches
    ): float {
        $turnaroundCode = self::normalizeTurnaround($turnaroundTime);
        $site = self::siteContext($website, $siteId);
        $profile = self::profileFor($site, ['digitizing', 'embroidery'], $turnaroundCode);
        $error = self::configurationError($customer, $website, $siteId, 'digitizing', $turnaroundTime);

        if ($error !== null) {
            throw new \RuntimeException($error);
        }

        $rate = self::customerRateOverride($customer, $turnaroundCode);
        $hasCustomerOverride = $rate !== null;
        if ($rate === null) {
            $rate = self::embroideryRateFromProfile($profile, $turnaroundCode);
        }

        $minimum = self::minimumCharge($profile, $turnaroundCode, $rate, $hasCustomerOverride);
        $maximumUnits = self::maximumUnits($customer, $profile);
        $stitchCount = (float) $stitches;

        if ($maximumUnits > 0) {
            $billable = min($stitchCount, $maximumUnits);
            $amount = $billable * ($rate / 1000);

            return round(max($minimum, $amount), 2);
        }

        return round(max($minimum, $stitchCount * ($rate / 1000)), 2);
    }

    public static function vector(?string $website, ?int $siteId, string $turnaroundTime, string $totalHours): float
    {
        return self::hourlyWork('vector', $website, $siteId, $turnaroundTime, $totalHours);
    }

    public static function color(?string $website, ?int $siteId, string $turnaroundTime, string $totalHours): float
    {
        return self::hourlyWork('color', $website, $siteId, $turnaroundTime, $totalHours);
    }

    public static function signupPackageFees(?SiteContext $site, string $packageType): array
    {
        $packageType = strtoupper(trim($packageType));

        if ($site?->id && Schema::hasTable('site_pricing_profiles')) {
            $profile = SitePricingProfile::query()
                ->active()
                ->where('site_id', $site->id)
                ->where('pricing_mode', 'package')
                ->where('work_type', 'digitizing')
                ->where('package_name', $packageType)
                ->orderBy('id')
                ->first();

            $config = json_decode((string) ($profile?->config_json ?: ''), true);

            if (is_array($config)) {
                $standard = $config['standard_rate'] ?? $config['normal_fee'] ?? null;
                $priority = $config['priority_rate'] ?? $config['urgent_fee'] ?? null;
                $superrush = $config['superrush_rate'] ?? $config['super_fee'] ?? null;

                if (is_numeric($standard) && is_numeric($priority) && is_numeric($superrush)) {
                    return [(float) $standard, (float) $priority, (float) $superrush];
                }
            }
        }

        // If the site did not explicitly configure signup package pricing,
        // leave customer-specific overrides blank so the account inherits the
        // live site pricing profiles instead of freezing legacy fallback rates.
        return [null, null, null];
    }

    public static function customerDefaultRates(?SiteContext $site): array
    {
        $standard = self::defaultEmbroideryRate($site, 'standard');
        $normal = self::defaultEmbroideryRate($site, 'normal') ?? self::defaultEmbroideryRate($site, 'express') ?? $standard;
        $priority = self::defaultEmbroideryRate($site, 'priority') ?? self::defaultEmbroideryRate($site, 'express');
        $superrush = self::defaultEmbroideryRate($site, 'superrush');

        return [
            'normal_fee' => $standard,
            'middle_fee' => $normal,
            'urgent_fee' => $priority,
            'super_fee' => $superrush,
        ];
    }

    public static function normalizeTurnaround(?string $turnaroundTime): string
    {
        return match (strtolower(trim((string) $turnaroundTime))) {
            'standard', '' => 'standard',
            'normal' => 'normal',
            'priority' => 'priority',
            'express' => 'express',
            'superrush', 'super_rush', 'super-rush' => 'superrush',
            default => 'standard',
        };
    }

    public static function turnaroundLabel(string $turnaroundCode): string
    {
        return match (self::normalizeTurnaround($turnaroundCode)) {
            'normal' => 'Normal',
            'priority' => 'Priority',
            'express' => 'Express',
            'superrush' => 'Super Rush',
            default => 'Standard',
        };
    }

    public static function workTypeLabel(string $workType): string
    {
        return match (strtolower(trim($workType))) {
            'vector' => 'Vector Art',
            'color' => 'Color Separation',
            default => 'Embroidery Digitizing',
        };
    }

    public static function turnaroundFeeSchedule(?AdminUser $customer, ?SiteContext $site, string $workType, bool $useCustomerOverrides = true): array
    {
        $normalizedWorkType = strtolower(trim($workType));
        $schedule = [];

        foreach (['standard', 'priority', 'superrush'] as $turnaroundCode) {
            if (in_array($normalizedWorkType, ['vector', 'color'], true)) {
                $profile = self::profileFor($site, [$normalizedWorkType], $turnaroundCode);
                $rate = self::hourlyRateFromProfile($profile);

                if ($rate === null) {
                    $schedule[$turnaroundCode] = [
                        'amount' => null,
                        'description' => null,
                    ];

                    continue;
                }

                $schedule[$turnaroundCode] = [
                    'amount' => round($rate, 2),
                    'description' => '$'.number_format($rate, 2).' / hour',
                ];

                continue;
            }

            $profile = self::profileFor($site, ['digitizing', 'embroidery'], $turnaroundCode);
            $customerRate = $useCustomerOverrides ? self::customerRateOverride($customer, $turnaroundCode) : null;
            $rate = $customerRate ?? self::embroideryRateFromProfile($profile, $turnaroundCode);
            $minimum = self::minimumCharge($profile, $turnaroundCode, $rate ?? 0.0, $customerRate !== null);

            if ($rate === null || $minimum === null) {
                $schedule[$turnaroundCode] = [
                    'amount' => null,
                    'minimum' => null,
                    'description' => null,
                ];

                continue;
            }

            $schedule[$turnaroundCode] = [
                'amount' => round($rate, 2),
                'minimum' => round($minimum, 2),
                'description' => '$'.number_format($rate, 2).'/1k stitches, (Min. charge $'.number_format($minimum, 2).')',
            ];
        }

        return $schedule;
    }

    private static function hourlyWork(
        string $workType,
        ?string $website,
        ?int $siteId,
        string $turnaroundTime,
        string $totalHours
    ): float {
        [$hours, $minutes] = self::timeParts($totalHours);
        $turnaroundCode = self::normalizeTurnaround($turnaroundTime);
        $site = self::siteContext($website, $siteId);
        $profile = self::profileFor($site, [$workType], $turnaroundCode);
        $error = self::configurationError(null, $website, $siteId, $workType, $turnaroundTime);

        if ($error !== null) {
            throw new \RuntimeException($error);
        }

        $hourlyRate = self::hourlyRateFromProfile($profile);

        return round(($hours * (float) $hourlyRate) + ($minutes * ((float) $hourlyRate / 60)), 2);
    }

    public static function configurationError(
        ?AdminUser $customer,
        ?string $website,
        ?int $siteId,
        string $workType,
        string $turnaroundTime
    ): ?string {
        $site = self::siteContext($website, $siteId);
        $turnaroundCode = self::normalizeTurnaround($turnaroundTime);
        $normalizedWorkType = strtolower(trim($workType));

        if (! $site?->id || ! Schema::hasTable('site_pricing_profiles')) {
            return 'Site pricing is not configured for this website yet. Add the required site pricing profile before continuing.';
        }

        $profile = self::profileFor(
            $site,
            in_array($normalizedWorkType, ['vector', 'color'], true) ? [$normalizedWorkType] : ['digitizing', 'embroidery'],
            $turnaroundCode
        );

        if (! $profile) {
            return sprintf(
                'No %s pricing profile is configured for %s on %s. Add it in Site Pricing before continuing.',
                self::workTypeLabel($normalizedWorkType),
                self::turnaroundLabel($turnaroundCode),
                $site->displayLabel()
            );
        }

        if (in_array($normalizedWorkType, ['vector', 'color'], true)) {
            if (self::hourlyRateFromProfile($profile) === null) {
                return sprintf(
                    'The %s pricing profile for %s on %s is incomplete. Set a fixed or hourly rate in Site Pricing before continuing.',
                    self::workTypeLabel($normalizedWorkType),
                    self::turnaroundLabel($turnaroundCode),
                    $site->displayLabel()
                );
            }

            return null;
        }

        $customerRate = self::customerRateOverride($customer, $turnaroundCode);
        if ($customerRate === null && self::embroideryRateFromProfile($profile, $turnaroundCode) === null) {
            return sprintf(
                'The %s pricing profile for %s on %s is incomplete. Set the stitch rate in Site Pricing before continuing.',
                self::workTypeLabel($normalizedWorkType),
                self::turnaroundLabel($turnaroundCode),
                $site->displayLabel()
            );
        }

        if (self::minimumCharge($profile, $turnaroundCode, 0.0, false) === null) {
            return sprintf(
                'The %s pricing profile for %s on %s is incomplete. Set the minimum charge in Site Pricing before continuing.',
                self::workTypeLabel($normalizedWorkType),
                self::turnaroundLabel($turnaroundCode),
                $site->displayLabel()
            );
        }

        return null;
    }

    private static function siteContext(?string $website, ?int $siteId): SiteContext
    {
        if ($siteId && Schema::hasTable('sites')) {
            $site = Site::query()->active()->find($siteId);

            if ($site) {
                return new SiteContext(
                    id: (int) $site->id,
                    legacyKey: (string) ($site->legacy_key ?: config('sites.primary_legacy_key', '1dollar')),
                    slug: (string) ($site->slug ?: $site->legacy_key ?: 'site'),
                    name: (string) ($site->name ?: $site->brand_name ?: 'Site'),
                    brandName: (string) ($site->brand_name ?: $site->name ?: 'Site'),
                    host: (string) ($site->primary_domain ?: config('sites.primary_host', 'localhost')),
                    supportEmail: (string) ($site->support_email ?: config('mail.site_from.address')),
                    fromEmail: (string) ($site->from_email ?: config('mail.from.address')),
                    websiteAddress: (string) ($site->website_address ?: $site->primary_domain ?: config('sites.primary_host', 'localhost')),
                    companyAddress: (string) ($site->company_address ?: env('SITE_COMPANY_ADDRESS', '')),
                    isPrimary: (bool) $site->is_primary,
                    timezone: (string) ($site->timezone ?: config('app.timezone', 'UTC')),
                );
            }
        }

        return SiteResolver::fromLegacyKey($website ?: config('sites.primary_legacy_key', '1dollar'))
            ?: SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
    }

    private static function profileFor(?SiteContext $site, array $workTypes, string $turnaroundCode): ?SitePricingProfile
    {
        if (! $site?->id || ! Schema::hasTable('site_pricing_profiles')) {
            return null;
        }

        $normalizedWorkTypes = array_map(static fn (string $workType) => strtolower(trim($workType)), $workTypes);

        return SitePricingProfile::query()
            ->active()
            ->where('site_id', $site->id)
            ->where(function ($query) use ($normalizedWorkTypes) {
                foreach ($normalizedWorkTypes as $workType) {
                    $query->orWhereRaw('LOWER(TRIM(COALESCE(work_type, ""))) = ?', [$workType]);
                }
            })
            ->where(function ($query) use ($turnaroundCode) {
                $query->whereRaw('LOWER(TRIM(COALESCE(turnaround_code, ""))) = ?', [$turnaroundCode])
                    ->orWhereNull('turnaround_code')
                    ->orWhere('turnaround_code', '');
            })
            ->orderByRaw('CASE WHEN LOWER(TRIM(COALESCE(turnaround_code, ""))) = ? THEN 0 ELSE 1 END', [$turnaroundCode])
            ->orderBy('id')
            ->first();
    }

    private static function customerRateOverride(?AdminUser $customer, string $turnaroundCode): ?float
    {
        if (! $customer) {
            return null;
        }

        $field = match ($turnaroundCode) {
            'normal' => 'middle_fee',
            'superrush' => 'super_fee',
            'priority', 'express' => 'urgent_fee',
            default => 'normal_fee',
        };

        $value = trim((string) ($customer->{$field} ?? ''));

        if ($value === '' || ! is_numeric($value) || (float) $value <= 0) {
            if ($turnaroundCode === 'normal') {
                $fallback = trim((string) ($customer->normal_fee ?? ''));

                return ($fallback !== '' && is_numeric($fallback) && (float) $fallback > 0) ? (float) $fallback : null;
            }

            return null;
        }

        return (float) $value;
    }

    private static function defaultEmbroideryRate(?SiteContext $site, string $turnaroundCode): ?float
    {
        $profile = self::profileFor($site, ['digitizing', 'embroidery'], self::normalizeTurnaround($turnaroundCode));

        return self::embroideryRateFromProfile($profile, self::normalizeTurnaround($turnaroundCode));
    }

    private static function embroideryRateFromProfile(?SitePricingProfile $profile, string $turnaroundCode): ?float
    {
        if ($profile) {
            $rate = match ((string) $profile->pricing_mode) {
                'fixed_price' => null,
                default => (float) ($profile->per_thousand_rate ?: 0),
            };

            if ($rate !== null && $rate > 0) {
                return $rate;
            }

            if ((float) ($profile->fixed_price ?: 0) > 0 && (float) ($profile->included_units ?: 0) > 0) {
                return ((float) $profile->fixed_price / (float) $profile->included_units) * 1000;
            }
        }

        return null;
    }

    private static function minimumCharge(?SitePricingProfile $profile, string $turnaroundCode, float $rate, bool $hasCustomerOverride): ?float
    {
        $configuredMinimum = ($profile && (float) ($profile->minimum_charge ?: 0) > 0)
            ? (float) $profile->minimum_charge
            : null;

        if ($configuredMinimum === null) {
            return null;
        }

        if ($hasCustomerOverride) {
            return max($configuredMinimum, round(6 * $rate, 2));
        }

        return $configuredMinimum;
    }

    private static function hourlyRateFromProfile(?SitePricingProfile $profile): ?float
    {
        if (! $profile) {
            return null;
        }

        $rate = (float) ($profile->overage_rate ?: $profile->fixed_price ?: $profile->per_thousand_rate ?: 0);

        return $rate > 0 ? $rate : null;
    }

    private static function maximumUnits(?AdminUser $customer, ?SitePricingProfile $profile): float
    {
        $customerCap = trim((string) ($customer?->max_num_stiches ?? ''));

        if ($customerCap !== '' && is_numeric($customerCap) && (float) $customerCap > 0) {
            return (float) $customerCap;
        }

        $includedUnits = (float) ($profile?->included_units ?: 0);

        return $includedUnits > 0 ? $includedUnits : 0.0;
    }

    private static function timeParts(string $totalHours): array
    {
        $totalHours = trim($totalHours);

        if ($totalHours === '') {
            return [0, 0];
        }

        if (preg_match('/^\d+$/', $totalHours) === 1) {
            return [(int) $totalHours, 0];
        }

        if (preg_match('/^(\d+):(\d{1,2})$/', $totalHours, $matches) !== 1) {
            return [0, 0];
        }

        return [(int) $matches[1], min(59, (int) $matches[2])];
    }
}
