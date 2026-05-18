<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderEvent extends Model
{
    protected $table = 'payment_provider_events';

    public $timestamps = false;

    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
