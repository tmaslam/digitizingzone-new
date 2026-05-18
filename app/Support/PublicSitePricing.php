<?php

namespace App\Support;

use App\Models\SitePricingProfile;
use App\Models\SitePromotion;
use Illuminate\Support\Facades\Schema;

class PublicSitePricing
{
    public static function forSite(SiteContext $site): array
    {
        $profiles = collect();
        if ($site->id && Schema::hasTable('site_pricing_profiles')) {
            $profiles = SitePricingProfile::query()
                ->active()
                ->where('site_id', $site->id)
                ->orderBy('work_type')
                ->orderBy('turnaround_code')
                ->get()
                ->map(fn (SitePricingProfile $profile) => self::mapProfile($profile));
        }

        $promotions = collect();
        if ($site->id && Schema::hasTable('site_promotions')) {
            $now = now()->format('Y-m-d H:i:s');
            $promotions = SitePromotion::query()
                ->active()
                ->where('site_id', $site->id)
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->orderByDesc('discount_value')
                ->get()
                ->map(fn (SitePromotion $promotion) => self::mapPromotion($promotion));
        }

        return [
            'profiles' => $profiles->values()->all(),
            'promotions' => $promotions->values()->all(),
            'notes' => [
                'Digitizing pricing is based mainly on stitch count and turnaround time.',
                'Vector work pricing depends on the configured turnaround profile and artwork complexity.',
                'Minor edits are free.',
            ],
        ];
    }

    private static function mapProfile(SitePricingProfile $profile): array
    {
        $summary = match ((string) $profile->pricing_mode) {
            'fixed_price' => '$'.number_format((float) $profile->fixed_price, 2).' fixed',
            'per_thousand' => '$'.number_format((float) $profile->per_thousand_rate, 2).' / 1,000 stitches',
            'package' => trim((string) ($profile->package_name ?: 'Package pricing')),
            default => '$'.number_format((float) ($profile->per_thousand_rate ?: $profile->fixed_price ?: 0), 2).' '.((float) ($profile->per_thousand_rate ?: 0) > 0 ? '/ 1,000 stitches' : 'starting rate'),
        };

        return [
            'title' => self::workTypeLabel((string) $profile->work_type),
            'turnaround' => self::turnaroundLabel((string) $profile->turnaround_code),
            'summary' => $summary,
            'minimum' => (float) $profile->minimum_charge > 0 ? '$'.number_format((float) $profile->minimum_charge, 2).' minimum' : null,
            'details' => trim((string) $profile->profile_name),
        ];
    }

    private static function mapPromotion(SitePromotion $promotion): array
    {
        $discount = match ((string) $promotion->discount_type) {
            'percent', 'percentage' => number_format((float) $promotion->discount_value, 2).'% off',
            default => '$'.number_format((float) $promotion->discount_value, 2).' off',
        };

        return [
            'name' => $promotion->promotion_name,
            'code' => $promotion->promotion_code,
            'work_type' => self::workTypeLabel((string) ($promotion->work_type ?: 'all')),
            'discount' => $discount,
            'minimum_order' => (float) $promotion->minimum_order_amount > 0 ? '$'.number_format((float) $promotion->minimum_order_amount, 2) : null,
        ];
    }

    private static function workTypeLabel(string $workType): string
    {
        return match (strtolower($workType)) {
            'vector', 'q-vector' => 'Vector Art',
            'color', 'qcolor' => 'Color Separation',
            'all', '' => 'All Services',
            default => 'Embroidery Digitizing',
        };
    }

    private static function turnaroundLabel(string $turnaround): string
    {
        return match (strtolower($turnaround)) {
            'priority' => 'Priority / 12 Hours',
            'superrush' => 'Super Rush / 6 Hours',
            'standard', '' => 'Standard / 24 Hours',
            default => ucfirst(str_replace('_', ' ', $turnaround)),
        };
    }
}
