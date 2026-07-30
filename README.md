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
├── demo-sso/            ← ALAT PERAGA SSO (Node.js, port 9000) — bukan bagian
│                          sistem yang diserahkan; lihat Bagian 11
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
| 3 — Integrasi & UAT | 27–31 Jul | Manajemen kategori (MAU), SSO Keycloak (HNR), aplikasi peraga SSO, kesiapan deployment, blackbox testing, **deployment + UAT** | 🔄 berjalan |
| 4 — Laporan | 1–7 Agu | Laporan KP final & serah terima | ⏳ belum |

**Fase 3 belum dapat dinyatakan selesai.** Pekerjaan fitur dan pengujian otomatis
sudah rampung — manajemen kategori, SSO Keycloak, aplikasi peraga `demo-sso/`,
single logout back-channel, berkas kontainer, serta **238 test lolos
(1156 assertion)** — tetapi **deployment nyata dan UAT bersama pembimbing lapangan
belum dilakukan**. Deployment akan ditempuh lewat **Docker**, dan berkas
kontainernya sendiri **belum pernah dibangun** (lihat §12.8).

Sisa pekerjaan sampai akhir KP (daftar lengkap beserta pemiliknya ada di
**ROADMAP.md**, dipisah antara yang *menghalangi serah terima* dan yang *baik untuk
ada*): deployment Docker · bagian Deployment pada README · UAT · tabel blackbox
modul dashboard & kuisioner (MAU) · laporan masing-masing · serah terima.

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

### 10.7 Back-channel logout (Single Logout)

Tanpa ini, keluar dari satu aplikasi tidak mengeluarkan pegawai dari aplikasi
lain: sesi portal tetap hidup sampai kedaluwarsa sendiri, meski Keycloak sudah
menganggap orangnya keluar.

Portal sudah menyediakan endpointnya:

```
POST /auth/keycloak/backchannel-logout
```

Keycloak memanggilnya **server-ke-server** — tanpa browser, tanpa sesi, tanpa
token CSRF. Yang membuktikan panggilan itu asli hanyalah *logout token* yang
ditandatangani realm; portal memverifikasi tanda tangannya terhadap JWKS realm,
memeriksa `iss`, `aud`, `exp`, `iat`, klaim `events`, ketiadaan `nonce`, serta
menolak token yang sama bila dikirim dua kali. Apa pun yang gagal salah satu
pemeriksaan itu dijawab `400` dan tidak mengakhiri sesi apa pun.

**Yang harus diisi di sisi Keycloak** — Clients → `eoffice-portal` → Settings:

| Kolom | Nilai |
|---|---|
| Backchannel logout URL | `https://<domain>/auth/keycloak/backchannel-logout` |
| Backchannel logout session required | **On** |
| Backchannel logout revoke offline sessions | sesuai kebijakan instansi |

**[perlu Dinkominfo]** — pengisian ini butuh akses admin realm produksi.

`Backchannel logout session required: On` yang membuat Keycloak menyertakan
klaim `sid`. Dengan `sid`, portal mengakhiri **tepat satu** sesi — sesi yang
benar-benar keluar. Tanpanya, token hanya membawa `sub`, dan portal terpaksa
mengakhiri **seluruh** sesi pegawai tersebut, termasuk di perangkat lain yang
tidak ikut keluar.

Realm dev Dinkominfo sudah mengiklankan dukungan ini
(`backchannel_logout_session_supported: true` pada dokumen discovery), jadi
tinggal kolom di atas yang perlu diisi.

**Memastikan sudah aktif:** masuk ke portal lewat SSO, lalu keluar dari
Keycloak di tempat lain (account console, atau aplikasi klien lain). Muat ulang
halaman portal — seharusnya kembali ke halaman login. Bila tetap masuk,
periksa `storage/logs/laravel.log`: penolakan logout token tercatat di sana
dengan alasannya.

Endpoint ini ikut aturan yang sama dengan dua rute SSO lainnya — bila
`KEYCLOAK_*` kosong, ia **404**, sehingga deployment tanpa SSO tidak
memaparkan apa pun.

---

## 11. `demo-sso/` — alat peraga SSO (bukan bagian sistem)

