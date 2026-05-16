<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'banner_image', 'sort_order', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function parent()   { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order'); }
    public function products() { return $this->hasMany(Product::class); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner')->singleFile();
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeTopLevel($q) { return $q->whereNull('parent_id'); }
}

// ─────────────────────────────────────────────────────────────