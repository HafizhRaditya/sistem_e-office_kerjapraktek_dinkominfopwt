<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The "Masuk dengan Keycloak" button must appear only when Keycloak is actually
 * configured.
 *
 * The route behind it 404s when KEYCLOAK_* is unset, so rendering the button
 * unconditionally would hand users a dead link on every environment without SSO
 * keys — exactly the dead end the "Lupa kata sandi" link was demoted to plain
 * text to avoid. These tests pin the button and its route together so the two
 * cannot drift apart.
 */
class KeycloakLoginButtonTest extends TestCase
{
    private function configureKeycloak(): void
    {
        config([
            'services.keycloak.base_url' => 'https://account.dev.example.test',
            'services.keycloak.realm' => 'EOffice',
            'services.keycloak.client_id' => 'eoffice-portal',
            'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.redirect' => 'http://127.0.0.1:8000/auth/keycloak/callback',
        ]);
    }

    private function clearKeycloakConfig(): void
    {
        config([
            'services.keycloak.base_url' => null,
            'services.keycloak.realm' => null,
            'services.keycloak.client_id' => null,
            'services.keycloak.client_secret' => null,
            'services.keycloak.redirect' => null,
        ]);
    }

    public function test_button_is_rendered_when_keycloak_is_configured(): void
    {
        $this->configureKeycloak();

        $this->get('/login')
            ->assertOk()
            ->assertSee('MASUK DENGAN KEYCLOAK')
            ->assertSee(route('keycloak.redirect'));
    }

    public function test_button_is_hidden_when_keycloak_is_not_configured(): void
    {
        $this->clearKeycloakConfig();

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('MASUK DENGAN KEYCLOAK')
            ->assertDontSee('/auth/keycloak/redirect');
    }

    /**
     * A single missing value is enough to disable the path: a half-configured
     * realm would send users to a broken Keycloak round trip.
     */
    public function test_button_is_hidden_when_only_the_secret_is_missing(): void
    {
        $this->configureKeycloak();
        config(['services.keycloak.client_secret' => null]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('MASUK DENGAN KEYCLOAK');
    }

    /** The NIP/NIK form stays the primary path and is never replaced. */
    public function test_password_form_remains_present_alongside_the_sso_button(): void
    {
        $this->configureKeycloak();

        $this->get('/login')
            ->assertOk()
            ->assertSee('MASUK SEKARANG')
            ->assertSee('name="nip_nik"', false)
            ->assertSee('name="password"', false)
            ->assertSee(route('login.attempt'));
    }

    /** The button is hidden precisely because this route would 404. */
    public function test_sso_route_returns_404_when_keycloak_is_not_configured(): void
    {
        $this->clearKeycloakConfig();

        $this->get('/auth/keycloak/redirect')->assertNotFound();
        $this->get('/auth/keycloak/callback')->assertNotFound();
    }
}
