<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Category;
use App\Models\Opd;
use App\Models\User;
use App\Support\ActivityType;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminCategoryCrudTest extends TestCase
{
    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('role', 'pegawai')->firstOrFail();
    }

    public function test_pegawai_is_forbidden_from_category_management(): void
    {
        $category = Category::orderBy('id')->firstOrFail();

        foreach ([
            route('admin.categories.index'),
            route('admin.categories.create'),
            route('admin.categories.edit', $category),
        ] as $url) {
            $this->actingAs($this->pegawai())->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_view_category_list_and_create_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Manajemen Kategori')
            ->assertSee('Tambah Kategori');

        $this->actingAs($this->admin())
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('Nama kategori')
            ->assertSee('Kategori aktif');
    }

    public function test_admin_can_create_category_with_generated_slug_and_audit_log(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Pelayanan Publik',
                'slug' => '',
                'sort_order' => 15,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::where('slug', 'pelayanan-publik')->firstOrFail();

        $this->assertSame('Pelayanan Publik', $category->name);
        $this->assertTrue($category->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin()->id,
            'subject_type' => 'category',
            'subject_id' => $category->id,
            'activity_type' => ActivityType::CATEGORY_CREATED,
        ]);
    }

    public function test_admin_can_update_and_toggle_category_without_losing_application_relations(): void
    {
        $category = Category::where('slug', 'data')->firstOrFail();
        $applicationIds = $category->applications()->pluck('applications.id')->all();

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Data Terpadu',
                'slug' => 'data-terpadu',
                'sort_order' => 25,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.edit', $category));

        $category->refresh();
        $this->assertSame('Data Terpadu', $category->name);
        $this->assertSame('data-terpadu', $category->slug);

        $this->actingAs($this->admin())
            ->patch(route('admin.categories.status', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertEqualsCanonicalizing(
            $applicationIds,
            $category->applications()->pluck('applications.id')->all(),
        );

        $log = ActivityLog::where('activity_type', ActivityType::CATEGORY_DEACTIVATED)
            ->where('subject_id', $category->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse($log->properties['after']['is_active']);
    }

    public function test_inactive_category_is_hidden_but_its_application_remains_on_dashboard(): void
    {
        $category = Category::create([
            'name' => 'Kategori Uji Nonaktif',
            'slug' => 'kategori-uji-nonaktif',
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $application = Application::create([
            'opd_id' => Opd::orderBy('id')->firstOrFail()->id,
            'name' => 'Aplikasi Hanya Kategori Nonaktif',
            'slug' => 'aplikasi-hanya-kategori-nonaktif',
            'description' => 'Tetap harus muncul pada filter semua kategori.',
            'app_group' => 'spbe',
            'is_active' => true,
            'is_new' => false,
            'sort_order' => 999,
        ]);
        $application->categories()->attach($category);

        $response = $this->actingAs($this->pegawai())->get(route('dashboard'))->assertOk();
        $dashboardCategorySlugs = $response->viewData('dashboardCategories')->pluck('slug')->all();
        $dashboardApplication = $response->viewData('apps')->firstWhere('id', $application->id);

        $this->assertNotNull($dashboardApplication, 'aplikasi harus tetap dikirim ke dashboard');
        $this->assertSame([], $dashboardApplication['categories']->all());
        $this->assertNotContains($category->slug, $dashboardCategorySlugs);
        $response
            ->assertSee($application->name)
            ->assertDontSee($category->name);
    }

    public function test_inactive_category_cannot_be_assigned_to_a_new_application(): void
    {
        $category = Category::create([
            'name' => 'Kategori Tidak Bisa Dipilih',
            'slug' => 'kategori-tidak-bisa-dipilih',
            'is_active' => false,
            'sort_order' => 999,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.store'), [
                'name' => 'Aplikasi Kategori Ditolak',
                'opd_id' => Opd::orderBy('id')->firstOrFail()->id,
                'slug' => 'aplikasi-kategori-ditolak',
                'description' => null,
                'app_group' => 'spbe',
                'category_ids' => [$category->id],
                'sort_order' => 999,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_ids.0');

        $this->assertNull(Application::where('slug', 'aplikasi-kategori-ditolak')->first());
    }

    public function test_category_has_no_permanent_delete_route(): void
    {
        $this->assertFalse(Route::has('admin.categories.destroy'));

        $category = Category::orderBy('id')->firstOrFail();
        $this->actingAs($this->admin())
            ->delete('/admin/kategori/'.$category->id)
            ->assertStatus(405);

        $this->assertNotNull(Category::find($category->id));
    }
}
