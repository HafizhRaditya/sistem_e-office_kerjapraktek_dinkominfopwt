# E-Office Banyumas — Rebuild Project

> **Dokumen ini adalah sumber konteks utama proyek.** Jika Anda adalah AI assistant (Claude, Gemini, ChatGPT, Antigravity, Copilot, dll.) yang diminta membantu proyek ini, baca file ini terlebih dahulu sebelum menulis kode atau memberi saran. Patuhi konvensi, skema, dan pembagian tugas di bawah.

---

## 1. Ringkasan Proyek

**E-Office Banyumas** (`eoffice.banyumaskab.go.id`) adalah portal Single Sign-On (SSO) milik Pemerintah Kabupaten Banyumas yang menjadi pintu masuk tunggal ke 131 aplikasi dinas/pemerintahan ("cukup sekali login untuk semua aplikasi").

Proyek ini adalah **pembangunan ulang (rebuild)** sistem tersebut dalam rangka **Kerja Praktik (KP)** mahasiswa Informatika Universitas Jenderal Soedirman di **Dinkominfo Kabupaten Banyumas**, periode **8 Juli – 7 Agustus 2026**. Target sistem selesai: **31 Juli 2026**.

### Alasan rebuild
Sistem lama berjalan di **PHP 5.5.33** (end-of-life sejak 2016), Nginx 1.10.2, Bootstrap, tanpa framework modern. Perlu diganti dengan stack yang aktif didukung.

### Ruang lingkup (permintaan resmi dari Dinkominfo)
1. **Rebuild sistem** — migrasi ke bahasa/framework baru, perbarui tampilan dan keamanan.
2. **Popup kuisioner** — popup yang selama ini menampilkan pengumuman ditambah fungsi kuisioner; **hitung dan tampilkan** jumlah user yang sudah mengeklik/mengisi kuisioner.
3. **Pembatasan hak akses aplikasi (RBAC)** — semua aplikasi **tetap tampil** ke semua pegawai; aplikasi yang tidak menjadi hak akses user **ditandai** (ikon gembok, label "Tidak Memiliki Akses", tombol nonaktif) dan diblokir di level route/server (403). Bukan disembunyikan. *(Revisi pembimbing lapangan.)*

### Di luar ruang lingkup (out of scope)
- Membangun ulang aplikasi-aplikasi tujuan (Presensi, SKP, dll.) — kita hanya membangun **portalnya**.
- Integrasi SSO nyata ke aplikasi eksternal (cukup disimulasikan dengan tautan keluar).
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
                          app_group (CHECK smartcity|spbe|tools), category (CHECK), is_active, is_new, sort_order, timestamps
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
- **1 pegawai = 1 klik per kuisioner** (selamanya): `UNIQUE (questionnaire_id, user_id)`.
- **1 kunjungan per tombol/pegawai/hari**: UNIQUE INDEX `uq_visit_daily` pada `(COALESCE(application_link_id,-1), user_id, visit_date)` — via raw `DB::statement`. Backend & Frontend aplikasi sama di hari sama = 2 kunjungan; tombol sama 2x sehari = 1.
- Tabel event (`application_visits`, `questionnaire_responses`, `activity_logs`) tanpa `created_at`/`updated_at` → model `$timestamps = false`.
- Statistik partisipasi = jumlah `questionnaire_responses` per kuisioner + persentase terhadap pegawai aktif + rekap per OPD.

---

## 6. Struktur Proyek

```
sistem_e-office_kerjapraktek_dinkominfopwt/
├── README.md            ← file ini (konteks utama untuk manusia & AI)
├── ROADMAP.md           ← rencana harian 8 Juli – 7 Agustus 2026
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

Lihat **ROADMAP.md** untuk rencana harian. Ringkasan fase:

| Fase | Periode | Target | Status |
|---|---|---|---|
| 0 — Analisis & desain | 8–10 Jul | ERD v2.1, KF final, mockup, stack final, repo siap | ✅ selesai |
| 1 — Fondasi | 13–17 Jul | Login → dashboard + grid aplikasi dari DB | 🔄 sebagian besar selesai (auth, RBAC 403, dashboard data-driven) |
| 2 — Fitur inti | 21–25 Jul | Admin panel + kuisioner & statistik penuh | ⏳ |
| 3 — Integrasi & UAT | 28–31 Jul | Deploy, UAT, **sistem selesai 31 Juli** | ⏳ |
| 4 — Laporan | 1–7 Agu | Laporan KP final & serah terima | ⏳ |

---

## 9. Menyiapkan Lingkungan Lokal

Proyek ini memakai PostgreSQL. Migration domain tidak kompatibel dengan SQLite
karena menggunakan `CHECK` constraint dan expression index PostgreSQL.

1. Buat dua database kosong pada server PostgreSQL:
   - `sistem_eoffice` untuk development.
   - `sistem_eoffice_test` untuk automated test.
2. Pastikan keduanya dimiliki oleh atau dapat dikelola penuh oleh user yang akan
   diisi pada `DB_USERNAME`. Pembuatan database dilakukan lewat akun administrator
   PostgreSQL; migration tetap menjadi satu-satunya pembentuk tabel aplikasi.
3. Salin `.env.example` menjadi `.env`, lalu isi kredensial PostgreSQL lokal.
4. Jalankan setup pada database development yang masih kosong:

```bash
composer run setup
```

Perintah tersebut memasang dependensi, membuat application key, menjalankan
migration + seeder, dan membangun aset frontend.

Automated test selalu memakai `sistem_eoffice_test` melalui `phpunit.xml`:

```bash
php artisan test
```

`tests/TestCase.php` memiliki safety guard: `migrate:fresh` hanya boleh berjalan
pada koneksi PostgreSQL dengan nama database berakhiran `_test`. Jangan mengubah
`DB_DATABASE` test menjadi database development atau production.
