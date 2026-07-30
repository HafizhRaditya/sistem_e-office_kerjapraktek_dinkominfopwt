<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\TestCase;

/**
 * Does the admin gate survive on the Livewire update endpoint? (finding A1)
 *
 * The three admin screens render their tables through Livewire components. The
 * page itself is protected by ['auth', EnsureUserIsAdmin], but a search
 * keystroke or a page change does NOT go back through that route — it goes to
 * the Livewire update endpoint, which Livewire registers with only
 * ['web', RequireLivewireHeaders].
 *
 * Livewire re-applies the ORIGINAL route's middleware on that endpoint, but only
 * those on its allow-list (Mechanisms/PersistentMiddleware/PersistentMiddleware.php).
 * EnsureUserIsAdmin is not on it, and this project never calls
 * addPersistentMiddleware(). These tests check whether that is exploitable.
 *
 * Livewire::test() is deliberately NOT used: it bypasses the HTTP endpoint
 * entirely (PersistentMiddleware skips anything that is not a real Livewire
 * route), so it would prove nothing about middleware. Every test here drives the
 * real endpoint with a real signed snapshot, exactly as a browser would.
 *
 * These assertions describe the SECURE behaviour. A failure here is the finding
 * reproducing itself, not a broken test.
 */
class LivewireAdminGuardTest extends TestCase
{
    private User $admin;

    private User $pegawai;

