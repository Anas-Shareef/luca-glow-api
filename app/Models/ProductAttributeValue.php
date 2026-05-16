<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductAttributeValue extends Model
{
    public $timestamps = false;
    protected $fillable = ['product_id', 'attribute_id', 'value'];
    public function product()   { return $this->belongsTo(Product::class); }
    public function attribute() { return $this->belongsTo(Attribute::class); }
}

// ─────────────────────────────────────────────────────────────