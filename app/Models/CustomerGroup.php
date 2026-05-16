<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CustomerGroup extends Model
{
    protected $fillable = ['name', 'color', 'discount_pct', 'auto_upgrade_threshold'];
    protected $casts    = ['discount_pct' => 'float'];

    public function customers()
    {
        return $this->hasMany(User::class, 'customer_group_id');
    }
}
