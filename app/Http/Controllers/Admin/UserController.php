<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Pengguna (`users`).
 *
 * Accounts are never deleted, only deactivated, so historical access, visits,
 * questionnaire responses and activity entries remain intact.
 */
class UserController extends Controller
{
    private const ROLES = ['admin', 'pegawai'];

    private const AUDIT_FIELDS = [
        'opd_id', 'nip_nik', 'name', 'email', 'role', 'is_active',
    ];

    /** Password policy, same as FR-A06: min 8, letters AND numbers. */
    private const PASSWORD_RULES = ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'];

    private const PASSWORD_MESSAGES = [
        'password.required' => 'Kata sandi wajib diisi.',
        'password.min' => 'Kata sandi minimal 8 karakter.',
        'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        'password.regex' => 'Kata sandi harus mengandung huruf dan angka.',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

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
        $user = User::create($data);

        $this->activityLogger->record(
            $request,
            ActivityType::USER_CREATED,
            "Membuat pengguna \"{$user->name}\".",
            subject: $user,
            properties: ['after' => $user->only(self::AUDIT_FIELDS)],
        );

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

        if ($user->is($request->user())) {
            $data['is_active'] = true;
            $data['role'] = $user->role;
        }

        $before = $user->only(self::AUDIT_FIELDS);
        $user->update($data);
        $changes = $this->activityLogger->changes($before, $user->fresh()->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::USER_UPDATED,
                "Memperbarui data pengguna \"{$user->name}\".",
                subject: $user,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'Data pengguna diperbarui.');
    }

    public function status(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $before = (bool) $user->is_active;
        $user->update(['is_active' => ! $before]);
        $user->refresh();

        $type = $user->is_active
            ? ActivityType::USER_ACTIVATED
            : ActivityType::USER_DEACTIVATED;

        $this->activityLogger->record(
            $request,
            $type,
            ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." akun \"{$user->name}\".",
            subject: $user,
            properties: [
                'before' => ['is_active' => $before],
                'after' => ['is_active' => (bool) $user->is_active],
            ],
        );

        return back()->with('status', $user->is_active
            ? "Akun \"{$user->name}\" diaktifkan."
            : "Akun \"{$user->name}\" dinonaktifkan.");
    }

    public function resetPassword(Request $request, User $user)
    {
        // Self-protection: this form sets a new password without asking for the
        // old one, which is right for helping someone else but wrong for your own
        // account. /ubah-sandi is the path for that, and it requires the current
        // password — routing self-resets there keeps that check from being bypassed.
        if ($user->is($request->user())) {
            return back()->withErrors([
                'password' => 'Anda tidak dapat mereset kata sandi akun sendiri di sini. Gunakan menu Ubah Sandi, yang meminta kata sandi lama.',
            ]);
        }

        $request->validate(['password' => self::PASSWORD_RULES], self::PASSWORD_MESSAGES);

        $user->update(['password' => $request->input('password')]);

        // Password values are deliberately never written to the audit trail.
        $this->activityLogger->record(
            $request,
            ActivityType::PASSWORD_RESET,
            "Mereset kata sandi pengguna \"{$user->name}\".",
            subject: $user,
        );

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', "Kata sandi \"{$user->name}\" berhasil direset.");
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