> ### ⚠️ Baca ini lebih dulu
>
> `demo-sso/` adalah **alat peraga untuk membuktikan rantai SSO**, bukan bagian
> dari sistem E-Office yang diserahkan. Ia **tidak dipasang di server produksi**,
> tidak dipakai pegawai, dan tidak menyimpan data apa pun.
>
> Satu-satunya tugasnya: menjadi **client kedua** pada realm Keycloak yang sama
> dengan portal, sehingga dapat ditunjukkan bahwa pengguna yang sudah masuk lewat
> SSO di E-Office **tidak dimintai kredensial lagi** saat membuka aplikasi lain.
> Itu bukti untuk klaim *"cukup sekali login untuk semua aplikasi"* (lingkup
> Bagian 1 butir 5) — dipakai saat demo, UAT, dan pengambilan screenshot laporan,
> lalu selesai.
>
> Aplikasi ini berdiri sendiri: Node.js + Express, punya `package.json`, `.env`,
> dan `node_modules` sendiri, terpisah penuh dari Laravel.

### 11.1 Menjalankannya

Perintah dijalankan **dari dalam `demo-sso/`**, bukan dari akar proyek:

```bash
cd demo-sso
```
```bash
npm install
```
```bash
npm start
```

Berjalan di **`http://127.0.0.1:9000`** (portal di `:8000`). Untuk sekadar
memeriksa berkasnya tanpa menjalankan server: `npm run check`.

> **Setup akar proyek TIDAK mencakup aplikasi ini.** Baik `composer run setup`
> maupun `npm install` di akar hanya memasang dependensi Laravel — `package.json`
> akar tidak memakai *workspaces* dan tidak mengetahui keberadaan `demo-sso/`.
> Selama `npm install` di dalam `demo-sso/` belum dijalankan, `npm start` akan
> gagal karena `node_modules` aplikasi ini belum ada.

> **Jangan menjalankan `npm install` untuk aplikasi ini dari akar proyek.**
> Berkas `package.json` milik demo pernah tersalin ke akar dan menimpa milik
> Laravel; `npm install` berikutnya membuang dependensi frontend Laravel
> (Vite, Tailwind, Alpine) karena membaca `package.json` yang salah. Selalu
> `cd demo-sso` terlebih dahulu.

### 11.2 Client Keycloak terpisah

Aplikasi ini **tidak boleh memakai client portal** (`eoffice-portal`). Buat
client **baru** pada realm yang sama, dengan cara yang sama seperti Bagian 10.1:

| Pengaturan | Nilai |
|---|---|
| Client ID | `eoffice-sso-demo` |
| Client authentication | **ON** (confidential) |
| Authentication flow | **Standard flow** |
| Valid redirect URIs | `http://127.0.0.1:9000/callback` |
| Valid post logout redirect URIs | `http://127.0.0.1:9000/logged-out` |
| Web origins | `http://127.0.0.1:9000` |

Kemudian salin `demo-sso/.env.example` menjadi `demo-sso/.env` dan isi:

| Variabel | Diambil dari |
|---|---|
| `OIDC_ISSUER` | Sama dengan issuer portal — `KEYCLOAK_BASE_URL` + `/realms/` + `KEYCLOAK_REALM` |
| `OIDC_CLIENT_ID` | Client ID yang baru dibuat di atas |
| `OIDC_CLIENT_SECRET` | Tab **Credentials** pada client tersebut *(rahasia — hanya di `.env`, jangan pernah ditulis di README, `.env.example`, atau commit)* |
| `OIDC_REDIRECT_URI` | Harus sama persis dengan *Valid redirect URIs* |
| `SESSION_SECRET` | Bangkitkan sendiri: `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"` |

`demo-sso/.env` dan `demo-sso/node_modules` sudah ditutup oleh `.gitignore`
milik folder tersebut, sehingga tidak dapat ikut ter-commit.

### 11.3 ⚠️ Entri seeder "Demo SSO" hanya untuk lokal

Seeder memuat satu aplikasi bernama **Demo SSO** yang tautannya menunjuk ke:

```
http://127.0.0.1:9000
```

