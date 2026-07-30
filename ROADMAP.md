# ROADMAP — Rebuild E-Office Banyumas

**Periode KP:** 7 Juli 2026 — 7 Agustus 2026 (Dinkominfo Kabupaten Banyumas, **Bidang Aplikasi Informatika**)
**Dasar:** Surat Dinkominfo Kabupaten Banyumas No. **400.14.5.4/2562/VI/2026** tanggal 15 Juni 2026, hal *Jawaban Permohonan Praktek Kerja Lapangan*
**Target sistem selesai:** **31 Juli 2026** (sebelum Agustus)
**Minggu terakhir (1–7 Agustus):** finalisasi laporan, dokumentasi, dan serah terima.

> **Catatan hari pertama.** Periode resmi pada surat dimulai **Selasa 7 Juli 2026**, tetapi proyek belum diserahkan kepada kami pada hari itu sehingga belum ada pekerjaan yang dikerjakan. Pengerjaan baru dimulai **Rabu 8 Juli 2026**, dan Fase 0 karena itu dihitung mulai tanggal tersebut.

**Tim & Judul KP (terkunci):**
| Inisial | Nama | NIM | Judul KP / Fokus |
|---|---|---|---|
| **HNR** | Hafizh Naufal Raditya | H1D024061 | *Perancangan dan Implementasi Modul Autentikasi dan Kontrol Akses Aplikasi pada Pembangunan Ulang Sistem E-Office Dinkominfo Kabupaten Banyumas* |
| **MAU** | Muhammad Abu Umar | H1D024084 | *Perancangan dan Implementasi Modul Dashboard Portal dan Kuisioner Partisipasi Pengguna pada Pembangunan Ulang Sistem E-Office Dinkominfo Kabupaten Banyumas* |
| **Bersama** | — | — | Analisis, desain database, integrasi, testing, deployment |

**Stack:** Laravel 13 (PHP 8.4) · **PostgreSQL 18** · Blade + Tailwind + Alpine.js

> **Prinsip kerja:** laporan KP dicicil setiap minggu (kolom "Laporan" di tiap fase), bukan ditunda ke akhir. Setiap hari kerja ditutup dengan commit ke Git + update progres singkat.

**Arti penanda status:**

| Penanda | Arti |
|---|---|
| ✅ | Selesai **dan terverifikasi di repo** — ada berkas, controller, atau test yang membuktikannya |
| 🔄 | Sebagian selesai |
| ⏳ | Belum dikerjakan |
| 🔍 | **Tidak terlihat dari kode** — kegiatan di luar repo (demo internal, presentasi, UAT, uji manual, status penulisan laporan). Status sebenarnya hanya diketahui HNR/MAU, jadi sengaja **tidak** diberi centang di sini |

---

## Fase 0 — Kick-off & Analisis (Rabu 8 Juli – Jumat 10 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Rab, 8 Jul** | Inventarisasi fitur login, sesi, dan alur SSO dari screenshot | Inventarisasi fitur dashboard, popup, profil dari screenshot | Setup repo GitHub, struktur folder, konfirmasi ke pembimbing lapangan: framework yang boleh dipakai, akses server/DB lama, format kuisioner yang diinginkan |
| **Kam, 9 Jul** | Draf kebutuhan fungsional modul auth + RBAC (daftar role & aturan akses) | Draf kebutuhan fungsional dashboard + kuisioner (alur popup, data yang dihitung) | Finalisasi tech stack; buat ERD bersama (users, roles, aplikasi, akses, kuisioner, respon) |
| **Jum, 10 Jul** | ✅ Review ERD sisi auth/akses; mockup halaman login & manajemen akses | ✅ Review ERD sisi kuisioner; mockup dashboard & popup | 🔍 Presentasi rencana ke pembimbing lapangan; 🔍 **Laporan: Bab 1 (Pendahuluan) selesai draf** |

**Deliverable Fase 0:** dokumen kebutuhan, ERD final, mockup, repo siap, stack disepakati.

---

