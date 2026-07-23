<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * UX polish of the admin panel (LANGKAH 2): Indonesian validation messages,
 * confirmation prompts before destructive actions, and empty states that tell
 * "no data yet" apart from "your filter matched nothing".
 *
 * No core logic is exercised here — that is covered by the CRUD/RBAC suites.
 * These assertions guard the wording and the guard rails a demo depends on.
 */
class AdminUxPolishTest extends TestCase
{
    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    // ------------------------------------------------- pesan validasi Indonesia

    public function test_the_application_locale_is_indonesian(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'), 'fallback tetap en agar kunci yang belum diterjemahkan tidak tampil mentah');
    }

    public function test_application_form_validation_messages_are_indonesian(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.aplikasi.create'))
            ->post(route('admin.aplikasi.store'), [
                'name' => '',
                'opd_id' => '',
                'slug' => 'Huruf Besar Dan Spasi',
                'app_group' => 'bukan-grup',
                'sort_order' => 'bukan-angka',
            ]);

        $response->assertRedirect(route('admin.aplikasi.create'));

        $errors = $this->validationErrors();

        $this->assertSame('Nama aplikasi wajib diisi.', $errors->first('name'));
        $this->assertSame('OPD pemilik wajib dipilih.', $errors->first('opd_id'));
        $this->assertSame('Grup aplikasi tidak valid.', $errors->first('app_group'));
        $this->assertSame('Urutan harus berupa angka.', $errors->first('sort_order'));

        // Nothing may leak through in English.
        $this->assertNoEnglishIn($errors->all());
    }

