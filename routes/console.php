<?php
// routes/console.php
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use App\Models\Coupon;

// ── Scheduled tasks ────────────────────────────────────────────────────────────

Artisan::command('lucaglow:auto-upgrade-customers', function () {
    $upgraded = 0;
    User::with('customerGroup')
        ->whereHas('customerGroup', fn ($q) => $q->where('name', '!=', 'VIP'))
        ->chunk(100, function ($users) use (&$upgraded) {
            foreach ($users as $user) {
                $before = $user->customer_group_id;
                $user->checkAndUpgradeGroup();
                if ($user->fresh()->customer_group_id !== $before) $upgraded++;
            }
        });
    $this->info("✅ Auto-upgraded {$upgraded} customer(s) to VIP.");
})->purpose('Automatically promote customers to VIP based on spend threshold');

Artisan::command('lucaglow:cleanup-expired-coupons', function () {
    $count = Coupon::where('expires_at', '<', now())
        ->where('is_active', true)
        ->update(['is_active' => false]);
    $this->info("✅ Deactivated {$count} expired coupon(s).");
})->purpose('Deactivate all expired coupons');

Artisan::command('lucaglow:low-stock-report', function () {
    $products = \App\Models\Product::active()->lowStock()->get(['name', 'sku', 'stock_quantity', 'low_stock_threshold']);
    if ($products->isEmpty()) {
        $this->info('✅ No low-stock products found.');
        return;
    }
    $this->table(['Name', 'SKU', 'Stock', 'Threshold'],
        $products->map(fn ($p) => [$p->name, $p->sku, $p->stock_quantity, $p->low_stock_threshold])->toArray()
    );
})->purpose('List all products below their low-stock threshold');

// ── Schedule registration ──────────────────────────────────────────────────────
Schedule::command('lucaglow:auto-upgrade-customers')->dailyAt('02:00');
Schedule::command('lucaglow:cleanup-expired-coupons')->daily();
