# E-Office Banyumas — Rebuild Project

> **Dokumen ini adalah sumber konteks utama proyek.** Jika Anda adalah AI assistant (Claude, Gemini, ChatGPT, Antigravity, Copilot, dll.) yang diminta membantu proyek ini, baca file ini terlebih dahulu sebelum menulis kode atau memberi saran. Patuhi konvensi, skema, dan pembagian tugas di bawah.

---

## 1. Ringkasan Proyek

**E-Office Banyumas** (`eoffice.banyumaskab.go.id`) adalah portal Single Sign-On (SSO) milik Pemerintah Kabupaten Banyumas yang menjadi pintu masuk tunggal ke 131 aplikasi dinas/pemerintahan ("cukup sekali login untuk semua aplikasi").

Proyek ini adalah **pembangunan ulang (rebuild)** sistem tersebut dalam rangka **Kerja Praktik (KP)** mahasiswa Informatika Universitas Jenderal Soedirman di **Dinkominfo Kabupaten Banyumas**, **Bidang Aplikasi Informatika**, periode **7 Juli – 7 Agustus 2026**. Target sistem selesai: **31 Juli 2026**.

Dasar penempatan: Surat Dinkominfo Kabupaten Banyumas No. **400.14.5.4/2562/VI/2026** tanggal 15 Juni 2026, hal *Jawaban Permohonan Praktek Kerja Lapangan*. Surat memakai istilah **Praktek Kerja Lapangan (PKL)**; di dokumen internal ini istilah yang dipakai adalah **Kerja Praktik (KP)** — keduanya merujuk kegiatan yang sama. Periode resmi dimulai 7 Juli, tetapi proyek baru diserahkan pada 8 Juli sehingga pengerjaan dihitung mulai tanggal tersebut.

### Alasan rebuild
Sistem lama berjalan di **PHP 5.5.33** (end-of-life sejak 2016), Nginx 1.10.2, Bootstrap, tanpa framework modern. Perlu diganti dengan stack yang aktif didukung.

### Ruang lingkup (permintaan resmi dari Dinkominfo)
1. **Rebuild sistem** — migrasi ke bahasa/framework baru, perbarui tampilan dan keamanan.
2. **Popup kuisioner** — popup yang selama ini menampilkan pengumuman ditambah fungsi kuisioner; **hitung dan tampilkan** jumlah user yang sudah mengeklik/mengisi kuisioner.
3. **Pembatasan hak akses aplikasi (RBAC)** — semua aplikasi **tetap tampil** ke semua pegawai; aplikasi yang tidak menjadi hak akses user **ditandai** (ikon gembok, label "Tidak Memiliki Akses", tombol nonaktif) dan diblokir di level route/server (403). Bukan disembunyikan. *(Revisi pembimbing lapangan.)*
4. **Manajemen kategori aplikasi** — kategori dapat diaktifkan/nonaktifkan dan satu aplikasi dapat memiliki banyak kategori. Kategori nonaktif tidak muncul di dashboard, tetapi tidak menyembunyikan aplikasi yang terhubung.
5. **Proof-of-concept SSO Keycloak** — E-Office dan satu aplikasi demo menjadi client pada realm yang sama untuk membuktikan login satu kali tanpa memasukkan kredensial kembali.

### Di luar ruang lingkup (out of scope)
- Membangun ulang aplikasi-aplikasi tujuan (Presensi, SKP, dll.) — kita hanya membangun **portalnya**.
- Mengintegrasikan seluruh aplikasi produksi ke Keycloak; tanggung jawab proyek dibatasi pada E-Office dan aplikasi demo pengujian.
- Migrasi data produksi (gunakan data dummy/seeder yang menyerupai struktur asli).

---

## 2. Tim & Pembagian Tugas

