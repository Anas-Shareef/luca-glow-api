<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'type', 'full_name', 'phone',
        'address_line_1', 'address_line_2',
        'city', 'state', 'pincode', 'country',
    ];

    public function order() { return $this->belongsTo(Order::class); }
}
