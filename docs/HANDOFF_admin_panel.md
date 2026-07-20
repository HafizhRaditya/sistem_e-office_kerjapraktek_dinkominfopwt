# HANDOFF — Modul Panel Admin (HNR)

> **Untuk sesi Claude Code / kontributor berikutnya.** Baca `README.md` dan `ROADMAP.md` lebih dulu untuk konteks proyek; dokumen ini khusus melanjutkan **modul Autentikasi & Kontrol Akses (HNR / Hafizh Naufal Raditya)**.
>
> Disusun: 16 Juli 2026 · Branch: `feat/admin-panel`

---

## 1. Posisi Git

| Hal | Kondisi |
|---|---|
| Branch aktif | **`feat/admin-panel`** |
| Vs `origin/main` | **7 commit ahead, 0 behind** (fast-forward bersih, tidak perlu merge/rebase) |
| Sudah ter-push | **6 commit** ada di `origin/feat/admin-panel` (tip `95ec3c7`) |
| **Belum ter-push** | **1 commit**: `777f641` (dokumen FR-A01) |
| Working tree | **Bersih** (`git status --porcelain` kosong) |

**Riwayat commit modul ini (terbaru → terlama):**

```
777f641  docs(kf-auth): align FR-A01 with the role-based landing behaviour   [BELUM PUSH]
95ec3c7  feat(admin): live type-as-you-go search on the admin lists (Livewire)
3fbc09b  feat(auth): role-based landing after login + /admin root route
fd9a25c  feat(admin): Manajemen Pengguna + Log Aktivitas (FR-A11/A12)
802e4d4  feat(admin): CRUD Manajemen Aplikasi & Tautan (FR-A11)
58a6ca6  feat(admin): admin layout + Manajemen Hak Akses (FR-A08/A11)
10e3126  feat(rbac): reject launch of inactive application/link with 403 (FR-A10)
--- di atas ini milik main / rekan tim ---
c9a8a95  Menambahkan/setup icon aplikasi   (MAU)
```

> **PENTING:** jangan push tanpa izin pemilik repo. Push dilakukan sendiri oleh Hafizh di terminal.

---

## 2. File yang dibuat / diubah

### Dibuat (semua milik HNR)

**Controller & middleware**
- `app/Http/Controllers/Admin/AccessController.php` — Manajemen Hak Akses
- `app/Http/Controllers/Admin/ApplicationController.php` — CRUD aplikasi
- `app/Http/Controllers/Admin/ApplicationLinkController.php` — CRUD tautan (nested)
- `app/Http/Controllers/Admin/UserController.php` — CRUD pengguna
- `app/Http/Controllers/Admin/ActivityLogController.php` — viewer log (read-only)
- `app/Http/Middleware/EnsureUserIsAdmin.php` — penjaga `role='admin'` → 403

**View**
- `resources/views/layouts/admin.blade.php` — layout admin (sidebar + header "EOB Admin" + dark mode)
- `resources/views/admin/akses/{index,edit}.blade.php`
- `resources/views/admin/aplikasi/{index,create,edit,_form}.blade.php`
- `resources/views/admin/aplikasi/link/{create,edit,_form}.blade.php`
- `resources/views/admin/pengguna/{index,create,edit,_form}.blade.php`
- `resources/views/admin/log/index.blade.php`

**Komponen Livewire 4 (single-file, prefix `⚡`)**
- `resources/views/components/admin/⚡access-table.blade.php`
- `resources/views/components/admin/⚡application-table.blade.php`
- `resources/views/components/admin/⚡user-table.blade.php`

**Test (feature, 6 berkas / 47 test)**
- `tests/Feature/LaunchGuardTest.php` (5)
- `tests/Feature/AdminAccessControlTest.php` (5)
- `tests/Feature/AdminApplicationCrudTest.php` (9)
- `tests/Feature/AdminUserManagementTest.php` (12)
- `tests/Feature/AdminActivityLogTest.php` (5)
- `tests/Feature/LoginRedirectTest.php` (8)

### Diubah
- `app/Http/Controllers/LaunchController.php` — guard `is_active`
- `app/Http/Controllers/AuthController.php` — `homeFor()` redirect per peran
- `routes/web.php` — **append** grup `admin/*` (route lama tidak disusun ulang)
- `ERD/KF_AUTH_RBAC.md` — FR-A01 disamakan dengan kode

### TIDAK disentuh (milik MAU / bersama)
`dashboard.blade.php` · `DashboardController.php` · `resources/js/app.js` · `EofficeV21Seeder.php` · `components/dashboard/⚡user-statistics.blade.php` · `layouts/app.blade.php` · `config/app.php` · `composer.json/lock` · seluruh modul kuisioner · **semua migration** (skema DB tidak diubah)

