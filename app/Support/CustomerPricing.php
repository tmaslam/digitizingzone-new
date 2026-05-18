<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class CustomerPricing
{
    public static function sitePricingPayload(SiteContext $site): array
    {
        $rates = SitePricing::customerDefaultRates($site);

        return self::filterExistingColumns([
            'normal_fee' => self::storedFeeValue('normal_fee', $rates['normal_fee'] ?? null),
            'middle_fee' => self::storedFeeValue('middle_fee', $rates['middle_fee'] ?? null),
            'urgent_fee' => self::storedFeeValue('urgent_fee', $rates['urgent_fee'] ?? null),
            'super_fee' => self::storedFeeValue('super_fee', $rates['super_fee'] ?? null),
        ]);
    }

    public static function customPricingPayload(array $input): array
    {
        return self::filterExistingColumns([
            'normal_fee' => self::storedFeeValue('normal_fee', self::nullableNumeric($input['normal_fee'] ?? null)),
            'middle_fee' => self::storedFeeValue('middle_fee', self::nullableNumeric($input['middle_fee'] ?? null)),
            'urgent_fee' => self::storedFeeValue('urgent_fee', self::nullableNumeric($input['urgent_fee'] ?? null)),
            'super_fee' => self::storedFeeValue('super_fee', self::nullableNumeric($input['super_fee'] ?? null)),
        ]);
    }

    private static function nullableNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private static function storedFeeValue(string $column, ?float $value): ?float
    {
        if ($value !== null) {
            return round($value, 2);
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', $column)) {
            return null;
        }

        static $nullableCache = [];

        if (! array_key_exists($column, $nullableCache)) {
            $columnMeta = collect(Schema::getColumns('users'))
                ->firstWhere('name', $column);

            $nullableCache[$column] = (bool) ($columnMeta['nullable'] ?? true);
        }

        return $nullableCache[$column] ? null : 0.0;
    }

    private static function filterExistingColumns(array $payload): array
    {
        if (! Schema::hasTable('users')) {
            return $payload;
        }

        $columns = collect(Schema::getColumns('users'))
            ->pluck('name')
            ->flip()
            ->all();

        return array_intersect_key($payload, $columns);
    }
}
