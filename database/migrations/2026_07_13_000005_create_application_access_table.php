<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * application_access — per-employee access grant. Dashboard marks the card,
 * server validates (403). UNIQUE(application_id, user_id) = one grant per pair.
 * Mirrors schema.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['application_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_access');
    }
};