**Itu alamat lokal — pada server, ia menunjuk ke server itu sendiri, bukan ke
komputer pengunjung.** Kartu tersebut karenanya tidak akan berfungsi bila ikut
terpasang di lingkungan produksi.

Dalam praktiknya hal itu **tidak akan terjadi**, karena seeder memang tidak
dijalankan di produksi:

| Lingkungan | Perintah | Akibatnya |
|---|---|---|
| Pengembangan | `php artisan migrate --seed` | Data dummy **termasuk** Demo SSO |
| **Produksi** | `php artisan migrate --force` (**tanpa `--seed`**) | Hanya struktur tabel; **tanpa** data dummy dan **tanpa** Demo SSO |

Akun admin pertama di produksi dibuat lewat perintah khusus, bukan seeder:

```bash
php artisan eoffice:create-admin
```

Jadi basis data produksi tidak pernah memuat pegawai dummy, aplikasi contoh,
maupun entri Demo SSO. Bila suatu saat aplikasi peraga ini benar-benar perlu
dipertunjukkan dari server, daftarkan sebagai aplikasi biasa lewat panel admin
dengan URL yang dapat dijangkau jaringan — bukan `127.0.0.1`.

---

## 12. Deployment ke Produksi

Bagian ini khusus untuk **server Dinkominfo**. Untuk menyiapkan laptop sendiri,
lihat §9 — prosedurnya berbeda dan tidak boleh ditukar.

Tersedia dua jalur. **Jalur A (Docker)** dianjurkan karena versi PHP, ekstensi,
dan PostgreSQL ikut terkunci di dalam image. **Jalur B (tanpa Docker)** untuk
server yang sudah punya PHP dan Nginx terpasang dan tidak boleh diubah.

Penanda **[perlu Dinkominfo]** berarti langkah itu menunggu keputusan, kredensial,
atau akses dari instansi — bukan sesuatu yang bisa diputuskan sendiri oleh tim KP.

---

### 12.1 ⚠️ PERINGATAN UTAMA — seeder tidak pernah dijalankan di produksi

> ### JANGAN PERNAH menjalankan `--seed` atau `db:seed` di server.
>
> Seeder membuat akun administrator dengan kata sandi **`password`**. Kata sandi
> itu **tertulis di dalam repositori publik ini** dan dapat dibaca siapa pun di
> internet. Menjalankan seeder di produksi sama dengan menyerahkan panel admin —
> beserta seluruh data pegawai — kepada publik.
>
> Seeder juga menanam pegawai dummy, aplikasi contoh, dan entri "Demo SSO" yang
> menunjuk ke `127.0.0.1`.
>
> **Perintah yang benar di produksi:**
>
> ```bash
> php artisan migrate --force
> ```
>
> **`--force` saja. TANPA `--seed`.** `--force` hanya berarti "jangan tanya
> konfirmasi interaktif", yang memang dibutuhkan di server tanpa terminal
> interaktif. Ia sama sekali tidak berhubungan dengan seeder.
>
> Akun admin pertama dibuat lewat perintah tersendiri — lihat §12.5 langkah 6.

Kegagalan ini **senyap**: seeder berjalan sukses, tidak ada pesan error, dan
sistem tampak normal. Yang berubah hanya satu — ada akun `admin` bersandi
`password` yang bisa dipakai siapa saja.

---

### 12.2 Prasyarat

**Jalur A — Docker**

| Kebutuhan | Catatan |
|---|---|
| Docker Engine + plugin Compose | `docker compose version` harus jalan |
| Akses jaringan keluar saat build | Wajib. Lihat §12.8 — font diunduh saat build |
| Server Linux x86-64 | Image berbasis Debian bookworm |

**Jalur B — tanpa Docker**

| Kebutuhan | Catatan |
|---|---|
| PHP **8.4** (minimal 8.3) | `composer.json` mensyaratkan `^8.3` |
| Ekstensi PHP | `pdo_pgsql` `pgsql` `intl` `zip` `mbstring` `opcache` — plus bawaan: `ctype` `dom` `fileinfo` `filter` `hash` `iconv` `json` `libxml` `openssl` `pcre` `session` `tokenizer` |
| PostgreSQL **18** | Migration memakai `CHECK` constraint dan expression index PostgreSQL; SQLite dan MySQL tidak didukung |
| Nginx (atau Apache) | Document root **wajib** ke `public/` |
| Node.js 22 + npm | Hanya untuk membangun aset; tidak perlu tetap terpasang setelahnya |
| Composer 2 | — |

