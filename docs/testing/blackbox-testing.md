# Tabel Skenario Blackbox Testing — Modul Autentikasi & Kontrol Akses (HNR)

> Bahan lampiran Laporan KP Bab 4 · Hafizh Naufal Raditya (H1D024061)
> Modul: *Perancangan dan Implementasi Modul Autentikasi dan Kontrol Akses Aplikasi*

---

## 1. Lingkungan & Dasar Pengujian

| Hal | Keterangan |
|---|---|
| Tanggal uji | 21 Juli 2026 |
| Commit diuji | `65d7030` (branch `main`) |
| Stack | Laravel 13 · PHP 8.4.12 · PostgreSQL 18.4 |
| Basis data uji | `sistem_eoffice_test` (terpisah dari `sistem_eoffice` pengembangan) |
| Perintah | `php artisan test` |
| Hasil suite | **90 test lolos, 0 gagal, 445 assertion** |

Akun uji dari seeder (`EofficeV21Seeder`), seluruh sandi `password`:

| NIP/NIK | Nama | Peran | OPD |
|---|---|---|---|
| `ADMIN001` | Admin E-Office | admin | DINKOMINFO |
| `3302010000000001` | Budi Santoso | pegawai | SETDA |
| `3302010000000002` | Siti Rahayu | pegawai | DINKOMINFO |
| `3302010000000003` | Agus Prasetyo | pegawai | DINKES |

Aplikasi uji: `banyumas-smart-city`, `simpus`, `e-planning`, `data-hub-banyumas`
(aktif) dan `agenda-pimpinan` (`is_active = false`).

### Arti kolom Status

| Status | Arti |
|---|---|
| ✅ **Lolos (otomatis)** | Diverifikasi oleh test otomatis pada `php artisan test`; nama berkas test dicantumkan |
| 🔍 **Perlu uji manual** | Fitur ada di kode, perilakunya dibaca dari sumber, **belum** ada test otomatis. Hasil Aktual diisi setelah diuji manual |

> **Catatan kejujuran.** Baris berstatus 🔍 sengaja tidak diklaim "lolos". Fitur-fitur
> tersebut memang terimplementasi (rujukan berkas + baris dicantumkan), tetapi belum
> tercakup test otomatis, sehingga tidak ada bukti eksekusi yang bisa dijadikan dasar.
> Isi kolom **Hasil Aktual** setelah menjalankan langkah ujinya di peramban.

---

## 2. Tabel Pengujian AUTENTIKASI (AUT-01 – AUT-10)

