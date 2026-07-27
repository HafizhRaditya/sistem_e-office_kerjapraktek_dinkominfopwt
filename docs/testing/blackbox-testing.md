# Tabel Skenario Blackbox Testing — Modul Autentikasi & Kontrol Akses (HNR)

> Bahan lampiran Laporan KP Bab 4 · Hafizh Naufal Raditya (H1D024061)
> Modul: *Perancangan dan Implementasi Modul Autentikasi dan Kontrol Akses Aplikasi*

---

## 1. Lingkungan & Dasar Pengujian

| Hal | Keterangan |
|---|---|
| Tanggal uji | 27 Juli 2026 (pembaruan; pengujian awal 22 Juli 2026) |
| Commit diuji | branch `feat/keycloak-sso` di atas `0825169` |
| Stack | Laravel 13.19 · PHP 8.4.12 · PostgreSQL 18.4 |
| Basis data uji | `sistem_eoffice_test` (terpisah dari `sistem_eoffice` pengembangan) |
| Perintah | `php artisan test` |
| Hasil suite | **178 test lolos, 0 gagal, 929 assertion** |

> Riwayat: 116 test (22 Juli, sebelum modul SSO) → 155 (tombol SSO) → **178**
> setelah alur SSO Keycloak diuji penuh.

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

## 4. Tabel Pengujian SSO KEYCLOAK (SSO-01 – SSO-18)

Jalur login **kedua** di samping NIP/NIK. Seluruh baris **terverifikasi otomatis**
oleh `KeycloakSsoLoginTest` (23 test) dan `KeycloakLoginButtonTest` (5 test).

> **Tidak ada test yang menghubungi Keycloak sungguhan.** Discovery, JWKS, dan
> token endpoint dilayani handler HTTP lokal; ID token ditandatangani memakai
> kunci uji statis pada `tests/Fixtures/KeycloakTestKeys.php`. Karena
> penandatanganan dan verifikasinya nyata, token bertanda tangan salah memang
> **gagal diverifikasi**, bukan diloloskan oleh stub.

