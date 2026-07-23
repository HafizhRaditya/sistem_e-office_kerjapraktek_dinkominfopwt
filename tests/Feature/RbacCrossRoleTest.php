<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationVisit;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cross-role RBAC verification (roadmap Fri 25 Jul): admin vs two pegawai from
 * different OPDs, plus live propagation of an access change.
 *
 * Runs inside a transaction on the isolated, migrated, and seeded PostgreSQL
 * test database. Instead of borrowing seeded employees, this suite builds its
 * own `UJI` fixtures so the expected access set is deterministic.
 *
 * Applications are the real seeded rows (no invented names), per the team rule
 * that test data must come from the database.
 */
class RbacCrossRoleTest extends TestCase
{
    private User $admin;

    private User $pegawaiA;   // OPD A — SETDA

    private User $pegawaiB;   // OPD B — DINKES

    private Application $smartCity;   // granted to A only

    private Application $dataHub;     // granted to A only

    private Application $simpus;      // granted to B only

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->admin = User::where('nip_nik', 'ADMIN001')->firstOrFail();

        $this->smartCity = Application::where('slug', 'banyumas-smart-city')->firstOrFail();
        $this->dataHub = Application::where('slug', 'data-hub-banyumas')->firstOrFail();
        $this->simpus = Application::where('slug', 'simpus')->firstOrFail();

        $this->pegawaiA = $this->makePegawai('UJIRBAC001', 'UJI Pegawai OPD A', 'SETDA');
        $this->pegawaiB = $this->makePegawai('UJIRBAC002', 'UJI Pegawai OPD B', 'DINKES');

