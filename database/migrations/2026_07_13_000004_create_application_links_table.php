<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * application_links — outbound launch buttons per application
 * (BACKEND | FRONTEND | BACKEND V2 | ...). Mirrors schema.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('url', 500);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['application_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_links');
    }
};
