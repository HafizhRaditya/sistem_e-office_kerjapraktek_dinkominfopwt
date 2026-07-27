<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing fixed categories, kept in their current dashboard order.
     *
     * @var array<string, string>
     */
    private const LEGACY_CATEGORIES = [
        'governance' => 'Governance',
        'economy' => 'Economy',
        'kinerja' => 'Kinerja',
        'gawai' => 'Gawai',
        'rencana' => 'Rencana',
        'uang' => 'Uang',
        'pajak' => 'Pajak',
        'kesehatan' => 'Kesehatan',
        'data' => 'Data',
        'wisata' => 'Wisata',
        'umum' => 'Umum',
    ];

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['is_active', 'sort_order'], 'idx_categories_active_order');
        });

        Schema::create('application_category', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->primary(['application_id', 'category_id']);
            $table->index('category_id', 'idx_application_category_category');
        });

        $now = now();
        $rows = collect(self::LEGACY_CATEGORIES)
            ->map(function (string $name, string $slug) use ($now): array {
                return [
                    'name' => $name,
                    'slug' => $slug,
                    'is_active' => true,
                    'sort_order' => (array_search($slug, array_keys(self::LEGACY_CATEGORIES), true) + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        DB::table('categories')->insert($rows);

        DB::statement(<<<'SQL'
            INSERT INTO application_category (application_id, category_id)
            SELECT applications.id, categories.id
            FROM applications
            INNER JOIN categories ON categories.slug = applications.category
            WHERE applications.category IS NOT NULL
            ON CONFLICT (application_id, category_id) DO NOTHING
        SQL);

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_filter');
        });

        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS chk_applications_category');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->index(['app_group', 'is_active'], 'idx_applications_group_active');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_group_active');
            $table->string('category', 30)->nullable();
        });

        $legacySlugs = implode(
            ', ',
            array_map(
                fn (string $slug): string => DB::getPdo()->quote($slug),
                array_keys(self::LEGACY_CATEGORIES),
            ),
        );

        DB::statement(<<<SQL
            UPDATE applications
            SET category = chosen.slug
            FROM (
                SELECT DISTINCT ON (application_category.application_id)
                    application_category.application_id,
                    categories.slug
                FROM application_category
                INNER JOIN categories ON categories.id = application_category.category_id
                WHERE categories.slug IN ({$legacySlugs})
                ORDER BY application_category.application_id, categories.sort_order, categories.id
            ) AS chosen
            WHERE chosen.application_id = applications.id
        SQL);

        Schema::dropIfExists('application_category');
        Schema::dropIfExists('categories');

        Schema::table('applications', function (Blueprint $table) {
            $table->index(['app_group', 'is_active', 'category'], 'idx_applications_filter');
        });

        DB::statement("ALTER TABLE applications ADD CONSTRAINT chk_applications_category CHECK (category IN ('governance','economy','kinerja','gawai','rencana','uang','pajak','kesehatan','data','wisata','umum'))");
    }
};
