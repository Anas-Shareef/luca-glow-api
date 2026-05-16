<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('full_name', 80);
            $table->string('phone', 20);
            $table->string('address_line_1', 120);
            $table->string('address_line_2', 60)->nullable();
            $table->string('city', 60);
            $table->string('state', 60);
            $table->string('pincode', 10);
            $table->string('country', 20)->default('India');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
