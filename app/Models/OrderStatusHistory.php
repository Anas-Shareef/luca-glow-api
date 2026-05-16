<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'from_status', 'to_status',
        'comment', 'customer_notified', 'changed_by',
    ];

    protected $casts = [
        'customer_notified' => 'boolean'
    ];

    public function order()     { return $this->belongsTo(Order::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
