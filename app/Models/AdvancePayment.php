<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvancePayment extends Model
{
    protected $table = 'advancepayment';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}
