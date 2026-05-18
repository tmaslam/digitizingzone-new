<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

class LegacyQuerySupport
{
    private static array $columnTypes = [];

    public static function applyActiveEndDate(EloquentBuilder|Builder $query, string $table, string $column = 'end_date'): EloquentBuilder|Builder
    {
        return $query->where(function ($activeQuery) use ($table, $column) {
            $activeQuery->whereNull($column);

            if (self::allowsBlankString($table, $column)) {
                $activeQuery->orWhere($column, '');
            }

            $activeQuery
                ->orWhere($column, '0000-00-00')
                ->orWhere($column, '0000-00-00 00:00:00');
        });
    }

    public static function applyBlankOrZeroFlag(EloquentBuilder|Builder $query, string $table, string $column): EloquentBuilder|Builder
    {
        return $query->where(function ($flagQuery) use ($table, $column) {
            $flagQuery->whereNull($column);

            if (self::allowsBlankString($table, $column)) {
                $flagQuery->orWhere($column, '');
            }

            $flagQuery
                ->orWhere($column, '0')
                ->orWhere($column, 0);
        });
    }

    private static function allowsBlankString(string $table, string $column): bool
    {
        $type = self::columnType($table, $column);

        return in_array($type, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set'], true);
    }

    private static function columnType(string $table, string $column): ?string
    {
        $cacheKey = $table.'.'.$column;

        if (array_key_exists($cacheKey, self::$columnTypes)) {
            return self::$columnTypes[$cacheKey];
        }

        if (! Schema::hasTable($table)) {
            return self::$columnTypes[$cacheKey] = null;
        }

        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return self::$columnTypes[$cacheKey] = strtolower((string) ($definition['type_name'] ?? $definition['type'] ?? ''));
            }
        }

        return self::$columnTypes[$cacheKey] = null;
    }
}
