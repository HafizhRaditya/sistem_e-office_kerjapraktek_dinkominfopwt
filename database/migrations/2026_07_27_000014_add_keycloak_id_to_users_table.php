<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.keycloak_id — the Keycloak subject (`sub`) claim, stored on the first
 * successful SSO login so later logins can rely on a stable link instead of
 * re-matching on the username.
 *
 * NULLABLE because the NIP/NIK password login stays the primary path: accounts
 * that never sign in through Keycloak simply keep NULL here.
 *
 * UNIQUE so one Keycloak identity can never be linked to two local accounts.
 * PostgreSQL allows many NULLs in a UNIQUE column, so the thousands of
 * password-only accounts do not collide — the same trick `users.email` uses.
 *
 * Note: `after()` is a MySQL-only modifier that PostgreSQL silently ignores;
 * it is kept to document the intended position next to the other login identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id', 255)->nullable()->unique()->after('nip_nik');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn('keycloak_id');
        });
    }
};
