<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * questionnaire_responses — participation = click on "Isi Kuisioner".
 * Event table: NO created_at/updated_at (team decision, final) — model has
 * $timestamps = false.
 *
 * Business rule: UNIQUE(questionnaire_id, user_id) = one employee is recorded
 * exactly once per questionnaire (forever). Repeat clicks are swallowed
 * idempotently (ON CONFLICT DO NOTHING / firstOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('clicked_at')->useCurrent();

            $table->unique(['questionnaire_id', 'user_id']); // 1 employee recorded once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
    }
};