## Fase 1 — Fondasi (Senin 13 Juli – Jumat 17 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 13 Jul** | ✅ Setup Laravel + migration 9 tabel domain (role = kolom `users.role`, tanpa tabel roles) | ✅ Setup frontend (layout utama, navbar, tema warna mengikuti identitas lama) | ✅ Seeder data dummy pegawai & aplikasi |
| **Sel, 14 Jul** | ✅ Fitur login (`nip_nik` + password), hash password, validasi Indonesia per-field | ✅ Dashboard: hero + grid launcher dari DB | — |
| **Rab, 15 Jul** | ✅ Middleware autentikasi, manajemen sesi, logout, ganti password (wajib verifikasi sandi lama) | ✅ Grid aplikasi dari DB: tab **Smart City/SPBE/Tools**, filter kategori, pencarian | — |
| **Kam, 16 Jul** | ✅ Proteksi login: rate limiting + Turnstile **aktif** (kunci terpasang; fail-closed di produksi) | ✅ Penghitung kunjungan bulan/tahun pada kartu | — |
| **Jum, 17 Jul** | ✅ Uji alur login end-to-end | 🔍 Uji tampilan responsif (desktop & mobile) | 🔍 Demo internal mingguan; 🔍 **Laporan: Bab 2 (Tinjauan Pustaka/Instansi) selesai draf** |

> **Sudah mendahului jadwal (masuk Fase 2):** RBAC penegakan server **403** pada `/launch/{slug}` ✅ · activity log ✅ · pencatatan kunjungan idempoten 1×/tombol/pegawai/hari ✅

**Deliverable Fase 1:** pengguna bisa login → melihat dashboard dengan grid aplikasi dari DB.

---

## Fase 2 — Fitur Inti (Senin 20 Juli – Jumat 24 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 20 Jul** | Migration & model sudah ada ✅ → lanjut: halaman admin manajemen **aplikasi & link** | Migration `questionnaires`/`questionnaire_responses` sudah ada ✅ → lanjut: kontrak data popup | Sepakati kontrak data kartu (applications + links + `can_access`) |
| **Sel, 21 Jul** | CRUD admin: penetapan **hak akses per pegawai** (`application_access`) | **Popup kuisioner** muncul setelah login (aktif + dalam periode + belum klik) | — |
| **Rab, 22 Jul** | Penanda akses di grid + blokir server **403** — sudah ✅; lanjut: tolak aplikasi/link `is_active=false` | Pencatatan **klik** kuisioner ke DB, idempoten (unik per user, `ON CONFLICT DO NOTHING`) | — |
| **Kam, 23 Jul** | Halaman admin manajemen **user** + viewer activity log | Halaman statistik partisipasi: total, persentase, **rekap per OPD**, daftar sudah/belum klik | — |
| **Jum, 24 Jul** | ✅ Uji RBAC lintas peran (admin vs pegawai beda OPD) | ✅ Uji kuisioner: klik terhitung sekali per user, statistik akurat | ✅ Integrasi kedua modul; 🔍 demo internal; 🔍 **Laporan: Bab 3 (Metodologi) selesai draf** |

> **Tercatat menyusul — dikerjakan pada fase ini tetapi belum masuk rencana harian awal:**
>
> | Pekerjaan | Pemilik | Bukti di repo |
> |---|---|---|
> | ✅ Manajemen OPD (CRUD master data) | **HNR** | `Admin/OpdController.php` · `AdminOpdCrudTest` |
> | ✅ Manajemen ikon aplikasi (unggah / URL / path) | **HNR** | `Admin/ApplicationController.php` (`resolveIconPath`) |
> | ✅ Halaman **Ringkasan** panel admin | **HNR** | `Admin/OverviewController.php` · `AdminOverviewTest` |
> | ✅ Kebijakan **nonaktif, bukan hapus** | **HNR** | Tanpa route `destroy` pada pengguna, aplikasi, tautan, OPD, dan kategori — dipensiunkan lewat `is_active = false`. **Banner dan kuisioner (modul MAU) masih memiliki penghapusan permanen** (`banners.destroy`, `questionnaires.destroy`) |

**Deliverable Fase 2:** RBAC + halaman admin berfungsi penuh; popup kuisioner dengan penghitung partisipasi & rekap per OPD berfungsi penuh.

---

