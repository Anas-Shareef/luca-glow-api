<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'reason', 'description',
        'status', 'admin_note', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function order()  { return $this->belongsTo(Order::class); }
    public function user()   { return $this->belongsTo(User::class); }
}
