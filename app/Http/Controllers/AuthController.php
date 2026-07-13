<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Basic session auth so the dashboard has a real logged-in user. Login uses
 * `nip_nik` (not email). Rate limiting, Turnstile server-side verification, and
 * activity logging are intentionally deferred to the full auth module (KF-A).
 */
class AuthController extends Controller
{
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
        ], [], [
            'nip_nik' => 'NIP/NIK',
            'password' => 'kata sandi',
        ]);

        $user = User::where('nip_nik', $credentials['nip_nik'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'nip_nik' => 'NIP/NIK atau kata sandi salah.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'nip_nik' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.',
            ]);
        }

        // No "remember" — the users table has no remember_token column by design.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
