<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'phone',
        'address_line_1', 'address_line_2',
        'city', 'state', 'pincode', 'country', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Convert to the shape the frontend expects */
    public function toApiArray(): array
    {
        return [
            'id'          => (string) $this->id,
            'fullName'    => $this->full_name,
            'phone'       => $this->phone,
            'street'      => $this->address_line_1,
            'apt'         => $this->address_line_2,
            'city'        => $this->city,
            'state'       => $this->state,
            'zip'         => $this->pincode,
            'country'     => $this->country,
            'isDefault'   => $this->is_default,
        ];
    }
}
