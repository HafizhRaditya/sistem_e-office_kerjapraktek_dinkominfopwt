<?php

namespace App\Services;

use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\JoseVerifier\JWTVerifier;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface as OpenIDClient;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Issuer\IssuerInterface;
use Facile\OpenIDClient\Issuer\Metadata\Provider\MetadataProviderBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Token\TokenSetInterface;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Psr\Http\Client\ClientInterface as PsrHttpClient;
use RuntimeException;
use Throwable;

/**
 * Keycloak SSO (OIDC) — the SECOND login path beside NIP/NIK.
 *
 * The whole point of using an OIDC client instead of a plain OAuth2 client is
 * that the ID token is verified cryptographically rather than trusted because
 * it arrived over a back channel. facile-it/php-openid-client does that for us:
 * IdTokenVerifier checks the signature against the issuer's jwks_uri (with an
 * automatic JWKS reload when it sees an unknown `kid`, so key rotation does not
 * lock users out) plus iss, aud, exp, iat and — because we hand it the nonce we
 * stashed before the redirect — the nonce too.
 *
 * Endpoints are never hardcoded: IssuerBuilder discovers authorization_endpoint,
 * token_endpoint, jwks_uri and end_session_endpoint from the issuer's
 * .well-known/openid-configuration document.
 *
 * The PSR-18 client is injected rather than auto-discovered on purpose. Both
 * Guzzle and symfony/http-client are installed (the latter arrived as a
 * facile-it dependency), so Psr18ClientDiscovery::find() could pick either one.
 * Injection makes the transport deterministic and lets tests swap in a mock
 * handler so no test ever talks to a real Keycloak.
 */
final class KeycloakOidcService
{
    /** Session keys holding the CSRF state and the replay-guard nonce. */
    public const SESSION_STATE = 'keycloak_state';

    public const SESSION_NONCE = 'keycloak_nonce';

    /** Kept so logout can send id_token_hint to Keycloak's end_session endpoint. */
    public const SESSION_ID_TOKEN = 'keycloak_id_token';

    /**
     * The OP's session id (`sid` claim) this login belongs to.
     *
     * Back-channel logout arrives naming a `sid`, not a portal session, so this
     * is the only thing that ties Keycloak's notion of the session to ours.
     * Kept in the session payload rather than a lookup table: it needs no
     * migration, and it cannot go stale — when the session dies the mapping
     * dies with it.
     */
    public const SESSION_SID = 'keycloak_sid';

    /** The single event that makes a logout token a logout token (OIDC BCL 1.0 §2.4). */
    public const BACKCHANNEL_LOGOUT_EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    private ?IssuerInterface $issuer = null;

    private ?OpenIDClient $client = null;

    private ?AuthorizationService $authorizationService = null;

    public function __construct(private readonly PsrHttpClient $httpClient) {}

    /**
     * Whether the SSO path is usable at all.
     *
     * Every consumer (route, controller, login view) checks this, so a checkout
     * without Keycloak keys simply runs on password login with the SSO button
     * hidden — no fatal error, no half-working button.
     */
    public function isEnabled(): bool
    {
        return filled($this->issuerUrl())
            && filled(config('services.keycloak.client_id'))
            && filled(config('services.keycloak.client_secret'))
            && filled(config('services.keycloak.redirect'));
    }