    /** Screens under test: admin page -> a term that must match a seeded row. */
    private const SCREENS = [
        'Manajemen Hak Akses' => ['/admin/akses', 'admin.access-table'],
        'Manajemen Pengguna' => ['/admin/pengguna', 'admin.user-table'],
        'Manajemen Aplikasi' => ['/admin/aplikasi', 'admin.application-table'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->admin = User::where('nip_nik', 'admin')->firstOrFail();
        $this->pegawai = $this->makePegawai('UJILW001', 'UJI Pegawai Livewire');
    }

    protected function tearDown(): void
    {
        DB::table('activity_logs')->where('user_id', $this->pegawai->id)->delete();
        User::where('id', $this->pegawai->id)->delete();

        parent::tearDown();
    }

    private function makePegawai(string $nipNik, string $name): User
    {
        $opd = Opd::where('code', 'SETDA')->firstOrFail();

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

    /**
     * Pull one component's wire:snapshot out of a rendered admin page.
     *
     * The snapshot is the signed state blob the browser holds; every subsequent
     * interaction posts it back. Capturing it as an admin is what lets the tests
     * below replay it as somebody else.
     */
    private function snapshotFor(string $html, string $componentName): string
    {
        $found = preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

        $this->assertNotFalse($found);
        $this->assertGreaterThan(0, $found, "Tidak ada wire:snapshot pada halaman untuk {$componentName}.");

        foreach ($matches[1] as $raw) {
            $snapshot = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');

            if (str_contains($snapshot, $componentName)) {
                return $snapshot;
            }
        }

        $this->fail("Snapshot untuk komponen {$componentName} tidak ditemukan pada halaman.");
    }

    /** Grab a live snapshot for a screen by loading it as a real admin. */
    private function captureSnapshotAsAdmin(string $path, string $componentName): string
    {
        $response = $this->actingAs($this->admin)->get($path);
        $response->assertOk();

        return $this->snapshotFor($response->getContent(), $componentName);
    }

    /**
     * Post a Livewire interaction exactly as the browser client does.
     *
     * @param  array<string, mixed>  $updates
     * @param  array<int, array<string, mixed>>  $calls
     */
    private function livewireUpdate(string $snapshot, array $updates = [], array $calls = [])
    {
        return $this->withHeaders([
            'X-Livewire' => 'true',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->postJson(route('default-livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => $calls,
            ]],
        ]);
    }

    /**
     * The HTML Livewire rendered back, where leaked rows would appear.
     *
     * A refused request is not JSON at all (Laravel renders the 403 page), so
     * this falls back to the raw body instead of blowing up on json() — the
     * point is to search whatever came back for employee data, whatever shape
     * it arrived in.
     */
    private function renderedHtml($response): string
    {
        $body = $response->getContent();

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return (string) $body;
        }

        return json_encode($payload['components'][0]['effects']['html'] ?? '', JSON_UNESCAPED_UNICODE);
    }

    // ===================================================================
    // 1. Baseline — the HTTP route itself is properly gated.
    // ===================================================================

    public function test_pegawai_ditolak_pada_halaman_admin_biasa(): void
    {
        foreach (self::SCREENS as $screen => [$path, $component]) {
            $this->actingAs($this->pegawai)
                ->get($path)
                ->assertForbidden(); // sanity: the normal gate works
        }
    }

    // ===================================================================
    // 2. The finding — a pegawai replaying an admin snapshot.
    // ===================================================================

    /**
     * Every screen is probed before asserting, on purpose.
     *
     * A plain foreach + assert would stop at the first failure and leave the
     * other two screens untested, which would understate (or overstate) how far
     * the gap reaches. Collecting first means the failure message names exactly
     * which of the three are open and which are not.
     */
    public function test_pegawai_tidak_bisa_mencari_lewat_endpoint_livewire(): void
    {
        $statuses = [];

        foreach (self::SCREENS as $screen => [$path, $component]) {
            $snapshot = $this->captureSnapshotAsAdmin($path, $component);

            $statuses[$screen] = $this->actingAs($this->pegawai)
                ->livewireUpdate($snapshot, updates: ['q' => 'a'])
                ->status();
        }

        // 403 exactly, not "anything that is not 200". A 404 or a 419 would
        // also keep the data in, but for an unrelated reason, and would hide a
        // regression the day the gate itself stopped firing.
        $this->assertSame(
            ['Manajemen Hak Akses' => 403, 'Manajemen Pengguna' => 403, 'Manajemen Aplikasi' => 403],
            $statuses,
            'Pencarian lewat endpoint Livewire tidak ditolak dengan 403 pada setiap layar admin.'
        );
    }

    public function test_pegawai_tidak_bisa_paginasi_lewat_endpoint_livewire(): void
    {
        $statuses = [];

        foreach (self::SCREENS as $screen => [$path, $component]) {
            $snapshot = $this->captureSnapshotAsAdmin($path, $component);

            $statuses[$screen] = $this->actingAs($this->pegawai)->livewireUpdate(
                $snapshot,
                calls: [['path' => '', 'method' => 'nextPage', 'params' => []]],
            )->status();
        }

        $this->assertSame(
            ['Manajemen Hak Akses' => 403, 'Manajemen Pengguna' => 403, 'Manajemen Aplikasi' => 403],
            $statuses,
            'Paginasi lewat endpoint Livewire tidak ditolak dengan 403 pada setiap layar admin.'
        );
    }

    /**
     * The sharpest version: does the response actually carry employee data?
     *
     * A 200 that renders an empty shell would be a much smaller problem than a
     * 200 that renders names and NIP/NIK, so this separates the two.
     */
    public function test_daftar_pengguna_tidak_bocor_ke_pegawai(): void
    {
        $snapshot = $this->captureSnapshotAsAdmin('/admin/pengguna', 'admin.user-table');

        $response = $this->actingAs($this->pegawai)
            ->livewireUpdate($snapshot, updates: ['q' => 'admin']);

        // Assert the status before reading the body: it names the reason the
        // data stayed in, and it settles the response so the refusal is treated
        // as an outcome under test rather than an unexpected exception.
        $response->assertForbidden();

        $html = $this->renderedHtml($response);

        // The admin's nip_nik is literally "admin", which also occurs in every
        // /admin/... URL on the refusal page — too generic to prove anything.
        // These two needles only appear if the table itself was rendered.
        $this->assertStringNotContainsString(
            $this->admin->name,
            $html,
            'Nama administrator terbaca oleh pegawai lewat endpoint Livewire.'
        );

        $this->assertStringNotContainsString(
            'admin.user-table',
            $html,
            'Komponen tabel pengguna ikut dirender untuk pegawai.'
        );

        $this->assertStringNotContainsString(
            'Nama &amp; NIP/NIK',
            $html,
            'Header tabel pengguna terkirim ke pegawai.'
        );
    }

    // ===================================================================
    // 3. Mid-session role change — the realistic vector.
    // ===================================================================

    /**
     * An admin is demoted to pegawai while their panel tab is still open.
     *
     * Their next keystroke in the search box reuses the snapshot the page was
     * rendered with. On a normal route the demotion bites immediately, because
     * EnsureUserIsAdmin re-reads the role from the database every request.
     */
    public function test_admin_yang_diturunkan_kehilangan_akses_pada_komponen_yang_sudah_termuat(): void
    {
        $demoted = $this->makePegawai('UJILW002', 'UJI Admin Diturunkan');
        $demoted->update(['role' => 'admin']);

        $page = $this->actingAs($demoted)->get('/admin/pengguna');
        $page->assertOk();
        $snapshot = $this->snapshotFor($page->getContent(), 'admin.user-table');

        // The demotion happens now — same session, tab still open.
        $demoted->update(['role' => 'pegawai']);

        // The normal route closes immediately.
        $this->actingAs($demoted->fresh())->get('/admin/pengguna')->assertForbidden();

        // Does the already-loaded component close too?
        $response = $this->actingAs($demoted->fresh())
            ->livewireUpdate($snapshot, updates: ['q' => 'admin']);

        $status = $response->status();
        $body = $this->renderedHtml($response);

        DB::table('activity_logs')->where('user_id', $demoted->id)->delete();
        User::where('id', $demoted->id)->delete();

        $this->assertSame(
            403,
            $status,
            'Admin yang sudah diturunkan jadi pegawai masih dilayani endpoint Livewire. '.
            'Data yang terkirim balik: '.mb_substr($body, 0, 600)
        );
    }

    // ===================================================================
    // 3b. Is the second layer alive, or is it dead code?
    // ===================================================================

    /**
     * Prove the component guard is not decorative.
     *
     * On the normal path both layers fire and the middleware gets there first,
     * so a passing test says nothing about the component. This strips
     * EnsureUserIsAdmin out of Livewire's allow-list — simulating exactly the
     * failure it exists to survive, a Livewire upgrade that stops replaying it —
     * and drives the real endpoint again. 'auth' stays on the list, so the
     * pegawai is still authenticated and the only thing left that can refuse is
     * the component's own boot().
     *
     * Livewire::test() is NOT used for this: it handles aborts differently from
     * the HTTP endpoint and reports no refusal at all, which would make the
     * guard look dead when it is not.
     *
     * The allow-list is a STATIC property, so it is restored in a finally block;
     * leaving it stripped would silently disarm every later test in the process.
     */
    public function test_komponen_admin_menolak_sendiri_tanpa_bantuan_middleware(): void
    {
        $registry = app(PersistentMiddleware::class);
        $original = $registry->getPersistentMiddleware();
        $statuses = [];

        $registry->setPersistentMiddleware(array_values(array_filter(
            $original,
            static fn ($middleware): bool => $middleware !== EnsureUserIsAdmin::class,
        )));

        try {
            foreach (self::SCREENS as $screen => [$path, $component]) {
                $snapshot = $this->captureSnapshotAsAdmin($path, $component);

                $statuses[$screen] = $this->actingAs($this->pegawai)
                    ->livewireUpdate($snapshot, updates: ['q' => 'a'])
                    ->status();
            }
        } finally {
            $registry->setPersistentMiddleware($original);
        }

        $this->assertSame(
            ['Manajemen Hak Akses' => 403, 'Manajemen Pengguna' => 403, 'Manajemen Aplikasi' => 403],
            $statuses,
            'Tanpa middleware persisten, komponen tidak menolak pegawai sendirian.'
        );
    }

    /** The gate must not have locked admins out of their own panel. */
    public function test_admin_tetap_bisa_memakai_ketiga_tabel(): void
    {
        foreach (self::SCREENS as $screen => [$path, $component]) {
            $snapshot = $this->captureSnapshotAsAdmin($path, $component);

            $this->actingAs($this->admin)
                ->livewireUpdate($snapshot, updates: ['q' => 'a'])
                ->assertOk();
        }
    }

    // ===================================================================
    // 4. Scoping the blast radius — how far does this actually reach?
    // ===================================================================

    /**
     * Read is one thing; write would be another. The leaked table markup embeds
     * real action forms (Nonaktifkan / Kelola) complete with a CSRF token, so it
     * is worth proving those targets stay shut.
     */
    public function test_pegawai_tetap_tidak_bisa_menulis_lewat_rute_aksi_admin(): void
    {
        $target = $this->makePegawai('UJILW004', 'UJI Sasaran Tulis');

        $this->actingAs($this->pegawai)
            ->patch("/admin/pengguna/{$target->id}/status")
            ->assertForbidden();

        $this->actingAs($this->pegawai)
            ->put("/admin/akses/{$target->id}", ['access' => []])
            ->assertForbidden();

        $this->assertTrue(
            $target->fresh()->is_active,
            'Status akun berubah padahal aksinya seharusnya ditolak.'
        );

        User::where('id', $target->id)->delete();
    }

    /**
     * Can a pegawai who was NEVER an admin mint an admin snapshot themselves?
     *
     * They can legitimately hold one snapshot: the dashboard statistics
     * component on their own portal page. If the component name inside it could
     * simply be swapped for an admin one, the exposure would be open to every
     * employee rather than only to whoever holds a leaked snapshot.
     */
    public function test_pegawai_tidak_bisa_memalsukan_snapshot_komponen_admin(): void
    {
        $page = $this->actingAs($this->pegawai)->get('/dashboard');
        $page->assertOk();

        $own = $this->snapshotFor($page->getContent(), 'dashboard.user-statistics');

        $forged = str_replace('dashboard.user-statistics', 'admin.user-table', $own);
        $this->assertNotSame($own, $forged, 'Nama komponen tidak ditemukan di dalam snapshot.');

        $response = $this->actingAs($this->pegawai)
            ->livewireUpdate($forged, updates: ['q' => 'admin']);

        $this->assertNotSame(
            200,
            $response->status(),
            'Snapshot palsu diterima: setiap pegawai bisa membaca tabel admin tanpa modal apa pun.'
        );
    }

    /**
     * Same shape, but for is_active (finding A2 seen from the Livewire side):
     * the account is switched off mid-session.
     */
    public function test_akun_yang_dinonaktifkan_kehilangan_akses_pada_komponen_yang_sudah_termuat(): void
    {
        $suspended = $this->makePegawai('UJILW003', 'UJI Admin Dinonaktifkan');
        $suspended->update(['role' => 'admin']);

        $page = $this->actingAs($suspended)->get('/admin/pengguna');
        $page->assertOk();
        $snapshot = $this->snapshotFor($page->getContent(), 'admin.user-table');

        $suspended->update(['is_active' => false]);

        $response = $this->actingAs($suspended->fresh())
            ->livewireUpdate($snapshot, updates: ['q' => 'admin']);

        DB::table('activity_logs')->where('user_id', $suspended->id)->delete();
        User::where('id', $suspended->id)->delete();

        $this->assertContains(
            $response->status(),
            [403, 404, 419],
            'Akun yang sudah dinonaktifkan masih dilayani endpoint Livewire '.
            "(HTTP {$response->status()})."
        );
    }
}
