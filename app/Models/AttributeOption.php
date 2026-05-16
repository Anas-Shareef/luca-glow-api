<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AttributeOption extends Model
{
    public $timestamps = false;
    protected $fillable = ['attribute_id', 'label', 'value', 'sort_order'];
    public function attribute() { return $this->belongsTo(Attribute::class); }
}
