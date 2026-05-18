<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteNegotiation extends Model
{
    protected $table = 'quote_negotiations';

    public $timestamps = false;

    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(AdminUser::class, 'customer_user_id', 'user_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending_admin_review', 'counter_offered', 'customer_replied']);
    }
}
