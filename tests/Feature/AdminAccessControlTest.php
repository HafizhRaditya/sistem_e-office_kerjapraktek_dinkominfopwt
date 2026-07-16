<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin panel access control + Manajemen Hak Akses (LANGKAH 2).
 *
 * Runs against the seeded dev PostgreSQL database (domain migrations are
 * PostgreSQL-only). actingAs() bypasses the /login flow + Turnstile, which is
 * correct: admin routes are guarded by auth + role, not by Turnstile.
 */
class AdminAccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'sistem_eoffice',
        ]);
        DB::purge('pgsql');
    }

    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/akses')->assertRedirect(route('login'));
    }

    public function test_pegawai_is_forbidden_with_403(): void
    {
        $this->actingAs($this->user('3302010000000002'))
            ->get('/admin/akses')
            ->assertStatus(403);
    }

    public function test_admin_can_view_the_access_list(): void
    {
        $this->actingAs($this->user('ADMIN001'))
            ->get('/admin/akses')
            ->assertOk()
            ->assertSee('Manajemen Hak Akses');
    }

    public function test_admin_can_open_a_user_detail_page(): void
    {
        $siti = $this->user('3302010000000002');

        $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.akses.edit', $siti))
            ->assertOk()
            ->assertSee($siti->name)
            ->assertSee($siti->nip_nik);
    }

    public function test_admin_can_sync_access_grants_and_they_take_effect_immediately(): void
    {
        $agus = $this->user('3302010000000003');
        $simpus = Application::where('slug', 'simpus')->firstOrFail();
        $eplanning = Application::where('slug', 'e-planning')->firstOrFail();
        $smartcity = Application::where('slug', 'banyumas-smart-city')->firstOrFail();

        $this->actingAs($this->user('ADMIN001'))
            ->put(route('admin.akses.update', $agus), ['access' => [$simpus->id, $eplanning->id]])
            ->assertRedirect(route('admin.akses.edit', $agus));

        $granted = $agus->fresh()->applicationAccess()->pluck('application_id')->all();
        $this->assertEqualsCanonicalizing([$simpus->id, $eplanning->id], $granted);

        // No re-login needed — can_access reads the live rows.
        $this->assertTrue($agus->fresh()->canAccessApp($eplanning));
        $this->assertFalse($agus->fresh()->canAccessApp($smartcity));
    }
}
