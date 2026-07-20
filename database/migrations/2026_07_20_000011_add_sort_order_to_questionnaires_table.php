<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('ends_at');
            $table->index(['is_active', 'sort_order'], 'idx_questionnaires_active_order');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropIndex('idx_questionnaires_active_order');
            $table->dropColumn('sort_order');
        });
    }
};
