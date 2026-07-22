<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Authentication failure paths and session lifecycle (FR-A01/A02/A12).
 *
 * LoginRedirectTest already covers the happy paths; this covers what happens
 * when login is refused — wrong credentials, unknown identity, empty fields,
 * throttling, a disabled account — plus logout.
 *
 * Turnstile is configured in phpunit.xml, so verification really runs and the
 * Cloudflare call is faked here. That keeps the whole pipeline under test
 * (validation -> throttle -> Turnstile -> credentials -> is_active).
 */
class AuthenticationTest extends TestCase
{
    private const VALID_PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        // The throttle key is (nip_nik + IP) and lives in the cache, not the
        // database, so RefreshDatabase does not reset it. Clear it explicitly so
        // attempt counts never leak between tests.
        RateLimiter::clear($this->throttleKeyFor('ADMIN001'));
        RateLimiter::clear($this->throttleKeyFor('3302010000000002'));
        RateLimiter::clear($this->throttleKeyFor('9999999999999999'));
    }

    /** Mirrors AuthController::throttleKey(): lowercased nip_nik + client IP. */
    private function throttleKeyFor(string $nip): string
    {
        return strtolower($nip).'|127.0.0.1';
    }

    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides */
    private function attemptLogin(array $overrides = [])
    {
        return $this->post('/login', array_merge([
            'nip_nik' => '3302010000000002',
            'password' => self::VALID_PASSWORD,
            'cf-turnstile-response' => 'dummy-token-for-test',
        ], $overrides));
    }

    // ------------------------------------------------------------------ AUT-03

    public function test_login_with_a_wrong_password_is_rejected_and_logged(): void
    {
        $siti = $this->user('3302010000000002');
        $before = ActivityLog::where('activity_type', 'login_failed')->count();

        $this->from('/login')
            ->attemptLogin(['password' => 'sandi-yang-salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['nip_nik' => 'NIP/NIK atau kata sandi salah.']);

        // Kredensial salah tidak boleh membuat sesi.
        $this->assertGuest();

        // FR-A12: the attempted account is the subject; the actor is unknown because authentication failed.
        $this->assertSame($before + 1, ActivityLog::where('activity_type', 'login_failed')->count());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'subject_type' => 'user',
            'subject_id' => $siti->id,
            'activity_type' => 'login_failed',
        ]);
    }

    // ------------------------------------------------------------------ AUT-04

    public function test_login_with_an_unknown_nip_is_rejected_with_the_same_message(): void
    {
        $this->from('/login')
            ->attemptLogin(['nip_nik' => '9999999999999999'])
            ->assertRedirect('/login')
            // Deliberately identical to the wrong-password message: the response
            // must not reveal whether the identity exists.
            ->assertSessionHasErrors(['nip_nik' => 'NIP/NIK atau kata sandi salah.']);

        $this->assertGuest();

        // Logged with a null user_id, since no account matched.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'subject_type' => 'login_identity',
            'subject_label' => '9999999999999999',
            'activity_type' => 'login_failed',
        ]);
    }

    // ------------------------------------------------------------------ AUT-05

    public function test_login_with_empty_fields_is_rejected_by_validation(): void
    {
        $this->from('/login')
            ->post('/login', ['nip_nik' => '', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'nip_nik' => 'NIP/NIK wajib diisi.',
                'password' => 'Kata sandi wajib diisi.',
            ]);

        $this->assertGuest();
    }

    // ------------------------------------------------------------------ AUT-06

    public function test_repeated_failures_are_throttled_after_five_attempts(): void
    {
        // Five failures are allowed through (each is rejected on credentials).
        for ($i = 1; $i <= 5; $i++) {
            $this->from('/login')
                ->attemptLogin(['password' => 'sandi-yang-salah'])
                ->assertSessionHasErrors(['nip_nik' => 'NIP/NIK atau kata sandi salah.']);
        }

        // The sixth is blocked by the limiter before credentials are even checked.
        $response = $this->from('/login')->attemptLogin(['password' => 'sandi-yang-salah']);

        $response->assertSessionHasErrors('nip_nik');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk',
            session('errors')->getBag('default')->first('nip_nik')
        );

        $this->assertGuest();
    }

    public function test_the_throttle_blocks_even_when_the_password_is_correct(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->from('/login')->attemptLogin(['password' => 'sandi-yang-salah']);
        }

        // Right password, but the limiter is already tripped for this nip+IP.
        $this->from('/login')->attemptLogin();

        // Rate limit berlaku sebelum kredensial diperiksa.
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ AUT-07

    public function test_a_deactivated_account_cannot_log_in_even_with_the_right_password(): void
    {
        $siti = $this->user('3302010000000002');
        $siti->update(['is_active' => false]);

        $this->from('/login')
            ->attemptLogin()   // correct password
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['nip_nik' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.']);

        // Akun nonaktif tidak mendapat sesi meski sandinya benar.
        $this->assertGuest();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'subject_type' => 'user',
            'subject_id' => $siti->id,
            'activity_type' => 'login_failed',
        ]);

        // last_login_at must not be touched by a refused login.
        $this->assertNull($siti->fresh()->last_login_at);
    }

    // ------------------------------------------------------------------ AUT-11

    public function test_logout_ends_the_session_and_is_logged(): void
    {
        $siti = $this->user('3302010000000002');

        $this->attemptLogin()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($siti);

        $this->post('/logout')->assertRedirect(route('login'));

        // Sesi berakhir setelah logout.
        $this->assertGuest();

        // FR-A12: attributed to the user who logged out (logged before Auth::logout()).
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $siti->id,
            'activity_type' => 'logout',
        ]);

        // A guarded page is no longer reachable with the dead session.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
