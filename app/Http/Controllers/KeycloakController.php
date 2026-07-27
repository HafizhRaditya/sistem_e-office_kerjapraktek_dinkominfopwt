<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\KeycloakOidcService;
use App\Support\ActivityType;
use App\Support\AuthLanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keycloak SSO (OIDC) — the SECOND login path. The NIP/NIK form in
 * AuthController stays the primary path and is untouched by this controller,
 * including its rate limiting and Turnstile check.
 *
 * Accounts are NEVER created here. The employee list is maintained by admins,
 * so an identity that Keycloak authenticates but the portal does not know is
 * rejected, not provisioned. Anything else would let whoever can create a
 * Keycloak account create a portal account.
 */
class KeycloakController extends Controller
{
    public function __construct(
        private readonly KeycloakOidcService $keycloak,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /** Kick off the flow: mint state + nonce, then bounce to Keycloak. */
    public function redirect(Request $request)
    {
        $this->assertEnabled();

        if (Auth::check()) {
            return redirect()->to(AuthLanding::homeFor($request->user()));
        }

        try {
            return redirect()->away($this->keycloak->authorizationUrl($request));
        } catch (Throwable $e) {
            // Discovery failing (Keycloak down, DNS, bad realm) must not show a
            // stack trace on a public page — send them back to the form login,
            // which still works.
            Log::error('Keycloak: gagal menyusun URL otorisasi.', ['exception' => $e]);

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Layanan SSO sedang tidak dapat dihubungi. Silakan masuk dengan NIP/NIK.',
            ]);
        }
    }

    /**
     * Return leg. The token set is already verified by the time we get it
     * (signature against JWKS, iss, aud, exp, nonce) — see KeycloakOidcService.
     */
    public function callback(Request $request)
    {
        $this->assertEnabled();

        try {
            $tokenSet = $this->keycloak->handleCallback($request);
        } catch (Throwable $e) {
            Log::warning('Keycloak: callback ditolak.', ['exception' => $e]);

            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                'Login SSO gagal: '.$e->getMessage(),
                subjectType: 'login_identity',
                subjectLabel: 'keycloak',
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Verifikasi SSO gagal. Silakan coba lagi atau masuk dengan NIP/NIK.',
            ]);
        }

        $claims = $tokenSet->claims();
        $subject = isset($claims['sub']) ? (string) $claims['sub'] : null;
        $username = isset($claims['preferred_username']) ? (string) $claims['preferred_username'] : null;

        if (blank($subject) || blank($username)) {
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                'Login SSO gagal: token Keycloak tidak memuat sub/preferred_username.',
                subjectType: 'login_identity',
                subjectLabel: $username ?? 'tidak diketahui',
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Token SSO tidak memuat identitas pengguna. Hubungi admin.',
            ]);
        }

        // Link priority: the stored subject wins. It survives an NIP correction
        // in either directory, which `preferred_username` would not.
        $user = User::where('keycloak_id', $subject)->first();
        $matchedBy = 'keycloak_id';

        if (! $user) {
            $user = User::where('nip_nik', $username)->first();
            $matchedBy = 'preferred_username';
        }

        if (! $user) {
            // Deliberately NOT creating an account here.
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Login SSO ditolak: {$username} tidak terdaftar sebagai pegawai.",
                subjectType: 'login_identity',
                subjectLabel: $username,
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => "Akun Keycloak \"{$username}\" belum terdaftar di E-Office. Hubungi admin OPD.",
            ]);
        }

        // The subject is UNIQUE. If this account was matched by NIP but the
        // subject already belongs to someone else, linking would hit the unique
        // constraint — refuse loudly instead of dying with a 500.
        if (filled($user->keycloak_id) && ! hash_equals((string) $user->keycloak_id, $subject)) {
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Login SSO ditolak: identitas Keycloak untuk {$username} tidak cocok dengan tautan yang tersimpan.",
                subject: $user,
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Identitas Keycloak tidak cocok dengan akun E-Office ini. Hubungi admin OPD.',
            ]);
        }

        if (User::where('keycloak_id', $subject)->whereKeyNot($user->getKey())->exists()) {
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Login SSO ditolak: identitas Keycloak sudah tertaut ke akun lain (dari {$username}).",
                subject: $user,
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Identitas Keycloak ini sudah tertaut ke akun E-Office lain. Hubungi admin OPD.',
            ]);
        }

        // Same rule as the password path: a deactivated account cannot log in,
        // no matter how valid the token is.
        if (! $user->is_active) {
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                'Login SSO ditolak: akun nonaktif.',
                subject: $user,
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.',
            ]);
        }

        // Explicit assignment, not mass assignment: `keycloak_id` is kept out of
        // $fillable on purpose (see App\Models\User).
        $user->keycloak_id = $subject;
        $user->last_login_at = now(); // FR-A01, same as the password path
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        // Needed as id_token_hint so Keycloak honours post_logout_redirect_uri.
        $idToken = $tokenSet->getIdToken();
        if (filled($idToken)) {
            $request->session()->put(KeycloakOidcService::SESSION_ID_TOKEN, $idToken);
        }

        $this->activityLogger->record(
            $request,
            ActivityType::LOGIN_SSO,
            'Login berhasil melalui Keycloak SSO.',
            subject: $user,
            properties: ['matched_by' => $matchedBy],
        );

        return redirect()->intended(AuthLanding::homeFor($user));
    }

    /** SSO off (or half-configured) means the routes do not exist at all. */
    private function assertEnabled(): void
    {
        abort_unless($this->keycloak->isEnabled(), 404);
    }
}