| No | Skenario | Langkah Uji | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|
| **SSO-01** | Login SSO pertama kali, NIP cocok | Klik **Masuk dengan Keycloak** → Keycloak mengembalikan token sah dengan `preferred_username = 3302010000000001` | Login berhasil, `keycloak_id` terisi `sub`, `last_login_at` diperbarui, dicatat `login_sso`, diarahkan ke `/dashboard` | Sesuai — `keycloak_id` = `kc-subject-0001`, `last_login_at` terisi, baris `login_sso` tercatat, pengalihan ke `/dashboard` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-02** | Login berikutnya memakai `sub`, bukan NIP | Akun sudah tertaut → token datang dengan `preferred_username` yang **sudah berubah** dan tidak cocok akun mana pun | Tetap login ke akun yang sama lewat `keycloak_id`; `properties.matched_by = keycloak_id` | Sesuai — login berhasil walau NIP tak dikenal, `matched_by` = `keycloak_id` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-03** | Identitas Keycloak tidak terdaftar | Token sah dengan `preferred_username = 9999999999999999` | Ditolak: "Akun Keycloak … belum terdaftar di E-Office. Hubungi admin OPD." **Tidak ada akun baru dibuat**, dicatat `login_failed` | Sesuai — jumlah baris `users` **tidak berubah**, tidak ada baris ber-NIP tersebut, `login_failed` tercatat dengan `subject_label` identitas itu | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-04** | Akun nonaktif ditolak walau token sah | `is_active = false` → callback dengan token sah | Ditolak: "Akun Anda dinonaktifkan. Hubungi admin OPD." (kalimat sama dengan jalur kata sandi), akun **tidak** ditautkan | Sesuai — tetap tamu, `keycloak_id` tetap kosong | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-05** | Akun sudah tertaut ke `sub` lain | Akun menyimpan `sub` lama → Keycloak mengirim `sub` berbeda untuk NIP yang sama | Ditolak dengan pesan jelas; tautan lama **tidak** ditimpa diam-diam | Sesuai — `keycloak_id` tetap `kc-subject-LAMA` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-06** | Konflik identitas bukan error 500 | Ulangi SSO-05, periksa kode status HTTP | Respons **302** (penolakan), bukan 500 | Sesuai — status 302 | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-07** | Tabrakan UNIQUE saat penautan (race) | Dua callback untuk `sub` sama tiba bersamaan; pesaing menautkan lebih dulu di antara pembacaan dan penulisan | Kalah lomba ditangani sebagai penolakan berpesan Indonesia, **bukan 500**; `sub` tetap milik pemenang | Sesuai — status 302, `sub` tetap pada akun pemenang, akun yang kalah tetap tak tertaut, `login_failed` tercatat | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-08** | Token bertanda tangan kunci asing | Token ditandatangani kunci lain dengan **`kid` yang sama** | Ditolak — penolakan harus berasal dari tanda tangan, bukan sekadar `kid` berbeda | Sesuai — ditolak, log mencatat `InvalidTokenException` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-09** | `iss` (penerbit) salah | Token dari issuer `https://evil.test/realms/Other` | Ditolak | Sesuai — ditolak, log mencatat `InvalidTokenClaimException` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-10** | `aud` (audiens) salah | `aud` diisi `aplikasi-lain` | Ditolak — token milik klien lain tidak boleh diterima | Sesuai | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-11** | Token kedaluwarsa | `exp` satu jam lampau | Ditolak | Sesuai — ditolak, log mencatat `InvalidTokenClaimException` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-12** | `nonce` tidak cocok (anti *replay*) | Token membawa `nonce` berbeda dari yang disimpan di sesi | Ditolak | Sesuai | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-13** | `state` tidak cocok (anti CSRF) | Callback membawa `state` penyerang | Ditolak sebelum penukaran token | Sesuai | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-14** | Callback tanpa sesi | Panggil `/auth/keycloak/callback` tanpa `state`/`nonce` di sesi | Ditolak — callback lama tidak dapat diputar ulang | Sesuai | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-15** | Logout mengakhiri sesi Keycloak | Login SSO → `POST /logout` | Sesi lokal berakhir **dan** dialihkan ke `end_session_endpoint` beserta `id_token_hint` + `post_logout_redirect_uri`; dicatat `logout` | Sesuai — pengalihan memuat `/protocol/openid-connect/logout`, `id_token_hint`, dan `post_logout_redirect_uri`; kembali menjadi tamu | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-16** | Logout tetap berhasil saat Keycloak mati | Keycloak tidak dapat dihubungi → `POST /logout` | Turun menjadi logout lokal biasa: sesi tetap berakhir, dialihkan ke `/login` — pengguna tidak terjebak dalam keadaan masih masuk | Sesuai — tetap dialihkan ke `/login`, kembali menjadi tamu | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-17** | Permulaan alur mengirim `state` + `nonce` | Buka `/auth/keycloak/redirect` | Dialihkan ke `authorization_endpoint` membawa `client_id`, `scope`, `state`, `nonce`; keduanya tersimpan di sesi | Sesuai — seluruh parameter ada pada URL, `state` dan `nonce` tersimpan di sesi | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-18** | Jalur SSO mati total tanpa konfigurasi | Kosongkan `KEYCLOAK_*` → buka `/login`, `/auth/keycloak/redirect`, `/auth/keycloak/callback` | Tombol **tidak** dirender; kedua route menjawab **404** | Sesuai — tombol absen, kedua route 404; tombol juga absen bila hanya `client_secret` yang hilang | ✅ Lolos (otomatis)<br>`KeycloakLoginButtonTest`, `KeycloakSsoLoginTest` |

### Regresi jalur lama (wajib: SSO tidak boleh merusak login NIP/NIK)

| No | Skenario | Hasil Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|
| **SSO-R1** | Login NIP/NIK tetap berfungsi | Login berhasil ke `/dashboard`; `keycloak_id` **tidak** tersentuh | Sesuai — login berhasil, `keycloak_id` tetap kosong | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-R2** | Rate limiting masih utuh | Setelah 5 percobaan gagal, percobaan dengan sandi **benar** tetap diblokir | Sesuai — tetap tamu, galat pada field `nip_nik` | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-R3** | Turnstile masih utuh | Token Turnstile yang gagal verifikasi tetap menolak login | Sesuai — galat pada field `turnstile`, tetap tamu | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |
| **SSO-R4** | Form NIP/NIK tidak digantikan | Tombol SSO tampil **berdampingan**, bukan menggantikan | Sesuai — `nip_nik`, `password`, dan tombol **MASUK SEKARANG** tetap ada bersama tombol SSO | ✅ Lolos (otomatis)<br>`KeycloakLoginButtonTest` |
| **SSO-R5** | Logout jalur kata sandi tidak berubah | Tanpa `id_token` di sesi, logout tetap mengarah ke `/login` | Sesuai | ✅ Lolos (otomatis)<br>`KeycloakSsoLoginTest` |

