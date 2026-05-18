<?php

namespace App\Models;

use App\Support\LegacyDate;
use Illuminate\Database\Eloquent\Model;

class OrderComment extends Model
{
    protected $table = 'comments';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    public function getDateAddedAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }

    public function getDateModifiedAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
