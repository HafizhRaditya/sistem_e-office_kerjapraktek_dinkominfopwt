<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authentication module (FR-A01..A12). Login identity is `nip_nik` (not email).
 */
class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** Max failed attempts per (nip_nik + IP) within the decay window. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nip_nik' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'nip_nik.required' => 'NIP/NIK wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ], [
            'nip_nik' => 'NIP/NIK',
            'password' => 'kata sandi',
        ]);

        // FR-A02 — rate limit: 5 attempts / minute per (nip_nik + IP).
        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Login diblokir (rate limit) untuk {$credentials['nip_nik']}.",
                subjectType: 'login_identity',
                subjectLabel: $credentials['nip_nik'],
            );

            throw ValidationException::withMessages([
                'nip_nik' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
            ])->status(429);
        }

        // FR-A02 — Cloudflare Turnstile (skipped until keys are configured).
        if (! $this->turnstilePasses($request)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'turnstile' => 'Verifikasi keamanan gagal. Silakan coba lagi.',
            ]);
        }

        $user = User::where('nip_nik', $credentials['nip_nik'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                "Kredensial salah untuk {$credentials['nip_nik']}.",
                subject: $user,
                subjectType: $user ? null : 'login_identity',
                subjectLabel: $user ? null : $credentials['nip_nik'],
            );

            throw ValidationException::withMessages([
                'nip_nik' => 'NIP/NIK atau kata sandi salah.',
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->activityLogger->record(
                $request,
                ActivityType::LOGIN_FAILED,
                'Login ditolak: akun nonaktif.',
                subject: $user,
            );

            throw ValidationException::withMessages([
                'nip_nik' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.',
            ]);
        }

        // Success.
        RateLimiter::clear($key);
        Auth::login($user); // no "remember" — users table has no remember_token
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]); // FR-A01
        $this->activityLogger->record(
            $request,
            ActivityType::LOGIN_SUCCESS,
            'Login berhasil.',
            subject: $user,
        );

        // Role-based landing (FR-A01): admin -> admin panel, pegawai -> portal.
        // intended() still wins, so a deep link the user was bounced from is honoured.
        return redirect()->intended($this->homeFor($user));
    }

    /**
     * Landing page for a user, by role: an admin lands in the admin panel, a
     * pegawai on the portal dashboard. An admin can still reach the portal via
     * the "Dashboard" item in the admin sidebar.
     */
    private function homeFor(User $user): string
    {
        return $user->isAdmin()
            ? route('admin.akses.index')
            : route('dashboard');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $this->activityLogger->record(
            $request,
            ActivityType::LOGOUT,
            'Logout.',
            subject: $user,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Throttle key: lowercased nip_nik + client IP (FR-A02).
     */
    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('nip_nik')).'|'.$request->ip();
    }

    /**
     * Verify the Cloudflare Turnstile token (FR-A02).
     *
     * A missing secret is treated differently per environment, on purpose:
     *
     * - local/testing: verification is skipped, so the app runs without
     *   Cloudflare keys and the login pipeline stays testable.
     * - production: verification FAILS. Forgetting TURNSTILE_SECRET on the
     *   server would otherwise disable bot protection silently — the login page
     *   would look and behave normally while the check was gone. Failing closed
     *   turns that misconfiguration into something the operator notices at once.
     */
    private function turnstilePasses(Request $request): bool
    {
        $secret = config('services.turnstile.secret');

        if (blank($secret)) {
            if (app()->environment('production')) {
                Log::error('TURNSTILE_SECRET is not set: login refused because bot protection cannot be verified.');

                return false;
            }

            return true; // local/testing: not configured → skip.
        }

        $token = $request->input('cf-turnstile-response');
        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        return $response->successful() && $response->json('success') === true;
    }
}
