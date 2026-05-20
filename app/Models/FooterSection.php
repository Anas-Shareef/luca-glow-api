<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FooterSection extends Model
{
    protected $fillable = ['title', 'order', 'is_active'];

    public function links(): HasMany
    {
        return $this->hasMany(FooterLink::class)->orderBy('order');
    }
}