| Nama | NIM | Judul KP (terkunci) | Tanggung jawab |
|---|---|---|---|
| **Hafizh Naufal Raditya (HNR)** | H1D024061 | *Perancangan dan Implementasi Modul Autentikasi dan Kontrol Akses Aplikasi pada Pembangunan Ulang Sistem E-Office Dinkominfo Kabupaten Banyumas* | Login (NIP/NIK), sesi, ganti password, proteksi login, **RBAC**: penanda akses aplikasi per pegawai, middleware 403, halaman admin manajemen user/aplikasi/hak akses, activity log |
| **Muhammad Abu Umar (MAU)** | H1D024084 | *Perancangan dan Implementasi Modul Dashboard Portal dan Kuisioner Partisipasi Pengguna pada Pembangunan Ulang Sistem E-Office Dinkominfo Kabupaten Banyumas* | Layout & dashboard, grid launcher aplikasi, tab/filter/pencarian, penghitung kunjungan, **popup kuisioner** + pencatatan partisipasi + halaman statistik partisipasi (termasuk rekap per OPD) |

Pembagian **per modul** (bukan per lapisan) — tiap orang menggarap fiturnya utuh dari database sampai tampilan. Pekerjaan bersama: analisis, ERD, integrasi, testing, deployment. Kontrak data paling kritis: `applications` + `application_links` + `application_access` (milik HNR) adalah sumber data grid dashboard (milik MAU).

---

## 3. Fitur Sistem Lama (hasil analisis 30 screenshot)

| # | Halaman/Fitur | Keterangan |
|---|---|---|
| 1 | Login | Username "NIP atau NIK" + kata sandi (toggle lihat) + Cloudflare Turnstile, tautan "Lupa Password" |
| 2 | Dashboard | Hero + CTA; seksi "Aplikasi Paling Sering Diakses"; tab grup **Smart City (123) / SPBE (26) / Tools (6)**; filter status & 11 kategori berwarna; pencarian |
| 3 | Kartu aplikasi | Label OPD pemilik, ikon, nama, 1–3 tombol tautan (BACKEND/FRONTEND/varian), status AKTIF, penghitung "pengunjung bulan/tahun ini" |
| 4 | Popup sambutan | Modal bergambar muncul setelah login → **akan dikembangkan menjadi popup kuisioner** |
| 5 | Ganti password | Form ubah kata sandi (di sistem lama tanpa verifikasi sandi lama — diperbaiki di rebuild) |
| 6 | Menu pengguna | Dropdown navbar: Ubah Sandi, Logout (tidak ada halaman profil biodata di sistem lama) |

Screenshot lengkap ada di folder `docs/screenshots/` (ss_01–ss_30).

---

## 4. Tech Stack (baru)

| Lapisan | Teknologi | Catatan |
|---|---|---|
| Backend | **Laravel 13 (PHP 8.4)** | Migrasi natural dari PHP lama; tim Dinkominfo familiar PHP |
| Frontend | **Blade + Tailwind CSS + Alpine.js** | Sederhana, cepat, cukup untuk portal |
| Database | **PostgreSQL 18** | Keputusan tim; wajib PostgreSQL (bukan MySQL) |
| Auth | Laravel session-based + kontrol akses via kolom `users.role` + tabel `application_access` | Dua role: admin, pegawai |
| Proteksi login | Rate limiting Laravel + CAPTCHA (Turnstile) | |
| Versi kontrol | Git + GitHub, branch `main` / `dev` / `feat/*` | PR wajib direview partner |

> Jika pembimbing lapangan mensyaratkan stack lain, perbarui tabel ini terlebih dahulu sebelum menulis kode.

---

## 5. Skema Database (ringkas — ERD lengkap di `docs/erd/`)

```
opds                    : id, code (UK), name, is_active, timestamps
users                   : id, opd_id (FK), nip_nik (UK, login), name, email (UK,null),
                          password, role (CHECK admin|pegawai), is_active, last_login_at, timestamps
applications            : id, opd_id (FK), name, slug (UK), description, icon,
                          app_group (CHECK smartcity|spbe|tools), is_active, is_new, sort_order, timestamps
categories               : id, name, slug (UK), is_active, sort_order, timestamps
application_category     : application_id (FK), category_id (FK)  [PRIMARY KEY(application_id,category_id)]
application_links       : id, application_id (FK), label, url, is_active, sort_order, timestamps  [UNIQUE(application_id,label)]
application_access      : id, application_id (FK), user_id (FK), timestamps  [UNIQUE(application_id,user_id)] -- hak akses per pegawai
application_visits      : id, application_id (FK), application_link_id (FK,null), user_id (FK), visit_date, visited_at  (tanpa timestamps)
questionnaires          : id, created_by (FK), title, description, banner_image, target_url,
                          is_active, starts_at, ends_at (CHECK ends>=starts), timestamps
questionnaire_responses : id, questionnaire_id (FK), user_id (FK), clicked_at  (tanpa timestamps)  [UNIQUE(questionnaire_id,user_id)]
activity_logs           : id, user_id (FK,null), application_id (FK,null), questionnaire_id (FK,null),
                          activity_type, description, ip_address, user_agent, created_at
```

