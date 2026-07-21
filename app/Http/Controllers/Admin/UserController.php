<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Pengguna (CRUD `users`).
 *
 * Self-protection: an admin may not deactivate, demote, or delete their own
 * account — that would lock them (or everyone) out of the admin panel.
 */
class UserController extends Controller
{
    private const ROLES = ['admin', 'pegawai'];

    /** Password policy, same as FR-A06: min 8, must contain letters AND numbers. */
    private const PASSWORD_RULES = ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'];

    private const PASSWORD_MESSAGES = [
        'password.required' => 'Kata sandi wajib diisi.',
        'password.min' => 'Kata sandi minimal 8 karakter.',
        'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        'password.regex' => 'Kata sandi harus mengandung huruf dan angka.',
    ];

    /**
     * The list itself (live search + filters + pagination) is rendered by the
     * <livewire:admin.user-table> component so searching filters as you type,
     * while the query stays server-side across the whole dataset.
     */
    public function index()
    {
        return view('admin.pengguna.index');
    }

    public function create()
    {
        return view('admin.pengguna.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateUser($request);
        // `password` is cast to 'hashed' on the model, so the plain value is hashed once.
        $user = User::create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$user->name}\" ditambahkan.");
    }

    public function edit(Request $request, User $user)
    {
        $user->load('opd');

        return view('admin.pengguna.edit', array_merge($this->formData(), [
            'user' => $user,
            'isSelf' => $user->is($request->user()),
        ]));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);

        // Self-protection: ignore any attempt to deactivate or demote yourself.
        if ($user->is($request->user())) {
            $data['is_active'] = true;
            $data['role'] = $user->role;
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'Data pengguna diperbarui.');
    }

    public function status(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active
            ? "Akun \"{$user->name}\" diaktifkan."
            : "Akun \"{$user->name}\" dinonaktifkan.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate(['password' => self::PASSWORD_RULES], self::PASSWORD_MESSAGES);

        $user->update(['password' => $request->input('password')]);

        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'password_changed',
            'description' => 'Kata sandi direset oleh admin '.$request->user()->name.'.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', "Kata sandi \"{$user->name}\" berhasil direset.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        // created_by uses ON DELETE RESTRICT on administrator-owned content.
        // Refuse cleanly instead of letting the database throw an FK error.
        if (DB::table('questionnaires')->where('created_by', $user->id)->exists()) {
            return back()->withErrors(['user' => 'Pengguna ini tercatat sebagai pembuat kuisioner, sehingga tidak dapat dihapus. Nonaktifkan akunnya saja.']);
        }

        if (DB::table('banners')->where('created_by', $user->id)->exists()) {
            return back()->withErrors(['user' => 'Pengguna ini tercatat sebagai pembuat banner, sehingga tidak dapat dihapus. Nonaktifkan akunnya saja.']);
        }

        $name = $user->name;
        // FK CASCADE also removes their access grants, visits, and questionnaire clicks.
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$name}\" dihapus.");
    }

    private function formData(): array
    {
        return [
            'opds' => Opd::orderBy('name')->get(),
            'roles' => self::ROLES,
        ];
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'nip_nik' => ['required', 'string', 'max:20', Rule::unique('users', 'nip_nik')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'role' => ['required', Rule::in(self::ROLES)],
        ];

        // Password is only set on create; changing it later goes through resetPassword().
        if (! $user) {
            $rules['password'] = self::PASSWORD_RULES;
        }

        $validated = $request->validate($rules, array_merge([
            'name.required' => 'Nama wajib diisi.',
            'nip_nik.required' => 'NIP/NIK wajib diisi.',
            'nip_nik.unique' => 'NIP/NIK sudah dipakai pengguna lain.',
            'nip_nik.max' => 'NIP/NIK maksimal 20 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'opd_id.required' => 'OPD wajib dipilih.',
            'opd_id.exists' => 'OPD yang dipilih tidak valid.',
            'role.required' => 'Peran wajib dipilih.',
            'role.in' => 'Peran hanya boleh admin atau pegawai.',
        ], self::PASSWORD_MESSAGES));

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
