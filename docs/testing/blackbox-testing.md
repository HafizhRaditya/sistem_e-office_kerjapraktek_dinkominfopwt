# Tabel Skenario Blackbox Testing — Modul Autentikasi & Kontrol Akses (HNR)

> Bahan lampiran Laporan KP Bab 4 · Hafizh Naufal Raditya (H1D024061)
> Modul: *Perancangan dan Implementasi Modul Autentikasi dan Kontrol Akses Aplikasi*

---

## 1. Lingkungan & Dasar Pengujian

| Hal | Keterangan |
|---|---|
| Tanggal uji | 22 Juli 2026 |
| Commit diuji | `1a11ad3` + branch `feat/reset-password-polish` |
| Stack | Laravel 13 · PHP 8.4.12 · PostgreSQL 18.4 |
| Basis data uji | `sistem_eoffice_test` (terpisah dari `sistem_eoffice` pengembangan) |
| Perintah | `php artisan test` |
| Hasil suite | **116 test lolos, 0 gagal, 596 assertion** |

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

Seluruh skenario pada dokumen ini terverifikasi otomatis. Kolom **Hasil Aktual**
diisi berdasarkan keluaran `php artisan test` yang nyata, bukan pengamatan manual.

> **Catatan validitas pengujian.** Test-test ini diuji balik dengan *mutation
> testing*: guard rate limit, pemeriksaan `is_active`, dan aturan `current_password`
> masing-masing dilumpuhkan sementara, lalu suite dijalankan ulang. Setiap mutasi
> membuat **tepat** test yang menargetkannya gagal — membuktikan test benar-benar
> menguji perilaku tersebut, bukan sekadar hijau. Kode dikembalikan seperti semula
> setelah pemeriksaan.

---

## 2. Tabel Pengujian AUTENTIKASI (AUT-01 – AUT-16)

Seluruh baris pada tabel ini **terverifikasi otomatis**.