**Aturan bisnis penting (ditegakkan DI DATABASE, bukan hanya di kode):**
- **Role** = kolom `users.role` CHECK ('admin','pegawai'); tidak ada tabel roles. **Login** memakai `nip_nik`.
- Semua aplikasi tetap tampil; hak akses (`application_access`) hanya **menandai** kartu + memvalidasi peluncuran server (403). `can_access(user,app) = role='admin' OR ada baris application_access(app,user)`.
- Relasi aplikasi–kategori bersifat many-to-many. Status kategori hanya menentukan kemunculan filter/label kategori di dashboard; status tersebut tidak menentukan kemunculan aplikasi.
- **1 pegawai = 1 klik per kuisioner** (selamanya): `UNIQUE (questionnaire_id, user_id)`.
- **1 kunjungan per tombol/pegawai/hari**: UNIQUE INDEX `uq_visit_daily` pada `(COALESCE(application_link_id,-1), user_id, visit_date)` — via raw `DB::statement`. Backend & Frontend aplikasi sama di hari sama = 2 kunjungan; tombol sama 2x sehari = 1.
- Tabel event (`application_visits`, `questionnaire_responses`, `activity_logs`) tanpa `created_at`/`updated_at` → model `$timestamps = false`.
- Statistik partisipasi = jumlah `questionnaire_responses` per kuisioner + persentase terhadap pegawai aktif + rekap per OPD.

---

## 6. Struktur Proyek

```
sistem_e-office_kerjapraktek_dinkominfopwt/
├── README.md            ← file ini (konteks utama untuk manusia & AI)
├── ROADMAP.md           ← rencana harian 7 Juli – 7 Agustus 2026
├── schema.sql           ← DDL rancangan (validasi ERD; sumber kebenaran = migration)
├── docs/
│   ├── screenshots/     ← 30 screenshot sistem lama (referensi rebuild)
│   ├── inventaris/      ← inventarisasi fitur per modul
│   ├── erd/             ← ERD final v2.1 (gambar + dokumen)
│   ├── kebutuhan/       ← KF_AUTH_RBAC & KF_DASHBOARD_KUISIONER (final)
│   ├── mockup/          ← mockup_login.html, mockup_dashboard_v2.html
│   └── testing/         ← tabel skenario blackbox testing (bahan laporan)
└── (proyek Laravel di root: app/ database/ resources/ routes/ ...)
```

---

## 7. Konvensi untuk Kontributor (termasuk AI Assistant)

1. **Bahasa:** komentar kode & nama variabel berbahasa **Inggris**; teks UI berbahasa **Indonesia** (pengguna adalah ASN).
2. **Jangan mengubah skema database** pada Bagian 5 tanpa persetujuan kedua anggota tim — grid dashboard dan RBAC saling bergantung padanya.
3. **Migration adalah satu-satunya sumber kebenaran struktur DB.** Jangan menambal database langsung via SQL/`psql`; ubah lewat migration lalu `php artisan migrate`. (`schema.sql` hanya artefak validasi rancangan.)
4. **Keamanan minimum:** password di-hash (bcrypt), semua form ber-CSRF token, input tervalidasi, query lewat Eloquent/parameter binding (tanpa raw SQL rentan injeksi), route sensitif di belakang middleware auth + cek akses, ubah sandi wajib verifikasi sandi lama.
5. **Data:** jangan pernah memakai data ASN asli dalam pengembangan — gunakan seeder dummy. `.env` tidak masuk Git; samakan konfigurasi lewat `.env.example`.
6. **Setiap fitur baru** harus: berjalan, teruji manual, di-PR ke `dev`, dan dicatat sebagai bahan laporan KP.
7. Saat membantu, **sebutkan file/tabel yang Anda ubah** dan jelaskan dampaknya ke modul partner (HNR ↔ MAU). Jangan sentuh modul partner tanpa koordinasi.

