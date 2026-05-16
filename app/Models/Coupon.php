<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_cart_value',
        'usage_limit', 'usage_per_customer', 'used_count',
        'is_active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($this->starts_at  && now()->lt($this->starts_at))  return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        return true;
    }

    public function calculateDiscount(int $cartTotal): int
    {
        if (!$this->isValid()) return 0;
        if ($this->min_cart_value && $cartTotal < $this->min_cart_value) return 0;

        return $this->type === 'percentage'
            ? (int) round($cartTotal * $this->value / 100)
            : min((int) $this->value, $cartTotal);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}

// ─────────────────────────────────────────────────────────────