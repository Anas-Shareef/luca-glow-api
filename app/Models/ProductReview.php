<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductReview extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'product_id',
        'user_id',
        'reviewer_name',
        'reviewer_email',
        'rating',
        'title',
        'comment',
        'is_published',
        'is_verified_purchase',
        'is_manual',
        'helpful_count',
    ];

    protected $casts = [
        'is_published'         => 'boolean',
        'is_verified_purchase' => 'boolean',
        'is_manual'            => 'boolean',
        'rating'               => 'integer',
        'helpful_count'        => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