**Verifikasi ekstensi sebelum mulai:**

```bash
composer check-platform-reqs --no-dev
```

**[perlu Dinkominfo]** — hal yang tidak bisa diputuskan sendiri:

| Hal | Kenapa perlu instansi |
|---|---|
| Nama domain / hostname portal | Menentukan `APP_URL` dan `KEYCLOAK_REDIRECT_URI` |
| Sertifikat TLS + siapa yang menerminasinya | Menentukan `SESSION_SECURE_COOKIE` dan `TRUSTED_PROXIES` |
| Kredensial PostgreSQL produksi | `DB_USERNAME`, `DB_PASSWORD` |
| Kunci Cloudflare Turnstile | Tanpa ini **login tidak bisa dipakai sama sekali** — lihat §12.3 |
| `client_secret` Keycloak untuk realm produksi | Berbeda dari realm dev; `eoffice-portal` dev tidak berlaku |
| Redirect URI produksi terdaftar di Keycloak | Callback ditolak bila belum terdaftar |
| Kebijakan backup basis data | Volume Docker **bukan** backup |

---

### 12.3 Variabel `.env` produksi

Salin `.env.example`, lalu isi. Jangan pernah menyalin `.env` dari laptop —
di dalamnya ada `APP_DEBUG=true` dan `DB_HOST=127.0.0.1`.

```dotenv
APP_NAME="E-Office Banyumas"
APP_ENV=production
APP_KEY=                      # isi dengan: php artisan key:generate
APP_DEBUG=false
APP_URL=https://<domain>      # [perlu Dinkominfo]
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=pgsql
DB_HOST=db                    # Docker: nama layanan. Tanpa Docker: host PostgreSQL
DB_PORT=5432
DB_DATABASE=sistem_eoffice
DB_USERNAME=                  # [perlu Dinkominfo]
DB_PASSWORD=                  # [perlu Dinkominfo]

SESSION_DRIVER=database       # WAJIB database — lihat catatan di bawah
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true    # hanya bila situs benar-benar HTTPS
SESSION_ENCRYPT=true

TRUSTED_PROXIES=              # lihat catatan di bawah

TURNSTILE_SITEKEY=            # [perlu Dinkominfo]
TURNSTILE_SECRET=             # [perlu Dinkominfo] — tanpa ini login MATI

KEYCLOAK_BASE_URL=            # [perlu Dinkominfo] — kosongkan bila SSO belum dipakai
KEYCLOAK_REALM=
KEYCLOAK_CLIENT_ID=
KEYCLOAK_CLIENT_SECRET=
KEYCLOAK_REDIRECT_URI=

LOG_CHANNEL=stack
LOG_STACK=daily                # WAJIB — lihat catatan di bawah
LOG_DAILY_DAYS=14
LOG_LEVEL=warning
```

Lima variabel yang perilakunya tidak terduga bila salah:

**`TURNSTILE_SECRET` — kosong di produksi berarti login DITOLAK seluruhnya.**
Ini disengaja (`AuthController::turnstilePasses`). Di `local`/`testing` verifikasi
dilewati, tetapi di `production` ia **gagal tertutup**: lupa mengisinya akan
menonaktifkan proteksi bot secara diam-diam, sedangkan halaman login tetap
tampak normal. Menolak login mengubah kesalahan konfigurasi itu jadi sesuatu
yang langsung ketahuan. Gejalanya: semua orang gagal masuk, dan log memuat
`TURNSTILE_SECRET is not set`.

**`SESSION_DRIVER` wajib `database`.** Pencabutan sesi saat kata sandi diganti
atau direset admin (`App\Support\UserSessions`) membaca tabel `sessions`
langsung. Pada driver lain pencabutan **tidak terjadi** — ia mencatat peringatan
di log dan mengembalikan 0, tetapi reset kata sandi tidak lagi mengeluarkan
penyusup dari sesi yang sedang berjalan.

