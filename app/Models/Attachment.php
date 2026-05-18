<?php

namespace App\Models;

use App\Support\LegacyDate;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'attach_files';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    public function getDateAddedAttribute(mixed $value): ?string
    {
        return LegacyDate::normalize($value);
    }
}
