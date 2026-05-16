<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Attribute extends Model
{
    protected $fillable = ['code', 'name', 'input_type', 'is_filterable', 'sort_order'];
    protected $casts    = ['is_filterable' => 'boolean'];

    public function options()
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }
}
