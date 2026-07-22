<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;

/**
 * Change password (FR-A06). Requires the current password before changing.
 */
class PasswordController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function edit()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
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
        $user->update(['password' => $request->input('password')]);

        $this->activityLogger->record(
            $request,
            ActivityType::PASSWORD_CHANGED,
            'Kata sandi berhasil diubah.',
            subject: $user,
        );

        return redirect()->route('password.edit')->with('status', 'Kata sandi berhasil diubah.');
    }
}