**`SESSION_SECURE_COOKIE=true` hanya bila situs benar-benar HTTPS.** Bila
disetel `true` pada situs HTTP, browser menolak menyimpan cookie sesi dan
**tidak ada seorang pun yang bisa login** — tanpa pesan error yang menjelaskan.

**`TRUSTED_PROXIES` jangan dibiarkan `*` bila port aplikasi ter-expose.**
Bawaannya `*` (`bootstrap/app.php`), yang aman **hanya** selama aplikasi
semata-mata dijangkau lewat proxy. Pada Jalur A port `app` memang tidak
dipublikasikan sama sekali, jadi `*` masih dapat diterima. Pada Jalur B, atau
bila port aplikasi terbuka, isi dengan alamat IP proxy yang sebenarnya —
header `X-Forwarded-For` dapat dipalsukan, dan itu memalsukan alamat IP yang
tercatat di jejak audit.

**`LOG_STACK=daily`, jangan `single`.** Driver `single` menulis ke **satu berkas
selamanya**, tanpa apa pun yang menghentikannya. Di server itu berarti berkas log
tumbuh sampai memenuhi disk — dan ketika disk penuh, aplikasi ikut mati. Pada
Jalur A berkas itu hidup di volume `eoffice-storage`, jadi ia bertahan melewati
setiap redeploy dan hanya bertambah. Driver `daily` merotasinya dan menyimpan
sebanyak `LOG_DAILY_DAYS` hari (bawaan 14).

Sebagai gambaran skalanya: basis data pengembangan di laptop tim sudah
menghasilkan `laravel.log` berukuran **14 MB** hanya dari pemakaian sehari-hari.

---

### 12.4 Pemeriksaan sebelum deploy — tabrakan `nip_nik`

Kolom `nip_nik` memakai UNIQUE biasa, dan PostgreSQL menerapkannya **peka huruf
besar-kecil**. Data lama bisa memuat `ADMIN001` dan `admin001` sebagai dua akun
berbeda. Jalankan ini pada basis data yang akan dipakai:

```bash
psql -U <user> -d sistem_eoffice -c "SELECT lower(nip_nik) AS identitas, count(*) AS jumlah, string_agg(nip_nik, ', ') AS ejaan FROM users GROUP BY 1 HAVING count(*) > 1;"
```

- **Nol baris** → aman, lanjutkan.
- **Ada baris** → akun-akun itu **tetap bisa login** dengan ejaan persisnya
  masing-masing, jadi ini bukan penghenti deployment. Tetapi rapikan salah
  satunya lewat panel admin sebelum suatu saat batasan di tingkat basis data
  ditambahkan, karena migration itu akan gagal selama duplikat masih ada.

Pada basis data baru yang kosong, pemeriksaan ini selalu nol.

---

### 12.5 Jalur A — deployment dengan Docker

> Berkas Docker **belum pernah dibangun**. Baca §12.8 sebelum menjalankan ini
> di server.

**1.** Salin repositori ke server, lalu siapkan `.env` sesuai §12.3.
`demo-sso/` tidak ikut masuk ke container (diblokir `.dockerignore`).

**2.** Bangun image:

```bash
docker compose build
```

**3.** Periksa compose sebelum menyalakan:

```bash
docker compose config
```

**4.** Nyalakan:

```bash
docker compose up -d
```

Layanan `db` punya healthcheck; `app` menunggu sampai PostgreSQL benar-benar
siap sebelum start, sehingga migration tidak berlomba dengan inisialisasi
basis data.

**5.** Jalankan migration — **tanpa `--seed`** (§12.1):

```bash
docker compose exec app php artisan migrate --force
```

**6.** Buat administrator pertama:

```bash
docker compose exec app php artisan eoffice:create-admin
```

Perintah ini meminta NIP/NIK, nama, kode OPD, dan kata sandi. Kata sandi
**selalu ditanyakan interaktif**, tidak pernah lewat argumen, supaya tidak
tertinggal di riwayat shell atau daftar proses. Kebijakan sandinya sama dengan
panel admin: minimal 8 karakter, mengandung huruf dan angka.

