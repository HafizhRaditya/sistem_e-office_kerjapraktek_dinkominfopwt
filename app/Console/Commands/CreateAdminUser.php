<?php

namespace App\Console\Commands;

use App\Models\Opd;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Create the first administrator on a fresh deployment.
 *
 * The demo seeder must never run in production — it creates accounts whose
 * password is literally "password". Without it there is no way into a freshly
 * migrated database, so this command fills that gap: one admin, credentials
 * chosen by the operator, validated by the same password policy the panel
 * enforces (FR-A06).
 *
 * Usage:
 *   php artisan eoffice:create-admin
 *   php artisan eoffice:create-admin --nip=ADMIN001 --name="Admin E-Office" --opd=DINKOMINFO
 *
 * The password is always prompted for (never a CLI argument) so it does not
 * end up in the shell history or the process list.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'eoffice:create-admin
                            {--nip= : NIP/NIK untuk login}
                            {--name= : Nama lengkap (boleh dengan gelar)}
                            {--opd= : Kode OPD, misalnya DINKOMINFO}
                            {--email= : Email (opsional)}';

    protected $description = 'Membuat satu akun administrator untuk deployment baru';

    public function handle(ActivityLogger $logger): int
    {
        $this->info('Membuat akun administrator E-Office.');
        $this->newLine();

        if (Opd::count() === 0) {
            $this->error('Belum ada OPD di basis data. Jalankan migration dan isi tabel opds terlebih dahulu.');

            return self::FAILURE;
        }

        // ?? not ?: — an option passed as an empty string is an answer ("no
        // email"), not a reason to prompt. Only a missing option asks.
        $nipNik = $this->option('nip') ?? $this->ask('NIP/NIK (dipakai untuk login)');
        $name = $this->option('name') ?? $this->ask('Nama lengkap');
        $opdCode = $this->option('opd') ?? $this->anticipate('Kode OPD', Opd::pluck('code')->all());
        $email = $this->option('email') ?? $this->ask('Email (kosongkan bila tidak ada)', '');

        $opd = Opd::where('code', $opdCode)->first();

        if (! $opd) {
            $this->error("OPD dengan kode \"{$opdCode}\" tidak ditemukan.");
            $this->line('OPD tersedia: '.Opd::pluck('code')->implode(', '));

            return self::FAILURE;
        }

        // secret() hides the input; asking twice guards against a typo that would
        // otherwise lock the operator out of the account they just created.
        $password = $this->secret('Kata sandi (min. 8 karakter, huruf dan angka)');
        $passwordConfirmation = $this->secret('Ulangi kata sandi');

        $validator = Validator::make([
            'nip_nik' => $nipNik,
            'name' => $name,
            'email' => $email ?: null,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'nip_nik' => ['required', 'string', 'max:20', 'unique:users,nip_nik'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            // Same policy as the admin panel (FR-A06).
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ], [
            'nip_nik.required' => 'NIP/NIK wajib diisi.',
            'nip_nik.unique' => 'NIP/NIK sudah dipakai pengguna lain.',
            'nip_nik.max' => 'NIP/NIK maksimal 20 karakter.',
            'name.required' => 'Nama wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.regex' => 'Kata sandi harus mengandung huruf dan angka.',
        ]);

        if ($validator->fails()) {
            $this->newLine();
            $this->error('Akun tidak dibuat:');

            foreach ($validator->errors()->all() as $message) {
                $this->line('  · '.$message);
            }

            return self::FAILURE;
        }

        // The 'hashed' cast on User::password hashes this exactly once.
        $user = User::create([
            'opd_id' => $opd->id,
            'nip_nik' => $nipNik,
            'name' => $name,
            'email' => $email ?: null,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);

        // FR-A12. There is no HTTP request on the console, so a synthetic one
        // carries the CLI context: no actor id (nobody was logged in), and a
        // user agent that makes the origin obvious in the activity log.
        // REMOTE_ADDR is left at its default rather than nulled — the trusted
        // proxy middleware calls IpUtils::checkIp(), which rejects a null.
        $request = Request::create('/', 'POST');
        $request->headers->set('User-Agent', 'artisan eoffice:create-admin');

        $logger->record(
            $request,
            ActivityType::USER_CREATED,
            "Akun administrator \"{$user->name}\" dibuat lewat konsol (deployment).",
            subject: $user,
            properties: ['after' => $user->only(['opd_id', 'nip_nik', 'name', 'email', 'role', 'is_active'])],
            actorId: null,
        );

        $this->newLine();
        $this->info('Akun administrator berhasil dibuat.');
        $this->table(
            ['NIP/NIK', 'Nama', 'OPD', 'Peran'],
            [[$user->nip_nik, $user->name, $opd->code, $user->role]],
        );
        $this->line('Silakan masuk melalui halaman login menggunakan NIP/NIK dan kata sandi tersebut.');

        return self::SUCCESS;
    }
}
