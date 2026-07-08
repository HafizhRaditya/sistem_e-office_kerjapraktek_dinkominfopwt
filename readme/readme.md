# E-Office Banyumas — Rebuild Project

> **Dokumen ini adalah sumber konteks utama proyek.** Jika Anda adalah AI assistant (Claude, Gemini, ChatGPT, Antigravity, Copilot, dll.) yang diminta membantu proyek ini, baca file ini terlebih dahulu sebelum menulis kode atau memberi saran. Patuhi konvensi, skema, dan pembagian tugas di bawah.
>
> **Versi 1.1 — 8 Juli 2026.** Perubahan: database ke PostgreSQL; tabel fitur sistem lama dikoreksi berdasarkan inventarisasi detail (lihat `docs/inventaris/`).

**Repository:** https://github.com/HafizhRaditya/sistem_e-office_kerjapraktek_dinkominfopwt

---

## 1. Ringkasan Proyek

**E-Office Banyumas** (`eoffice.banyumaskab.go.id`) adalah portal Single Sign-On (SSO) milik Pemerintah Kabupaten Banyumas — pintu masuk tunggal bagi user OPD ke **131 aplikasi** dinas/pemerintahan (64 aktif, 67 tidak aktif) dengan tagline "cukup sekali login untuk semua aplikasi".

Proyek ini adalah **pembangunan ulang (rebuild)** sistem tersebut dalam rangka **Kerja Praktik (KP)** mahasiswa Informatika Universitas Jenderal Soedirman di **Dinkominfo Kabupaten Banyumas**, periode **8 Juli – 7 Agustus 2026**. Target sistem selesai: **31 Juli 2026**.

### Alasan rebuild
Sistem lama berjalan di **PHP 5.5.33** (end-of-life sejak 2016), Nginx 1.10.2, Bootstrap, tanpa framework modern. Perlu diganti dengan stack yang aktif didukung.

### Ruang lingkup (permintaan resmi dari Dinkominfo)
1. **Rebuild sistem** — migrasi ke bahasa/framework baru, perbarui tampilan dan keamanan.
2. **Popup kuisioner** — popup yang selama ini hanya menampilkan banner sambutan/pengumuman dikembangkan menjadi popup kuisioner; **hitung dan tampilkan** jumlah user yang sudah mengeklik/mengisi kuisioner.
3. **Pembatasan hak akses aplikasi (RBAC)** — saat ini semua user melihat seluruh 131 aplikasi; ke depan user yang tidak punya hak akses ke suatu aplikasi **tidak melihat** aplikasi tersebut di dashboard (dan diblokir di level route/server, bukan hanya disembunyikan di frontend).

### Di luar ruang lingkup (out of scope)
- Membangun ulang aplikasi-aplikasi tujuan (SIMPUS, E-Monev, dll.) — kita hanya membangun **portalnya**.
- Integrasi SSO nyata ke aplikasi eksternal (cukup disimulasikan dengan tautan keluar).
- Migrasi data produksi (gunakan data dummy/seeder yang menyerupai struktur asli).

---

## 2. Tim & Pembagian Tugas

| Nama | NIM | Role | Tanggung jawab |
|---|---|---|---|
| **Hafizh Naufal Raditya (HNR)** | H1D024061 | Fullstack — Auth & Access Control | Login (NIP/NIK), sesi, ganti password, proteksi login (CAPTCHA + rate limit), **RBAC**: model role, hak akses aplikasi per role/user, filter akses, halaman admin manajemen user/role/aplikasi, activity log |
| **Muhammad Abu Umar (MAU)** | H1D024084 | Fullstack — Portal & Kuisioner | Layout, dark/light mode, dashboard (hero, aplikasi terpopuler, tab, filter kategori, pencarian, grid kartu aplikasi, penghitung kunjungan), **popup kuisioner** + pencatatan partisipasi + halaman statistik partisipasi |

Pekerjaan bersama: analisis, ERD, integrasi, testing, deployment. Kontrak data paling kritis: tabel `applications` + pivot hak akses (milik HNR) adalah sumber data grid dashboard (milik MAU).

