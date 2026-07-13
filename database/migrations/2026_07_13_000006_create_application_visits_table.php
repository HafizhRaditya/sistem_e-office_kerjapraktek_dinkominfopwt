<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * application_visits — valid visits only (passed can_access + active link).
 * Event table: NO created_at/updated_at (team decision, final) — model has
 * $timestamps = false.
 *
 * Business rule: one visit per (button/link + employee + date). The rule is
 * enforced by the named UNIQUE INDEX uq_visit_daily below; COALESCE unifies
 * NULL links (visit without a specific link) so they still count once per day
 * instead of slipping through because NULL is treated as distinct. A plain
 * $table->unique() cannot express COALESCE, so it is built via raw statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('application_link_id')->nullable()->constrained('application_links')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('visit_date')->default(DB::raw('CURRENT_DATE')); // visit date (WIB)
            $table->timestampTz('visited_at')->useCurrent();

            $table->index(['application_id', 'visit_date'], 'idx_visits_app_date');
        });

        // One visit per button/employee/date. COALESCE(-1) normalises NULL links.
        DB::statement('CREATE UNIQUE INDEX uq_visit_daily ON application_visits (COALESCE(application_link_id, -1), user_id, visit_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('application_visits');
    }
};
