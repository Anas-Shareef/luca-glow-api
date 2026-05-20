<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('footer_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('footer_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('footer_section_id')->constrained('footer_sections')->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_links');
        Schema::dropIfExists('footer_sections');
    }
};