---

## 8. Status & Timeline

Lihat **ROADMAP.md** untuk rencana harian, rincian pekerjaan per fase, dan daftar
sisa pekerjaan. Ringkasan fase — status ditentukan dari **bukti di repo** (berkas,
controller, migration, test), bukan dari rencana:

| Fase | Periode | Target | Status |
|---|---|---|---|
| 0 — Analisis & desain | 8–10 Jul | ERD v2.1, KF final, mockup, stack final, repo siap | ✅ selesai |
| 1 — Fondasi | 13–17 Jul | Login → dashboard + grid aplikasi dari DB | ✅ selesai |
| 2 — Fitur inti | 20–24 Jul | Panel admin lengkap (akses, aplikasi, tautan, pengguna, OPD, ringkasan) + kuisioner & statistik | ✅ selesai |
| 3 — Integrasi & UAT | 27–31 Jul | Manajemen kategori (MAU), SSO Keycloak (HNR), kesiapan deployment, blackbox testing, **deployment + UAT** | 🔄 berjalan |
| 4 — Laporan | 1–7 Agu | Laporan KP final & serah terima | ⏳ belum |

**Fase 3 belum dapat dinyatakan selesai.** Pekerjaan fitur dan pengujian otomatis
sudah rampung — manajemen kategori, SSO Keycloak, kesiapan deployment, serta
**178 test lolos (929 assertion)** — tetapi **deployment nyata dan UAT bersama
pembimbing lapangan belum dilakukan**.

Sisa pekerjaan sampai akhir KP (pemilik lengkap ada di ROADMAP.md): deployment ·
UAT · tabel blackbox modul dashboard & kuisioner (MAU) · uji tampilan responsif
(MAU) · aplikasi peraga SSO · laporan masing-masing · serah terima.

> **Kegiatan di luar repo** — demo internal, presentasi, UAT, uji manual, dan status
> penulisan laporan — ditandai 🔍 di ROADMAP dan sengaja **tidak** diberi status
> selesai, karena tidak dapat diverifikasi dari kode.

---

## 9. Menyiapkan Lingkungan Lokal

Proyek ini memakai PostgreSQL. Migration domain tidak kompatibel dengan SQLite
karena menggunakan `CHECK` constraint dan expression index PostgreSQL.

1. Buat dua database kosong pada server PostgreSQL:
   - `sistem_eoffice` untuk development.
   - `sistem_eoffice_test` untuk automated test.

   Test sengaja memakai database **terpisah** dari development supaya menjalankan
   `php artisan test` tidak pernah mengubah data kerja Anda. Nama database test
   ditetapkan di `phpunit.xml` (`DB_DATABASE=sistem_eoffice_test`), bukan di `.env`.

2. Pastikan keduanya dimiliki oleh atau dapat dikelola penuh oleh user yang akan
   diisi pada `DB_USERNAME`. Pembuatan database dilakukan lewat akun administrator
   PostgreSQL; migration tetap menjadi satu-satunya pembentuk tabel aplikasi.

   Dijalankan **sekali per mesin** sebagai superuser `postgres` — ganti `<user>`
   dengan nilai `DB_USERNAME` Anda (mis. `eoffice`):

   ```sql
   ALTER ROLE <user> CREATEDB;
   CREATE DATABASE sistem_eoffice_test OWNER <user>;
   ```

   Contoh pemanggilan dari terminal Windows:

   ```bash
   psql -U postgres -c "ALTER ROLE eoffice CREATEDB;"
   psql -U postgres -c "CREATE DATABASE sistem_eoffice_test OWNER eoffice;"
   ```

   Lewati langkah ini dan seluruh test akan gagal dengan pesan yang sama:
   `SQLSTATE[08006] ... database "sistem_eoffice_test" does not exist`. Itu tanda
   database test belum dibuat, **bukan** kode yang rusak.
3. Salin `.env.example` menjadi `.env`, lalu isi kredensial PostgreSQL lokal.
4. Jalankan setup pada database development yang masih kosong:

```bash
composer run setup
```

