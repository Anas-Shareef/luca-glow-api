<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\CustomerGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Coupon;
use App\Models\Slider;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles & Permissions ────────────────────────────────
        $roles = ['Super Admin', 'Admin', 'Viewer'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
        }

        $permissions = [
            'view dashboard',
            'manage products', 'manage categories',
            'view orders', 'manage orders',
            'view customers', 'manage customers',
            'manage marketing', 'manage settings',
            'manage staff',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // Assign permissions to roles
        Role::findByName('Super Admin', 'sanctum')->givePermissionTo(Permission::all());
        Role::findByName('Admin',       'sanctum')->givePermissionTo([
            'view dashboard', 'manage products', 'manage categories', 
            'view orders', 'manage orders', 'view customers', 
            'manage customers', 'manage marketing'
        ]);
        Role::findByName('Viewer',      'sanctum')->givePermissionTo(['view dashboard', 'view orders', 'view customers']);

        // ── Super Admin User ───────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@lucaglow.com'],
            ['name' => 'Luca Admin', 'password' => Hash::make('password'), 'is_active' => true]
        );
        $admin->assignRole('Super Admin');

        // ── Customer Groups ────────────────────────────────────
        $groups = [
            ['name' => 'Regular',     'color' => '#94a3b8', 'discount_pct' => 0,    'auto_upgrade_threshold' => null],
            ['name' => 'VIP',         'color' => '#f59e0b', 'discount_pct' => 5,    'auto_upgrade_threshold' => 10000],
            ['name' => 'Wholesale',   'color' => '#8b5cf6', 'discount_pct' => 15,   'auto_upgrade_threshold' => null],
            ['name' => 'First-Time',  'color' => '#10b981', 'discount_pct' => 0,    'auto_upgrade_threshold' => null],
        ];
        foreach ($groups as $g) {
            CustomerGroup::firstOrCreate(['name' => $g['name']], $g);
        }

        // ── Categories ─────────────────────────────────────────
        $catData = [
            ['name' => 'Skincare & Face',  'slug' => 'skincare-face'],
            ['name' => 'Cleansing Soaps',  'slug' => 'cleansing-soaps'],
            ['name' => 'Lykha Makeup',     'slug' => 'lykha-makeup'],
            ['name' => 'Fragrances',       'slug' => 'fragrances'],
            ['name' => 'Body & Hair Care', 'slug' => 'body-hair-care'],
        ];

        $cats = [];
        foreach ($catData as $c) {
            $cats[$c['slug']] = Category::firstOrCreate(
                ['slug' => $c['slug']],
                array_merge($c, ['is_active' => true, 'sort_order' => array_search($c, $catData)])
            );
        }

        // Sub-categories
        Category::firstOrCreate(['slug' => 'men'], [
            'name'      => 'For Men',
            'slug'      => 'men',
            'parent_id' => $cats['body-hair-care']->id,
            'is_active' => true,
        ]);

        // ── Global Attributes ──────────────────────────────────
        $skinType = Attribute::firstOrCreate(
            ['code' => 'skin_type'],
            ['name' => 'Skin Type', 'input_type' => 'select', 'is_filterable' => true]
        );
        $skinTypeOpts = ['All Skin Types', 'Dry', 'Oily', 'Combination', 'Sensitive', 'Normal'];
        foreach ($skinTypeOpts as $i => $opt) {
            AttributeOption::firstOrCreate(
                ['attribute_id' => $skinType->id, 'value' => strtolower(str_replace(' ', '_', $opt))],
                ['label' => $opt, 'sort_order' => $i]
            );
        }

        $volume = Attribute::firstOrCreate(
            ['code' => 'volume'],
            ['name' => 'Volume / Pack Size', 'input_type' => 'select', 'is_filterable' => false]
        );
        foreach (['20ml', '30ml', '50ml', '100ml', '150ml', '200ml'] as $i => $vol) {
            AttributeOption::firstOrCreate(
                ['attribute_id' => $volume->id, 'value' => $vol],
                ['label' => $vol, 'sort_order' => $i]
            );
        }

        // ── Products (real Luca Glow brand lineup) ─────────────
        $products = [
            ['sku' => 'LG-FC-001', 'name' => 'Luca Face Cream',               'cat' => 'skincare-face',  'price' => 1899, 'sale' => 1599, 'stock' => 234],
            ['sku' => 'LG-SP-002', 'name' => 'Sun Protection SPF 50+',        'cat' => 'skincare-face',  'price' => 999,  'sale' => null,  'stock' => 112],
            ['sku' => 'LG-KF-003', 'name' => 'Kojic Facewash',                'cat' => 'skincare-face',  'price' => 349,  'sale' => null,  'stock' => 8  ],
            ['sku' => 'LG-GS-004', 'name' => 'Gluta Soap',                   'cat' => 'cleansing-soaps','price' => 350,  'sale' => 299,  'stock' => 545],
            ['sku' => 'LG-KS-005', 'name' => 'Kojic Soap',                   'cat' => 'cleansing-soaps','price' => 350,  'sale' => null,  'stock' => 321],
            ['sku' => 'LY-FR-006', 'name' => 'Lykha Foundation — Rosy Brown', 'cat' => 'lykha-makeup',   'price' => 2499, 'sale' => null,  'stock' => 67 ],
            ['sku' => 'LY-FD-007', 'name' => 'Lykha Foundation — Dust',      'cat' => 'lykha-makeup',   'price' => 2499, 'sale' => null,  'stock' => 42 ],
            ['sku' => 'LY-LS-008', 'name' => 'Lykha 4-in-1 Lipstick',        'cat' => 'lykha-makeup',   'price' => 999,  'sale' => null,  'stock' => 189],
            ['sku' => 'LG-PF-009', 'name' => 'Luca Perfume (Full Size)',      'cat' => 'fragrances',     'price' => 999,  'sale' => null,  'stock' => 0  ],
            ['sku' => 'LG-PP-010', 'name' => 'Luca Pocket Perfume',          'cat' => 'fragrances',     'price' => 399,  'sale' => null,  'stock' => 301],
            ['sku' => 'LG-BO-011', 'name' => 'Luca Beard Oil',               'cat' => 'body-hair-care', 'price' => 1299, 'sale' => null,  'stock' => 156, 'inactive' => true],
            ['sku' => 'LG-HO-012', 'name' => 'Luca Nourishing Hair Oil',     'cat' => 'body-hair-care', 'price' => 799,  'sale' => null,  'stock' => 278],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['sku' => $p['sku']], [
                'category_id'        => $cats[$p['cat']]->id,
                'name'               => $p['name'],
                'slug'               => str($p['name'])->slug(),
                'type'               => 'simple',
                'price_inr'          => $p['price'],
                'special_price'      => $p['sale'],
                'stock_quantity'     => $p['stock'],
                'low_stock_threshold'=> 10,
                'is_active'          => !($p['inactive'] ?? false),
            ]);
        }

        // ── Coupons ────────────────────────────────────────────
        $coupons = [
            ['code' => 'GLOW2026',   'type' => 'percentage', 'value' => 15,  'min' => 999,  'limit' => 500,  'expiry' => '2026-01-31'],
            ['code' => 'WELCOME100', 'type' => 'fixed',      'value' => 100, 'min' => 599,  'limit' => 1000, 'expiry' => '2026-12-31'],
            ['code' => 'LYKHA10',    'type' => 'percentage', 'value' => 10,  'min' => 2000, 'limit' => 300,  'expiry' => '2026-03-31'],
            ['code' => 'FREESHIP',   'type' => 'fixed',      'value' => 79,  'min' => 499,  'limit' => null, 'expiry' => null],
        ];

        foreach ($coupons as $c) {
            Coupon::firstOrCreate(['code' => $c['code']], [
                'type'           => $c['type'],
                'value'          => $c['value'],
                'min_cart_value' => $c['min'],
                'usage_limit'    => $c['limit'],
                'is_active'      => true,
                'expires_at'     => $c['expiry'],
            ]);
        }

        // ── Sliders ────────────────────────────────────────────
        $sliders = [
            ['title' => 'Feel The Change',    'subtitle' => 'Clean Beauty for Everyone',    'link' => '/catalog',               'sort' => 1],
            ['title' => 'Glow Beyond Limits', 'subtitle' => 'Lykha Makeup Collection',      'link' => '/collections/lykha-makeup', 'sort' => 2],
            ['title' => 'Refined Grooming',   'subtitle' => 'For the Modern Man',           'link' => '/collections/men',       'sort' => 3],
        ];

        foreach ($sliders as $s) {
            Slider::firstOrCreate(['title' => $s['title']], [
                'subtitle'   => $s['subtitle'],
                'link_url'   => $s['link'],
                'sort_order' => $s['sort'],
                'is_active'  => true,
            ]);
        }

        // ── Global Settings ────────────────────────────────────
        $settings = [
            ['key' => 'store_name',      'value' => 'Luca Glow',            'group' => 'general'],
            ['key' => 'tagline',         'value' => 'Feel The Change',       'group' => 'general'],
            ['key' => 'support_email',   'value' => 'hello@lucaglow.com',    'group' => 'general'],
            ['key' => 'support_phone',   'value' => '+91 95670 46209',       'group' => 'general'],
            ['key' => 'address',         'value' => 'Kozhikode, Kerala, India — 673001', 'group' => 'general'],
            ['key' => 'maintenance',     'value' => '0',                     'group' => 'general'],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(['key' => $s['key']], $s);
        }

        $this->command->info('✅  Luca Glow seed complete — 12 products, 5 categories, 4 coupons, 3 sliders.');
    }
}
