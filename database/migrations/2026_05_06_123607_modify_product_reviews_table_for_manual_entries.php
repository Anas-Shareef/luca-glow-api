<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('reviewer_name')->nullable()->after('user_id');
            $table->string('reviewer_email')->nullable()->after('reviewer_name');
            $table->boolean('is_manual')->default(false)->after('is_verified_purchase');
            $table->boolean('is_published')->default(false)->change(); // Default to false for moderation
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn(['reviewer_name', 'reviewer_email', 'is_manual']);
            $table->boolean('is_published')->default(true)->change();
        });
    }
};