Perintah tersebut memasang dependensi, membuat application key, menjalankan
migration + seeder, dan membangun aset frontend.

5. Buat symlink penyimpanan berkas unggahan:

```bash
php artisan storage:link
```

**Wajib, dan `composer run setup` belum menjalankannya.** Gambar banner yang
diunggah lewat panel admin disimpan ke `storage/app/public/banners/`, lalu dirujuk
di browser sebagai `/storage/banners/...`. Symlink `public/storage` inilah yang
menyambungkan keduanya.

Tanpa symlink tersebut, unggahan **tetap tersimpan dan tidak ada pesan error apa
pun** — tetapi gambarnya tampil rusak di dashboard maupun di panel admin. Gejalanya
menyesatkan karena tidak ada yang terlihat gagal. Symlink ini masuk `.gitignore`
(`/public/storage`), jadi setiap mesin harus membuatnya sendiri sekali.

### Ringkasan urutan setup

```bash
composer install                 # dependensi PHP
npm install                      # dependensi frontend
php artisan migrate              # bentuk tabel (schema dari migration)
php artisan db:seed              # data dummy pegawai, aplikasi, kuisioner
php artisan storage:link         # symlink unggahan -> public/storage
npm run build                    # aset produksi (atau `npm run dev` saat mengembangkan)
```

Langkah 1–2 dan 4–6 di atas setara dengan `composer run setup`, **kecuali
`storage:link`** yang harus dijalankan terpisah.

> **Catatan `npm run dev`:** jika dev server pernah dijalankan lalu dimatikan paksa,
> berkas `public/hot` bisa tertinggal dan membuat `@vite` menunjuk ke server yang
> sudah mati — akibatnya seluruh JS (Alpine/Livewire) mati. Untuk demo, pakai
> `npm run build` dan pastikan `public/hot` tidak ada.

Automated test selalu memakai `sistem_eoffice_test` melalui `phpunit.xml`:

```bash
php artisan test
```

Database test **cukup dibuat kosong** — isinya tidak perlu disiapkan manual.
`tests/TestCase.php` memakai `RefreshDatabase` dengan `$seed = true`, sehingga tiap
kali `php artisan test` dijalankan, seluruh migration dijalankan ulang dari nol lalu
seeder diisikan ke database test. Konsekuensinya: isi `sistem_eoffice_test` selalu
ditimpa saat test berjalan, jadi jangan menyimpan apa pun yang berharga di sana.

`tests/TestCase.php` memiliki safety guard: `migrate:fresh` hanya boleh berjalan
pada koneksi PostgreSQL dengan nama database berakhiran `_test`. Jangan mengubah
`DB_DATABASE` test menjadi database development atau production.

---

## 10. Menyiapkan SSO Keycloak (opsional)

Portal memiliki **dua jalur login**. Jalur utama tetap NIP/NIK + kata sandi dan
selalu berfungsi. Jalur kedua adalah SSO Keycloak (OIDC), yang **hanya aktif bila
dikonfigurasi**. Melewati seluruh bagian ini sepenuhnya sah: aplikasi berjalan
normal tanpa Keycloak.

### 10.1 Mendaftarkan client di Keycloak

Pada Keycloak Admin Console, pilih realm yang dipakai (mis. `EOffice`) →
**Clients** → **Create client**.

| Pengaturan | Nilai | Alasan |
|---|---|---|
| Client type | **OpenID Connect** | Bukan SAML |
| Client ID | `eoffice-portal` | Nilai ini yang diisikan ke `KEYCLOAK_CLIENT_ID` |
| **Client authentication** | **ON** (confidential) | Portal adalah aplikasi server. Dengan ON, Keycloak menerbitkan *client secret* dan penukaran token terjadi server-ke-server — secret tidak pernah sampai ke browser. Bila OFF (public client), tidak ada secret dan konfigurasi ini **tidak akan jalan** |
| Authentication flow | **Standard flow** ✅ | Authorization Code Flow. Biarkan *Direct access grants*, *Implicit*, dan *Service accounts* **mati** — tidak dipakai dan hanya memperluas permukaan serang |

Kemudian isi URL pada tab **Settings**:

