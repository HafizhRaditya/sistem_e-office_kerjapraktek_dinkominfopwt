<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs — audit trail. Event table: NO $table->timestamps(); it has
 * only created_at (no updated_at), per schema.sql — model has $timestamps=false.
 * All FKs are nullable and SET NULL on delete (e.g. user_id null on login_failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->string('activity_type', 50); // login_success | login_failed | logout | password_changed | app_launched | quiz_clicked | access_denied
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent(); // created_at only, no updated_at

            $table->index(['user_id', 'created_at'], 'idx_logs_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
