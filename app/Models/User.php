<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'name', 'email', 'password',
        'customer_group_id', 'is_active',
        'phone', 'skin_type', 'skin_concern', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_login_at'  => 'datetime',
        'email_verified_at' => 'datetime',
        'password'       => 'hashed',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    // ── Accessors ─────────────────────────────────────────────

    /**
     * Total spend across all COMPLETED orders — used for VIP segmentation
     * and displayed in the admin customer profile.
     */
    protected function totalSpend(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orders()
                ->where('status', 'delivered')
                ->sum('total_amount_inr')
        );
    }

    protected function totalOrders(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->orders()->count()
        );
    }

    protected function avgOrderValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                $count = $this->total_orders;
                return $count > 0 ? (int) ($this->total_spend / $count) : 0;
            }
        );
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', $this->name))
                ->map(fn ($n) => strtoupper($n[0] ?? ''))
                ->take(2)
                ->implode('')
        );
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeSearch($q, string $term)
    {
        return $q->where(function ($query) use ($term) {
            $query->where('name',  'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ── Group Automation ─────────────────────────────────────
    public function checkAndUpgradeGroup(): void
    {
        $this->loadMissing('customerGroup');
        $groupName = $this->customerGroup?->name ?? 'First-Time';

        // 1. Move from First-Time to Regular on first order
        if ($groupName === 'First-Time' && $this->total_orders > 0) {
            $regular = CustomerGroup::where('name', 'Regular')->first();
            if ($regular) {
                $this->update(['customer_group_id' => $regular->id]);
                $groupName = 'Regular';
            }
        }

        // 2. Move to VIP if spend threshold met
        $vip = CustomerGroup::where('name', 'VIP')->first();
        if ($vip && $groupName !== 'VIP' && $groupName !== 'Wholesale') {
            $threshold = $vip->auto_upgrade_threshold ?? 10000;
            if ($this->total_spend >= $threshold) {
                $this->update(['customer_group_id' => $vip->id]);
            }
        }
    }
    // ── Relationships ─────────────────────────────────────────
    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }
}