Basis data yang baru dimigrasi belum punya OPD, dan perintah ini menolak jalan
tanpa OPD. Isi tabel `opds` lebih dulu — lewat SQL, atau lewat panel admin
setelah admin pertama ada (masalah ayam-telur ini diselesaikan dengan menambah
satu OPD lewat SQL).

**Langkah yang TIDAK perlu dijalankan manual pada Jalur A:**

| Langkah | Sudah dijalankan di mana |
|---|---|
| `composer install --no-dev` | saat `docker compose build`, tahap `app` |
| `npm run build` | saat `docker compose build`, tahap `frontend` |
| `php artisan storage:link` | dibuat di Dockerfile, ditegaskan ulang oleh entrypoint |
| `config:cache` `route:cache` `view:cache` | oleh `docker/entrypoint.sh` tiap container start |

Cache sengaja dibangun saat start, bukan saat build: `config:cache` membekukan
*environment* ke dalam berkas PHP, sehingga membangunnya saat build akan
membekukan nilai milik mesin build — termasuk `APP_KEY` yang kosong.

**Memperbarui versi:**

```bash
git pull
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force   # bila ada migration baru
```

**Volume — jangan sampai salah perintah:**

| Perintah | Akibat |
|---|---|
| `docker compose down` | Container berhenti. Basis data dan unggahan **aman**. |
| `docker compose down -v` | **MENGHAPUS basis data dan seluruh unggahan.** |

Volume Docker **bukan** backup — ia berada di server yang sama. Backup
terjadwal `pg_dump` tetap wajib. **[perlu Dinkominfo]**

---

### 12.6 Jalur B — deployment tanpa Docker

**1.** Pasang PHP 8.4 beserta ekstensi pada §12.2, PostgreSQL 18, Nginx, Node 22,
dan Composer 2.

**2.** Salin repositori ke server (mis. `/var/www/eoffice`), siapkan `.env`
sesuai §12.3 dengan `DB_HOST` menunjuk host PostgreSQL yang sebenarnya.

**3.** Buat basis data kosong, lalu:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate          # hanya bila APP_KEY masih kosong
php artisan migrate --force       # TANPA --seed (§12.1)
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan eoffice:create-admin
```

**4.** Kepemilikan berkas — `storage/` dan `bootstrap/cache/` harus dapat
ditulis oleh user PHP-FPM:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
```

**5.** Nginx: pakai `docker/nginx.conf` sebagai acuan. Yang wajib sama —
`root` menunjuk ke `public/` (**bukan** ke akar proyek; bila salah, `.env`
dapat diunduh lewat HTTP), `.php` diteruskan ke PHP-FPM, dan
`client_max_body_size` minimal sebesar `post_max_size` PHP.

**Setiap kali menarik perubahan:** ulangi `composer install --no-dev`,
`npm run build` bila `resources/` berubah, `migrate --force` bila ada migration
baru, lalu **selalu**:

```bash
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Melewatkan pembersihan cache adalah kegagalan senyap klasik: kode baru sudah
terpasang, tetapi yang dijalankan tetap konfigurasi dan rute versi lama.

---

### 12.7 Verifikasi pasca-deploy

Lakukan berurutan. Setiap butir menguji hal berbeda.

**1. Health check**

```bash
curl -i https://<domain>/up
```
Harus `200`. Bila gagal, PHP-FPM atau basis data belum hidup.

**2. Halaman login tampil dengan gaya utuh**
Buka `https://<domain>/`. Tamu diarahkan ke `/login`. Bila halaman tampil tanpa
gaya (teks polos), aset Vite tidak terpasang — `npm run build` belum berjalan
atau `public/build` tidak ikut ter-deploy.

**3. Masuk sebagai admin** dengan akun dari `eoffice:create-admin`.
Gagal semua padahal sandi benar? Periksa dua hal: `TURNSTILE_SECRET` kosong,
atau `SESSION_SECURE_COOKIE=true` pada situs yang belum HTTPS (§12.3).

**4. Unggah gambar dan pastikan TAMPIL**
Panel admin → Banner → tambah banner dengan gambar. Lalu buka dashboard dan
pastikan gambarnya benar-benar muncul. Ini menguji `storage:link` **dan** volume
`storage` sekaligus. Bila gambar rusak, symlink atau volume-nya bermasalah —
dan unggahannya sendiri **tetap tersimpan tanpa pesan error apa pun**.

