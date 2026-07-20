<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Role-based landing after login (FR-A01, revised): an admin lands in the admin
 * panel, a pegawai on the portal dashboard. Also covers the /admin root route.
 *
 * Turnstile keys are configured in .env, so the real verify call is faked here —
 * that lets the whole login pipeline (validation -> throttle -> Turnstile ->
 * credentials -> redirect) run in the test.
 */
class LoginRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'sistem_eoffice',
        ]);
        DB::purge('pgsql');

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);
    }

    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    private function attemptLogin(string $nip)
    {
        return $this->post('/login', [
            'nip_nik' => $nip,
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ]);
    }

    public function test_admin_lands_on_the_admin_panel_after_login(): void
    {
        $this->attemptLogin('ADMIN001')->assertRedirect(route('admin.akses.index'));
        $this->assertAuthenticated();
    }

    public function test_pegawai_lands_on_the_portal_dashboard_after_login(): void
    {
        $this->attemptLogin('3302010000000002')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_admin_root_redirects_to_manajemen_hak_akses(): void
    {
        $this->actingAs($this->user('ADMIN001'))
            ->get('/admin')
            ->assertRedirect(route('admin.akses.index'));
    }

    public function test_pegawai_hitting_the_admin_root_gets_403(): void
    {
        $this->actingAs($this->user('3302010000000002'))
            ->get('/admin')
            ->assertStatus(403);
    }

    public function test_guest_hitting_the_admin_root_is_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_logged_in_admin_visiting_login_goes_to_the_admin_panel(): void
    {
        $this->actingAs($this->user('ADMIN001'))
            ->get('/login')
            ->assertRedirect(route('admin.akses.index'));
    }

    public function test_logged_in_pegawai_visiting_login_goes_to_the_dashboard(): void
    {
        $this->actingAs($this->user('3302010000000002'))
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_intended_url_still_wins_over_the_role_default(): void
    {
        // An admin bounced off a deep admin link must land back on it, not on the
        // role default.
        $this->get('/admin/pengguna')->assertRedirect(route('login'));

        $this->attemptLogin('ADMIN001')->assertRedirect(route('admin.users.index'));
    }
}
