<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Turnstile behaviour when TURNSTILE_SECRET is missing (FR-A02).
 *
 * The two environments differ on purpose. Locally a missing secret skips
 * verification so the app runs without Cloudflare keys. In production it must
 * fail closed: forgetting the secret on the server would otherwise disable bot
 * protection while the login page kept working normally, which is the kind of
 * misconfiguration nobody notices until it is exploited.
 */
class TurnstileFailClosedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test here rewrites the environment string, and Laravel's CSRF
        // exemption keys off env being "testing" — so any rewrite makes the
        // requests 419 before reaching the controller. Disabling that one
        // middleware keeps the rest of the login pipeline (throttle, Turnstile,
        // credentials, is_active) under test, which is the part that matters.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function actAsProduction(): void
    {
        app()['env'] = 'production';
    }

    private function attemptLogin(): \Illuminate\Testing\TestResponse
    {
        return $this->from('/login')->post('/login', [
            'nip_nik' => '3302010000000002',
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ]);
    }

    public function test_production_refuses_login_when_the_secret_is_missing(): void
    {
        $this->actAsProduction();
        config(['services.turnstile.secret' => null]);

        // No Http::fake() needed: the request must be refused before any call to
        // Cloudflare is attempted.
        Http::fake();

        $this->attemptLogin()
            ->assertRedirect('/login')
            ->assertSessionHasErrors('turnstile');

        // Kredensial benar pun tidak boleh lolos tanpa verifikasi.
        $this->assertGuest();

        Http::assertNothingSent();
    }

    public function test_production_still_allows_login_when_the_secret_is_configured(): void
    {
        $this->actAsProduction();
        config(['services.turnstile.secret' => 'secret-produksi']);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $this->attemptLogin()->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_production_refuses_login_when_cloudflare_rejects_the_token(): void
    {
        $this->actAsProduction();
        config(['services.turnstile.secret' => 'secret-produksi']);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false], 200)]);

        $this->attemptLogin()->assertSessionHasErrors('turnstile');

        $this->assertGuest();
    }

    /**
     * The local escape hatch must survive: without it the app cannot be run or
     * tested without Cloudflare credentials.
     */
    public function test_local_still_skips_verification_when_the_secret_is_missing(): void
    {
        app()['env'] = 'local';
        config(['services.turnstile.secret' => null]);

        Http::fake();

        $this->attemptLogin()->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }
}