**5. Halaman error tidak membocorkan detail**

```bash
curl -i https://<domain>/halaman-yang-tidak-ada
```
Harus halaman 404 yang rapi. Bila muncul jejak tumpukan, nama berkas, atau
kutipan kode, `APP_DEBUG` masih `true` — **hentikan dan perbaiki sebelum portal
dibuka untuk pengguna.** Jejak tumpukan Laravel membocorkan isi variabel
environment, termasuk kredensial basis data.

**6. Berkas sensitif tidak dapat diunduh**

```bash
curl -i https://<domain>/.env
curl -i https://<domain>/storage/logs/laravel.log
```
Keduanya harus `403` atau `404`. Bila salah satunya mengembalikan isi berkas,
`root` Nginx menunjuk ke akar proyek, bukan ke `public/`.

**7. Nonaktifkan akun langsung memutus sesi**
Buat satu akun pegawai uji, masuk dengan akun itu di jendela penyamaran,
lalu nonaktifkan dari panel admin. Muat ulang halaman pegawai: harus terlempar
ke halaman login **seketika**, bukan menunggu sesi kedaluwarsa.

**8. Periksa log**

```bash
docker compose logs app | tail -50        # Jalur A
tail -50 storage/logs/laravel.log         # Jalur B
```
Peringatan `UserSessions::purge dilewati` berarti `SESSION_DRIVER` bukan
`database` (§12.3).

---

### 12.8 ⚠️ Status berkas Docker — belum pernah dibangun

`Dockerfile`, `docker-compose.yml`, `docker/nginx.conf`, `docker/entrypoint.sh`,
dan `.dockerignore` **ditulis pada mesin yang belum memiliki Docker**. Berkas
tersebut disusun dari `composer.json`, `package.json`, dan `vite.config.js`, serta
sudah melewati pemeriksaan statis — tetapi **belum pernah satu kali pun
dibangun**.

**`docker compose build` wajib dijalankan dan berhasil sebelum berkas ini
dipakai di server Dinkominfo.**

Hal yang hanya dapat dipastikan oleh build sungguhan:

| Titik risiko | Kenapa belum pasti |
|---|---|
| Nama berkas komponen Livewire ber-emoji (`⚡`) | Tiga komponen tabel admin bernama `⚡*.blade.php` (konvensi Livewire 4). `LANG=C.UTF-8` sudah dipasang, tetapi perilaku `COPY` dan `view:cache` terhadap U+26A1 di container belum terbukti. **Gagalnya berisik**, bukan senyap: entrypoint menjalankan `view:cache`, sehingga container tidak akan start bila bermasalah. Nama berkasnya **jangan diganti** — itu konvensi Livewire, bukan kekeliruan. |
| Jaringan saat build | `vite.config.js` mengunduh font Inter dari fonts.bunny.net **saat build**. Build host tanpa akses keluar akan gagal di tahap `frontend`. |
| `laravel-vite-plugin` membaca `.env` | Tahap `frontend` tidak memiliki `.env`. Kemungkinan besar jatuh ke nilai bawaan, belum terbukti. |
| `artisan package:discover` saat build | Berjalan tanpa `.env` dan tanpa `APP_KEY`. Umumnya aman, belum terbukti. |
| Tag image | `php:8.4-fpm-bookworm`, `node:22-bookworm-slim`, `nginx:1.27-alpine`, `postgres:18-bookworm`, `composer:2` belum diverifikasi keberadaannya. |
| Nginx me-resolve `app` saat start | Sudah dimitigasi `depends_on` dan `restart: unless-stopped`, tetapi urutannya belum teramati. |
| Collation basis data | Dev berjalan di Windows dengan `English_Indonesia.1252`, nama yang tidak ada di Linux. Produksi memakai `en_US.UTF-8`. Urutan nama campuran huruf besar-kecil dapat sedikit berbeda antar lingkungan. |

Bila `docker compose build` gagal, perbaiki `Dockerfile` — **jangan** mengganti
nama berkas komponen dan **jangan** mengubah kode aplikasi untuk mengakomodasi
build.
