<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransactionItem extends Model
{
    protected $table = 'payment_transaction_items';

    public $timestamps = false;

    protected $guarded = [];

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id', 'bill_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
