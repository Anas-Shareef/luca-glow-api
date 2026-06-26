<?php
// ============================================================
// Product.php
// ============================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'category_id', 'sku', 'name', 'slug', 'type',
        'description', 'ingredients', 'how_to_use',
        'skin_type', 'gender', 'volume',
        'price_inr', 'special_price',
        'special_price_starts_at', 'special_price_ends_at',
        'stock_quantity', 'low_stock_threshold',
        'meta_title', 'meta_description', 'shipping_returns', 'is_active',
    ];

    protected $casts = [
        'is_active'               => 'boolean',
        'special_price_starts_at' => 'datetime',
        'special_price_ends_at'   => 'datetime',
        'price_paise'             => 'integer',
        'compare_at_price_paise'  => 'integer',
        'stock'                   => 'integer',
    ];

    // ── Legacy/Compatibility Accessors and Mutators ────────────────
    public function getPriceInrAttribute()
    {
        return (int) (($this->compare_at_price_paise ?: $this->price_paise) / 100);
    }

    public function setPriceInrAttribute($value)
    {
        $this->attributes['compare_at_price_paise'] = $value * 100;
    }

    public function getSpecialPriceAttribute()
    {
        if (!$this->compare_at_price_paise) {
            return null;
        }
        return (int) ($this->price_paise / 100);
    }

    public function setSpecialPriceAttribute($value)
    {
        if ($value === null) {
            if (isset($this->attributes['compare_at_price_paise'])) {
                $this->attributes['price_paise'] = $this->attributes['compare_at_price_paise'];
                unset($this->attributes['compare_at_price_paise']);
            }
            $this->attributes['compare_at_price_paise'] = null;
        } else {
            $this->attributes['price_paise'] = $value * 100;
        }
    }

    public function getStockQuantityAttribute()
    {
        return (int) $this->stock;
    }

    public function setStockQuantityAttribute($value)
    {
        $this->attributes['stock'] = $value;
    }

    public function getLowStockThresholdAttribute()
    {
        return 10;
    }

    public function setLowStockThresholdAttribute($value)
    {
        // low_stock_threshold is ignored as it doesn't exist in Supabase DB
    }

    // ── Relationships ─────────────────────────────────────────
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attributeValues()
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    // ── Spatie Media Library ──────────────────────────────────
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)->height(200)
            ->format('webp')
            ->performOnCollections('gallery');

        $this->addMediaConversion('medium')
            ->width(600)->height(600)
            ->format('webp')
            ->performOnCollections('gallery');
    }

    // ── Accessors ─────────────────────────────────────────────

    /** Effective selling price — respects sale schedule */
    protected function effectivePrice(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->special_price) return $this->price_inr;

                $now = now();
                $inWindow =
                    (!$this->special_price_starts_at || $now->gte($this->special_price_starts_at)) &&
                    (!$this->special_price_ends_at   || $now->lte($this->special_price_ends_at));

                return $inWindow ? $this->special_price : $this->price_inr;
            }
        );
    }

    protected function discountPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->special_price || !$this->price_inr) return null;
                return round((($this->price_inr - $this->special_price) / $this->price_inr) * 100);
            }
        );
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stock_quantity <= $this->low_stock_threshold
        );
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $media = $this->getFirstMedia('gallery');
                return $media ? $media->getUrl('medium') : null;
            }
        );
    }

    protected function newArrival(): Attribute
    {
        return Attribute::make(
            get: function () {
                $cutoff = \Illuminate\Support\Facades\Cache::remember('new_arrival_cutoff', 3600, function() {
                    $threshold = now()->subDays(90);
                    $hasNew = \App\Models\Product::where('created_at', '>', $threshold)->exists();
                    if ($hasNew) {
                        return $threshold;
                    }
                    $twelfth = \App\Models\Product::orderBy('created_at', 'desc')
                        ->skip(11)
                        ->first();
                    return $twelfth ? $twelfth->created_at : now()->subDays(365);
                });

                return $this->created_at && $this->created_at->gte($cutoff);
            }
        );
    }

    protected function bestSeller(): Attribute
    {
        return Attribute::make(
            get: function () {
                $bestsellerIds = \Illuminate\Support\Facades\Cache::remember('bestseller_product_ids', 3600, function() {
                    $ids = \App\Models\Product::has('orderItems', '>=', 3)
                        ->pluck('id')
                        ->toArray();
                    
                    if (count($ids) >= 4) {
                        return $ids;
                    }
                    
                    return \App\Models\Product::withCount('orderItems')
                        ->orderBy('order_items_count', 'desc')
                        ->orderBy('price_inr', 'desc')
                        ->take(12)
                        ->pluck('id')
                        ->toArray();
                });

                return in_array($this->id, $bestsellerIds);
            }
        );
    }

    /** Returns an array of dynamic tags for the product */
    protected function dynamicTags(): Attribute
    {
        return Attribute::make(
            get: function () {
                $tags = [];
                if ($this->new_arrival)    $tags[] = 'New Arrival';
                if ($this->best_seller)    $tags[] = 'Best Seller';
                if ($this->is_low_stock)   $tags[] = 'Low Stock';
                if ($this->discount_percentage > 0) $tags[] = 'Sale';
                
                // Dynamic Trendy/Featured logic
                if ($this->best_seller || $this->new_arrival) $tags[] = 'Trendy';
                // Mock rating check for Featured (assuming rating is handled or mocked in controller)
                // For now, if it's a best seller, it's also featured
                if ($this->best_seller) $tags[] = 'Featured';

                return $tags;
            }
        );
    }

    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function () {
                return round($this->reviews()->where('is_published', true)->avg('rating') ?: 0, 1);
            }
        );
    }

    protected function reviewCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviews()->where('is_published', true)->count()
        );
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('stock', '<=', 10);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku',  'like', "%{$term}%");
        });
    }
}
