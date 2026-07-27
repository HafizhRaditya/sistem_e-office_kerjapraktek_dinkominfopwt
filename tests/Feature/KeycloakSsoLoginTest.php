<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\KeycloakOidcService;
use App\Support\ActivityType;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Fixtures\KeycloakTestKeys;
use Tests\TestCase;

/**
 * Keycloak SSO login flow (second login path beside NIP/NIK).
 *
 * No test here reaches a real Keycloak. Discovery, JWKS and the token endpoint
 * are all served by a Guzzle handler wired into the service, and the ID tokens
 * are signed locally with the static test keys in Tests\Fixtures. That means the
 * signature verification under test is the real one — a token signed with the
 * wrong key genuinely fails to verify rather than being waved through by a stub.
 */
class KeycloakSsoLoginTest extends TestCase
{
    private const ISSUER_HOST = 'https://keycloak.test';

    private const ISSUER = 'https://keycloak.test/realms/EOffice';

    private const CLIENT_ID = 'eoffice-portal';

    private const REDIRECT = 'http://localhost/auth/keycloak/callback';

    private const STATE = 'STATE_FOR_TEST';

    private const NONCE = 'NONCE_FOR_TEST';

    private const SUBJECT = 'kc-subject-0001';

    /** Seeded pegawai used as the SSO identity across these tests. */
    private const PEGAWAI_NIP = '3302010000000001';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url' => self::ISSUER_HOST,
            'services.keycloak.realm' => 'EOffice',
            'services.keycloak.client_id' => self::CLIENT_ID,
            'services.keycloak.client_secret' => 'test-client-secret',
            'services.keycloak.redirect' => self::REDIRECT,
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $overrides */
    private function signIdToken(array $overrides = [], ?JWK $signingKey = null): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'sub' => self::SUBJECT,
            'aud' => self::CLIENT_ID,
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => self::NONCE,
            'preferred_username' => self::PEGAWAI_NIP,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
        ], $overrides);

        $key = $signingKey ?? new JWK(KeycloakTestKeys::realm());

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($key, ['alg' => 'RS256', 'kid' => KeycloakTestKeys::KID])
            ->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    /**
     * Bind the OIDC service to a transport that answers discovery, JWKS and the
     * token endpoint locally. Pass $unreachable to simulate Keycloak being down.
     */
    private function fakeKeycloak(?string $idToken = null, bool $unreachable = false): void
    {
        $idToken ??= $this->signIdToken();

        $publicJwks = ['keys' => [(new JWK(KeycloakTestKeys::realm()))->toPublic()->jsonSerialize()]];

        $handler = static function (RequestInterface $request) use ($publicJwks, $idToken, $unreachable) {
            if ($unreachable) {
                throw new RuntimeException('Keycloak tidak dapat dihubungi (simulasi).');
            }

            $uri = (string) $request->getUri();

            if (str_contains($uri, '.well-known')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'issuer' => self::ISSUER,
                    'authorization_endpoint' => self::ISSUER.'/protocol/openid-connect/auth',
                    'token_endpoint' => self::ISSUER.'/protocol/openid-connect/token',
                    'userinfo_endpoint' => self::ISSUER.'/protocol/openid-connect/userinfo',
                    'jwks_uri' => self::ISSUER.'/protocol/openid-connect/certs',
                    'end_session_endpoint' => self::ISSUER.'/protocol/openid-connect/logout',
                    'response_types_supported' => ['code'],
                    'subject_types_supported' => ['public'],
                    'id_token_signing_alg_values_supported' => ['RS256'],
                ], JSON_THROW_ON_ERROR)));
            }

            if (str_contains($uri, '/certs')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode($publicJwks, JSON_THROW_ON_ERROR)));
            }

            if (str_contains($uri, '/token')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'access_token' => 'dummy-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 300,
                    'id_token' => $idToken,
                ], JSON_THROW_ON_ERROR)));
            }

            throw new RuntimeException("URL tak terduga dalam test: {$uri}");
        };

        $this->app->singleton(
            KeycloakOidcService::class,
            static fn (): KeycloakOidcService => new KeycloakOidcService(new GuzzleClient(['handler' => $handler])),
        );
    }

    private function hitCallback(string $state = self::STATE, string $sessionState = self::STATE, string $sessionNonce = self::NONCE)
    {
        return $this->withSession([
            KeycloakOidcService::SESSION_STATE => $sessionState,
            KeycloakOidcService::SESSION_NONCE => $sessionNonce,
        ])->get('/auth/keycloak/callback?code=AUTH_CODE&state='.$state);
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', self::PEGAWAI_NIP)->firstOrFail();
    }

    // ------------------------------------------------- 1. happy path & linking

    public function test_matching_preferred_username_logs_in_and_links_subject(): void
    {
        $user = $this->pegawai();
        $this->assertNull($user->keycloak_id);

        $this->fakeKeycloak();

        $this->hitCallback()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());

        $user->refresh();
        $this->assertSame(self::SUBJECT, $user->keycloak_id, 'sub Keycloak harus tersimpan ke keycloak_id');
        $this->assertNotNull($user->last_login_at, 'last_login_at harus diperbarui seperti jalur kata sandi');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => ActivityType::LOGIN_SSO,
        ]);
    }

    // --------------------------------------------- 2. subject is the main link

    public function test_second_login_matches_on_keycloak_id_not_nip(): void
    {
        $user = $this->pegawai();
        $user->keycloak_id = self::SUBJECT;
        $user->save();

        // The NIP in Keycloak no longer matches anything locally; only the
        // stored subject can still resolve this account.
        $this->fakeKeycloak($this->signIdToken(['preferred_username' => 'NIP-SUDAH-BERUBAH']));

        $this->hitCallback()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        $log = ActivityLog::where('user_id', $user->id)
            ->where('activity_type', ActivityType::LOGIN_SSO)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('keycloak_id', $log->properties['matched_by'] ?? null);
    }

    // ------------------------------------- 3. unknown identity is not created

    public function test_unknown_preferred_username_is_rejected_and_creates_no_user(): void
    {
        $before = User::count();

        $this->fakeKeycloak($this->signIdToken([
            'preferred_username' => '9999999999999999',
            'sub' => 'kc-subject-unknown',
        ]));

        $this->hitCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('keycloak');

        $this->assertGuest();
        $this->assertSame($before, User::count(), 'SSO tidak boleh membuat akun baru');
        $this->assertDatabaseMissing('users', ['nip_nik' => '9999999999999999']);

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityType::LOGIN_FAILED,
            'subject_label' => '9999999999999999',
        ]);
    }

    // ------------------------------------------------- 4. deactivated account

    public function test_inactive_account_is_rejected_even_with_a_valid_token(): void
    {
        $user = $this->pegawai();
        $user->is_active = false;
        $user->save();

        $this->fakeKeycloak();

        $this->hitCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('keycloak');

        $this->assertGuest();
        $this->assertNull($user->fresh()->keycloak_id, 'akun nonaktif tidak boleh ditautkan');
    }

    // ------------------------------------------------ 5. conflicting identity

    public function test_account_already_linked_to_a_different_subject_is_rejected(): void
    {
        $user = $this->pegawai();
        $user->keycloak_id = 'kc-subject-LAMA';
        $user->save();

        // Same NIP, but Keycloak now presents a different subject.
        $this->fakeKeycloak($this->signIdToken(['sub' => 'kc-subject-BARU']));

        $response = $this->hitCallback();

        $response->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
        $this->assertSame('kc-subject-LAMA', $user->fresh()->keycloak_id, 'tautan lama tidak boleh ditimpa diam-diam');
    }

    public function test_conflicting_identity_does_not_produce_a_server_error(): void
    {
        $user = $this->pegawai();
        $user->keycloak_id = 'kc-subject-LAMA';
        $user->save();

        $this->fakeKeycloak($this->signIdToken(['sub' => 'kc-subject-BARU']));

        $response = $this->hitCallback();

        $this->assertSame(302, $response->getStatusCode(), 'konflik harus jadi redirect, bukan 500');
    }

    /**
     * The TOCTOU race: two callbacks for the same subject arrive together, both
     * see the subject unclaimed, and one loses at the UNIQUE index. Checking
     * before writing cannot close that window — only the database can — so the
     * violation must surface as a refusal, never as a 500.
     */
    public function test_unique_violation_while_linking_is_refused_not_a_server_error(): void
    {
        $user = $this->pegawai();
        $rival = User::where('nip_nik', '3302010000000002')->firstOrFail();

        $this->fakeKeycloak();

        // Stand in for the competing callback: claim the subject for another
        // account in the window between our lookup and our write.
        //
        // It hooks `retrieved` rather than `saving` deliberately — `saving` fires
        // inside the savepoint the controller opens, so the rollback would undo
        // this claim too and the race would never be reproduced. `retrieved`
        // fires when the controller resolves the account by NIP, which is before
        // the savepoint exists. The query builder is used so no model events
        // recurse. Both fixtures are read before the listener is registered so
        // their own retrievals do not trigger it.
        $targetId = (int) $user->getKey();
        $raced = false;

        User::retrieved(function (User $retrieved) use ($rival, $targetId, &$raced): void {
            if ($raced || (int) $retrieved->getKey() !== $targetId) {
                return;
            }

            $raced = true;
            DB::table('users')->where('id', $rival->id)->update(['keycloak_id' => self::SUBJECT]);
        });

        $response = $this->hitCallback();

        $this->assertSame(302, $response->getStatusCode(), 'unique violation harus jadi redirect, bukan 500');
        $response->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();

        // The subject stayed with whoever won the race; we did not overwrite it.
        $this->assertSame(self::SUBJECT, $rival->fresh()->keycloak_id);
        $this->assertNull($user->fresh()->keycloak_id);

        // The actor is null on a refusal — nobody is authenticated yet — so the
        // account appears as the log's SUBJECT, not as its user_id.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'activity_type' => ActivityType::LOGIN_FAILED,
            'subject_type' => 'user',
            'subject_id' => $user->id,
        ]);
    }

    // ------------------------------------------------------ 6. malformed tokens

    public function test_token_signed_with_a_foreign_key_is_rejected(): void
    {
        // Same `kid` as the realm key, so rejection must come from the signature
        // itself rather than from a key-id mismatch.
        $this->fakeKeycloak($this->signIdToken(signingKey: new JWK(KeycloakTestKeys::foreign())));

        $this->hitCallback()->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
    }

    public function test_token_with_wrong_issuer_is_rejected(): void
    {
        $this->fakeKeycloak($this->signIdToken(['iss' => 'https://evil.test/realms/Other']));

        $this->hitCallback()->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
    }

    public function test_token_for_another_audience_is_rejected(): void
    {
        $this->fakeKeycloak($this->signIdToken(['aud' => 'aplikasi-lain']));

        $this->hitCallback()->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->fakeKeycloak($this->signIdToken(['exp' => time() - 3600, 'iat' => time() - 7200]));

        $this->hitCallback()->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
    }

    public function test_token_with_mismatched_nonce_is_rejected(): void
    {
        $this->fakeKeycloak($this->signIdToken(['nonce' => 'NONCE_LAIN']));

        $this->hitCallback()->assertRedirect(route('login'))->assertSessionHasErrors('keycloak');
        $this->assertGuest();
    }

    public function test_mismatched_state_is_rejected(): void
    {
        $this->fakeKeycloak();

        $this->hitCallback(state: 'STATE_PENYERANG')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('keycloak');

        $this->assertGuest();
    }

    public function test_callback_without_a_session_is_rejected(): void
    {
        $this->fakeKeycloak();

        // No state/nonce in the session at all — a replayed or forged callback.
        $this->get('/auth/keycloak/callback?code=AUTH_CODE&state='.self::STATE)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('keycloak');

        $this->assertGuest();
    }

    // ------------------------------------------------------------- 7-8. logout

    public function test_logout_ends_the_local_session_and_redirects_to_end_session(): void
    {
        $user = $this->pegawai();
        $this->fakeKeycloak();

        $response = $this->actingAs($user)
            ->withSession([KeycloakOidcService::SESSION_ID_TOKEN => 'ID_TOKEN_UNTUK_HINT'])
            ->post('/logout');

        $response->assertRedirect();

        $target = $response->headers->get('Location');
        $this->assertStringContainsString('/protocol/openid-connect/logout', (string) $target);
        $this->assertStringContainsString('id_token_hint=ID_TOKEN_UNTUK_HINT', (string) $target);
        $this->assertStringContainsString('post_logout_redirect_uri=', (string) $target);

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'activity_type' => ActivityType::LOGOUT,
        ]);
    }

    public function test_logout_still_succeeds_when_keycloak_is_unreachable(): void
    {
        $user = $this->pegawai();
        $this->fakeKeycloak(unreachable: true);

        $this->actingAs($user)
            ->withSession([KeycloakOidcService::SESSION_ID_TOKEN => 'ID_TOKEN_UNTUK_HINT'])
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_login_logout_is_unaffected_by_sso(): void
    {
        $user = $this->pegawai();
        $this->fakeKeycloak();

        // No SSO id_token in the session: a plain local logout.
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // --------------------------------------------------------- 9. regressions

    public function test_password_login_still_works(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'challenges.cloudflare.com/*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);
        \Illuminate\Support\Facades\RateLimiter::clear(strtolower(self::PEGAWAI_NIP).'|127.0.0.1');

        $this->post('/login', [
            'nip_nik' => self::PEGAWAI_NIP,
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertNull($this->pegawai()->fresh()->keycloak_id, 'login kata sandi tidak boleh menyentuh keycloak_id');
    }

    public function test_password_login_rate_limiting_is_intact(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'challenges.cloudflare.com/*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);
        \Illuminate\Support\Facades\RateLimiter::clear(strtolower(self::PEGAWAI_NIP).'|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'nip_nik' => self::PEGAWAI_NIP,
                'password' => 'salah-sekali',
                'cf-turnstile-response' => 'dummy-token-for-test',
            ]);
        }

        $this->post('/login', [
            'nip_nik' => self::PEGAWAI_NIP,
            'password' => 'password',
            'cf-turnstile-response' => 'dummy-token-for-test',
        ])->assertSessionHasErrors('nip_nik');

        $this->assertGuest();
    }

    public function test_password_login_turnstile_is_intact(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'challenges.cloudflare.com/*' => \Illuminate\Support\Facades\Http::response(['success' => false], 200),
        ]);
        \Illuminate\Support\Facades\RateLimiter::clear(strtolower(self::PEGAWAI_NIP).'|127.0.0.1');

        $this->post('/login', [
            'nip_nik' => self::PEGAWAI_NIP,
            'password' => 'password',
            'cf-turnstile-response' => 'token-ditolak',
        ])->assertSessionHasErrors('turnstile');

        $this->assertGuest();
    }

    // ------------------------------------------------------ 10. disabled routes

    public function test_routes_are_absent_when_keycloak_is_not_configured(): void
    {
        config([
            'services.keycloak.base_url' => null,
            'services.keycloak.realm' => null,
            'services.keycloak.client_id' => null,
            'services.keycloak.client_secret' => null,
            'services.keycloak.redirect' => null,
        ]);

        $this->get('/auth/keycloak/redirect')->assertNotFound();
        $this->get('/auth/keycloak/callback')->assertNotFound();
    }

    public function test_redirect_sends_the_browser_to_keycloak_with_state_and_nonce(): void
    {
        $this->fakeKeycloak();

        $response = $this->get('/auth/keycloak/redirect');

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/protocol/openid-connect/auth', $target);
        $this->assertStringContainsString('client_id='.self::CLIENT_ID, $target);
        $this->assertStringContainsString('state=', $target);
        $this->assertStringContainsString('nonce=', $target);
        $this->assertStringContainsString('scope=', $target);

        $response->assertSessionHas(KeycloakOidcService::SESSION_STATE);
        $response->assertSessionHas(KeycloakOidcService::SESSION_NONCE);
    }

    public function test_already_authenticated_user_is_not_sent_through_sso_again(): void
    {
        $this->fakeKeycloak();

        $this->actingAs($this->pegawai())
            ->get('/auth/keycloak/redirect')
            ->assertRedirect(route('dashboard'));
    }
}