    public function test_user_form_validation_messages_are_indonesian(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => '',
                'nip_nik' => str_repeat('9', 25),   // melebihi 20 karakter
                'email' => 'bukan-email',
                'opd_id' => '',
                'role' => 'superadmin',             // peran ini tidak ada di sistem
                'password' => 'abc',
            ]);

        $errors = $this->validationErrors();

        $this->assertSame('Nama wajib diisi.', $errors->first('name'));
        $this->assertSame('NIP/NIK maksimal 20 karakter.', $errors->first('nip_nik'));
        $this->assertSame('Format email tidak valid.', $errors->first('email'));
        $this->assertSame('Peran hanya boleh admin atau pegawai.', $errors->first('role'));
        $this->assertSame('Kata sandi minimal 8 karakter.', $errors->first('password'));

        $this->assertNoEnglishIn($errors->all());
    }

    /**
     * The edge rules the controllers do NOT hand-translate (max/string/integer/
     * exists). Before lang/id these fell through to Laravel's English defaults.
     */
    public function test_untranslated_edge_rules_still_come_out_indonesian(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.aplikasi.create'))
            ->post(route('admin.aplikasi.store'), [
                'name' => str_repeat('a', 200),   // max:150
                'opd_id' => 999999,               // exists:opds,id
                'slug' => 'sah-saja',
                'app_group' => 'tools',
                'sort_order' => 0,
            ]);

        $errors = $this->validationErrors();

        $this->assertSame('Nama maksimal 150 karakter.', $errors->first('name'));
        $this->assertSame('OPD yang dipilih tidak valid atau sudah nonaktif.', $errors->first('opd_id'));
        $this->assertNoEnglishIn($errors->all());
    }

    public function test_activity_log_filter_validation_is_indonesian(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.logs.index'))
            ->get(route('admin.logs.index', ['from' => 'bukan-tanggal', 'user' => 'bukan-angka']));

        $errors = $this->validationErrors();

        $this->assertSame('Tanggal awal tidak valid.', $errors->first('from'));
        $this->assertSame('Pengguna harus berupa angka bulat.', $errors->first('user'));
        $this->assertNoEnglishIn($errors->all());
    }

    // ---------------------------------------------- konfirmasi aksi destruktif

    /**
     * Deletion was withdrawn from this module, so the actions needing a prompt
     * are now deactivation and password reset — both consequential, neither
     * permanent.
     */
    public function test_consequential_actions_ask_for_confirmation(): void
    {
        $pegawai = User::where('nip_nik', '3302010000000002')->firstOrFail();

        // Nonaktifkan akun + reset sandi (halaman Ubah Pengguna)
        $html = $this->actingAs($this->admin())
            ->get(route('admin.users.edit', $pegawai))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nonaktifkan Akun', $html);
        $this->assertStringContainsString('Reset kata sandi', $html);
        $this->assertSame(2, substr_count($html, 'return confirm('),
            'halaman Ubah Pengguna harus mengonfirmasi dua aksi: nonaktifkan akun dan reset sandi');

        // Aktif/nonaktif akun (daftar pengguna)
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('return confirm(', false);
    }

    /**
     * The counterpart: the pages that used to carry a delete button must no
     * longer offer one, so the withdrawal cannot be quietly reverted.
     */
    public function test_no_page_offers_permanent_deletion_in_this_module(): void
    {
        $app = Application::where('slug', 'banyumas-smart-city')->firstOrFail();
        $link = $app->links()->orderBy('id')->firstOrFail();
        $pegawai = User::where('nip_nik', '3302010000000002')->firstOrFail();

        $pages = [
            'Ubah Aplikasi' => route('admin.aplikasi.edit', $app),
            'Ubah Tautan' => route('admin.aplikasi.link.edit', [$app, $link]),
            'Ubah Pengguna' => route('admin.users.edit', $pegawai),
        ];

        foreach ($pages as $screen => $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            foreach (['Hapus Aplikasi', 'Hapus Tautan', 'Hapus Pengguna'] as $label) {
                $this->assertStringNotContainsString($label, $html,
                    "\"{$screen}\" tidak boleh lagi menawarkan \"{$label}\"");
            }

            $this->assertStringContainsString('Status', $html,
                "\"{$screen}\" harus menjelaskan status aktif/nonaktif sebagai gantinya");
        }
    }

    // ------------------------------------------------------------ empty state

    public function test_every_admin_list_says_no_match_rather_than_no_data_when_filtered(): void
    {
        $nonsense = 'zzz-tidak-mungkin-ada-zzz';

        $pages = [
            'Manajemen Aplikasi' => route('admin.aplikasi.index', ['q' => $nonsense]),
            'Manajemen Pengguna' => route('admin.users.index', ['q' => $nonsense]),
            'Manajemen Hak Akses' => route('admin.akses.index', ['q' => $nonsense]),
            'Log Aktivitas' => route('admin.logs.index', ['type' => $nonsense]),
        ];

        foreach ($pages as $screen => $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Tidak ada hasil yang cocok', $html,
                "\"{$screen}\" harus membedakan hasil filter kosong dari data kosong");
            $this->assertStringNotContainsString('Belum ada', $html,
                "\"{$screen}\" tidak boleh bilang \"Belum ada\" padahal datanya ada, hanya tersaring");
        }
    }

    public function test_genuinely_empty_table_says_belum_ada_data_and_offers_a_way_in(): void
    {
        $html = Blade::render(
            '<table><tbody><x-admin.empty-row :colspan="6" :filtered="false" title="Belum ada aplikasi" hint="Petunjuk."><a href="#">Tambah Aplikasi</a></x-admin.empty-row></tbody></table>'
        );

        $this->assertStringContainsString('Belum ada aplikasi', $html);
        $this->assertStringContainsString('Petunjuk.', $html);
        $this->assertStringContainsString('Tambah Aplikasi', $html, 'empty state harus menawarkan jalan keluar');
        $this->assertStringNotContainsString('Tidak ada hasil yang cocok', $html);
        $this->assertStringContainsString('colspan="6"', $html);
    }

    public function test_empty_row_hides_the_call_to_action_when_the_blank_is_caused_by_a_filter(): void
    {
        $html = Blade::render(
            '<table><tbody><x-admin.empty-row :colspan="5" :filtered="true" title="Belum ada aplikasi"><a href="#">Tambah Aplikasi</a></x-admin.empty-row></tbody></table>'
        );

        $this->assertStringContainsString('Tidak ada hasil yang cocok', $html);
        $this->assertStringNotContainsString('Belum ada aplikasi', $html);
        $this->assertStringNotContainsString('Tambah Aplikasi', $html,
            '"Tambah" menyesatkan kalau tabel kosong hanya karena filter');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Validation errors flashed by the last request, as a MessageBag.
     *
     * The session stores the bag in its serialised array form here, so unwrap
     * whichever shape comes back rather than assuming one.
     */
    private function validationErrors(): MessageBag
    {
        $raw = session('errors');

        if ($raw instanceof ViewErrorBag) {
            return $raw->getBag('default');
        }

        return new MessageBag($raw['default']['messages'] ?? []);
    }

    /** @param  array<int, string>  $messages */
    private function assertNoEnglishIn(array $messages): void
    {
        foreach ($messages as $message) {
            foreach ([' field ', ' must ', ' is invalid', ' has already', 'The selected'] as $english) {
                $this->assertStringNotContainsStringIgnoringCase($english, $message,
                    "pesan validasi masih berbahasa Inggris: \"{$message}\"");
            }
        }
    }
}
