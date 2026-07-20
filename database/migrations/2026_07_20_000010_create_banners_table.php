<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone dashboard banners shown in the popup carousel and hero area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('target_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['is_active', 'sort_order'], 'idx_banners_active_order');
        });

        DB::statement('ALTER TABLE banners ADD CONSTRAINT chk_banners_period CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