---

## 5. Catatan Interpretasi (untuk pembahasan Bab 4)

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

4. **Cakupan pengujian otomatis: 100%.** Seluruh **55 skenario** pada dokumen ini
   (AUT-01…16, RBAC-01…14, SSO-01…18, dan SSO-R1…R5) terverifikasi oleh test
   otomatis. Kesenjangan yang
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

9. **`sub` bersifat otoritatif pada penautan SSO.** Urutan pencocokan adalah
   `users.keycloak_id` lebih dulu, baru `users.nip_nik`. Bila `preferred_username`
   kelak berubah di Keycloak — koreksi NIP, mutasi antar-OPD — penautan **tetap
   mengikuti `sub`** (SSO-02). Alasannya: `sub` adalah satu-satunya pengenal yang
   dijamin OIDC stabil dan tidak pernah dipakai ulang, sedangkan NIP adalah data
   administratif yang bisa disunting di kedua direktori. Mempercayai NIP di atas
   `sub` berarti penyuntingan NIP dapat diam-diam mengarahkan satu identitas
   Keycloak ke akun pegawai lain.

10. **SSO tidak pernah membuat akun (SSO-03).** Daftar pegawai dikelola admin.
    Bila portal memprovisikan akun secara otomatis, siapa pun yang dapat membuat
    akun Keycloak otomatis memperoleh akun E-Office. Karena itu identitas yang
    berhasil diautentikasi Keycloak namun tidak dikenal portal **ditolak**, dan
    test menegakkannya dengan memeriksa jumlah baris `users` tidak berubah.

11. **Tabrakan UNIQUE ditangani basis data, bukan pemeriksaan awal (SSO-07).**
    Versi pertama memakai pemeriksaan "apakah `sub` sudah dipakai akun lain?"
    sebelum menulis. Pemeriksaan itu ternyata **tidak pernah dapat tereksekusi**
    (bila pembacaan lewat `keycloak_id` gagal, berarti belum ada yang memakainya)
    sekaligus **tidak menutup** celah waktu antara pembacaan dan penulisan. Kini
    penulisannya dibungkus `try/catch` atas `UniqueConstraintViolationException`
    sehingga basis data yang memutuskan.
    Satu perilaku PostgreSQL menjadi penentu di sini: statement yang gagal
    **membatalkan seluruh transaksi**, sehingga setiap query sesudahnya gagal
    dengan `SQLSTATE[25P02]`. Tanpa penanganan, pencatatan `login_failed` di dalam
    blok `catch` ikut gagal dan penolakan kembali berubah menjadi error 500.
    Karena itu penulisan dibungkus `DB::transaction()` agar memperoleh *savepoint*
    tersendiri. Perilaku ini diverifikasi dengan *mutation testing*: savepoint
    dilepas sementara, dan test SSO-07 langsung gagal dengan **500** alih-alih
    302 — membuktikan savepoint itulah yang menegakkan properti tersebut.

12. **Tombol SSO hanya muncul bila terkonfigurasi (SSO-18).** Route SSO menjawab
    404 ketika `KEYCLOAK_*` kosong, sehingga tombol tanpa syarat akan menjadi
    tautan mati di lingkungan yang belum dikonfigurasi. Ini pertimbangan yang sama
    dengan tautan "Lupa password" pada butir 5. Test mengunci tombol dan route-nya
    sebagai satu pasangan agar keduanya tidak dapat lepas sinkron.

13. **Verifikasi tanda tangan diuji sungguhan, bukan disimulasikan.** Kunci uji
    statis pada `tests/Fixtures/` dipakai untuk menandatangani ID token secara
    nyata, dan kunci "penyerang" sengaja memakai `kid` yang sama dengan kunci
    realm — sehingga penolakan pada SSO-08 hanya mungkin berasal dari tanda tangan
    yang tidak cocok. Keaslian penolakan diperiksa melalui jenis exception yang
    tercatat di log: SSO-08 menghasilkan `InvalidTokenException` (kegagalan tanda
    tangan) sedangkan SSO-09/SSO-11 menghasilkan `InvalidTokenClaimException`
    (kegagalan klaim) — berbeda sesuai mode kegagalannya, bukan sekadar "gagal".
