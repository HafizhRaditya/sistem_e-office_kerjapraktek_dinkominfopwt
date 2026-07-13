<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Change password (FR-A06). Requires the current password before changing —
 * this closes a gap in the old system, which let users set a new password
 * without proving the old one.
 */
class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            // min 8 + must contain letters AND numbers (two regexes share one message)
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ], [
            'current_password.required' => 'Kata sandi lama wajib diisi.',
            'current_password.current_password' => 'Kata sandi lama salah.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.regex' => 'Kata sandi baru harus mengandung huruf dan angka.',
        ]);

        $user = $request->user();

        // The `password` attribute is cast to 'hashed', so assigning the plain
        // value hashes it exactly once.
        $user->update(['password' => $request->input('password')]);

        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'password_changed',
            'description' => 'Kata sandi berhasil diubah.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('password.edit')->with('status', 'Kata sandi berhasil diubah.');
    }
}
