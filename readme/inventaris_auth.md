# Inventarisasi Fitur — Modul Autentikasi, Sesi & Alur SSO

**Penyusun:** Hafizh Naufal Raditya (H1D024061)
**Tanggal:** Rabu, 8 Juli 2026 — Fase 0, Hari 1
**Sumber:** 30 screenshot sistem lama E-Office Banyumas (`docs/screenshots/ss_01–ss_30`) + Wappalyzer
**Repo:** https://github.com/HafizhRaditya/sistem_e-office_kerjapraktek_dinkominfopwt

---

## 1. Halaman Login (`eoffice.banyumaskab.go.id`) — ss_28, ss_29

Layout dua panel: kiri branding, kanan form.

### Panel kiri (branding)
| Elemen | Detail |
|---|---|
| Logo | "EOB — E-OFFICE banyumas" |
| Tagline | "Cukup sekali login untuk **semua aplikasi**." (kata "semua aplikasi" beraksen oranye) |
| Deskripsi | "Portal E-Office Kabupaten Banyumas mempermudah akses portal satu pintu untuk seluruh layanan pemerintah." |
| Latar | Merah khas identitas (di luar kartu putih) |

### Panel kanan (form Sign In)
| Elemen | Detail |
|---|---|
| Judul | "Sign In" + subteks "Silakan masuk untuk melanjutkan tugas Anda" |
| Field 1 | **Username** — placeholder "**NIP atau NIK**", ikon orang → login menerima dua jenis identitas |
| Field 2 | **Kata Sandi** — ikon gembok + **toggle lihat/sembunyikan password** (ikon mata) |
| CAPTCHA | **Cloudflare Turnstile** (widget dengan status "Success!") |
| Tombol | "**MASUK SEKARANG**" (merah, lebar penuh) |
| Tautan | "**Lupa Password?**" di bawah tombol |
| Footer | "© 2026 Dinkominfo Kabupaten Banyumas" |

### State error yang terdokumentasi
| Screenshot | Pesan | Pemicu |
|---|---|---|
| ss_28 | "Username atau Password salah." | Kredensial tidak cocok |
| ss_28 | "Username cannot be blank." / "Password cannot be blank." | Field kosong (validasi server, bahasa Inggris — **tidak konsisten**, perlu diseragamkan ke Indonesia di sistem baru) |
| ss_29 | "Verifikasi gagal. Aktivitas mencurigakan terdeteksi." | Turnstile/anti-bot menolak — indikasi ada deteksi aktivitas mencurigakan di sisi server |

**Catatan:** semua pesan error tampil sekaligus dalam blok merah di atas tombol; error tidak melekat per field.

---

## 2. Ubah Password (`/ubahsandi`) — ss_25, ss_26

| Elemen | Detail |
|---|---|
| Akses | Dropdown nama user di navbar → "Ubah Sandi" |
| Layout | Kartu putih di tengah, latar merah penuh |
| Tautan kembali | "← Kembali ke Dashboard" di atas kartu |
| Field 1 | "Kata Sandi Baru" |
| Field 2 | "Ulangi Kata Sandi Baru" |
| CAPTCHA | **Google reCAPTCHA v2** ("I'm not a robot") — **berbeda dengan login yang memakai Turnstile**; sistem baru sebaiknya diseragamkan satu penyedia |
| Tombol | "SIMPAN PERUBAHAN" (merah) |
| Validasi | Tooltip browser HTML5 "Please fill out this field" (ss_26) → mengandalkan validasi native `required` |
| Kelemahan | **Tidak ada field "kata sandi lama"** — perubahan password tanpa konfirmasi sandi lama adalah celah keamanan (session hijacking → ganti password). Sistem baru wajib menambahkannya. Tidak terlihat pula indikator kekuatan password / aturan minimal karakter. |

---

## 3. Sesi & Menu Pengguna — ss_04, ss_05, ss_08, ss_24

