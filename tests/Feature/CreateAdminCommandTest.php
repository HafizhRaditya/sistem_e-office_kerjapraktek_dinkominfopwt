<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Opd;
use App\Models\User;
use App\Support\ActivityType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `php artisan eoffice:create-admin` — the way into a freshly deployed system.
 *
 * The demo seeder cannot run in production (its accounts use the password
 * "password"), so without this command a migrated database has no account to
 * log in with. These tests cover that it creates a usable admin, and that it
 * refuses the inputs that would otherwise produce a broken or duplicate one.
 */
class CreateAdminCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        User::where('nip_nik', 'like', 'UJI%')->delete();

        parent::tearDown();
    }

    private function opdCode(): string
    {
        return Opd::orderBy('id')->firstOrFail()->code;
    }

    public function test_it_creates_an_admin_with_a_working_password(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM01',
            '--name' => 'Uji Administrator',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'RahasiaKuat9')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKuat9')
            ->assertExitCode(0);

        $admin = User::where('nip_nik', 'UJIADM01')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);

        // Hashed exactly once by the 'hashed' cast — never stored in plain text.
        $this->assertTrue(Hash::check('RahasiaKuat9', $admin->password));
        $this->assertNotSame('RahasiaKuat9', $admin->password);
    }

    public function test_the_new_admin_can_actually_log_in(): void
    {
        // Turnstile keys are set in phpunit.xml, so the verify call really runs.
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM02',
            '--name' => 'Uji Administrator Dua',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'RahasiaKuat9')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKuat9')
            ->assertExitCode(0);

        // End to end through the real login form: an account that cannot sign in
        // would defeat the whole point of the command.
        $this->post('/login', [
            'nip_nik' => 'UJIADM02',
            'password' => 'RahasiaKuat9',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ])->assertRedirect(route('admin.akses.index'));

        $this->assertAuthenticatedAs(User::where('nip_nik', 'UJIADM02')->firstOrFail());
    }

    public function test_it_refuses_a_duplicate_nip(): void
    {
        $before = User::count();

        $this->artisan('eoffice:create-admin', [
            '--nip' => 'ADMIN001',   // already seeded
            '--name' => 'Uji Duplikat',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'RahasiaKuat9')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKuat9')
            ->expectsOutputToContain('NIP/NIK sudah dipakai pengguna lain.')
            ->assertExitCode(1);

        $this->assertSame($before, User::count(), 'tidak boleh ada akun baru');
    }

    public function test_it_refuses_a_weak_password(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM03',
            '--name' => 'Uji Sandi Lemah',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'abc')
            ->expectsQuestion('Ulangi kata sandi', 'abc')
            ->assertExitCode(1);

        $this->assertNull(User::where('nip_nik', 'UJIADM03')->first());
    }

    public function test_it_refuses_a_mismatched_confirmation(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM04',
            '--name' => 'Uji Konfirmasi',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'RahasiaKuat9')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaBeda9')
            ->assertExitCode(1);

        $this->assertNull(User::where('nip_nik', 'UJIADM04')->first());
    }

    public function test_it_refuses_an_unknown_opd(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM05',
            '--name' => 'Uji OPD',
            '--opd' => 'OPD-TIDAK-ADA',
            '--email' => '',
        ])->assertExitCode(1);

        $this->assertNull(User::where('nip_nik', 'UJIADM05')->first());
    }

    /**
     * FR-A12: creating an administrator out-of-band must leave a trace, with the
     * console named as the origin since nobody was signed in.
     */
    public function test_it_records_the_creation_in_the_activity_log(): void
    {
        $this->artisan('eoffice:create-admin', [
            '--nip' => 'UJIADM06',
            '--name' => 'Uji Jejak Audit',
            '--opd' => $this->opdCode(),
            '--email' => '',
        ])
            ->expectsQuestion('Kata sandi (min. 8 karakter, huruf dan angka)', 'RahasiaKuat9')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKuat9')
            ->assertExitCode(0);

        $admin = User::where('nip_nik', 'UJIADM06')->firstOrFail();

        $log = ActivityLog::where('activity_type', ActivityType::USER_CREATED)
            ->where('subject_type', 'user')
            ->where('subject_id', $admin->id)
            ->first();

        $this->assertNotNull($log, 'pembuatan admin harus tercatat di activity_logs');
        $this->assertNull($log->user_id, 'tidak ada pelaku yang login di konsol');
        $this->assertStringContainsString('konsol', $log->description);
        $this->assertSame('artisan eoffice:create-admin', $log->user_agent);
    }
}
