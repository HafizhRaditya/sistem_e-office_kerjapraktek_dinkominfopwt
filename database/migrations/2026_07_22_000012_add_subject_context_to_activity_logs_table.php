<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 200)->nullable();
            $table->jsonb('properties')->nullable();

            $table->index(['subject_type', 'subject_id'], 'idx_logs_subject');
        });

        // Preserve useful object context for existing operational logs.
        DB::statement(<<<'SQL'
            UPDATE activity_logs AS logs
            SET subject_type = 'application',
                subject_id = logs.application_id,
                subject_label = applications.name
            FROM applications
            WHERE logs.application_id = applications.id
              AND logs.subject_type IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE activity_logs AS logs
            SET subject_type = 'questionnaire',
                subject_id = logs.questionnaire_id,
                subject_label = questionnaires.title
            FROM questionnaires
            WHERE logs.questionnaire_id = questionnaires.id
              AND logs.subject_type IS NULL
        SQL);

        // Historically user_id on failed logins meant the attempted account,
        // not a proven actor. Move that account to the subject columns so the
        // actor column no longer makes a false attribution.
        DB::statement(<<<'SQL'
            UPDATE activity_logs AS logs
            SET subject_type = 'user',
                subject_id = logs.user_id,
                subject_label = users.name,
                user_id = NULL
            FROM users
            WHERE logs.user_id = users.id
              AND logs.activity_type = 'login_failed'
        SQL);

        // The old admin reset entry had the target in user_id. Its actor cannot
        // be reconstructed reliably, so retain the target as subject and leave
        // the old actor unknown. New reset entries record both correctly.
        DB::statement(<<<'SQL'
            UPDATE activity_logs AS logs
            SET subject_type = 'user',
                subject_id = logs.user_id,
                subject_label = users.name,
                user_id = NULL
            FROM users
            WHERE logs.user_id = users.id
              AND logs.activity_type = 'password_changed'
              AND logs.description LIKE 'Kata sandi direset oleh admin %'
        SQL);
    }

    public function down(): void
    {
        // Best-effort restoration of the old meaning for rows transformed above.
        DB::statement(<<<'SQL'
            UPDATE activity_logs
            SET user_id = subject_id
            WHERE user_id IS NULL
              AND subject_type = 'user'
              AND activity_type IN ('login_failed', 'password_changed')
              AND subject_id IS NOT NULL
        SQL);

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_logs_subject');
            $table->dropColumn(['subject_type', 'subject_id', 'subject_label', 'properties']);
        });
    }
};