| Kolom | Development | Produksi (pola) |
|---|---|---|
| **Valid redirect URIs** | `http://127.0.0.1:8000/auth/keycloak/callback`<br>`http://localhost:8000/auth/keycloak/callback` | `https://eoffice.banyumaskab.go.id/auth/keycloak/callback` |
| **Valid post logout redirect URIs** | `http://127.0.0.1:8000/*` | `https://eoffice.banyumaskab.go.id/*` |
| **Web origins** | `http://127.0.0.1:8000` | `https://eoffice.banyumaskab.go.id` |

Catatan penting:

- **Daftarkan `127.0.0.1` dan `localhost` sebagai entri terpisah.** Keycloak
  mencocokkan redirect URI sebagai string, bukan sebagai alamat — keduanya tidak
  dianggap sama meskipun menunjuk mesin yang sama. Bila hanya salah satu yang
  terdaftar, membuka portal lewat host yang lain akan ditolak Keycloak dengan
  *"Invalid parameter: redirect_uri"*.
- **Redirect URI harus sama persis dengan `KEYCLOAK_REDIRECT_URI` di `.env`,**
  termasuk skema, port, dan tanpa garis miring tambahan di akhir.
- **Jangan memakai wildcard pada Valid redirect URIs.** Wildcard hanya wajar pada
  *post logout*, karena tujuan pasca-logout memang bisa beragam.
- Produksi wajib **HTTPS**. Di produksi `SESSION_SECURE_COOKIE=true` juga harus
  aktif (lihat `.env.example`).

Setelah client tersimpan, buka tab **Credentials** untuk mengambil *client secret*.

### 10.2 Mengisi `.env`

Lima variabel, seluruhnya **kosong** di `.env.example` dan diisi di `.env` lokal
masing-masing (`.env` tidak masuk Git):

| Variabel | Diambil dari | Contoh (dev) |
|---|---|---|
| `KEYCLOAK_BASE_URL` | Alamat dasar server Keycloak, **tanpa** `/realms/...` | `https://account.dev.banyumaskab.go.id` |
| `KEYCLOAK_REALM` | Nama realm pada Admin Console | `EOffice` |
| `KEYCLOAK_CLIENT_ID` | Client ID yang dibuat di 10.1 | `eoffice-portal` |
| `KEYCLOAK_CLIENT_SECRET` | Tab **Credentials** pada client tersebut | *(rahasia — jangan pernah ditulis di README, `.env.example`, atau commit)* |
| `KEYCLOAK_REDIRECT_URI` | Harus sama persis dengan salah satu *Valid redirect URIs* | `http://127.0.0.1:8000/auth/keycloak/callback` |

Aplikasi menyusun *issuer* sebagai `KEYCLOAK_BASE_URL` + `/realms/` +
`KEYCLOAK_REALM`, lalu **menemukan sendiri** seluruh endpoint (authorization,
token, JWKS, end session) dari dokumen `.well-known/openid-configuration`. Tidak
ada endpoint yang ditulis manual, sehingga perubahan di sisi Keycloak tidak perlu
diikuti perubahan kode.

Setelah mengubah `.env`, bersihkan cache konfigurasi:

```bash
php artisan config:clear
```

### 10.3 Konvensi pemetaan akun (wajib dibaca)

> **Username akun Keycloak HARUS berisi NIP/NIK pegawai**, sama persis dengan
> kolom `users.nip_nik` di E-Office.

Saat callback, portal membaca klaim `preferred_username` dari ID token dan
mencocokkannya ke `users.nip_nik`. Bila username di Keycloak berisi hal lain
(email, nama, `budi.santoso`), pencocokan gagal dan login ditolak.

**SSO tidak pernah membuat akun baru.** Pegawai harus sudah terdaftar di E-Office
lebih dulu melalui Panel Admin → Manajemen Pengguna. Identitas yang berhasil
diautentikasi Keycloak namun tidak dikenal portal akan ditolak dengan pesan:

> *"Akun Keycloak "…" belum terdaftar di E-Office. Hubungi admin OPD."*

Ini keputusan keamanan, bukan keterbatasan: bila portal memprovisikan akun secara
otomatis, siapa pun yang dapat membuat akun Keycloak otomatis memperoleh akun
E-Office. Daftar pegawai tetap dikelola admin.

