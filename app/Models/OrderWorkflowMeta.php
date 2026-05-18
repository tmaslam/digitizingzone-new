<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderWorkflowMeta extends Model
{
    protected $table = 'order_workflow_meta';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }
}
