<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Themed 403 page (LANGKAH 3).
 *
 * The point of these tests is that the page is *only* cosmetic: the status code
 * stays 403 (never a redirect), and the specific abort() message still reaches
 * the screen. The three launch rejections are told apart by that message, so
 * losing it would quietly reduce three distinct failures to one generic wall.
 */
class ForbiddenPageTest extends TestCase
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

    public function test_the_custom_page_is_used_and_the_status_is_still_403(): void
    {
        $response = $this->actingAs($this->user('3302010000000002'))->get('/admin/akses');

        $response->assertStatus(403);
        $response->assertSee('Error 403');
        $response->assertSee('Anda tidak memiliki akses ke halaman ini');
        $response->assertSee('E-Office Banyumas — Pemerintah Kabupaten Banyumas', false);

        // Cosmetic only: still a hard 403, never a bounce to another page.
        $this->assertFalse($response->isRedirect(), '403 tidak boleh berubah menjadi redirect');
    }

    public function test_the_brand_red_is_used(): void
    {
        $html = $this->actingAs($this->user('3302010000000002'))
            ->get('/admin/akses')
            ->getContent();

        // --color-brand is #d32f2f, so the brand utilities carry the portal red.
        $this->assertStringContainsString('text-brand', $html);
        $this->assertStringContainsString('bg-brand', $html);
    }

    /**
     * Regression guard for the three launch rejections — each must still say
     * which one it was.
     */
    public function test_every_specific_abort_message_still_reaches_the_page(): void
    {
        $budi = $this->user('3302010000000001');
        $siti = $this->user('3302010000000002');

        // 1. No access at all.
        $this->actingAs($siti)->get('/launch/simpus')
            ->assertStatus(403)
            ->assertSee('Anda tidak memiliki akses ke aplikasi ini.');

        // 2. Has access, but the application is inactive.
        $this->actingAs($budi)->get('/launch/agenda-pimpinan')
            ->assertStatus(403)
            ->assertSee('Aplikasi ini sedang tidak aktif.');

        // 3. Application fine, the specific link is inactive.
        $dataHub = \App\Models\Application::where('slug', 'data-hub-banyumas')->firstOrFail();
        $inactiveLink = $dataHub->links()->where('is_active', false)->firstOrFail();

        $this->actingAs($siti)->get("/launch/data-hub-banyumas/{$inactiveLink->id}")
            ->assertStatus(403)
            ->assertSee('Tautan aplikasi ini sedang tidak aktif.');
    }

    public function test_the_generic_headline_is_not_duplicated_by_the_abort_message(): void
    {
        $html = $this->actingAs($this->user('3302010000000002'))
            ->get('/admin/akses')
            ->getContent();

        // EnsureUserIsAdmin aborts with its own sentence; the headline must not
        // be printed twice when the two happen to say the same thing.
        $this->assertSame(1, substr_count($html, 'Anda tidak memiliki akses ke halaman ini'));
        $this->assertStringContainsString('Halaman ini hanya untuk administrator.', $html);
    }

    public function test_the_back_button_follows_the_role(): void
    {
        // Pegawai -> portal dashboard.
        $this->actingAs($this->user('3302010000000002'))
            ->get('/admin/akses')
            ->assertSee('Kembali ke Dashboard');

        // Admin (rejected by the availability guard, not the role guard) -> panel.
        $this->actingAs($this->user('ADMIN001'))
            ->get('/launch/agenda-pimpinan')
            ->assertSee('Kembali ke Panel Admin');
    }

    public function test_a_guest_is_offered_the_login_page_instead_of_a_dashboard_link(): void
    {
        // Rendered directly: every guarded route bounces a guest to /login before
        // it can 403, so this branch has no reachable URL to drive.
        $html = view('errors.403', [
            'exception' => new HttpException(403, 'Anda tidak memiliki akses ke aplikasi ini.'),
        ])->render();

        $this->assertStringContainsString('Masuk ke E-Office', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringNotContainsString('Kembali ke Dashboard', $html);
        $this->assertStringNotContainsString('Keluar', $html, 'tamu tidak punya sesi untuk di-logout');
    }
}