    /**
     * Start the flow: mint state + nonce, remember them, and hand back the
     * Keycloak authorization URL to redirect the browser to.
     *
     * state  — ties the callback to this browser session (CSRF).
     * nonce  — ties the ID token to this very request (replay protection); it
     *          is verified inside the token, which is why it must survive the
     *          round trip in the session.
     */
    public function authorizationUrl(Request $request): string
    {
        $this->assertEnabled();

        $state = Str::random(40);
        $nonce = Str::random(40);

        $request->session()->put(self::SESSION_STATE, $state);
        $request->session()->put(self::SESSION_NONCE, $nonce);

        return $this->authorizationService()->getAuthorizationUri($this->client(), [
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Finish the flow. Returns the verified token set, or throws.
     *
     * The state and nonce are pulled (not read) from the session so a callback
     * URL cannot be replayed a second time.
     */
    public function handleCallback(Request $request): TokenSetInterface
    {
        $this->assertEnabled();

        $expectedState = $request->session()->pull(self::SESSION_STATE);
        $nonce = $request->session()->pull(self::SESSION_NONCE);

        // Keycloak reports user-facing failures (cancelled consent, disabled
        // client) as query parameters rather than an HTTP error status.
        if (filled($request->query('error'))) {
            throw new RuntimeException(
                'Keycloak menolak permintaan login: '.(string) $request->query('error')
            );
        }

        if (blank($expectedState) || blank($nonce)) {
            throw new RuntimeException('Sesi login Keycloak tidak ditemukan atau sudah kedaluwarsa.');
        }

        $returnedState = (string) $request->query('state', '');

        if (! hash_equals((string) $expectedState, $returnedState)) {
            throw new RuntimeException('Parameter state tidak cocok; permintaan login ditolak.');
        }

        if (blank($request->query('code'))) {
            throw new RuntimeException('Keycloak tidak mengirimkan kode otorisasi.');
        }

        // Handing the AuthSession to callback() is what switches nonce and
        // state-hash verification on inside the ID token verifier.
        $authSession = AuthSession::fromArray([
            'state' => $expectedState,
            'nonce' => $nonce,
        ]);

        return $this->authorizationService()->callback(
            $this->client(),
            [
                'code' => (string) $request->query('code'),
                'state' => $returnedState,
            ],
            (string) config('services.keycloak.redirect'),
            $authSession,
        );
    }

    /**
     * RP-initiated logout URL (OIDC session management).
     *
     * Keycloak only honours post_logout_redirect_uri when it can tell who is
     * asking, hence id_token_hint. We still pass client_id as a fallback for
     * the case where the ID token was lost (e.g. the session expired first).
     * Returns null when SSO is off or the realm advertises no end_session
     * endpoint, so callers fall back to a local-only logout.
     */
    public function endSessionUrl(?string $idToken, string $postLogoutRedirectUri): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $metadata = $this->issuer()->getMetadata();

        if (! $metadata->has('end_session_endpoint')) {
            return null;
        }

        $params = array_filter([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'client_id' => (string) config('services.keycloak.client_id'),
        ], static fn (?string $value): bool => filled($value));

        return (string) $metadata->get('end_session_endpoint').'?'.http_build_query($params);
    }

    /**
     * Verify a back-channel logout token and return its claims (OIDC Back-Channel
     * Logout 1.0 §2.6).
     *
     * This endpoint is unauthenticated by design — Keycloak calls it
     * server-to-server with no session and no CSRF token — so the token itself
     * is the ONLY evidence that the request is genuine. Everything below either
     * verifies it or throws; there is no path that returns unverified claims.
     *
     * The cryptography is not hand-rolled. JWTVerifier resolves the signing key
     * from the token's `kid` against the issuer's JWKS (reloading the JWKS if
     * the kid is unknown, so key rotation does not break logout) and checks the
     * signature, `iss`, `aud`, `exp`, `iat` and `nbf`. Only the rules specific
     * to logout tokens are applied here, because a general-purpose JWT verifier
     * has no reason to know them.
     *
     * @return array<string, mixed> the verified claims
     *
     * @throws RuntimeException when the token is not a valid logout token
     */
    public function verifyLogoutToken(string $logoutToken): array
    {
        $this->assertEnabled();

        $metadata = $this->issuer()->getMetadata();

        $verifier = new JWTVerifier(
            issuer: (string) $this->issuerUrl(),
            clientId: (string) config('services.keycloak.client_id'),
            clientSecret: (string) config('services.keycloak.client_secret'),
            jwksProvider: (new JwksProviderBuilder())
                ->withHttpClient($this->httpClient)
                ->withRequestFactory(new HttpFactory())
                ->withJwksUri((string) $metadata->get('jwks_uri'))
                ->build(),
        );

        try {
            // Signature + iss/aud/exp/iat/nbf. Throws on anything wrong.
            //
            // JWTVerifier additionally requires `sub` and `exp`, which the spec
            // marks optional for logout tokens. Keycloak sends both, and the
            // failure mode of being stricter is that a session is NOT ended —
            // exactly what happens today without this feature at all. Refusing
            // an unusual token is the safe direction to be wrong in.
            $claims = $verifier->verify($logoutToken);
        } catch (Throwable $e) {
            throw new RuntimeException('Logout token tidak lolos verifikasi: '.$e->getMessage(), 0, $e);
        }

        // §2.4: the token MUST carry the back-channel logout event. Without this
        // check any ID token Keycloak ever issued — which clients legitimately
        // hold — would be accepted here as a logout instruction.
        $events = $claims['events'] ?? null;

        if (! is_array($events) || ! array_key_exists(self::BACKCHANNEL_LOGOUT_EVENT, $events)) {
            throw new RuntimeException('Logout token tidak memuat klaim events back-channel logout.');
        }

        // §2.4: a logout token MUST NOT contain `nonce`. Its presence is the
        // signature of an ID token being replayed as a logout token.
        if (array_key_exists('nonce', $claims)) {
            throw new RuntimeException('Logout token memuat nonce; itu menandakan ID token yang diputar ulang.');
        }

        // §2.4: sub, sid, or both — but not neither, or there is nothing to end.
        if (blank($claims['sub'] ?? null) && blank($claims['sid'] ?? null)) {
            throw new RuntimeException('Logout token tidak memuat sub maupun sid.');
        }

        return $claims;
    }

    /** Issuer URL, e.g. https://host/realms/EOffice — the discovery root. */
    public function issuerUrl(): ?string
    {
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $realm = trim((string) config('services.keycloak.realm'), '/');

        if ($baseUrl === '' || $realm === '') {
            return null;
        }

        return $baseUrl.'/realms/'.$realm;
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Konfigurasi Keycloak belum lengkap.');
        }
    }

    /**
     * Discovery. Both the metadata document and the JWKS are fetched through
     * the injected client, which is what makes the whole flow mockable.
     */
    private function issuer(): IssuerInterface
    {
        $httpFactory = new HttpFactory();

        return $this->issuer ??= (new IssuerBuilder())
            ->setMetadataProviderBuilder(
                (new MetadataProviderBuilder())
                    ->setHttpClient($this->httpClient)
                    ->setRequestFactory($httpFactory)
                    ->setUriFactory($httpFactory)
            )
            ->setJwksProviderBuilder(
                (new JwksProviderBuilder())
                    ->withHttpClient($this->httpClient)
                    ->withRequestFactory($httpFactory)
            )
            ->build((string) $this->issuerUrl());
    }

    private function client(): OpenIDClient
    {
        return $this->client ??= (new ClientBuilder())
            ->setIssuer($this->issuer())
            ->setClientMetadata(ClientMetadata::fromArray([
                'client_id' => (string) config('services.keycloak.client_id'),
                'client_secret' => (string) config('services.keycloak.client_secret'),
                'redirect_uris' => [(string) config('services.keycloak.redirect')],
                'response_types' => ['code'],
                // Confidential client: the secret is sent on the token request,
                // never exposed to the browser.
                'token_endpoint_auth_method' => 'client_secret_basic',
            ]))
            ->setHttpClient($this->httpClient)
            ->build();
    }

    private function authorizationService(): AuthorizationService
    {
        $httpFactory = new HttpFactory();

        return $this->authorizationService ??= (new AuthorizationServiceBuilder())
            ->setHttpClient($this->httpClient)
            ->setRequestFactory($httpFactory)
            ->build();
    }
}