Inventarisasi fitur lengkap per modul: `docs/inventaris/INVENTARIS_HNR_AUTH.md` dan `docs/inventaris/INVENTARIS_MAU_DASHBOARD.md`.

---

## 3. Fitur Sistem Lama (hasil inventarisasi 30 screenshot — 8 Juli 2026)

| # | Halaman/Fitur | Keterangan | Bukti |
|---|---|---|---|
| 1 | Login | Username "NIP atau NIK" + kata sandi (toggle lihat) + Cloudflare Turnstile, tautan Lupa Password; error: kredensial salah, field kosong, "aktivitas mencurigakan terdeteksi" | ss_28, ss_29 |
| 2 | Dashboard `/site` | Hero + CTA "Mulai Eksplorasi Aplikasi"; seksi "Aplikasi Paling Sering Diakses" (5 teratas); tab Smart City (123) / SPBE (26) / Tools (6) / Aplikasi Baru; filter status Semua (131)/Aktif (64)/Tidak Aktif (67); filter 11 kategori berwarna (Governance, Economy, Kinerja, Gawai, Rencana, Uang, Pajak, Kesehatan, Data, Wisata, Umum); search bar | ss_01, ss_05, ss_12, ss_23 |
| 3 | Kartu aplikasi | Label OPD pemilik, ikon, nama, 1–3 tombol tautan (BACKEND/FRONTEND/varian V2 dll.), Status AKTIF, penghitung "Pengunjung bulan ini / tahun ini" | ss_02, ss_08–ss_22 |
| 4 | Popup setelah login | Modal banner sambutan bergambar + tombol tutup ✕; hanya informasi, tanpa pencatatan interaksi → **dikembangkan menjadi popup kuisioner** | ss_27 |
| 5 | Dark/Light mode | Toggle di navbar, tema penuh untuk seluruh halaman | ss_05–ss_23 |
| 6 | Menu pengguna | Tombol nama+gelar di navbar → dropdown: Ubah Sandi, Logout | ss_04, ss_08 |
| 7 | Ubah password `/ubahsandi` | Kata sandi baru + ulangi + Google reCAPTCHA; **tanpa verifikasi sandi lama** (celah keamanan, wajib diperbaiki) | ss_25, ss_26 |

**Catatan koreksi v1.1:** klaim versi sebelumnya tentang "Aplikasi Wajib/Pilihan", halaman profil biodata pegawai, riwayat aktivitas, dan statistik ±20.000 pengguna **tidak terbukti di screenshot** dan telah dihapus. Halaman profil tidak ditemukan di sistem lama (perlu dikonfirmasi ke pembimbing; jika dibuat, statusnya "peningkatan").

---

## 4. Tech Stack (baru)

| Lapisan | Teknologi | Catatan |
|---|---|---|
| Backend | **Laravel 13 (PHP 8.4)** | Migrasi natural dari PHP lama; tim Dinkominfo familiar PHP |
| Frontend | **Blade + Tailwind CSS + Alpine.js** | Sederhana, cepat, cukup untuk portal; wajib dukung dark/light mode |
| Database | **PostgreSQL 17** | Keputusan tim 8 Juli 2026 |
| Auth | Laravel session-based + middleware custom RBAC | Bisa memakai `spatie/laravel-permission` |
| Proteksi login | Rate limiting Laravel + CAPTCHA (Cloudflare Turnstile, satu penyedia untuk login & ubah sandi) | |
| Versi kontrol | Git + GitHub, branch `main` / `dev` / `feat/*` | PR wajib direview partner |

> Jika pembimbing lapangan mensyaratkan stack lain, perbarui tabel ini terlebih dahulu sebelum menulis kode.

---

## 5. Skema Database (ringkas — ERD lengkap di `docs/erd/`)

