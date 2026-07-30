<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\KeycloakOidcService;
use App\Support\ActivityType;
use App\Support\AuthLanding;
use App\Support\UserSessions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Link priority: the stored subject wins — `sub` is AUTHORITATIVE.
        //
        // Design decision: if `preferred_username` later changes in Keycloak (an
        // NIP correction, a transfer between OPD), the link still follows `sub`,
        // and the account resolved is the one that subject was bound to. `sub` is
        // the only identifier OIDC guarantees to be stable and never reassigned;
        // an NIP is administrative data that can be edited in either directory.
        // Trusting the NIP over the subject would mean an NIP edit could silently
        // point a Keycloak identity at a different employee's account.
        $user = User::where('keycloak_id', $subject)->first();
        $matchedBy = 'keycloak_id';

        if (! $user) {
            // Keycloak lowercases usernames on storage, so an account created
            // there as "ADMIN001" arrives here as "admin001" and would never
            // match a `nip_nik` stored in upper case. Compare case-insensitively.
            //
            // lower() on both sides rather than ILIKE: `preferred_username` is
            // attacker-influenced input, and ILIKE would treat any `%` or `_`
            // inside it as wildcards — turning an exact lookup into a pattern
            // match. The binding stays parameterised either way.
            $candidates = User::whereRaw('lower(nip_nik) = lower(?)', [$username])->get();

            // UNIQUE(nip_nik) is case-sensitive, so "ADMIN001" and "admin001"
            // can legally coexist as two different accounts. Case-insensitive
            // matching would then find both, and picking one arbitrarily could
            // sign somebody into a colleague's account. Refuse instead.
            if ($candidates->count() > 1) {
                $this->activityLogger->record(
                    $request,
                    ActivityType::LOGIN_FAILED,
                    "Login SSO ditolak: {$username} cocok dengan lebih dari satu akun (perbedaan huruf besar-kecil).",
                    subjectType: 'login_identity',
                    subjectLabel: $username,
                );

                return redirect()->route('login')->withErrors([
                    'keycloak' => 'Identitas Keycloak ini cocok dengan lebih dari satu akun E-Office. Hubungi admin OPD.',
                ]);
            }

            $user = $candidates->first();
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

        try {
            // Wrapped so the write gets its own savepoint. PostgreSQL aborts the
            // WHOLE transaction on a failed statement — every later query then
            // fails with 25P02 "current transaction is aborted". Without the
            // savepoint the activity-log write in the catch block below would be
            // the next casualty, and the refusal would turn back into a 500.
            DB::transaction(static fn () => $user->save());
        } catch (UniqueConstraintViolationException $e) {
            // `keycloak_id` is UNIQUE. Checking for a clash before writing would
            // still leave a gap between the check and the write, so the database
            // is the only thing that can decide this without a race: two
            // callbacks for the same subject arriving together both see a free
            // slot, and one of them loses here. Catching it turns what would be
            // a 500 into a plain refusal the admin can act on.
            Log::warning('Keycloak: sub sudah tertaut ke akun lain.', ['exception' => $e]);

            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Login SSO ditolak: identitas Keycloak sudah tertaut ke akun E-Office lain (dari {$username}).",
                subject: $user,
            );

            return redirect()->route('login')->withErrors([
                'keycloak' => 'Identitas Keycloak ini sudah tertaut ke akun E-Office lain. Hubungi admin OPD.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Needed as id_token_hint so Keycloak honours post_logout_redirect_uri.
        $idToken = $tokenSet->getIdToken();
        if (filled($idToken)) {
            $request->session()->put(KeycloakOidcService::SESSION_ID_TOKEN, $idToken);
        }

        // The OP's session id. Back-channel logout arrives naming this and
        // nothing else, so without it stored here that request has no way to
        // find the portal session it is meant to end.
        //
        // The realm advertises backchannel_logout_session_supported: true,
        // which per OIDC BCL 1.0 is exactly the promise that `sid` is present
        // in ID tokens. Stored conditionally anyway: a realm that later turns
        // that setting off should degrade to "no back-channel logout", not to
        // a callback that fails.
        $sid = isset($claims['sid']) ? (string) $claims['sid'] : null;
        if (filled($sid)) {
            $request->session()->put(KeycloakOidcService::SESSION_SID, $sid);
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

    /**
     * Back-channel logout (OIDC Back-Channel Logout 1.0).
     *
     * Keycloak calls this server-to-server when the employee signs out
     * elsewhere — another client, or the Keycloak account console. There is no
     * browser, no session and no CSRF token on this request, so the logout
     * token is the ONLY evidence it is genuine; KeycloakOidcService verifies it
     * completely or throws.
     *
     * Responses are deliberately uninformative. The spec wants 200 on success
     * and 400 on a bad token, and an unauthenticated endpoint should not
     * explain to whoever is probing it whether a subject exists, whether it had
     * sessions, or why a token was rejected. The detail goes to the log and the
     * activity trail instead.
     */
    public function backchannelLogout(Request $request)
    {
        $this->assertEnabled();

        $logoutToken = (string) $request->input('logout_token', '');

        if (blank($logoutToken)) {
            return response('', 400);
        }

        try {
            $claims = $this->keycloak->verifyLogoutToken($logoutToken);
        } catch (Throwable $e) {
            Log::warning('Keycloak: logout token back-channel ditolak.', ['exception' => $e]);

            return response('', 400);
        }

        $subject = isset($claims['sub']) ? (string) $claims['sub'] : null;
        $sid = isset($claims['sid']) ? (string) $claims['sid'] : null;

        // Replay guard. A logout token is single-use; the same one arriving
        // twice is either a retry or a replay, and neither should run again.
        // `add` is atomic, so two simultaneous deliveries cannot both win.
        $jti = isset($claims['jti']) ? (string) $claims['jti'] : null;
        if (filled($jti) && ! Cache::add('kc-logout-jti:'.sha1($jti), true, now()->addMinutes(10))) {
            return response('', 200);
        }

        // `sub` is the authoritative link to a local account — the same claim
        // the login callback binds to keycloak_id.
        $user = filled($subject) ? User::where('keycloak_id', $subject)->first() : null;

        if (! $user) {
            // Nothing to end: this subject was never linked to a portal account.
            // Still a 200 — the OP did its part correctly, and there is no
            // reason to tell an unauthenticated caller that the subject is
            // unknown here.
            return response('', 200);
        }

        // With a sid, end exactly that session. Without one, the token refers to
        // every session this subject has, which is what `sub`-only means.
        $ended = filled($sid)
            ? UserSessions::purgeByKeycloakSid($user->id, $sid)
            : UserSessions::purge($user->id);

        if ($ended > 0) {
            $this->activityLogger->record(
                $request,
                ActivityType::LOGOUT_SSO_BACKCHANNEL,
                "Sesi diakhiri oleh Keycloak (back-channel logout): {$ended} sesi.",
                subject: $user,
                properties: ['sesi_diakhiri' => $ended, 'memakai_sid' => filled($sid)],
                // Nobody was logged in on this request; the OP is the actor.
                actorId: null,
            );
        }

        return response('', 200);
    }

    /** SSO off (or half-configured) means the routes do not exist at all. */
    private function assertEnabled(): void
    {
        abort_unless($this->keycloak->isEnabled(), 404);
    }
}
