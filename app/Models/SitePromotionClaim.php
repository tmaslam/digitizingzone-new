<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePromotionClaim extends Model
{
    protected $table = 'site_promotion_claims';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'verification_required' => 'bool',
        'payment_required' => 'bool',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function promotion()
    {
        return $this->belongsTo(SitePromotion::class, 'site_promotion_id');
    }

    public function customer()
    {
        return $this->belongsTo(AdminUser::class, 'user_id', 'user_id');
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function redeemedOrder()
    {
        return $this->belongsTo(Order::class, 'redeemed_order_id', 'order_id');
    }
}
