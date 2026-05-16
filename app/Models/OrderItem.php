<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_sku',
        'quantity', 'unit_price_inr', 'subtotal_inr', 'variant_info',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'unit_price_inr' => 'integer',
        'subtotal_inr'   => 'integer',
    ];

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
