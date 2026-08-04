<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KeycloakOidcService;
use App\Support\ActivityType;
use App\Support\UserSessions;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
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
 * Keycloak back-channel logout (OIDC Back-Channel Logout 1.0).
 *
 * When an employee signs out of another application — or of the Keycloak
 * account console — Keycloak calls this portal server-to-server and tells it to
 * drop the matching session. There is no browser on that request, no session
 * and no CSRF token, so the signed logout token IS the authentication.
 *
 * That makes the refusal cases the important ones. Most of this file is about
 * what must NOT end a session: a token signed with the wrong key, an ID token
 * replayed as a logout token, a token for another audience or issuer, and the
 * same token delivered twice.
 *
 * No test reaches a real Keycloak. Discovery and JWKS are served by a Guzzle
 * handler wired into the service, and the tokens are signed locally with the
 * static test keys — so the signature verification under test is the real one.
 *
 * The realm at Dinkominfo advertises backchannel_logout_session_supported:true,
 * which per the spec is the promise that `sid` appears in ID tokens; that is
 * what makes the sid-scoped path below the normal one rather than a fallback.
 */
class KeycloakBackchannelLogoutTest extends TestCase
{
    private const ISSUER_HOST = 'https://keycloak.test';

    private const ISSUER = 'https://keycloak.test/realms/EOffice';

    private const CLIENT_ID = 'eoffice-portal';

    private const SUBJECT = 'kc-subject-0001';

    private const SID = 'kc-session-aaaa';

    private const OTHER_SID = 'kc-session-bbbb';

