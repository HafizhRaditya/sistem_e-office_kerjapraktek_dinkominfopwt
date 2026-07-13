<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authentication module (FR-A01..A12). Login identity is `nip_nik` (not email).
 */
class AuthController extends Controller
{
    /** Max failed attempts per (nip_nik + IP) within the decay window. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
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
            $this->log($request, 'login_failed', null, "Login diblokir (rate limit) untuk {$credentials['nip_nik']}.");

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
            $this->log($request, 'login_failed', $user?->id, "Kredensial salah untuk {$credentials['nip_nik']}.");

            throw ValidationException::withMessages([
                'nip_nik' => 'NIP/NIK atau kata sandi salah.',
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->log($request, 'login_failed', $user->id, 'Login ditolak: akun nonaktif.');

            throw ValidationException::withMessages([
                'nip_nik' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.',
            ]);
        }

        // Success.
        RateLimiter::clear($key);
        Auth::login($user); // no "remember" — users table has no remember_token
        $request->session()->regenerate();

        $user->update(['last_login_at' => now()]); // FR-A01
        $this->log($request, 'login_success', $user->id, 'Login berhasil.');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        $this->log($request, 'logout', $userId, 'Logout.');

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
     * Record an activity_logs entry (FR-A12). Event table — created_at is filled
     * by the DB default.
     */
    private function log(Request $request, string $type, ?int $userId, ?string $description = null): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'activity_type' => $type,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Verify the Cloudflare Turnstile token (FR-A02).
     *
     * TODO(HNR): No Turnstile keys are set yet (TURNSTILE_SITEKEY / TURNSTILE_SECRET
     * in .env). Until the secret is configured, verification is SKIPPED so local
     * login works. Once the secret is present, this performs real server-side
     * verification and login is rejected when the token is missing or invalid.
     */
    private function turnstilePasses(Request $request): bool
    {
        $secret = config('services.turnstile.secret');

        if (blank($secret)) {
            return true; // dev/local: not configured → skip.
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
