<?php
// ============================================================
// MIGRATION: 2024_01_01_000001_create_customer_groups_table.php
// ============================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// --- Customer Groups ---
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();           // VIP, Regular, Wholesale, First-Time
            $table->string('color', 20)->nullable();        // hex for badge
            $table->decimal('discount_pct', 5, 2)->default(0); // group-wide discount
            $table->unsignedInteger('auto_upgrade_threshold')->nullable(); // ₹ spend threshold
            $table->timestamps();
        });

        // --- Users / Customers ---
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('phone', 20)->nullable();
            $table->string('skin_type', 30)->nullable();    // Dry, Oily, Combination, Sensitive, Normal
            $table->string('skin_concern', 60)->nullable(); // Acne, Aging, Pigmentation…
            $table->timestamp('last_login_at')->nullable();
        });

        // --- Categories (nested set / adjacency list) ---
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('banner_image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'is_active']);
        });

        // --- Attributes ---
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();           // skin_type, volume, fragrance_profile
            $table->string('name', 100);
            $table->enum('input_type', ['select', 'multiselect', 'text', 'boolean'])->default('select');
            $table->boolean('is_filterable')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('value', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        // --- Products ---
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->enum('type', ['simple', 'configurable'])->default('simple');
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('how_to_use')->nullable();
            $table->string('skin_type', 50)->nullable();
            $table->string('volume', 30)->nullable();

            // Pricing (INR)
            $table->unsignedInteger('price_inr');
            $table->unsignedInteger('special_price')->nullable();
            $table->timestamp('special_price_starts_at')->nullable();
            $table->timestamp('special_price_ends_at')->nullable();

            // Inventory
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(10);

            // SEO
            $table->string('meta_title', 120)->nullable();
            $table->text('meta_description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sku', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('slug');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 200);
            $table->unique(['product_id', 'attribute_id']);
        });

        // --- Orders ---
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();   // LG-2025-1284
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('total_amount_inr');
            $table->unsignedInteger('subtotal_inr');
            $table->unsignedInteger('shipping_amount_inr')->default(79);
            $table->unsignedInteger('tax_amount_inr')->default(0);
            $table->unsignedInteger('discount_amount_inr')->default(0);
            $table->string('coupon_code', 40)->nullable();
            $table->enum('status', ['pending','processing','shipped','delivered','cancelled'])->default('pending');
            $table->string('payment_method', 40)->default('cod');
            $table->string('payment_status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('customer_id');
            $table->index('order_number');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);            // snapshot at time of order
            $table->string('product_sku',  80);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_inr');      // price at time of sale
            $table->unsignedInteger('subtotal_inr');
            $table->string('variant_info', 100)->nullable(); // "50ml / Oily Skin"
            $table->index('order_id');
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing', 'shipping']);
            $table->string('full_name', 120);
            $table->string('phone', 20);
            $table->string('address_line_1', 200);
            $table->string('address_line_2', 200)->nullable();
            $table->string('city', 80);
            $table->string('state', 80)->default('Kerala');
            $table->string('pincode', 10);
            $table->string('country', 60)->default('India');
            $table->unique(['order_id', 'type']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('comment')->nullable();
            $table->boolean('customer_notified')->default(false);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });

        // --- Coupons ---
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->unsignedInteger('value');               // % or ₹
            $table->unsignedInteger('min_cart_value')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_customer')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['code', 'is_active']);
        });

        // --- Sliders ---
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('subtitle', 200)->nullable();
            $table->string('button_text', 60)->nullable();
            $table->string('link_url', 250)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // --- Settings key-value store ---
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 60)->default('general');
            $table->timestamps();
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('customer_groups');
    }
};
