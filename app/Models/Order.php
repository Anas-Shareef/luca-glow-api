<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_id',
        'total_amount_inr', 'subtotal_inr', 'shipping_amount_inr',
        'tax_amount_inr', 'discount_amount_inr', 'coupon_code',
        'status', 'payment_method', 'payment_status', 'refund_status', 'notes',
    ];

    protected $casts = [
        'total_amount_inr'    => 'integer',
        'subtotal_inr'        => 'integer',
        'shipping_amount_inr' => 'integer',
        'tax_amount_inr'      => 'integer',
        'discount_amount_inr' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses()
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function shippingAddress()
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress()
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function refundRequest()
    {
        return $this->hasOne(RefundRequest::class)->latest();
    }

    // ── Accessors ─────────────────────────────────────────────
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => '₹' . number_format($this->total_amount_inr, 0, '.', ',')
        );
    }

    // ── Boot — auto-generate order number ────────────────────
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (!$order->order_number) {
                $order->order_number = 'LG-' . now()->year . '-' . str_pad(
                    static::withTrashed()->count() + 1,
                    4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'like', "%{$term}%")
              ->orWhereHas('customer', fn ($cq) =>
                  $cq->where('name', 'like', "%{$term}%")
                     ->orWhere('phone', 'like', "%{$term}%")
              );
        });
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);
        return $query;
    }
}
