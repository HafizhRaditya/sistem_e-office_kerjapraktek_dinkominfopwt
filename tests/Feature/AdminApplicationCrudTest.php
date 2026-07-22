<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Admin — Manajemen Aplikasi & Tautan (LANGKAH 3).
 *
 * Runs inside a transaction on the isolated, migrated, and seeded PostgreSQL
 * test database. Test-created rows use an "uji-" slug prefix for clarity.
 */
class AdminApplicationCrudTest extends TestCase
{
    protected function tearDown(): void
    {
        // Cascades to links/access/visits of the test applications.
        Application::where('slug', 'like', 'uji-%')->delete();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000002')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Uji Aplikasi',
            'opd_id' => Opd::orderBy('id')->firstOrFail()->id,
            'slug' => 'uji-aplikasi',
            'description' => 'Aplikasi untuk pengujian.',
            'app_group' => 'spbe',
            'category' => 'data',
            'sort_order' => 99,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_pegawai_is_forbidden_from_application_management(): void
    {
        $this->actingAs($this->pegawai())->get('/admin/aplikasi')->assertStatus(403);
    }

    public function test_admin_can_view_the_application_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/aplikasi')
            ->assertOk()
            ->assertSee('Manajemen Aplikasi');
    }

    public function test_admin_can_create_an_application(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.store'), $this->payload())
            ->assertRedirect();

        $app = Application::where('slug', 'uji-aplikasi')->first();

        $this->assertNotNull($app);
        $this->assertSame('Uji Aplikasi', $app->name);
        $this->assertSame('spbe', $app->app_group);
        $this->assertSame('data', $app->category);
        $this->assertTrue($app->is_active);
        $this->assertFalse($app->is_new); // checkbox absent => false
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        // 'simpus' already exists in the seeded data (applications.slug is UNIQUE).
        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.store'), $this->payload(['slug' => 'simpus']))
            ->assertSessionHasErrors('slug');
    }

    public function test_invalid_app_group_and_category_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.store'), $this->payload([
                'slug' => 'uji-salah',
                'app_group' => 'bogus',
                'category' => 'bogus',
            ]))
            ->assertSessionHasErrors(['app_group', 'category']);

        $this->assertNull(Application::where('slug', 'uji-salah')->first());
    }

    public function test_admin_can_update_an_application(): void
    {
        $this->actingAs($this->admin())->post(route('admin.aplikasi.store'), $this->payload());
        $app = Application::where('slug', 'uji-aplikasi')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.aplikasi.update', $app), $this->payload([
                'name' => 'Uji Aplikasi Diubah',
                'app_group' => 'tools',
                'is_active' => null, // unchecked => nonaktif
            ]))
            ->assertRedirect(route('admin.aplikasi.edit', $app));

        $app->refresh();
        $this->assertSame('Uji Aplikasi Diubah', $app->name);
        $this->assertSame('tools', $app->app_group);
        $this->assertFalse($app->is_active);
    }

    public function test_link_can_be_added_and_duplicate_label_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.aplikasi.store'), $this->payload(['slug' => 'uji-link']));
        $app = Application::where('slug', 'uji-link')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.link.store', $app), [
                'label' => 'Backend',
                'url' => 'https://contoh.banyumaskab.go.id/admin',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.aplikasi.edit', $app));

        $this->assertSame(1, $app->links()->count());

        // UNIQUE(application_id, label) — same label on the same app is rejected.
        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.link.store', $app), [
                'label' => 'Backend',
                'url' => 'https://lain.banyumaskab.go.id',
                'sort_order' => 2,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('label');

        $this->assertSame(1, $app->links()->count());
    }

    public function test_invalid_link_url_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.aplikasi.store'), $this->payload(['slug' => 'uji-url']));
        $app = Application::where('slug', 'uji-url')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.link.store', $app), [
                'label' => 'Frontend',
                'url' => 'bukan-url',
                'sort_order' => 0,
            ])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, $app->links()->count());
    }

    /**
     * Applications and links are never deleted, only deactivated (field decision,
     * Dinkominfo): deleting an application cascades into its links, every
     * employee's access grants for it, and the dashboard module's visit records.
     */
    public function test_there_is_no_route_to_delete_an_application_or_a_link(): void
    {
        $this->assertFalse(Route::has('admin.aplikasi.destroy'), 'route hapus aplikasi harus sudah tidak ada');
        $this->assertFalse(Route::has('admin.aplikasi.link.destroy'), 'route hapus tautan harus sudah tidak ada');

        $this->actingAs($this->admin())->post(route('admin.aplikasi.store'), $this->payload(['slug' => 'uji-hapus']));
        $app = Application::where('slug', 'uji-hapus')->firstOrFail();
        $link = $app->links()->create(['label' => 'Frontend', 'url' => 'https://contoh.go.id', 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->admin())->delete('/admin/aplikasi/'.$app->id)->assertStatus(405);
        $this->actingAs($this->admin())->delete("/admin/aplikasi/{$app->id}/link/{$link->id}")->assertStatus(405);

        $this->assertNotNull(Application::where('slug', 'uji-hapus')->first(), 'aplikasi harus tetap ada');
        $this->assertSame(1, DB::table('application_links')->where('application_id', $app->id)->count());
    }

    /**
     * Deactivation replaces deletion, so it must keep what deletion destroyed:
     * the links and access grants survive, and an inactive application is already
     * refused at launch by LaunchController.
     */
    public function test_deactivating_an_application_keeps_its_links_and_grants(): void
    {
        $this->actingAs($this->admin())->post(route('admin.aplikasi.store'), $this->payload(['slug' => 'uji-nonaktif']));
        $app = Application::where('slug', 'uji-nonaktif')->firstOrFail();
        $app->links()->create(['label' => 'Frontend', 'url' => 'https://contoh.go.id', 'is_active' => true, 'sort_order' => 0]);

        $pegawai = User::where('nip_nik', '3302010000000002')->firstOrFail();
        DB::table('application_access')->insert([
            'application_id' => $app->id,
            'user_id' => $pegawai->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.aplikasi.update', $app), $this->payload([
                'slug' => 'uji-nonaktif',
                'is_active' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $app->fresh()->is_active);
        $this->assertSame(1, DB::table('application_links')->where('application_id', $app->id)->count());
        $this->assertSame(1, DB::table('application_access')->where('application_id', $app->id)->count());

        // The guard already covers this, but state it here too: deactivating is a
        // real retirement, not a cosmetic flag.
        $this->actingAs($pegawai)->get('/launch/uji-nonaktif')->assertStatus(403);
    }

    public function test_the_edit_page_offers_deactivation_instead_of_deletion(): void
    {
        $app = Application::where('slug', 'banyumas-smart-city')->firstOrFail();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.aplikasi.edit', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Status Ketersediaan', $html);
        $this->assertStringNotContainsString('Hapus Aplikasi', $html);
    }
}
