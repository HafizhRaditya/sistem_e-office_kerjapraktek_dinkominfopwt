<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use App\Support\UserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Revoking access mid-session (findings A2 and A3).
 *
 * A2 — both login paths refuse an inactive account, but nothing re-read
 *      `is_active` afterwards, so "Nonaktifkan" left an already signed-in
 *      employee working until their session lapsed.
 * A3 — changing a password left every other session of that account signed in,
 *      which is backwards: an admin resets a password precisely when they think
 *      somebody else is in the account.
 *
 * NOTE on the session driver. phpunit.xml sets SESSION_DRIVER=array, so the
 * `sessions` table is not used during tests and UserSessions::purge() would
 * short-circuit. The tests that exercise eviction therefore switch the driver to
 * 'database' explicitly and seed rows, which is the configuration production
 * actually runs (config/session.php defaults to 'database').
 */
class RevokedAccessTest extends TestCase
{
    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000001')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('nip_nik', 'admin')->firstOrFail();
    }

    private function makeUser(string $nipNik, string $name): User
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
     * Encode session attributes the way the configured driver does.
     *
     * Kept in step with production rather than hard-coded: config/session.php
     * sets serialization to 'json', and a fixture that used serialize() instead
     * is precisely how a reader bug once went unnoticed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function encodeSessionPayload(array $attributes): string
    {
        return config('session.serialization', 'json') === 'json'
            ? json_encode($attributes, JSON_THROW_ON_ERROR)
            : serialize($attributes);
    }

    /** Seed a server-side session row as if this user had a browser open. */
    private function seedSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode(self::encodeSessionPayload([])),
            'last_activity' => time(),
        ]);
    }

    // ===================================================================
    // A2 — is_active is re-checked on every request
    // ===================================================================

    public function test_pegawai_aktif_tetap_bisa_membuka_portal(): void
    {
        $this->withoutVite();

        $this->actingAs($this->pegawai())->get(route('dashboard'))->assertOk();
    }

    public function test_akun_yang_dinonaktifkan_langsung_kehilangan_sesi_berjalan(): void
    {
        $this->withoutVite();

        $user = $this->makeUser('UJIREV001', 'UJI Dinonaktifkan');

        // Signed in and working.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // An admin switches the account off elsewhere.
        $user->update(['is_active' => false]);

        // The very next request is bounced, not served until session expiry.
        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        User::where('id', $user->id)->delete();
    }

    public function test_akun_yang_dinonaktifkan_ditolak_di_endpoint_livewire(): void
    {
        $user = $this->makeUser('UJIREV002', 'UJI Dinonaktifkan Livewire');
        $user->update(['is_active' => false]);

        // A JSON/Livewire caller gets a hard status rather than a redirect the
        // client would swallow.
        $this->actingAs($user->fresh())
            ->withHeaders(['X-Livewire' => 'true', 'Content-Type' => 'application/json'])
            ->postJson(route('default-livewire.update'), ['components' => []])
            ->assertForbidden();

        User::where('id', $user->id)->delete();
    }

    public function test_admin_yang_dinonaktifkan_kehilangan_panel(): void
    {
        $admin = $this->makeUser('UJIREV003', 'UJI Admin Dinonaktifkan');
        $admin->update(['role' => 'admin']);

        $this->actingAs($admin->fresh())->get('/admin/akses')->assertOk();

        $admin->update(['is_active' => false]);

        $this->actingAs($admin->fresh())
            ->get('/admin/akses')
            ->assertRedirect(route('login'));

        User::where('id', $admin->id)->delete();
    }

    // ===================================================================
    // A3 — a password change ends the account's other sessions
    // ===================================================================

    public function test_purge_mencabut_sesi_lain_dan_menyisakan_yang_ditunjuk(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser('UJIREV004', 'UJI Pemilik Sesi');
        $other = $this->makeUser('UJIREV005', 'UJI Pengguna Lain');

        $this->seedSession('sesi-ini', $user->id);
        $this->seedSession('sesi-ponsel', $user->id);
        $this->seedSession('sesi-warnet', $user->id);
        $this->seedSession('sesi-orang-lain', $other->id);
        $this->seedSession('sesi-tamu', null);

        $ended = UserSessions::purge($user->id, 'sesi-ini');

        $this->assertSame(2, $ended);
        $this->assertSame(
            ['sesi-ini'],
            DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all(),
            'Sesi yang ditunjuk untuk disisakan ikut tercabut.'
        );

        // Nobody else is touched.
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertSame(1, DB::table('sessions')->whereNull('user_id')->count());

        DB::table('sessions')->whereIn('id', [
            'sesi-ini', 'sesi-ponsel', 'sesi-warnet', 'sesi-orang-lain', 'sesi-tamu',
        ])->delete();
        User::whereIn('id', [$user->id, $other->id])->delete();
    }

    public function test_purge_mencabut_seluruh_sesi_bila_tidak_ada_yang_disisakan(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser('UJIREV006', 'UJI Cabut Semua');

        $this->seedSession('cabut-1', $user->id);
        $this->seedSession('cabut-2', $user->id);

        $this->assertSame(2, UserSessions::purge($user->id));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());

        User::where('id', $user->id)->delete();
    }

    /**
     * The driver guard must be loud, not silent: on any driver other than
     * database the eviction cannot happen, and a security control that quietly
     * does nothing is worse than one that is absent.
     */
    public function test_purge_tidak_mengklaim_berhasil_pada_driver_lain(): void
    {
        config(['session.driver' => 'array']);

        $user = $this->makeUser('UJIREV007', 'UJI Driver Lain');
        $this->seedSession('tetap-ada', $user->id);

        $this->assertSame(0, UserSessions::purge($user->id));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());

        DB::table('sessions')->where('id', 'tetap-ada')->delete();
        User::where('id', $user->id)->delete();
    }

    public function test_ganti_sandi_sendiri_mencabut_sesi_lain(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);
        config(['session.driver' => 'database']);

        $user = $this->makeUser('UJIREV008', 'UJI Ganti Sandi');

        $this->seedSession('perangkat-lain-1', $user->id);
        $this->seedSession('perangkat-lain-2', $user->id);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'SandiBaru9',
                'password_confirmation' => 'SandiBaru9',
            ])
            ->assertRedirect(route('password.edit'));

        $this->assertTrue(Hash::check('SandiBaru9', $user->fresh()->password));

        $this->assertSame(
            0,
            DB::table('sessions')->whereIn('id', ['perangkat-lain-1', 'perangkat-lain-2'])->count(),
            'Sesi di perangkat lain masih hidup setelah kata sandi diganti.'
        );

        User::where('id', $user->id)->delete();
    }

    public function test_reset_sandi_oleh_admin_mencabut_seluruh_sesi_target(): void
    {
        config(['session.driver' => 'database']);

        $target = $this->makeUser('UJIREV009', 'UJI Target Reset');

        $this->seedSession('target-1', $target->id);
        $this->seedSession('target-2', $target->id);

        $this->actingAs($this->admin())
            ->put(route('admin.users.password', $target), [
                'password' => 'SandiBaru9',
                'password_confirmation' => 'SandiBaru9',
            ])
            ->assertRedirect();

        $this->assertSame(
            0,
            DB::table('sessions')->where('user_id', $target->id)->count(),
            'Reset kata sandi oleh admin tidak mengeluarkan pengguna dari sesi yang sedang berjalan.'
        );

        DB::table('activity_logs')->where('subject_id', $target->id)->delete();
        User::where('id', $target->id)->delete();
    }
}
