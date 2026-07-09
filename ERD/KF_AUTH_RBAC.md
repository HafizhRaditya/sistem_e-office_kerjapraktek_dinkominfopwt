# Kebutuhan Fungsional — Modul Autentikasi & RBAC

**Penyusun:** Hafizh Naufal Raditya (H1D024061) · **Status:** Draf v1.0 — 10 Juli 2026
**Dasar:** `docs/inventaris/INVENTARIS_HNR_AUTH.md`, `docs/erd/ERD.md` (v1.0), `schema.sql`
**Kode kebutuhan:** melanjutkan FR-A01…A12 dari inventarisasi, kini dirinci dengan aktor, prasyarat, alur, dan kriteria penerimaan.

---

## 1. Daftar Role

| Role | Kode | Deskripsi | Contoh pemegang |
|---|---|---|---|
| **Superadmin** | `superadmin` | Pengelola portal (Dinkominfo). Melihat & mengelola SEMUA data: user, role, OPD, aplikasi, hak akses, kuisioner, log | Admin Dinkominfo |
| **Admin OPD** | `admin_opd` | Pengelola di tingkat OPD-nya sendiri: melihat user OPD-nya, mengusulkan/mengatur akses aplikasi milik OPD-nya (lingkup terbatas `users.opd_id` yang sama) | Operator DINKES, SETDA, dsb. |
| **Pegawai** | `pegawai` | Pengguna akhir. Login → dashboard → meluncurkan aplikasi yang menjadi haknya | Seluruh ASN |

Catatan: satu user boleh multi-role (`role_user`), mis. pegawai DINKES yang juga admin OPD DINKES. Daftar role disimpan di tabel `roles` sehingga role baru dapat ditambah tanpa mengubah kode (hanya superadmin yang boleh menambah).

## 2. Aturan Akses (matriks role × kemampuan)

| Kemampuan | Superadmin | Admin OPD | Pegawai |
|---|---|---|---|
| Login / logout / ubah sandi | ✔ | ✔ | ✔ |
| Melihat dashboard & meluncurkan aplikasi sesuai hak | ✔ (semua aplikasi) | ✔ | ✔ |
| CRUD user | ✔ semua | Lihat user OPD-nya | ✖ |
| CRUD role & penetapan role ke user | ✔ | ✖ | ✖ |
| CRUD aplikasi & tautan | ✔ | ✖ (usul ke superadmin) | ✖ |
| Menetapkan hak akses aplikasi (application_role / application_user) | ✔ | ✔ terbatas aplikasi milik OPD-nya | ✖ |
| CRUD kuisioner + lihat statistik partisipasi | ✔ | ✖ | ✖ |
| Melihat activity log | ✔ | ✖ | ✖ |

**Aturan emas (AB1 di ERD):** himpunan aplikasi yang terlihat dan dapat diluncurkan user = aplikasi AKTIF yang terhubung ke role user (`application_role`) ∪ ditetapkan langsung ke user (`application_user`); `superadmin` melewati filter. Diimplementasikan SEKALI sebagai Eloquent scope `Application::accessibleBy($user)` dan dipakai oleh dashboard (MAU) dan middleware peluncuran (HNR).

## 3. Rincian Kebutuhan Fungsional

### FR-A01 — Login NIP/NIK
- **Aktor:** semua role. **Prasyarat:** akun aktif (`is_active = true`).
- **Alur:** buka `/login` → isi username (dicocokkan ke `users.nip` ATAU `users.nik`) + kata sandi → lolos Turnstile → sesi dibuat → `last_login_at` diperbarui → catat `activity_logs (login_success)` → arahkan ke dashboard.
- **Alternatif:** kredensial salah → pesan "NIP/NIK atau kata sandi salah." per form, catat `login_failed`; akun nonaktif → "Akun Anda dinonaktifkan. Hubungi admin OPD."
- **Kriteria terima:** login berhasil ≤ 3 detik; user nonaktif tidak pernah bisa masuk; percobaan gagal tercatat.

### FR-A02 — Proteksi login
Rate limiting 5 percobaan/menit per IP+username (respons 429 dengan pesan Indonesia); Cloudflare Turnstile wajib valid di sisi server. **Kriteria:** percobaan ke-6 dalam semenit ditolak sebelum verifikasi kredensial.

### FR-A03 — Pesan error konsisten
Seluruh pesan bahasa Indonesia, melekat di bawah field terkait (bukan blok gabungan seperti sistem lama). Validasi server-side selalu berjalan meski validasi HTML5 dilewati.

### FR-A04 — Toggle lihat kata sandi (ikon mata, murni frontend).

### FR-A05 — Logout: hapus sesi + regenerate token, catat `activity_logs (logout)`, arahkan ke `/login`.

### FR-A06 — Ubah kata sandi
- **Alur:** menu user → form: **kata sandi lama** (baru, perbaikan celah sistem lama), kata sandi baru, konfirmasi → validasi kekuatan (min. 8, huruf & angka) → simpan hash → invalidasi sesi lain → catat `password_changed`.
- **Kriteria:** kata sandi lama salah = ditolak; sandi baru lemah = ditolak dengan penjelasan aturan.

### FR-A07 — Middleware auth
Seluruh route selain `/login` & aset publik di belakang middleware `auth`. Sesi kedaluwarsa → redirect login dengan pesan "Sesi Anda berakhir, silakan masuk kembali."

### FR-A08–A09 — Manajemen role & hak akses
CRUD role (superadmin); penetapan hak per role dan/atau per user melalui halaman admin dengan pencarian aplikasi + toggle. Perubahan hak langsung berlaku pada permintaan berikutnya (tanpa perlu re-login).

### FR-A10 — Penegakan akses dua lapis ⭐ (permintaan utama atasan)
- **Lapis tampilan:** grid dashboard hanya merender hasil `accessibleBy($user)`.
- **Lapis server:** route peluncuran `/launch/{application:slug}/{link}` diproteksi middleware `can:launch,application`; user tanpa hak → **404** (bukan 403, agar keberadaan aplikasi tidak bocor).
- **Kriteria terima (skenario uji wajib):** (1) pegawai DINKES tidak melihat & tidak bisa menembak URL aplikasi SETDA; (2) setelah admin memberi hak, aplikasi muncul tanpa re-login; (3) superadmin melihat semua; (4) aplikasi nonaktif tidak bisa diluncurkan siapa pun kecuali superadmin melihat statusnya di admin.

### FR-A11 — Halaman admin (user, role, aplikasi, akses) — tabel berpaginasi, pencarian, zebra-striping sesuai design system.

### FR-A12 — Activity log — mencatat `login_success/login_failed/logout/password_changed/app_launched/quiz_clicked`; superadmin dapat memfilter per user/aksi/rentang tanggal.

## 4. Kebutuhan Non-Fungsional Terkait
Password bcrypt (cost default Laravel); CSRF di semua form; cookie sesi `HttpOnly + Secure + SameSite=Lax`; tidak ada data ASN asli di lingkungan dev (seeder dummy); halaman login responsif ≥ 360px.

## 5. Ketergantungan & Asumsi
Bergantung pada ERD v1.0 (tabel `users, roles, role_user, applications, application_role, application_user, activity_logs`). Asumsi A3 (tiga role dasar) dan A5 (peluncuran = tautan keluar) berlaku sampai ada jawaban pembimbing; keduanya aditif (lihat ERD §6).
