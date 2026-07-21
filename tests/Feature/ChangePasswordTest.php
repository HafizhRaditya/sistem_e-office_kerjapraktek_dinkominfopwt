<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Change password (FR-A06).
 *
 * The old system let a user set a new password without proving the old one;
 * the rebuild closes that gap, so the current-password check is the point of
 * these tests. The password policy (min 8, letters AND numbers) is verified
 * alongside it, and the happy path is proven end to end: the new password
 * actually works at the login form.
 */
class ChangePasswordTest extends TestCase
{
    private const CURRENT_PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000002')->firstOrFail();
    }

    // ------------------------------------------------------------------ AUT-09

    public function test_the_old_password_must_be_correct(): void
    {
        $user = $this->pegawai();
        $hashBefore = $user->password;

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'bukan-sandi-lama',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrors(['current_password' => 'Kata sandi lama salah.']);

        // The stored hash is untouched, and the original password still works.
        $this->assertSame($hashBefore, $user->fresh()->password, 'sandi tidak boleh berubah');
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    // ------------------------------------------------------------------ AUT-10

    public function test_a_new_password_shorter_than_eight_characters_is_rejected(): void
    {
        $user = $this->pegawai();
        $hashBefore = $user->password;

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => 'abc1',
                'password_confirmation' => 'abc1',
            ])
            ->assertSessionHasErrors(['password' => 'Kata sandi baru minimal 8 karakter.']);

        $this->assertSame($hashBefore, $user->fresh()->password);
    }

    public function test_a_new_password_without_digits_is_rejected(): void
    {
        $user = $this->pegawai();

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => 'hanyahurufsaja',
                'password_confirmation' => 'hanyahurufsaja',
            ])
            ->assertSessionHasErrors(['password' => 'Kata sandi baru harus mengandung huruf dan angka.']);

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->pegawai();

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia456',
            ])
            ->assertSessionHasErrors(['password' => 'Konfirmasi kata sandi baru tidak cocok.']);

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public function test_a_valid_change_succeeds_is_logged_and_the_new_password_works(): void
    {
        $user = $this->pegawai();
        $newPassword = 'rahasia123';

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHas('status', 'Kata sandi berhasil diubah.');

        // Hashed exactly once by the 'hashed' cast — not stored in plain text.
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check($newPassword, $fresh->password));
        $this->assertFalse(Hash::check(self::CURRENT_PASSWORD, $fresh->password), 'sandi lama harus tidak berlaku lagi');
        $this->assertNotSame($newPassword, $fresh->password);

        // FR-A12.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => 'password_changed',
        ]);

        // End to end: the new password really works at the login form.
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'nip_nik' => '3302010000000002',
            'password' => $newPassword,
            'cf-turnstile-response' => 'dummy-token-for-test',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($fresh);
    }

    public function test_a_guest_cannot_reach_the_change_password_page(): void
    {
        $this->get(route('password.edit'))->assertRedirect(route('login'));
        $this->put(route('password.update'), [])->assertRedirect(route('login'));
    }
}
