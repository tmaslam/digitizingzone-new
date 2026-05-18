<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    public $timestamps = false;

    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function customer()
    {
        return $this->belongsTo(AdminUser::class, 'user_id', 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function items()
    {
        return $this->hasMany(PaymentTransactionItem::class, 'payment_transaction_id');
    }
}