---

## 3. Status fitur

### ✅ Selesai & teruji
| Fitur | Catatan |
|---|---|
| Guard `is_active` di `/launch` | Aplikasi **atau** link nonaktif → 403, tanpa redirect, **tanpa** catat `application_visits` |
| Layout admin | Sidebar 5 menu, header "EOB Admin", info user, dark mode, responsif |
| Manajemen Hak Akses | Daftar (live search + filter OPD, paginasi) + halaman "Atur Akses" (toggle Alpine, indikator perubahan, save sticky, sinkron `application_access`) |
| Manajemen Aplikasi & Tautan | CRUD penuh, constraint dihormati (slug unik, CHECK `app_group`/`category`, unique `(application_id,label)`) |
| Manajemen Pengguna | CRUD, aktif/nonaktif, reset sandi (tercatat `password_changed`), penjaga akun-sendiri |
| Log Aktivitas | Tabel paginasi + filter user / jenis / rentang tanggal (server-side) |
| Redirect login per peran | admin → `/admin/akses`, pegawai → `/dashboard`, `intended()` menang |
| Route root `/admin` | Sebelumnya 404; kini redirect ke Manajemen Hak Akses |
| Live search daftar admin | Livewire, debounce 300ms, tanpa Enter, state ke URL |

### ❌ Belum dikerjakan
| Item | Alasan / keputusan |
|---|---|
| **Tombol "Panel Admin" di navbar portal** | Butuh edit `layouts/app.blade.php` (**file MAU**) — sengaja di-skip, menunggu koordinasi. Akibatnya admin yang sedang di `/dashboard` tidak punya jalan balik ke panel selain mengetik `/admin` |
| **Halaman error 403 bertema** | Masih memakai halaman bawaan Laravel (pesan tersampaikan, tapi belum bergaya merah-putih) |
| **Filter Log Aktivitas dibuat live** | **Sengaja tidak dibuat** — keputusan eksplisit pemilik repo. Tetap pakai tombol "Terapkan" |
| **Dashboard ringkasan admin** | Tidak ada; `/admin` langsung ke Manajemen Hak Akses (di luar roadmap) |
| **Verifikasi visual via screenshot** | Renderer screenshot di sandbox bermasalah sepanjang sesi; verifikasi dilakukan lewat DOM/JS + feature test |

---

## 4. Keputusan penting yang sudah diambil

1. **Admin TIDAK menembus `is_active`.** `is_active` = *ketersediaan*, bukan *izin*. Aplikasi/link nonaktif tidak dapat diluncurkan **siapa pun**, termasuk admin (admin mengelolanya lewat panel, bukan meluncurkannya). Sesuai FR-A10(4). Mudah dibalik bila diminta — satu blok `if` di `LaunchController`.
2. **Alpine vs Livewire — dipakai dua-duanya, sesuai konteks:**
   - **Daftar BERPAGINASI** (hak akses, aplikasi, pengguna) → **Livewire** server-side. Client-side salah di sini karena hanya menyaring baris halaman yang tampil.
   - **Daftar dimuat penuh** (grid dashboard, "Atur Akses") → **Alpine** client-side, instan.
   - **Jangan** memindahkan pencarian/tab/filter dashboard pegawai ke Livewire — sudah benar dengan Alpine.
3. **Setiap form di halaman sendiri** (create/edit aplikasi, create/edit tautan, create/edit pengguna) agar error bag & `old()` tidak saling tabrak.
4. **Kolom `icon` tidak disentuh** pada CRUD aplikasi — ikon dikelola lewat aset/seeder MAU; create → `null`, update → nilai lama dipertahankan.
5. **Penjaga akun-sendiri:** admin tidak bisa menonaktifkan, **menurunkan peran**, maupun menghapus akunnya sendiri. Guard *demote* adalah tambahan (menurunkan diri = mengunci diri dari panel).
6. **Penjaga `questionnaires.created_by` (ON DELETE RESTRICT):** menghapus pembuat kuisioner ditolak dengan pesan ramah, bukan error 500.
7. **Validasi di lapis aplikasi** menjadi penjaga depan constraint DB (pesan Indonesia), DB tetap sebagai backstop.
8. **Test memakai PostgreSQL dev, bukan sqlite.** Migration kita PostgreSQL-only (`ALTER TABLE ADD CONSTRAINT`, index `COALESCE`), sehingga sqlite `:memory:` bawaan phpunit tidak bisa memigrasinya. Semua feature test meng-override koneksi ke `pgsql` di `setUp()` dan **tidak** memakai `RefreshDatabase`; data uji diberi prefix (`uji-`, `UJI`) lalu dibersihkan di `tearDown()`.

---

## 5. Utang teknis / hal belum tuntas

