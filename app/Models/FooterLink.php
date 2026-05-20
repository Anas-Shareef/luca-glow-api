<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FooterLink extends Model
{
    protected $fillable = ['footer_section_id', 'label', 'url', 'order'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(FooterSection::class, 'footer_section_id');
    }
}