Aturan lain yang berlaku sama dengan jalur kata sandi:

- Akun dengan `is_active = false` **ditolak** walaupun token Keycloak-nya sah.
- Hak akses aplikasi (RBAC) tidak terpengaruh jalur login — `application_access`
  tetap penentunya.

**Penautan mengikuti `sub`, bukan NIP.** Saat login SSO pertama berhasil, portal
menyimpan `sub` (subject Keycloak) ke `users.keycloak_id`. Login berikutnya
dicocokkan lewat kolom itu lebih dulu. Artinya bila NIP pegawai kelak dikoreksi di
Keycloak, penautannya tetap utuh — `sub` adalah satu-satunya pengenal yang dijamin
OIDC stabil dan tidak pernah dipakai ulang, sedangkan NIP adalah data
administratif yang bisa disunting.

### 10.4 Perilaku saat `KEYCLOAK_*` kosong

**Ini disengaja, bukan kerusakan:**

- Tombol **"Masuk dengan Keycloak"** tidak dirender di halaman login.
- `/auth/keycloak/redirect` dan `/auth/keycloak/callback` menjawab **404**.
- Login NIP/NIK berjalan normal seperti biasa.

Tombol dan route sengaja dikunci pada gerbang yang sama. Bila tombol tetap
ditampilkan tanpa konfigurasi, pengguna akan mengklik tautan mati — persoalan yang
sama dengan tautan "Lupa password" yang karenanya diturunkan menjadi teks biasa.

**Satu nilai yang hilang sudah cukup untuk mematikan jalur SSO.** Konfigurasi
setengah jadi tidak akan menghasilkan tombol yang "sebagian jalan"; ia hanya akan
mengirim pengguna ke perjalanan bolak-balik yang gagal di tengah.

### 10.5 Catatan lingkungan Windows — `OPENSSL_CONF`

Pada instalasi PHP Windows (mis. Laragon), `openssl_pkey_new()` sering gagal
dengan `error:07000072:configuration file routines::no such file` karena PHP tidak
menemukan `openssl.cnf`. Yang gagal hanya **pembuatan** kunci; memuat kunci yang
sudah ada tidak terpengaruh.

**Test kami tidak membutuhkan ini** — `tests/Fixtures/KeycloakTestKeys.php`
memakai kunci RSA statis yang sudah dibuat sebelumnya, justru supaya `php artisan
test` berjalan di mesin mana pun tanpa konfigurasi tambahan.

Setel variabel ini hanya bila Anda menjalankan perkakas yang **membuat** kunci RSA
sendiri (mis. membangkitkan pasangan kunci baru):

```bash
export OPENSSL_CONF="C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/extras/ssl/openssl.cnf"
```

Sesuaikan versi PHP pada path tersebut dengan yang terpasang di mesin Anda.

### 10.6 Setelah menarik perubahan ini (wajib untuk seluruh anggota tim)

`git pull` saja **tidak cukup**. Branch ini mengubah dependensi dan skema:

```bash
composer install        # dependensi berubah — pustaka OIDC + pergeseran versi
php artisan migrate     # migration 000014: kolom users.keycloak_id
```

Melewatkan `php artisan migrate` menimbulkan gejala yang menyesatkan: `php artisan
test` **tetap hijau** (database test dibangun ulang tiap kali dijalankan)
sementara database development memunculkan error 500. Bila test hijau tetapi
aplikasi gagal, migration tertinggal adalah tersangka pertama.

> **Pin sementara pada `composer.json`.** Tiga paket dikunci di versi 7.4:
> `symfony/console`, `symfony/string`, dan `symfony/http-client`. Tanpa pin
> tersebut, pemasangan pustaka OIDC ikut menaikkan komponen Symfony ke versi
> mayor 8 sebagai efek samping — perubahan yang tidak diminta dan tidak diinginkan
> pada sistem yang sedang dibekukan menjelang serah terima.
>
> **Pin ini bersifat SEMENTARA dan dicabut pasca-KP,** saat peningkatan ke Symfony
> 8 dikerjakan sebagai pekerjaan tersendiri yang direview sadar. Laravel 13 sendiri
> sudah menerima `^7.4 || ^8.0`, jadi pin ini membekukan waktu, bukan batas teknis.
