<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * `nip_nik` means the same identity to both doors (finding A4).
 *
 * The column carries a plain UNIQUE index, which PostgreSQL applies
 * case-sensitively. The two login paths disagreed about what that meant: the
 * Keycloak callback matched with lower() on both sides, the password form
 * matched exactly. So an employee stored as "ADMIN001" could sign in through
 * SSO typing "admin001" and be refused at the form with the correct password.
 *
 * Two halves are covered here:
 *  - the password form now resolves the identity case-insensitively, and
 *    refuses rather than guessing when legacy data is ambiguous;
 *  - the admin panel and the console refuse to CREATE a new collision.
 *
 * The database-level constraint is NOT part of this: a unique index on
 * lower(nip_nik) is a migration, and migrations need Hafizh's approval.
 */
class NipNikCaseConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);
    }

    /** Mirrors AuthController::throttleKey(): lowercased nip_nik + client IP. */
    private function clearThrottle(string $nip): void
    {
        RateLimiter::clear(strtolower($nip).'|127.0.0.1');
    }

    /** @param array<string, mixed> $overrides */
    private function attemptLogin(array $overrides = [])
    {
        return $this->post('/login', array_merge([
            'nip_nik' => 'admin',
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ], $overrides));
    }

    private function opdId(): int
    {
        return Opd::where('code', 'SETDA')->firstOrFail()->id;
    }

    /**
     * Insert straight through the query builder, bypassing the model and its
     * validation. This is legacy data that the new rule would now refuse — the
     * point is to prove the login path survives it.
     */
    private function forceUser(string $nipNik, string $name): int
    {
        return DB::table('users')->insertGetId([
            'opd_id' => $this->opdId(),
            'nip_nik' => $nipNik,
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => 'pegawai',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ===================================================================
    // Login resolves the identity the same way SSO does
    // ===================================================================

    public function test_login_berhasil_walau_huruf_besar_kecil_berbeda(): void
    {
        $this->clearThrottle('ADMIN');

        // The seeded account is stored as "admin"; the employee types it shouting.
        $this->attemptLogin(['nip_nik' => 'ADMIN'])->assertRedirect();

        $this->assertAuthenticatedAs(User::where('nip_nik', 'admin')->firstOrFail());
    }

    public function test_login_dengan_huruf_persis_tetap_berjalan(): void
    {
        $this->clearThrottle('admin');

        $this->attemptLogin()->assertRedirect();

        $this->assertAuthenticatedAs(User::where('nip_nik', 'admin')->firstOrFail());
    }

    public function test_nip_nik_yang_tidak_terdaftar_tetap_ditolak(): void
    {
        $this->clearThrottle('TIDAKADA123');

        $this->attemptLogin(['nip_nik' => 'TIDAKADA123'])
            ->assertSessionHasErrors('nip_nik');

        $this->assertGuest();
    }

    /**
     * Colliding legacy rows must not lock their owners out.
     *
     * Before this change both accounts worked, each with its own exact spelling.
     * Refusing every ambiguous lookup would have turned a consistency fix into
     * an outage for exactly the people it is about, so an exact-case match still
     * resolves.
     */
    public function test_pemilik_baris_bertabrakan_tetap_bisa_masuk_dengan_ejaan_persis(): void
    {
        $this->clearThrottle('UJICASE001');

        $besar = $this->forceUser('UJICASE001', 'UJI Huruf Besar');
        $kecil = $this->forceUser('ujicase001', 'UJI Huruf Kecil');

        $this->attemptLogin(['nip_nik' => 'UJICASE001'])->assertRedirect();
        $this->assertAuthenticatedAs(User::find($besar));

        $this->post('/logout');
        $this->clearThrottle('ujicase001');

        $this->attemptLogin(['nip_nik' => 'ujicase001'])->assertRedirect();
        $this->assertAuthenticatedAs(User::find($kecil));

        DB::table('activity_logs')->whereIn('user_id', [$besar, $kecil])->delete();
        DB::table('users')->whereIn('id', [$besar, $kecil])->delete();
    }

    /**
     * Ambiguity with no exact match must refuse, never guess: choosing one of
     * two accounts could sign somebody into a colleague's.
     */
    public function test_login_ditolak_bila_ejaan_tidak_persis_dan_cocok_lebih_dari_satu(): void
    {
        $this->clearThrottle('UjiCase001');

        $besar = $this->forceUser('UJICASE001', 'UJI Huruf Besar');
        $kecil = $this->forceUser('ujicase001', 'UJI Huruf Kecil');

        // Neither row is spelled this way, so nothing here says which is meant.
        $this->attemptLogin(['nip_nik' => 'UjiCase001'])
            ->assertSessionHasErrors('nip_nik');

        $this->assertGuest();

        DB::table('activity_logs')->whereIn('user_id', [$besar, $kecil])->delete();
        DB::table('users')->whereIn('id', [$besar, $kecil])->delete();
    }

    // ===================================================================
    // New collisions can no longer be created
    // ===================================================================

    public function test_admin_tidak_bisa_membuat_pengguna_yang_bertabrakan_hanya_karena_huruf(): void
    {
        $admin = User::where('nip_nik', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'UJI Tabrakan Huruf',
                'nip_nik' => 'ADMIN',           // seeded account is "admin"
                'opd_id' => $this->opdId(),
                'role' => 'pegawai',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('nip_nik');

        $this->assertSame(
            0,
            User::where('nip_nik', 'ADMIN')->count(),
            'Akun yang bertabrakan huruf besar-kecil tetap terbuat.'
        );
    }

    public function test_admin_tidak_bisa_mengubah_nip_nik_menjadi_bertabrakan(): void
    {
        $admin = User::where('nip_nik', 'admin')->firstOrFail();
        $target = User::where('nip_nik', '3302010000000001')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => $target->name,
                'nip_nik' => 'Admin',
                'opd_id' => $target->opd_id,
                'role' => $target->role,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('nip_nik');

        $this->assertSame('3302010000000001', $target->fresh()->nip_nik);
    }

    /** The ignore() half: keeping your own nip_nik must not trip the rule. */
    public function test_menyimpan_pengguna_tanpa_mengubah_nip_nik_tetap_boleh(): void
    {
        $admin = User::where('nip_nik', 'admin')->firstOrFail();
        $target = User::where('nip_nik', '3302010000000001')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'UJI Nama Baru',
                'nip_nik' => $target->nip_nik,
                'opd_id' => $target->opd_id,
                'role' => $target->role,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('UJI Nama Baru', $target->fresh()->name);
    }

    public function test_perintah_create_admin_menolak_tabrakan_huruf(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'Admin',
            '--name' => 'UJI Admin Tabrakan',
            '--opd' => 'SETDA',
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'Rahasia123')
            ->expectsQuestion('Ulangi kata sandi', 'Rahasia123')
            ->assertExitCode(1);

        $this->assertSame(0, User::where('nip_nik', 'Admin')->count());
    }
}
