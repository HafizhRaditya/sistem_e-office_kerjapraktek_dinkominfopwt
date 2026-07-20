<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Server-side launch guard (FR-A10): access enforcement + is_active availability.
 *
 * These tests run against the seeded dev PostgreSQL database, because the domain
 * migrations use PostgreSQL-only DDL (ALTER TABLE ADD CONSTRAINT, a COALESCE
 * expression index) that the default in-memory sqlite test connection cannot
 * host. No RefreshDatabase — assertions read the existing seeded rows. Using
 * actingAs() bypasses the /login flow (and its Turnstile check), which is correct
 * because /launch is guarded by the auth + access logic, not by Turnstile.
 */
class LaunchGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Point the default connection at the real seeded pgsql dev database.
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

    public function test_pegawai_without_access_gets_403(): void
    {
        // Siti (3302010000000002) has NO access to SIMPUS.
        $this->actingAs($this->user('3302010000000002'))
            ->get('/launch/simpus')
            ->assertStatus(403)
            ->assertSee('Anda tidak memiliki akses ke aplikasi ini.');
    }

    public function test_inactive_application_is_403_even_with_access_and_records_no_visit(): void
    {
        // Budi (3302010000000001) HAS access to Agenda Pimpinan, but it is is_active=false.
        $before = ApplicationVisit::count();

        $this->actingAs($this->user('3302010000000001'))
            ->get('/launch/agenda-pimpinan')
            ->assertStatus(403)
            ->assertSee('Aplikasi ini sedang tidak aktif.');

        $this->assertSame($before, ApplicationVisit::count(), 'an inactive application must not record a visit');
    }

    public function test_admin_does_not_bypass_inactive_application(): void
    {
        $this->actingAs($this->user('ADMIN001'))
            ->get('/launch/agenda-pimpinan')
            ->assertStatus(403)
            ->assertSee('Aplikasi ini sedang tidak aktif.');
    }

    public function test_inactive_link_is_403_and_records_no_visit(): void
    {
        $app = Application::where('slug', 'data-hub-banyumas')->firstOrFail();
        $inactiveLink = $app->links()->where('label', 'Backend V2')->firstOrFail();
        $before = ApplicationVisit::count();

        $this->actingAs($this->user('3302010000000002'))
            ->get("/launch/data-hub-banyumas/{$inactiveLink->id}")
            ->assertStatus(403)
            ->assertSee('Tautan aplikasi ini sedang tidak aktif.');

        $this->assertSame($before, ApplicationVisit::count(), 'an inactive link must not record a visit');
    }

    public function test_active_link_launches_and_records_one_visit(): void
    {
        $app = Application::where('slug', 'data-hub-banyumas')->firstOrFail();
        $activeLink = $app->links()->where('label', 'Frontend')->firstOrFail();
        $siti = $this->user('3302010000000002');

        // Clean this (link,user,today) so the idempotent insert actually inserts.
        ApplicationVisit::where('application_link_id', $activeLink->id)
            ->where('user_id', $siti->id)
            ->where('visit_date', now()->toDateString())
            ->delete();

        $before = ApplicationVisit::count();

        $this->actingAs($siti)
            ->get("/launch/data-hub-banyumas/{$activeLink->id}")
            ->assertStatus(302);

        $this->assertSame($before + 1, ApplicationVisit::count(), 'an active launch must record exactly one visit');
    }
}
