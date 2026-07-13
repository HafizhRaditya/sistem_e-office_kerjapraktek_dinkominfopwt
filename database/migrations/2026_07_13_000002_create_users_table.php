<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users — single login identity is `nip_nik` (NIP or NIK), NOT email.
 * Role is a CHECK-constrained column, NOT a separate table.
 * Mirrors schema.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->restrictOnDelete();
            $table->string('nip_nik', 20)->unique();          // single login identity
            $table->string('name', 150);                       // incl. title, e.g. "ADI NUGROHO, S.Kom."
            $table->string('email', 150)->nullable()->unique(); // nullable; PG allows many NULLs
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('role', 10)->default('pegawai');    // CHECK added below
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();  // FR-A01
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('opd_id', 'idx_users_opd');
        });

        // Role is not a table: enforce the allowed values at the DB level.
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin','pegawai'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
