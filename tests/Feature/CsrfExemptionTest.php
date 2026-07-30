<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The CSRF exemption for Keycloak's back-channel logout endpoint.
 *
 * This needs its own test because an ordinary HTTP test CANNOT prove it.
 * PreventRequestForgery::handle() short-circuits on runningUnitTests(), so every
 * request the test suite makes skips CSRF entirely — the whole of
 * KeycloakBackchannelLogoutTest would stay green even if the exemption were
 * missing or misspelled, and the failure would appear only in production as a
 * 419 on every logout Keycloak sends.
 *
 * So the middleware's own exclusion list is inspected directly instead.
 *
 * The second test matters as much as the first: an exemption that is too broad
 * silently removes CSRF protection from forms that need it, and that is a much
 * worse outcome than the one being fixed.
 */
class CsrfExemptionTest extends TestCase
{
    private const BACKCHANNEL_PATH = 'auth/keycloak/backchannel-logout';

    private function middleware(): PreventRequestForgery
    {
        return app(PreventRequestForgery::class);
    }

    /** inExceptArray() is protected; it is the method that decides. */
    private function isExempt(string $path): bool
    {
        $method = new ReflectionMethod(PreventRequestForgery::class, 'inExceptArray');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->middleware(), Request::create('/'.ltrim($path, '/'), 'POST'));
    }

    public function test_endpoint_back_channel_logout_dikecualikan_dari_csrf(): void
    {
        // Keycloak calls this server-to-server: no browser, no session, and so
        // no CSRF token it could ever send. The signed logout token is what
        // authenticates it instead.
        $this->assertTrue(
            $this->isExempt(self::BACKCHANNEL_PATH),
            'Endpoint back-channel logout tidak dikecualikan; Keycloak akan menerima 419 di produksi.'
        );
    }

    public function test_pengecualian_terdaftar_persis_satu_path(): void
    {
        $this->assertSame(
            [self::BACKCHANNEL_PATH],
            $this->middleware()->getExcludedPaths(),
            'Daftar pengecualian CSRF harus memuat tepat satu path.'
        );
    }

    /**
     * The routes that must keep CSRF protection. If a wildcard or a stray slash
     * ever widened the exemption, this is what would notice.
     */
    public function test_rute_lain_tetap_dilindungi_csrf(): void
    {
        $stillProtected = [
            'login',
            'logout',
            'ubah-sandi',
            'admin/akses/1',
            'admin/pengguna',
            'admin/pengguna/1/status',
            'admin/pengguna/1/reset-sandi',
            'kuisioner/1/klik',
            'auth/keycloak/callback',
        ];

        $leaked = array_values(array_filter($stillProtected, fn (string $path): bool => $this->isExempt($path)));

        $this->assertSame(
            [],
            $leaked,
            'Rute berikut ikut kehilangan proteksi CSRF: '.implode(', ', $leaked)
        );
    }
}