1. **1 commit belum ter-push:** `777f641` (dokumen FR-A01).
2. **Dokumen KF_AUTH_RBAC masih v1.0 secara umum.** Hanya FR-A01 yang disamakan. Bagian lain masih menyebut **3 role** (`superadmin`/`admin_opd`/`pegawai`) dan tabel `roles`/`role_user` — padahal skema final hanya **2 role** lewat kolom `users.role`. Perlu penyisiran menyeluruh sebelum dipakai di laporan.
3. **Jebakan `public/hot`.** Jika `npm run dev` pernah jalan lalu dimatikan, file `public/hot` tertinggal dan membuat `@vite` menunjuk ke dev server mati → **seluruh JS mati** (Alpine/Livewire tidak jalan, grid kosong). Pernah terjadi di sesi ini. Untuk demo: `npm run build` + `php artisan serve`, pastikan `public/hot` **tidak ada**.
4. **Feature test tidak mengeksekusi JavaScript.** 47 test hijau tapi tidak akan menangkap kerusakan aset/JS seperti poin 3 — selalu buka website sungguhan sebelum demo.
5. **Ikon aplikasi:** hanya `simpus` yang punya ikon. Berkas `public/images/applications/e-planing.webp` (typo, satu `n`) tidak terpakai karena `applications.e-planning.icon = NULL`. **Ranah MAU** — kabari dia, jangan diperbaiki sepihak.
6. **Rate limit login mengembalikan 302, bukan 429.** Blokir + pesan Indonesia berfungsi, tapi FR-A02 menulis "respons 429". Untuk form web, redirect-back adalah perilaku Laravel yang wajar; putuskan apakah dokumen atau kode yang disesuaikan.
7. **Turnstile aktif** (kunci asli ada di `.env`, tidak masuk Git). Akibatnya login **tidak bisa** diuji lewat curl. Untuk test, endpoint verifikasi Cloudflare di-*fake* (`Http::fake`) — lihat `LoginRedirectTest`.
8. **Data dev bisa termutasi oleh test** (hak akses, log, kunjungan). Reset dengan `php artisan migrate:fresh --seed`.

---

## 6. Aturan tim yang WAJIB dipatuhi

1. **Jangan sentuh file modul MAU** tanpa koordinasi: `dashboard.blade.php`, `DashboardController.php`, `resources/js/app.js`, `EofficeV21Seeder.php`, `components/dashboard/*`, `layouts/app.blade.php`, dan seluruh modul kuisioner.
2. **`routes/web.php` dipakai berdua** — hanya **tambahkan** di grup terpisah, jangan menyusun ulang route yang ada.
3. **Jangan ubah skema DB / migration** tanpa persetujuan kedua anggota tim. Migration adalah satu-satunya sumber kebenaran; jangan menambal lewat SQL langsung.
4. **Jangan push tanpa izin.** Push dilakukan sendiri oleh pemilik repo di terminal. Commit lokal saja.
5. **Data harus asli dari DB, bukan karangan.** Peran hanya `admin` & `pegawai` (tidak ada "Super Admin"); kategori hanya 11 nilai sesuai CHECK; OPD & aplikasi dari tabel `opds`/`applications`.
6. **Alpine dan Livewire dua-duanya dipakai** sesuai konteks (lihat Keputusan #2). Jangan menyeragamkan salah satunya.
7. **Bahasa:** komentar kode & nama variabel **Inggris**; teks UI **Indonesia**.
8. **`.env` tidak masuk Git.** Konfigurasi baru didokumentasikan lewat `.env.example`.

---

## 7. Cara menjalankan & memverifikasi

```bash
# aset (WAJIB — pastikan public/hot tidak ada)
npm run build

# server
php artisan serve --host=127.0.0.1 --port=8000

# reset data dev bila perlu
php artisan migrate:fresh --seed

# seluruh test (47 test, butuh PostgreSQL dev hidup + sudah di-seed)
php artisan test
```

**Kredensial seeder** (semua sandi `password`):

| NIP/NIK | Nama | Peran | Mendarat di |
|---|---|---|---|
| `ADMIN001` | Admin E-Office | admin | `/admin/akses` |
| `3302010000000001` | Budi Santoso (SETDA) | pegawai | `/dashboard` |
| `3302010000000002` | Siti Rahayu (DINKOMINFO) | pegawai | `/dashboard` |
| `3302010000000003` | Agus Prasetyo (DINKES) | pegawai | `/dashboard` |

**Uji asap cepat:** login `ADMIN001` → harus mendarat di `/admin/akses`; ketik di kotak cari → tabel menyaring tanpa Enter. Login pegawai → `/dashboard`; buka `/admin` → **403**; buka `/launch/simpus` (tanpa hak) → **403**.
