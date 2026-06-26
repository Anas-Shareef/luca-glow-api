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
        'quantity'          => 'integer',
        'unit_price_paise'  => 'integer',
        'subtotal_paise'    => 'integer',
    ];

    // ── Legacy/Compatibility Accessors and Mutators ────────────────
    public function getUnitPriceInrAttribute()
    {
        return (int) ($this->unit_price_paise / 100);
    }

    public function setUnitPriceInrAttribute($value)
    {
        $this->attributes['unit_price_paise'] = $value * 100;
    }

    public function getSubtotalInrAttribute()
    {
        return (int) ($this->subtotal_paise / 100);
    }

    public function setSubtotalInrAttribute($value)
    {
        $this->attributes['subtotal_paise'] = $value * 100;
    }

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