```
users                  : id, nip (unik), nik (unik, nullable), name, title (gelar), opd_id,
                         email, phone, photo, password, is_active, last_login_at, timestamps
opds                   : id, code (mis. DINKES, SETDA), name
roles                  : id, name, description
role_user              : user_id, role_id
applications           : id, opd_id, name, slug, description, icon, group (smartcity|spbe|tools),
                         category (governance|economy|...), is_active, is_new, sort_order
application_links      : id, application_id, label (BACKEND|FRONTEND|dll), url   -- satu app bisa >1 tautan
application_visits     : id, application_id, user_id, visited_at                 -- sumber penghitung bulan/tahun
application_role       : application_id, role_id          -- hak akses per role
application_user       : application_id, user_id          -- override hak akses per user (opsional)
questionnaires         : id, title, description, image, target_url, is_active,
                         starts_at, ends_at, created_by
questionnaire_responses: id, questionnaire_id, user_id (unik per kuisioner), clicked_at
activity_logs          : id, user_id, action, ip_address, user_agent, created_at
```

**Aturan bisnis penting:**
- Grid dashboard hanya merender aplikasi yang boleh diakses user (union dari `application_role` via role user + `application_user`). Route/akses server juga diproteksi middleware — bukan sekadar sembunyi di UI.
- Satu user dihitung **sekali** per kuisioner (unique constraint `questionnaire_id + user_id`).
- Statistik partisipasi = jumlah `questionnaire_responses` per kuisioner + persentase terhadap user aktif.
- Penghitung "pengunjung bulan/tahun ini" dihitung dari `application_visits` (paritas fitur lama).

---

## 6. Struktur Proyek

```
sistem_e-office_kerjapraktek_dinkominfopwt/
├── README.md            ← file ini (konteks utama untuk manusia & AI)
├── ROADMAP.md           ← rencana harian 8 Juli – 7 Agustus 2026
├── docs/
│   ├── screenshots/     ← 30 screenshot sistem lama (ss_01–ss_30)
│   ├── inventaris/      ← inventarisasi fitur per modul (HNR & MAU)
│   ├── erd/             ← diagram ERD
│   └── testing/         ← tabel skenario blackbox testing (bahan laporan)
└── src/                 ← proyek Laravel
```

---

## 7. Konvensi untuk Kontributor (termasuk AI Assistant)

1. **Bahasa:** komentar kode & nama variabel berbahasa **Inggris**; teks UI berbahasa **Indonesia** (pengguna adalah ASN) — termasuk pesan error (sistem lama mencampur Inggris/Indonesia; jangan diulangi).
2. **Jangan mengubah skema database** pada Bagian 5 tanpa persetujuan kedua anggota tim — grid dashboard dan RBAC saling bergantung padanya.
3. **Keamanan minimum:** password di-hash (bcrypt/argon2), semua form ber-CSRF token, input tervalidasi server-side, query lewat Eloquent/parameter binding, route sensitif di belakang middleware auth + cek akses, ubah password wajib verifikasi sandi lama.
4. **Data:** jangan pernah memakai data ASN asli dalam pengembangan — gunakan seeder dummy.
5. **Setiap fitur baru** harus: berjalan, teruji manual, di-PR ke `dev`, dan dicatat sebagai bahan laporan KP.
6. Saat membantu, **sebutkan file/tabel yang Anda ubah** dan jelaskan dampaknya ke modul partner (HNR ↔ MAU).

---

## 8. Status & Timeline

Lihat **ROADMAP.md** untuk rencana harian. Ringkasan fase:

| Fase | Periode | Target | Status |
|---|---|---|---|
| 0 — Analisis & desain | 8–10 Jul | ERD, mockup, stack final, repo siap | 🔄 Berjalan — inventarisasi fitur selesai 8 Jul |
| 1 — Fondasi | 13–17 Jul | Login → dashboard + grid aplikasi dari DB | ⏳ |
| 2 — Fitur inti | 20–24 Jul | RBAC penuh + kuisioner & statistik penuh | ⏳ |
| 3 — Integrasi & UAT | 27–31 Jul | Deploy, UAT, **sistem selesai 31 Juli** | ⏳ |
| 4 — Laporan | 1–7 Agu | Laporan KP final & serah terima | ⏳ |