| No | Skenario | Langkah Uji | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| **AUT-01** | Login admin dengan kredensial benar | Buka `/login` → isi `ADMIN001` / `password` → klik **Masuk** | Sesi dibuat, `last_login_at` diperbarui, dicatat `login_success`, diarahkan ke `/admin/akses` | Sesuai — pengalihan ke `/admin/akses`, autentikasi terverifikasi | ✅ Lolos (otomatis)<br>`LoginRedirectTest` |
| **AUT-02** | Login pegawai dengan kredensial benar | Buka `/login` → isi `3302010000000002` / `password` → klik **Masuk** | Sesi dibuat, diarahkan ke `/dashboard` (bukan panel admin) | Sesuai — pengalihan ke `/dashboard` | ✅ Lolos (otomatis)<br>`LoginRedirectTest` |
| **AUT-03** | Login dengan kata sandi salah | Isi `3302010000000002` / `sandi-yang-salah` → **Masuk** | Ditolak, pesan **"NIP/NIK atau kata sandi salah."**, dicatat `login_failed` beratribut pengguna tersebut, sesi tidak dibuat | Sesuai — pesan cocok persis, tetap tamu, jumlah `login_failed` bertambah 1 dengan `user_id` terisi | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-04** | Login dengan NIP/NIK tidak terdaftar | Isi `9999999999999999` / `password` → **Masuk** | Ditolak dengan pesan **sama persis** seperti AUT-03 (tidak membocorkan apakah NIP terdaftar); dicatat `login_failed` dengan `user_id` kosong | Sesuai — pesan identik dengan AUT-03, baris log tercatat dengan `user_id = null` | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-05** | Field kosong divalidasi | Kosongkan NIP/NIK dan kata sandi → **Masuk** | Pesan Indonesia per-field: "NIP/NIK wajib diisi." dan "Kata sandi wajib diisi." | Sesuai — kedua pesan muncul pada field masing-masing, tetap tamu | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-06** | Rate limit percobaan login | Ulangi login gagal **6×** dengan NIP/IP sama dalam < 60 detik | Lima percobaan pertama ditolak karena kredensial; percobaan ke-6 diblokir limiter: **"Terlalu banyak percobaan masuk. Silakan coba lagi dalam N detik."** Batas 5×/60 detik per (NIP + IP) | Sesuai — percobaan ke-6 memunculkan pesan rate limit, bukan pesan kredensial | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-06b** | Rate limit berlaku sebelum kredensial diperiksa | Setelah 5× gagal, coba login dengan kata sandi **benar** | Tetap diblokir — limiter dievaluasi lebih dulu daripada pencocokan kredensial | Sesuai — tetap tamu meski sandi benar | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-07** | Login akun nonaktif | Nonaktifkan akun pegawai (`is_active = false`) → coba login dengan sandi **benar** | Ditolak: **"Akun Anda dinonaktifkan. Hubungi admin OPD."** Sesi tidak dibuat, dan `last_login_at` tidak diperbarui | Sesuai — tetap tamu, `login_failed` tercatat, `last_login_at` tetap kosong | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-08** | Akses halaman tanpa login | Sebagai tamu, buka `/dashboard` lalu `/admin/akses` | Dialihkan ke `/login` (bukan 403 — keberadaan halaman tidak dibocorkan ke tamu) | Sesuai — keduanya dialihkan ke `/login` | ✅ Lolos (otomatis)<br>`LoginRedirectTest`, `AdminAccessControlTest` |
| **AUT-09** | Ubah sandi dengan sandi lama salah | Login → `/ubah-sandi` → isi sandi lama `bukan-sandi-lama`, sandi baru valid → **Simpan** | Ditolak: **"Kata sandi lama salah."** Hash sandi tidak berubah dan sandi lama tetap berlaku | Sesuai — hash sebelum dan sesudah identik, sandi lama masih cocok | ✅ Lolos (otomatis)<br>`ChangePasswordTest` |
| **AUT-10** | Ubah sandi: kebijakan sandi ditegakkan | (a) sandi baru `abc1` · (b) sandi baru `hanyahurufsaja` · (c) konfirmasi tidak cocok | (a) "Kata sandi baru minimal 8 karakter." (b) "Kata sandi baru harus mengandung huruf dan angka." (c) "Konfirmasi kata sandi baru tidak cocok." Sandi tidak berubah pada ketiga kasus | Sesuai — ketiga pesan muncul tepat, sandi lama tetap berlaku | ✅ Lolos (otomatis)<br>`ChangePasswordTest` |
| **AUT-10b** | Ubah sandi valid, terpakai untuk login | Sandi lama benar + sandi baru `rahasia123` + konfirmasi cocok → **Simpan** → logout → login memakai sandi baru | Berhasil, flash "Kata sandi berhasil diubah.", dicatat `password_changed`, sandi lama tidak berlaku lagi, sandi baru dapat dipakai masuk | Sesuai — login ulang dengan sandi baru berhasil ke `/dashboard`; sandi tersimpan ter-hash, bukan teks polos | ✅ Lolos (otomatis)<br>`ChangePasswordTest` |
| **AUT-11** | Logout mengakhiri sesi | Login sebagai pegawai → kirim `POST /logout` → buka kembali `/dashboard` | Sesi di-*invalidate*, token CSRF diregenerasi, dicatat `logout` beratribut pengguna tersebut, dialihkan ke `/login`; `/dashboard` kembali meminta login | Sesuai — kembali menjadi tamu, baris `logout` tercatat, `/dashboard` dialihkan ke `/login` | ✅ Lolos (otomatis)<br>`AuthenticationTest` |
| **AUT-12** | Halaman ubah sandi tertutup bagi tamu | Tanpa sesi, buka `/ubah-sandi` dan kirim `PUT` ke `/ubah-sandi` | Keduanya dialihkan ke `/login` | Sesuai | ✅ Lolos (otomatis)<br>`ChangePasswordTest` |
| **AUT-13** | Admin mereset kata sandi pegawai | Panel Admin → Manajemen Pengguna → **Kelola** pada pegawai → isi *Kata sandi baru* + *Ulangi* → **Reset Kata Sandi** (muncul konfirmasi) | Sandi tersimpan ter-hash (bukan teks polos), dicatat `password_changed` dengan keterangan nama admin pelakunya, muncul pesan "Kata sandi … berhasil direset." | Sesuai — `Hash::check` cocok, baris log tercatat | ✅ Lolos (otomatis)<br>`AdminUserManagementTest` |
| **AUT-14** | Pegawai dapat masuk memakai sandi hasil reset admin | Lanjutan AUT-13: keluar dari sesi admin → buka `/login` → masuk sebagai pegawai itu dengan sandi baru | Login berhasil, diarahkan ke `/dashboard` | Sesuai — diuji ujung-ke-ujung lewat form `/login` sungguhan, bukan sekadar pemeriksaan hash | ✅ Lolos (otomatis)<br>`AdminUserManagementTest` |
| **AUT-15** | Admin ditolak mereset kata sandi akunnya sendiri | Panel Admin → **Kelola** pada akun sendiri → kirim `PUT` ke `/admin/pengguna/{id}/reset-sandi` | Ditolak: "Anda tidak dapat mereset kata sandi akun sendiri di sini. Gunakan menu Ubah Sandi, yang meminta kata sandi lama." Hash sandi admin tidak berubah | Sesuai — hash sebelum dan sesudah identik | ✅ Lolos (otomatis)<br>`AdminUserManagementTest` |
| **AUT-16** | Formulir reset disembunyikan pada akun sendiri | Buka halaman Ubah Pengguna untuk akun sendiri, lalu untuk akun pegawai lain | Akun sendiri: formulir tidak ditampilkan, diganti tautan ke **Ubah Sandi**. Akun lain: formulir tampil normal | Sesuai — kolom sandi absen pada halaman akun sendiri, hadir pada akun lain | ✅ Lolos (otomatis)<br>`AdminUserManagementTest` |

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