    private const PEGAWAI_NIP = '3302010000000001';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.base_url' => self::ISSUER_HOST,
            'services.keycloak.realm' => 'EOffice',
            'services.keycloak.client_id' => self::CLIENT_ID,
            'services.keycloak.client_secret' => 'test-client-secret',
            'services.keycloak.redirect' => 'http://localhost/auth/keycloak/callback',
            // The eviction reads the sessions table directly; phpunit.xml runs
            // on the array driver, where that table is never touched.
            'session.driver' => 'database',
        ]);

        Cache::flush(); // the jti replay guard lives here

        $this->fakeKeycloak();
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $overrides */
    private function signLogoutToken(array $overrides = [], ?JWK $signingKey = null): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'sub' => self::SUBJECT,
            'aud' => self::CLIENT_ID,
            'iat' => time(),
            'exp' => time() + 300,
            'jti' => 'jti-'.bin2hex(random_bytes(6)),
            'sid' => self::SID,
            'events' => [KeycloakOidcService::BACKCHANNEL_LOGOUT_EVENT => new \stdClass],
        ], $overrides);

        $key = $signingKey ?? new JWK(KeycloakTestKeys::realm());

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature($key, ['alg' => 'RS256', 'kid' => KeycloakTestKeys::KID])
            ->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    /** An ID token as the callback would receive it, optionally carrying `sid`. */
    private function signIdToken(?string $sid): string
    {
        $claims = [
            'iss' => self::ISSUER,
            'sub' => self::SUBJECT,
            'aud' => self::CLIENT_ID,
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => 'NONCE_FOR_TEST',
            'preferred_username' => self::PEGAWAI_NIP,
        ];

        if ($sid !== null) {
            $claims['sid'] = $sid;
        }

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))
            ->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->addSignature(new JWK(KeycloakTestKeys::realm()), ['alg' => 'RS256', 'kid' => KeycloakTestKeys::KID])
            ->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    /** Serve discovery + JWKS locally; nothing here reaches a real Keycloak. */
    private function fakeKeycloak(bool $withTokenEndpoint = false, ?string $idTokenSid = self::SID): void
    {
        $publicJwks = ['keys' => [(new JWK(KeycloakTestKeys::realm()))->toPublic()->jsonSerialize()]];
        $idToken = $withTokenEndpoint ? $this->signIdToken($idTokenSid) : null;

        $handler = static function (RequestInterface $request) use ($publicJwks, $idToken) {
            $uri = (string) $request->getUri();

            if ($idToken !== null && str_contains($uri, '/token')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'access_token' => 'dummy-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 300,
                    'id_token' => $idToken,
                ], JSON_THROW_ON_ERROR)));
            }

            if (str_contains($uri, '.well-known')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'issuer' => self::ISSUER,
                    'authorization_endpoint' => self::ISSUER.'/protocol/openid-connect/auth',
                    'token_endpoint' => self::ISSUER.'/protocol/openid-connect/token',
                    'jwks_uri' => self::ISSUER.'/protocol/openid-connect/certs',
                    'end_session_endpoint' => self::ISSUER.'/protocol/openid-connect/logout',
                    'backchannel_logout_supported' => true,
                    'backchannel_logout_session_supported' => true,
                    'response_types_supported' => ['code'],
                    'subject_types_supported' => ['public'],
                    'id_token_signing_alg_values_supported' => ['RS256'],
                ], JSON_THROW_ON_ERROR)));
            }

            if (str_contains($uri, '/certs')) {
                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode($publicJwks, JSON_THROW_ON_ERROR)));
            }

            throw new RuntimeException("URL tak terduga dalam test: {$uri}");
        };

        $this->app->singleton(
            KeycloakOidcService::class,
            static fn (): KeycloakOidcService => new KeycloakOidcService(new GuzzleClient(['handler' => $handler])),
        );
    }

    private function linkedPegawai(): User
    {
        $user = User::where('nip_nik', self::PEGAWAI_NIP)->firstOrFail();
        $user->keycloak_id = self::SUBJECT;
        $user->save();

        return $user;
    }

    /**
     * Encode session attributes the way the configured driver does.
     *
     * Read from config rather than hard-coded, so the day someone switches
     * session.serialization the fixtures follow instead of silently drifting
     * away from production again.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function encodeSessionPayload(array $attributes): string
    {
        return config('session.serialization', 'json') === 'json'
            ? json_encode($attributes, JSON_THROW_ON_ERROR)
            : serialize($attributes);
    }

    /** A server-side session row as if this browser were signed in via SSO. */
    private function seedSession(string $id, ?int $userId, ?string $sid): void
    {
        $attributes = $sid === null ? [] : [KeycloakOidcService::SESSION_SID => $sid];

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            // Encoded the way Laravel actually encodes it. This used to call
            // serialize(), which quietly matched a reader that also used
            // unserialize() — both wrong together, so the tests passed while
            // the production path could never find a session at all.
            // config/session.php sets serialization to 'json'.
            'payload' => base64_encode(self::encodeSessionPayload($attributes)),
            'last_activity' => time(),
        ]);
    }

    private function postLogout(?string $token)
    {
        return $this->post('/auth/keycloak/backchannel-logout', $token === null ? [] : [
            'logout_token' => $token,
        ]);
    }

    private function sessionExists(string $id): bool
    {
        return DB::table('sessions')->where('id', $id)->exists();
    }

    // ------------------------------------------------------- 1. the happy path

    /**
     * The half everything else depends on.
     *
     * If the SSO callback never records the sid, the sid-scoped tests below
     * would still pass — they seed sessions by hand — while real logins stayed
     * unreachable by back-channel logout. So the capture is proven through the
     * actual callback, not asserted against a constant.
     */
    public function test_sid_disimpan_ke_sesi_saat_callback_sso(): void
    {
        $this->fakeKeycloak(withTokenEndpoint: true);

        $this->withSession([
            KeycloakOidcService::SESSION_STATE => 'STATE_FOR_TEST',
            KeycloakOidcService::SESSION_NONCE => 'NONCE_FOR_TEST',
        ])
            ->get('/auth/keycloak/callback?code=AUTH_CODE&state=STATE_FOR_TEST')
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(self::SID, session(KeycloakOidcService::SESSION_SID));
    }

    /** A realm that stops sending `sid` must not break login. */
    public function test_callback_tetap_berhasil_bila_realm_tidak_mengirim_sid(): void
    {
        $this->fakeKeycloak(withTokenEndpoint: true, idTokenSid: null);

        $this->withSession([
            KeycloakOidcService::SESSION_STATE => 'STATE_FOR_TEST',
            KeycloakOidcService::SESSION_NONCE => 'NONCE_FOR_TEST',
        ])
            ->get('/auth/keycloak/callback?code=AUTH_CODE&state=STATE_FOR_TEST')
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertNull(session(KeycloakOidcService::SESSION_SID));
    }

    /**
     * The reader must parse a session LARAVEL wrote, not one the test invented.
     *
     * Every other sid test seeds its own row, so the fixture and the reader can
     * agree with each other while both disagree with production — which is
     * exactly what happened: the fixture used serialize() and so did the
     * reader, while config/session.php has always been set to 'json'. The tests
     * were green and purgeByKeycloakSid() could never find a real session.
     *
     * This test closes that hole by logging in for real and then asking the
     * production code to find the session Laravel itself persisted.
     */
    public function test_pembaca_sid_mengenali_sesi_yang_ditulis_laravel(): void
    {
        $this->fakeKeycloak(withTokenEndpoint: true);

        $this->withSession([
            KeycloakOidcService::SESSION_STATE => 'STATE_FOR_TEST',
            KeycloakOidcService::SESSION_NONCE => 'NONCE_FOR_TEST',
        ])
            ->get('/auth/keycloak/callback?code=AUTH_CODE&state=STATE_FOR_TEST')
            ->assertRedirect();

        $user = User::where('nip_nik', self::PEGAWAI_NIP)->firstOrFail();

        $rows = DB::table('sessions')->where('user_id', $user->id)->count();
        $this->assertGreaterThan(0, $rows, 'Laravel tidak menulis baris sesi; test ini tidak menguji apa pun.');

        $ended = UserSessions::purgeByKeycloakSid($user->id, self::SID);

        $this->assertSame(
            1,
            $ended,
            'purgeByKeycloakSid tidak mengenali sesi yang ditulis Laravel — '.
            'format serialisasi pembaca tidak cocok dengan yang dipakai framework.'
        );
    }

    public function test_logout_token_sah_mengakhiri_sesi_dengan_sid_yang_cocok(): void
    {
        $user = $this->linkedPegawai();

        $this->seedSession('sesi-target', $user->id, self::SID);
        $this->seedSession('sesi-lain', $user->id, self::OTHER_SID);

        $this->postLogout($this->signLogoutToken())->assertOk();

        $this->assertFalse($this->sessionExists('sesi-target'), 'Sesi dengan sid yang cocok harus diakhiri.');
        $this->assertTrue($this->sessionExists('sesi-lain'), 'Sesi dengan sid berbeda TIDAK boleh ikut diakhiri.');
    }

    public function test_logout_token_tanpa_sid_mengakhiri_seluruh_sesi_subjek(): void
    {
        $user = $this->linkedPegawai();

        $this->seedSession('sesi-a', $user->id, self::SID);
        $this->seedSession('sesi-b', $user->id, self::OTHER_SID);

        // sub-only means "every session of this subject" (OIDC BCL 1.0 §2.4).
        $token = $this->signLogoutToken(['sid' => null]);
        $this->postLogout($token)->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_sesi_pengguna_lain_tidak_pernah_ikut_terputus(): void
    {
        $user = $this->linkedPegawai();
        $other = User::where('nip_nik', '3302010000000002')->firstOrFail();

        $this->seedSession('sesi-target', $user->id, self::SID);
        $this->seedSession('sesi-orang-lain', $other->id, self::SID); // sid sama, pemilik beda

        $this->postLogout($this->signLogoutToken())->assertOk();

        $this->assertFalse($this->sessionExists('sesi-target'));
        $this->assertTrue(
            $this->sessionExists('sesi-orang-lain'),
            'sid dicocokkan dalam lingkup satu pengguna; milik orang lain tidak boleh tersentuh.'
        );
    }

    public function test_aktivitas_dicatat_saat_sesi_diakhiri(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $this->postLogout($this->signLogoutToken())->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityType::LOGOUT_SSO_BACKCHANNEL,
            'subject_id' => $user->id,
            'user_id' => null, // nobody was logged in; the OP is the actor
        ]);
    }

    // --------------------------------------------------- 2. refusals (the point)

    public function test_token_ditandatangani_kunci_asing_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        // Same kid as the realm key, different private key: the refusal has to
        // come from the signature failing, not from the kid being unknown.
        $forged = $this->signLogoutToken([], new JWK(KeycloakTestKeys::foreign()));

        $this->postLogout($forged)->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'), 'Token palsu tidak boleh mengakhiri sesi.');
    }

    public function test_id_token_yang_diputar_ulang_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        // An ID token is correctly signed and correctly addressed — the ONLY
        // things separating it from a logout token are the events claim and the
        // nonce. Without those two checks this would end a session.
        $idTokenShaped = $this->signLogoutToken(['events' => null, 'nonce' => 'NONCE_FOR_TEST']);

        $this->postLogout($idTokenShaped)->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    public function test_token_tanpa_klaim_events_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $this->postLogout($this->signLogoutToken(['events' => null]))->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    public function test_token_yang_memuat_nonce_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $this->postLogout($this->signLogoutToken(['nonce' => 'apa-saja']))->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    public function test_token_untuk_audience_lain_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $this->postLogout($this->signLogoutToken(['aud' => 'klien-lain']))->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    public function test_token_dari_issuer_lain_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $this->postLogout($this->signLogoutToken(['iss' => 'https://keycloak.palsu/realms/EOffice']))
            ->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        // Expired by an hour, well past CLOCK_TOLERANCE_SECONDS. A token only a
        // minute stale is now deliberately still accepted — that allowance is
        // the whole point of the tolerance, and the next test pins it down.
        $this->postLogout($this->signLogoutToken(['exp' => time() - 3600, 'iat' => time() - 7200]))
            ->assertStatus(400);

        $this->assertTrue($this->sessionExists('sesi-target'));
    }

    /**
     * A token stamped a moment ahead of our clock must still be accepted.
     *
     * This is the regression guard for the real failure seen on 3 August 2026:
     * the verifier ran at zero clock tolerance, so Keycloak's clock being a
     * fraction of a second ahead was enough to refuse a valid token with "The
     * JWT is issued in the future". Login failed intermittently, which is far
     * harder to diagnose than failing outright.
     */
    public function test_token_dengan_selisih_jam_kecil_tetap_diterima(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        // 30 seconds into the future — inside the tolerance, so it must work.
        $this->postLogout($this->signLogoutToken(['iat' => time() + 30]))->assertOk();

        $this->assertFalse(
            $this->sessionExists('sesi-target'),
            'Token dengan selisih jam kecil seharusnya tetap mengakhiri sesi.'
        );
    }

    public function test_permintaan_tanpa_logout_token_ditolak(): void
    {
        $this->postLogout(null)->assertStatus(400);
    }

    public function test_token_yang_bukan_jwt_ditolak(): void
    {
        $this->postLogout('bukan-jwt-sama-sekali')->assertStatus(400);
    }

    // --------------------------------------------------------- 3. replay & noise

    public function test_token_yang_sama_tidak_diproses_dua_kali(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-target', $user->id, self::SID);

        $token = $this->signLogoutToken();

        $this->postLogout($token)->assertOk();
        $this->assertFalse($this->sessionExists('sesi-target'));

        // A second delivery is a retry or a replay. It must not run again — and
        // it must not report failure either, or Keycloak would keep retrying.
        $this->seedSession('sesi-target', $user->id, self::SID);
        $this->postLogout($token)->assertOk();

        $this->assertTrue(
            $this->sessionExists('sesi-target'),
            'Logout token yang diputar ulang tidak boleh mengakhiri sesi lagi.'
        );
    }

    public function test_subjek_yang_tidak_dikenal_dijawab_200_tanpa_efek(): void
    {
        // The OP did its part correctly; there is simply no local account bound
        // to this subject. Answering 400 would tell an unauthenticated caller
        // which subjects exist here.
        $this->postLogout($this->signLogoutToken(['sub' => 'kc-subject-tidak-dikenal']))
            ->assertOk();
    }

    public function test_sid_yang_tidak_cocok_tidak_mengakhiri_apa_pun(): void
    {
        $user = $this->linkedPegawai();
        $this->seedSession('sesi-lain', $user->id, self::OTHER_SID);

        $this->postLogout($this->signLogoutToken())->assertOk();

        $this->assertTrue($this->sessionExists('sesi-lain'));
    }

    // ----------------------------------------------------- 4. SSO tidak dikonfigurasi

    public function test_endpoint_404_saat_keycloak_tidak_dikonfigurasi(): void
    {
        // Same rule as the other two SSO routes: an unconfigured deployment
        // exposes nothing at all.
        config(['services.keycloak.client_secret' => null]);

        $this->postLogout($this->signLogoutToken())->assertNotFound();
    }
}