| Elemen | Detail |
|---|---|
| Indikator sesi | Nama pengguna + gelar tampil sebagai tombol biru di navbar ("ADI NUGROHO, S.Kom.") di semua halaman |
| Dropdown | 2 item: **Ubah Sandi** (ikon gembok), **Logout** (ikon keluar) |
| Proteksi halaman | Dashboard `/site` hanya bisa diakses setelah login (semua screenshot dashboard dalam keadaan login) |
| Durasi sesi | Tidak terlihat dari screenshot — **perlu konfirmasi** ke pembimbing (timeout? remember me tidak ada di form login) |

---

## 4. Alur SSO / Peluncuran Aplikasi — ss_01, ss_02, ss_08–ss_22

Fungsi SSO portal: setelah login sekali, user meluncurkan aplikasi tujuan dari kartu aplikasi.

| Temuan | Detail |
|---|---|
| Tombol peluncur | Tiap kartu punya 1–3 tombol tautan: "**BACKEND**", "**FRONTEND**", beberapa punya varian "**BACKEND V2**" (SIMLOG), "**BACKEND SMP**"/"**BACKEND SD**" (SPMB Online), "**FULL CYCLE**" (SIM PKB), "**FRONTEND SIMANTAP**"/"**FRONTEND SIBINTANG**" (SIMANTAP) |
| Makna | Satu aplikasi bisa punya beberapa endpoint (panel admin/backend vs tampilan publik/frontend, atau versi berbeda) |
| Status | Label "Status: **AKTIF**" per aplikasi (hijau); kategori dashboard juga mencatat "Tidak Aktif (67)" → ada aplikasi nonaktif yang disembunyikan/ditandai |
| Kontrol akses saat ini | **Semua user melihat semua aplikasi** (131 aplikasi tampil untuk satu akun) — inilah masalah yang akan diselesaikan dengan RBAC |
| Mekanisme SSO teknis | Tidak terlihat dari screenshot (token? redirect berparameter? cukup tautan biasa?) — **perlu konfirmasi** ke pembimbing lapangan |

---

## 5. Kebutuhan Fungsional Modul Auth & RBAC (draf untuk Kamis, 9 Juli)

| Kode | Kebutuhan | Asal |
|---|---|---|
| FR-A01 | User dapat login dengan NIP/NIK + kata sandi | Paritas sistem lama |
| FR-A02 | Login dilindungi CAPTCHA (Turnstile) + rate limiting | Paritas + peningkatan |
| FR-A03 | Pesan error login berbahasa Indonesia, konsisten, melekat per field | Peningkatan |
| FR-A04 | User dapat melihat/menyembunyikan input kata sandi | Paritas |
| FR-A05 | User dapat logout | Paritas |
| FR-A06 | User dapat mengubah kata sandi **dengan verifikasi sandi lama** + aturan kekuatan sandi | Peningkatan keamanan |
| FR-A07 | Halaman portal hanya dapat diakses user terautentikasi (middleware) | Paritas |
| FR-A08 | Sistem memiliki role (min.: superadmin, admin OPD, pegawai) | Baru — RBAC |
| FR-A09 | Hak akses aplikasi dapat ditetapkan per role dan/atau per user | Baru — RBAC |
| FR-A10 | User hanya melihat & dapat mengakses aplikasi sesuai haknya; pemblokiran juga di level route/server | Baru — RBAC (permintaan utama) |
| FR-A11 | Admin dapat mengelola user, role, aplikasi, dan penetapan akses (CRUD) | Baru — pendukung RBAC |
| FR-A12 | Aktivitas login tercatat (log: user, waktu, IP) | Peningkatan |

---

## 6. Pertanyaan untuk Pembimbing Lapangan (hari ini)

1. Mekanisme SSO ke aplikasi tujuan saat ini: token/redirect khusus, atau sekadar tautan? Apakah rebuild cukup menautkan keluar?
2. Sumber data user: apakah tersambung ke database kepegawaian (Simpeg/BKPSDM) atau berdiri sendiri? NIK diverifikasi ke mana?
3. Fitur "Lupa Password" alurnya seperti apa (email? melalui admin OPD?) — tidak ada screenshot-nya.
4. Role apa saja yang dibutuhkan untuk pembatasan akses? Per OPD, per jabatan, atau daftar manual per user?
5. Kebijakan sesi: timeout berapa lama? Boleh login di banyak perangkat?