## Fase 3 — Integrasi, Testing & Selesai (Senin 27 Juli – Jumat 31 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 27 Jul** | ✅ Bugfix hasil integrasi sisi auth/akses | ✅ Bugfix sisi dashboard/kuisioner + polish UI | ✅ Uji integrasi menyeluruh (178 test lolos) |
| **Sel, 28 Jul** | ✅ Blackbox testing modul auth & RBAC (tabel skenario untuk laporan) | ⏳ Blackbox testing modul dashboard & kuisioner (tabel skenario untuk laporan) | — |
| **Rab, 29 Jul** | ✅ Perbaikan hasil testing | ⏳ Perbaikan hasil testing | ✅ Persiapan deployment (perintah admin pertama, Turnstile fail-closed, sesi produksi, reverse proxy) |
| **Kam, 30 Jul** | — | — | ⏳ **Deployment + UAT bersama pembimbing lapangan**; catat masukan |
| **Jum, 31 Jul** | — | — | ⏳ Perbaikan minor hasil UAT → **SISTEM DINYATAKAN SELESAI** 🎉; 🔍 screenshot semua halaman baru untuk lampiran laporan |

> **Tercatat menyusul — permintaan tambahan pembimbing lapangan & kesiapan rilis:**
>
> | Pekerjaan | Pemilik | Bukti di repo |
> |---|---|---|
> | ✅ **Manajemen kategori** (PR #17) — kategori menjadi entitas tersendiri, relasi banyak-ke-banyak dengan aplikasi, dapat diaktifkan/dinonaktifkan. Kategori nonaktif menghilangkan filternya dari dashboard tanpa menyembunyikan aplikasinya | **MAU** | `Admin/CategoryController.php` · `Models/Category.php` · migration `..._000013_create_categories_and_application_category_tables` · `AdminCategoryCrudTest` |
> | ✅ **SSO Keycloak** (PR #18) — portal menjadi OIDC client dengan discovery otomatis dan verifikasi tanda tangan ID token terhadap JWKS. Jalur **kedua** di samping login NIP/NIK; login lama tidak berubah | **HNR** | `KeycloakController.php` · `Services/KeycloakOidcService.php` · migration `..._000014_add_keycloak_id_to_users_table` · `KeycloakSsoLoginTest` (23) · `KeycloakLoginButtonTest` (5) |
> | ✅ **Kesiapan deployment** — perintah pembuat admin pertama, Turnstile fail-closed di produksi, konfigurasi sesi produksi & reverse proxy | **HNR** | `Console/Commands/CreateAdminUser.php` · `CreateAdminCommandTest` · `TurnstileFailClosedTest` · `trustProxies` di `bootstrap/app.php` · `SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT`/`TRUSTED_PROXIES` di `.env.example` |
> | ✅ Blackbox testing modul **auth, RBAC, dan SSO** (57 skenario) | **HNR** | `docs/testing/blackbox-testing.md` |
> | ✅ **Aplikasi peraga SSO** (PR #20–22) — client kedua pada realm yang sama, membuktikan "cukup sekali login" secara nyata | **MAU** (aplikasi) / **HNR** (penempatan & dokumentasi) | `demo-sso/` · entri seeder `Demo SSO` · README §11 |
> | ⏳ Blackbox testing modul **dashboard & kuisioner** | **MAU** | Belum ada tabelnya di `docs/testing/blackbox-testing.md` |
>
> **Perbaikan menyusul setelah pengujian nyata bersama Keycloak instansi:**
>
> | Perbaikan | Pemilik | Bukti di repo |
> |---|---|---|
> | ✅ Pencocokan `preferred_username` → `nip_nik` **tanpa peka huruf besar-kecil** (PR #19). Keycloak menyimpan username dalam huruf kecil, sehingga akun ber-NIP huruf besar selalu ditolak | **HNR** | `KeycloakController.php` (`lower(nip_nik) = lower(?)`) · 4 test pada `KeycloakSsoLoginTest` |
> | ✅ **Pendaratan berbasis peran dari akar situs** (PR #20/21). Akar selalu mengarah ke `/dashboard` sehingga mencatat tujuan semu; admin mendarat di portal, bukan panel | **HNR** | `routes/web.php` · 5 test pada `LoginRedirectTest` · skenario AUT-17/18 |
> | ✅ Akun admin diselaraskan ke `nip_nik` **`admin`** agar cocok dengan username Keycloak instansi (PR #20/21) | **HNR** | `EofficeV21Seeder.php` · 35 rujukan pada 19 berkas test |
> | ✅ `APP_TIMEZONE` didokumentasikan di `.env.example`. Tanpa itu Laravel jatuh ke UTC dan seluruh stempel waktu meleset 7 jam **tanpa satu pun pesan error** | **HNR** | `.env.example` |
> | 🔄 **Single Logout (SLO) back-channel** — permintaan pembimbing lapangan. Analisis selesai; menunggu verifikasi klaim `sid` lewat satu kali login nyata | **HNR** | belum ada kode; rencana pada `feat/keycloak-backchannel-logout` |

**Deliverable Fase 3:** sistem ter-deploy, lolos UAT, dokumentasi teknis lengkap.

**Status Fase 3: 🔄 berjalan.** Pekerjaan fitur dan pengujian otomatis sudah selesai
(**238 test lolos, 1156 assertion**), tetapi **deployment nyata dan UAT belum dilakukan**,
sehingga fase ini belum dapat dinyatakan selesai. Deployment akan ditempuh lewat
**Docker** (lihat Sisa Pekerjaan butir 1); berkas kontainernya sudah ada di repo
tetapi **belum pernah dibangun** — `docker compose build` masih harus dijalankan
dan berhasil lebih dulu.

---

## Fase 4 — Laporan & Penutupan (Sabtu 1 Agustus – Jumat 7 Agustus)

| Tanggal | Kegiatan (keduanya, masing-masing untuk laporannya sendiri) |
|---|---|
| **1–2 Agu** | Bab 4 (Hasil & Pembahasan): masing-masing menulis modulnya sendiri, lengkap dengan screenshot & tabel pengujian |
| **Sen, 3 Agu** | Bab 5 (Penutup) + abstrak + lampiran |
| **Sel, 4 Agu** | Tukar laporan: Hafizh mereview laporan Umar dan sebaliknya |
| **Rab, 5 Agu** | Revisi hasil review + konsultasi pembimbing |
| **Kam, 6 Agu** | Finalisasi laporan, minta tanda tangan/nilai pembimbing lapangan |
| **Jum, 7 Agu** | **Hari terakhir KP:** serah terima sistem + dokumentasi ke Dinkominfo, perpisahan |

---

## Status Fase — berdasarkan bukti di repo

Status di bawah ditentukan dari **berkas, controller, migration, dan test yang benar-benar
ada di repo**, bukan dari rencana. Kegiatan yang tidak meninggalkan jejak di repo ditandai
🔍 dan sengaja tidak diberi status selesai.

| Fase | Status | Dasar penilaian |
|---|---|---|
| **0 — Analisis & desain** | ✅ Selesai | `ERD/ERD_v2.1_final.png`, `ERD/ERD_v2.md`, `ERD/schema.sql`, `ERD/KF_AUTH_RBAC.md`, `ERD/KF_DASHBOARD_KUISIONER.md`, `Mockup/mockup_dashboard_v2.html`, `readme/inventaris_*.md` |
| **1 — Fondasi** | ✅ Selesai | Login `nip_nik`, sesi, ubah sandi, rate limiting, Turnstile aktif, dashboard grid dari DB, penghitung kunjungan — seluruhnya tertutup test |
| **2 — Fitur inti** | ✅ Selesai | Panel admin (akses, aplikasi, tautan, pengguna, OPD, kategori, banner, kuisioner, log, ringkasan), RBAC 403, activity log, statistik partisipasi |
| **3 — Integrasi & UAT** | 🔄 **Berjalan** | Fitur & pengujian otomatis selesai (kategori, SSO Keycloak, aplikasi peraga, single logout back-channel, berkas kontainer, **238 test**). **Deployment nyata dan UAT belum dilakukan; berkas Docker belum pernah dibangun** |
| **4 — Laporan & penutupan** | ⏳ Belum | Tidak ada artefak laporan di repo |

**Ditandai 🔍 — tidak terlihat dari kode, status sebenarnya hanya diketahui HNR/MAU:**
presentasi ke pembimbing lapangan (Fase 0) · demo internal mingguan (Fase 1 & 2) ·
status penulisan Bab 1/2/3 · uji tampilan responsif (Fase 1, MAU) ·
UAT bersama pembimbing (Fase 3) · pengambilan screenshot untuk lampiran.

---

## Sisa Pekerjaan sampai Akhir KP

**Menghalangi serah terima** — tanpa ini sistem tidak dapat dinyatakan selesai:

| # | Pekerjaan | Pemilik | Catatan |
|---|---|---|---|
| 1 | **Deployment lewat Docker** | Bersama | Diputuskan memakai Docker agar lingkungan produksi dapat direproduksi dan tidak bergantung pada pemasangan manual PHP/PostgreSQL di server. Kesiapan aplikasinya sudah ada — `eoffice:create-admin`, Turnstile fail-closed, `SESSION_SECURE_COOKIE`, `TrustProxies`; yang belum adalah berkas Docker dan deployment nyatanya |
| 2 | **Bagian Deployment pada README** | **HNR** | Prosedur produksi (`migrate --force` **tanpa** `--seed` + `eoffice:create-admin`) saat ini hanya tertulis di §11.3, terkubur di bagian aplikasi peraga. Risiko terbesar: seeder membuat akun admin bersandi `password`; bila `--seed` terlanjur dijalankan di produksi, server langsung memiliki administrator bersandi yang tertulis di repo publik |
| 3 | **UAT bersama pembimbing lapangan** | Bersama | Prasyarat penetapan "sistem selesai" |
| 4 | **Tabel blackbox modul dashboard & kuisioner** | **MAU** | `docs/testing/blackbox-testing.md` baru memuat AUT (18), RBAC (14), dan SSO (18+5) — seluruhnya modul HNR |
| 5 | **Laporan Bab 1–5 masing-masing** + abstrak & lampiran | Masing-masing | 🔍 |
| 6 | **Serah terima sistem & dokumentasi** ke Dinkominfo | Bersama | Hari terakhir KP |

**Baik untuk ada** — tidak menghalangi serah terima:

| # | Pekerjaan | Pemilik | Catatan |
|---|---|---|---|
| 7 | **Single Logout (SLO) back-channel** | **HNR** | Permintaan pembimbing lapangan. Analisis selesai: `sid` sebagai penentu, `sub` sebagai penyaring, disimpan di payload sesi sehingga **tanpa perubahan skema**. Terhenti menunggu verifikasi bahwa Keycloak menyertakan `sid` pada ID token. Catatan: SLO **tidak dapat diuji ujung-ke-ujung dari lokal** karena Keycloak instansi tidak dapat menjangkau `127.0.0.1`; pembuktian ditempuh lewat test otomatis |
| 8 | **Uji tampilan responsif** (desktop & mobile) | **MAU** | 🔍 Tidak terlihat dari kode |
| 9 | **Screenshot seluruh halaman** untuk lampiran laporan | Masing-masing | 🔍 |
| 10 | **Catatan `npm run build` setelah `git pull`** di README | **HNR** | Aset frontend yang tertinggal membuat tampilan menyimpang **tanpa pesan error** — pernah terjadi pada chip kategori dashboard |
| 11 | **Cabut pin sementara `symfony/*` `7.4.*`** dari `composer.json` | **HNR** | Pasca-KP, saat peningkatan ke Symfony 8 dikerjakan sebagai pekerjaan tersendiri; alasannya terdokumentasi di README §10.6 |

---

## Aturan Main Tim

1. **Daily sync 15 menit** tiap pagi: apa yang dikerjakan kemarin, hari ini, dan blocker.
2. **Git workflow:** branch `main` (stabil), `dev` (integrasi), `feat/nama-fitur` per pekerjaan. Merge lewat pull request yang direview partner. **Selalu `git pull` sebelum mulai kerja, commit + push setelah selesai.**
3. **Definisi "selesai":** fitur berjalan + sudah diuji + sudah di-merge + tercatat untuk bahan laporan.
4. **Buffer:** jika ada hari libur/kegiatan kantor, geser tugas hari itu, jangan geser deadline fase.
5. **Titik kritis integrasi:** `applications` + `application_links` + `application_access` (HNR) adalah sumber data grid dashboard (MAU) — jangan ubah skemanya tanpa diskusi.
6. **Migration = satu-satunya sumber kebenaran struktur DB.** Jangan menambal database langsung via SQL/`psql`.
7. **Pembagian per modul, bukan per lapisan.** Jangan mengerjakan modul partner tanpa koordinasi (riwayat kontribusi ikut jadi bahan laporan individual).