| No | Skenario | Langkah Uji | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| **AUT-01** | Login admin dengan kredensial benar | Buka `/login` → isi `ADMIN001` / `password` → klik **Masuk** | Sesi dibuat, `last_login_at` diperbarui, dicatat `login_success`, diarahkan ke `/admin/akses` | Sesuai — pengalihan ke `/admin/akses`, autentikasi terverifikasi | ✅ Lolos (otomatis)<br>`LoginRedirectTest` |
| **AUT-02** | Login pegawai dengan kredensial benar | Buka `/login` → isi `3302010000000002` / `password` → klik **Masuk** | Sesi dibuat, diarahkan ke `/dashboard` (bukan panel admin) | Sesuai — pengalihan ke `/dashboard` | ✅ Lolos (otomatis)<br>`LoginRedirectTest` |
| **AUT-03** | Login dengan kata sandi salah | Isi `3302010000000002` / `salahsandi` → **Masuk** | Ditolak, pesan **"NIP/NIK atau kata sandi salah."**, dicatat `login_failed`, penghitung rate limit bertambah | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:68–75` |
| **AUT-04** | Login dengan NIP/NIK tidak terdaftar | Isi `9999999999999999` / `password` → **Masuk** | Ditolak dengan pesan **sama persis** seperti AUT-03 (tidak membocorkan apakah NIP terdaftar) | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:66–75` |
| **AUT-05** | Field kosong divalidasi | Kosongkan NIP/NIK dan kata sandi → **Masuk** | Pesan Indonesia per-field: "NIP/NIK wajib diisi." dan "Kata sandi wajib diisi." | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:36–44` |
| **AUT-06** | Rate limit percobaan login | Ulangi login gagal **6×** dengan NIP/IP sama dalam < 60 detik | Percobaan ke-6 diblokir: **"Terlalu banyak percobaan masuk. Silakan coba lagi dalam N detik."**, dicatat `login_failed` (rate limit). Batas 5×/60 detik per (NIP + IP) | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:48–56` |
| **AUT-07** | Login akun nonaktif | Admin nonaktifkan akun pegawai lewat panel → pegawai coba login dengan sandi **benar** | Ditolak: **"Akun Anda dinonaktifkan. Hubungi admin OPD."** Sesi tidak dibuat meski sandi benar | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:78–86` |
| **AUT-08** | Akses halaman tanpa login | Sebagai tamu, buka `/dashboard` lalu `/admin/akses` | Dialihkan ke `/login` (bukan 403 — keberadaan halaman tidak dibocorkan ke tamu) | Sesuai — keduanya dialihkan ke `/login` | ✅ Lolos (otomatis)<br>`LoginRedirectTest`, `AdminAccessControlTest` |
| **AUT-09** | Ubah sandi dengan sandi lama salah | Login → `/ubah-sandi` → isi sandi lama `salah`, sandi baru valid → **Simpan** | Ditolak: **"Kata sandi lama salah."** Sandi tidak berubah | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`PasswordController.php:22–28` |
| **AUT-10** | Ubah sandi: kebijakan sandi & keberhasilan | (a) sandi baru `abc` → (b) sandi baru `hanyahuruf` → (c) sandi baru `rahasia123` + konfirmasi cocok | (a) "Kata sandi baru minimal 8 karakter." (b) "Kata sandi baru harus mengandung huruf dan angka." (c) berhasil, dicatat `password_changed`, sandi baru dapat dipakai login | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`PasswordController.php:25–32` |

**Tambahan — logout** *(di luar penomoran AUT karena belum ada test otomatis)*

| No | Skenario | Langkah Uji | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| AUT-11 | Logout mengakhiri sesi | Login → klik **Logout** → tekan tombol *back* peramban → buka `/dashboard` | Sesi di-*invalidate*, token CSRF diregenerasi, dicatat `logout`; `/dashboard` kembali meminta login | *(diisi saat uji manual)* | 🔍 Perlu uji manual<br>`AuthController.php:112–120` |

---

## 3. Tabel Pengujian RBAC & KONTROL AKSES (RBAC-01 – RBAC-14)

Seluruh baris pada tabel ini **terverifikasi otomatis**.

| No | Skenario | Langkah Uji | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| **RBAC-01** | Admin dapat membuka seluruh halaman panel | Login `ADMIN001`, buka 12 halaman `/admin/*` (Beranda, Hak Akses, Atur Akses, Aplikasi, Tambah/Ubah Aplikasi, Tambah/Ubah Tautan, Pengguna, Tambah/Ubah Pengguna, Log Aktivitas) | Seluruh halaman terbuka (200); Beranda mengalihkan (302) | Sesuai — 12/12 terbuka, nol penolakan | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-02** | Pegawai ditolak dari seluruh panel admin | Login pegawai OPD A **dan** OPD B, buka 12 halaman `/admin/*` yang sama | **403** pada semua halaman, bukan pengalihan | Sesuai — 24/24 percobaan ditolak 403 | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-03** | Tamu diarahkan ke login, bukan 403 | Tanpa sesi, buka `/admin/akses` | Dialihkan ke `/login` | Sesuai | ✅ Lolos<br>`AdminAccessControlTest` |
| **RBAC-04** | Pegawai meluncurkan aplikasi yang menjadi haknya | Pegawai SETDA (hak: Smart City, Data Hub) buka `/launch/banyumas-smart-city` | 302 keluar ke URL aplikasi, 1 kunjungan tercatat | Sesuai | ✅ Lolos<br>`RbacCrossRoleTest`, `LaunchGuardTest` |
| **RBAC-05** | Pegawai ditolak pada aplikasi tanpa hak akses | Pegawai SETDA buka `/launch/simpus` | 403 + "Anda tidak memiliki akses ke aplikasi ini." (bukan pengalihan, agar keberadaan aplikasi tidak bocor) | Sesuai | ✅ Lolos<br>`RbacCrossRoleTest`, `LaunchGuardTest` |
| **RBAC-06** | Pegawai OPD lain memiliki hak akses berbeda | Pegawai DINKES (hak: SIMPUS) buka `/launch/simpus`, `/launch/banyumas-smart-city`, `/launch/data-hub-banyumas` | SIMPUS → 302; dua lainnya → 403 | Sesuai | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-07** | Himpunan hak dua OPD terbukti berbeda | Bandingkan daftar `application_access` pegawai OPD A vs OPD B | Kedua himpunan berbeda dan tidak beririsan | Sesuai — irisan kosong terverifikasi | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-08** | Pemberian hak akses lewat panel langsung berlaku | Pegawai coba aplikasi (403) → admin centang aplikasi itu pada **Atur Akses** (`PUT /admin/akses/{user}`) → pegawai coba lagi | Sebelum 403 → sesudah 302, **tanpa login ulang**; baris `application_access` persis sesuai centang | Sesuai | ✅ Lolos<br>`RbacCrossRoleTest`, `AdminAccessControlTest` |
| **RBAC-09** | Pencabutan hak akses lewat panel langsung berlaku | Pegawai berhasil membuka aplikasi → admin hapus centang → pegawai coba lagi | Sebelum 302 → sesudah **403** | Sesuai | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-10** | Perubahan hak berlaku dalam satu sesi berjalan | Pegawai login lewat form `/login` sungguhan → coba aplikasi (403) → hak ditambahkan di tengah sesi → coba lagi (302) → hak dicabut → coba lagi (403) | Ketiga hasil berubah tanpa login ulang; sesi tidak pernah diperbarui | Sesuai — status autentikasi diperiksa di tiap langkah, sesi tetap sama | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-11** | Admin menembus **izin** (tanpa baris hak akses) | Pastikan admin tidak memiliki baris `application_access`, lalu buka `/launch/banyumas-smart-city` | 302 keluar + tepat 1 kunjungan tercatat | Sesuai — admin terverifikasi 0 baris hak akses | ✅ Lolos<br>`RbacCrossRoleTest` |
| **RBAC-12** | Aplikasi nonaktif ditolak meski pegawai punya hak | Pegawai dengan hak atas `agenda-pimpinan` (`is_active=false`) membukanya | 403 + "Aplikasi ini sedang tidak aktif."; **tanpa** catatan kunjungan | Sesuai — jumlah kunjungan tidak bertambah | ✅ Lolos<br>`LaunchGuardTest` |
| **RBAC-13** | Admin **tidak** menembus aplikasi/tautan nonaktif | `ADMIN001` buka `/launch/agenda-pimpinan`, lalu tautan "Backend V2" (`is_active=false`) pada Data Hub | Keduanya 403; tanpa catatan kunjungan. `is_active` = *ketersediaan*, bukan *izin* | Sesuai | ✅ Lolos<br>`LaunchGuardTest`, `RbacCrossRoleTest` |
| **RBAC-14** | Tautan nonaktif ditolak untuk pegawai | Pegawai membuka tautan "Backend V2" Data Hub (`is_active=false`) | 403 + "Tautan aplikasi ini sedang tidak aktif."; tanpa catatan kunjungan | Sesuai | ✅ Lolos<br>`LaunchGuardTest` |

---

## 4. Catatan Interpretasi (untuk pembahasan Bab 4)

1. **RBAC-11 s.d. RBAC-14 bersama-sama membuktikan satu aturan desain:** admin
   menembus *izin* (tidak memerlukan baris `application_access`), tetapi **tidak**
   menembus *ketersediaan* (`is_active`). Aplikasi atau tautan nonaktif tidak dapat
   diluncurkan siapa pun termasuk admin — admin mengelolanya lewat panel, bukan
   meluncurkannya. Sesuai FR-A10 butir 4.

2. **Penolakan memakai 403, bukan pengalihan.** Untuk pengguna yang **sudah login**
   namun tidak berhak, sistem menjawab 403 agar keberadaan sumber daya tidak
   terbocorkan lewat pola pengalihan. Untuk **tamu**, pengalihan ke `/login` tetap
   dipakai (RBAC-03) karena masalahnya autentikasi, bukan otorisasi.

3. **Batas pembuktian RBAC-10.** Penambahan/pencabutan hak di tengah sesi ditulis
   langsung ke basis data, bukan melalui HTTP panel — dalam satu test tidak mungkin
   ada dua sesi login serentak (admin dan pegawai). Kesetaraannya dijamin RBAC-08,
   yang membuktikan panel menghasilkan baris yang sama persis. Digabung, keduanya
   menutup klaim: *panel menulis baris X* **dan** *baris X langsung berlaku dalam
   sesi berjalan*.

4. **Cakupan pengujian otomatis.** Dari 25 skenario di dokumen ini, **17 terverifikasi
   otomatis** (seluruh RBAC-01…14 + AUT-01, AUT-02, AUT-08) dan **8 masih memerlukan
   uji manual** (AUT-03…07, AUT-09…11). Kesenjangan ini terkonsentrasi pada jalur
   *kegagalan* login dan fitur ubah sandi. Menambahkan test otomatis untuk kelompok
   tersebut adalah peluang perbaikan yang layak dicatat pada Bab 5 (Saran).

5. **Turnstile pada pengujian.** Verifikasi Cloudflare Turnstile aktif di lingkungan
   pengembangan. Pada pengujian otomatis, endpoint verifikasinya dipalsukan
   (`Http::fake`) dan `phpunit.xml` memakai kunci placeholder, sehingga seluruh alur
   login tetap dapat diuji tanpa memanggil layanan luar.
