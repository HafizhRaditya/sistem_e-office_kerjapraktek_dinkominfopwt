<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * applications — launcher catalogue. app_group & category are CHECK-constrained.
 * Mirrors schema.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 255)->nullable();
            $table->string('app_group', 20);                 // CHECK added below
            $table->string('category', 30)->nullable();      // CHECK added below (NULL allowed)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_new')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('opd_id', 'idx_applications_opd');
            $table->index(['app_group', 'is_active', 'category'], 'idx_applications_filter');
        });

        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_applications_app_group CHECK (app_group IN ('smartcity','spbe','tools'))");
        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_applications_category CHECK (category IN ('governance','economy','kinerja','gawai','rencana','uang','pajak','kesehatan','data','wisata','umum'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