4. **Cakupan pengujian otomatis: 100%.** Seluruh **32 skenario** pada dokumen ini
   (AUT-01…16 dan RBAC-01…14) terverifikasi oleh test otomatis. Kesenjangan yang
   sempat ada — jalur *kegagalan* login (sandi salah, NIP tak dikenal, field kosong,
   rate limit, akun nonaktif), logout, dan seluruh fitur ubah sandi — ditutup dengan
   menambahkan `AuthenticationTest` (7 test) dan `ChangePasswordTest` (6 test).
   Cakupan reset kata sandi oleh admin (AUT-13…16) menyusul kemudian. Jumlah test
   suite naik dari 90 menjadi **116**.

5. **Pemulihan kata sandi mandiri ("lupa password") sengaja ditunda** atas arahan
   pembimbing lapangan; untuk sementara pemulihan ditempuh lewat admin (AUT-13/14).
   Karena itu halaman login **tidak** menyediakan tautan "Lupa password" — yang ada
   hanya keterangan "Lupa kata sandi? Hubungi admin OPD Anda.", bukan tautan mati.

6. **Reset oleh admin dan ubah sandi mandiri sengaja dipisah.** Formulir reset di
   panel tidak meminta kata sandi lama — wajar karena admin sedang menolong orang
   lain. Untuk akun sendiri jalur itu ditutup (AUT-15/16) agar pemeriksaan kata
   sandi lama pada `/ubah-sandi` (AUT-09) tidak dapat dilewati.

7. **Pesan penolakan login sengaja dibuat seragam.** AUT-03 (sandi salah) dan AUT-04
   (NIP tidak terdaftar) mengembalikan kalimat yang **sama persis**. Ini keputusan
   keamanan: bila pesannya dibedakan, form login dapat dipakai untuk menyimpulkan
   NIP/NIK mana yang terdaftar di sistem (*user enumeration*). Test AUT-04 menegakkan
   properti ini, sehingga perubahan yang tidak sengaja membedakannya akan tertangkap.

8. **Turnstile pada pengujian.** Verifikasi Cloudflare Turnstile aktif di lingkungan
   pengembangan. Pada pengujian otomatis, endpoint verifikasinya dipalsukan
   (`Http::fake`) dan `phpunit.xml` memakai kunci placeholder, sehingga seluruh alur
   login tetap dapat diuji tanpa memanggil layanan luar.