        // OPD A gets Smart City + Data Hub; OPD B gets SIMPUS only. The two sets
        // are deliberately disjoint so a leak in either direction is visible.
        $this->grant($this->pegawaiA, [$this->smartCity, $this->dataHub]);
        $this->grant($this->pegawaiB, [$this->simpus]);
    }

    protected function tearDown(): void
    {
        $ids = [$this->pegawaiA->id, $this->pegawaiB->id];

        ApplicationVisit::whereIn('user_id', $ids)->delete();
        DB::table('activity_logs')->whereIn('user_id', $ids)->delete();
        ApplicationAccess::whereIn('user_id', $ids)->delete();
        User::whereIn('id', $ids)->delete();

        parent::tearDown();
    }

    private function makePegawai(string $nipNik, string $name, string $opdCode): User
    {
        $opd = Opd::where('code', $opdCode)->firstOrFail();

        // Leftovers from an interrupted run would break the unique nip_nik.
        User::where('nip_nik', $nipNik)->delete();

        return User::create([
            'opd_id' => $opd->id,
            'nip_nik' => $nipNik,
            'name' => $name,
            'password' => 'password',
            'role' => 'pegawai',
            'is_active' => true,
        ]);
    }

    /** @param  array<int, Application>  $apps */
    private function grant(User $user, array $apps): void
    {
        foreach ($apps as $app) {
            ApplicationAccess::firstOrCreate([
                'application_id' => $app->id,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Every page of the admin panel, keyed by its Indonesian screen name so a
     * failure names the screen rather than a bare URL.
     *
     * @return array<string, string>
     */
    private function adminPages(): array
    {
        $link = $this->smartCity->links()->orderBy('id')->firstOrFail();

        $opd = Opd::where('code', 'SETDA')->firstOrFail();

        return [
            'Beranda admin (/admin)' => '/admin',
            'Manajemen Hak Akses' => route('admin.akses.index'),
            'Atur Akses pengguna' => route('admin.akses.edit', $this->pegawaiA),
            'Manajemen OPD' => route('admin.opds.index'),
            'Tambah OPD' => route('admin.opds.create'),
            'Ubah OPD' => route('admin.opds.edit', $opd),
            'Manajemen Aplikasi' => route('admin.aplikasi.index'),
            'Tambah Aplikasi' => route('admin.aplikasi.create'),
            'Ubah Aplikasi' => route('admin.aplikasi.edit', $this->smartCity),
            'Tambah Tautan' => route('admin.aplikasi.link.create', $this->smartCity),
            'Ubah Tautan' => route('admin.aplikasi.link.edit', [$this->smartCity, $link]),
            'Manajemen Pengguna' => route('admin.users.index'),
            'Tambah Pengguna' => route('admin.users.create'),
            'Ubah Pengguna' => route('admin.users.edit', $this->pegawaiA),
            'Log Aktivitas' => route('admin.logs.index'),
        ];
    }

    // ---------------------------------------------------------------- skenario 1

    public function test_admin_can_reach_every_admin_page(): void
    {
        foreach ($this->adminPages() as $screen => $url) {
            $status = $this->actingAs($this->admin)->get($url)->status();

            $this->assertContains($status, [200, 302],
                "admin harus bisa membuka \"{$screen}\" ({$url}), dapat {$status}");
        }
    }

    // ---------------------------------------------------------------- skenario 2

    public function test_pegawai_is_forbidden_from_every_admin_page(): void
    {
        foreach ([$this->pegawaiA, $this->pegawaiB] as $pegawai) {
            foreach ($this->adminPages() as $screen => $url) {
                $this->actingAs($pegawai)
                    ->get($url)
                    ->assertStatus(403, "\"{$screen}\" harus 403 untuk {$pegawai->name}");
            }
        }
    }

    public function test_pegawai_opd_a_launches_only_its_granted_applications(): void
    {
        // Granted -> redirected out to the target application.
        $this->actingAs($this->pegawaiA)
            ->get('/launch/banyumas-smart-city')
            ->assertStatus(302);

        // Not granted -> hard 403, no redirect (presence must not leak).
        $this->actingAs($this->pegawaiA)
            ->get('/launch/simpus')
            ->assertStatus(403)
            ->assertSee('Anda tidak memiliki akses ke aplikasi ini.');
    }

    // ---------------------------------------------------------------- skenario 3

    public function test_pegawai_opd_b_has_a_different_access_set_than_opd_a(): void
    {
        $this->actingAs($this->pegawaiB)
            ->get('/launch/simpus')
            ->assertStatus(302);

        $this->actingAs($this->pegawaiB)
            ->get('/launch/banyumas-smart-city')
            ->assertStatus(403);

        $this->actingAs($this->pegawaiB)
            ->get('/launch/data-hub-banyumas')
            ->assertStatus(403);

        // The two OPDs really do resolve to different sets, not the same one.
        $setA = $this->pegawaiA->fresh()->accessibleApplicationIds();
        $setB = $this->pegawaiB->fresh()->accessibleApplicationIds();

        sort($setA);
        sort($setB);

        $this->assertNotEquals($setA, $setB, 'hak akses dua OPD seharusnya berbeda');
        $this->assertEmpty(array_intersect($setA, $setB), 'kedua set sengaja dibuat tidak beririsan');
    }

    // ---------------------------------------------------------------- skenario 4

    public function test_granting_access_through_the_panel_takes_effect_on_the_next_launch(): void
    {
        // Before: B has no access to Data Hub.
        $this->actingAs($this->pegawaiB)->get('/launch/data-hub-banyumas')->assertStatus(403);

        // Admin grants it through Manajemen Hak Akses (real HTTP, real form payload).
        $this->actingAs($this->admin)
            ->put(route('admin.akses.update', $this->pegawaiB), [
                'access' => [$this->simpus->id, $this->dataHub->id],
            ])
            ->assertRedirect(route('admin.akses.edit', $this->pegawaiB));

        // The panel wrote exactly the rows the launch guard reads.
        $this->assertEqualsCanonicalizing(
            [$this->simpus->id, $this->dataHub->id],
            $this->pegawaiB->fresh()->accessibleApplicationIds()
        );

        // After: the very next launch succeeds — no re-login in between.
        $this->actingAs($this->pegawaiB)->get('/launch/data-hub-banyumas')->assertStatus(302);
    }

    public function test_revoking_access_through_the_panel_takes_effect_on_the_next_launch(): void
    {
        $this->actingAs($this->pegawaiA)->get('/launch/banyumas-smart-city')->assertStatus(302);

        // Admin unticks Smart City, leaving only Data Hub.
        $this->actingAs($this->admin)
            ->put(route('admin.akses.update', $this->pegawaiA), [
                'access' => [$this->dataHub->id],
            ])
            ->assertRedirect(route('admin.akses.edit', $this->pegawaiA));

        $this->actingAs($this->pegawaiA)
            ->get('/launch/banyumas-smart-city')
            ->assertStatus(403)
            ->assertSee('Anda tidak memiliki akses ke aplikasi ini.');
    }

    /**
     * The strict form of "no re-login": one continuous session established
     * through the real /login form, with the access row flipped underneath it.
     * The session cookie is never re-issued — assertAuthenticatedAs() proves the
     * same login is still in force at every step.
     */
    public function test_access_change_applies_within_one_continuous_session(): void
    {
        $this->post('/login', [
            'nip_nik' => 'UJIRBAC001',
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->pegawaiA);

        // 1. Not granted yet.
        $this->get('/launch/simpus')->assertStatus(403);
        $this->assertAuthenticatedAs($this->pegawaiA);

        // 2. Access appears mid-session (same write the panel performs).
        ApplicationAccess::create([
            'application_id' => $this->simpus->id,
            'user_id' => $this->pegawaiA->id,
        ]);

        $this->get('/launch/simpus')->assertStatus(302);
        $this->assertAuthenticatedAs($this->pegawaiA);

        // 3. Access is taken away again, still mid-session.
        ApplicationAccess::where('user_id', $this->pegawaiA->id)
            ->where('application_id', $this->simpus->id)
            ->delete();

        $this->get('/launch/simpus')->assertStatus(403);
        $this->assertAuthenticatedAs($this->pegawaiA);
    }

    // ---------------------------------------------------------------- skenario 5

    /**
     * The two halves of the existing decision, stated together: an admin bypasses
     * *permission* (no application_access row is needed) but NOT *availability*
     * (is_active). Documented as decision #1 in the handoff — asserted here, not
     * changed.
     */
    public function test_admin_launches_an_active_application_without_any_access_row(): void
    {
        $this->assertEmpty($this->admin->accessibleApplicationIds(),
            'admin sengaja tidak punya baris application_access — aksesnya lewat bypass peran');

        $link = $this->smartCity->links()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        // Make the idempotent insert actually insert, so the visit is observable.
        ApplicationVisit::where('application_link_id', $link->id)
            ->where('user_id', $this->admin->id)
            ->where('visit_date', now()->toDateString())
            ->delete();

        $before = ApplicationVisit::count();

        $this->actingAs($this->admin)
            ->get('/launch/banyumas-smart-city')
            ->assertStatus(302);

        $this->assertSame($before + 1, ApplicationVisit::count());

        // This test owns the row it created; the admin is a seeded user, so it is
        // not swept by tearDown() with the UJI fixtures.
        ApplicationVisit::where('application_link_id', $link->id)
            ->where('user_id', $this->admin->id)
            ->where('visit_date', now()->toDateString())
            ->delete();
    }

    public function test_admin_does_not_bypass_an_inactive_link_either(): void
    {
        $inactiveLink = $this->dataHub->links()->where('is_active', false)->firstOrFail();
        $before = ApplicationVisit::count();

        $this->actingAs($this->admin)
            ->get("/launch/data-hub-banyumas/{$inactiveLink->id}")
            ->assertStatus(403)
            ->assertSee('Tautan aplikasi ini sedang tidak aktif.');

        $this->assertSame($before, ApplicationVisit::count(),
            'peluncuran yang ditolak tidak boleh mencatat kunjungan');
    }
}
