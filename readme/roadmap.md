# ROADMAP — Rebuild E-Office Banyumas

**Periode KP:** 8 Juli 2026 — 7 Agustus 2026 (Dinkominfo Kabupaten Banyumas)
**Target sistem selesai:** **31 Juli 2026** (sebelum Agustus)
**Minggu terakhir (1–7 Agustus):** finalisasi laporan, dokumentasi, dan serah terima.

**Tim:**
| Inisial | Nama | NIM | Fokus |
|---|---|---|---|
| **HNR** | Hafizh Naufal Raditya | H1D024061 | Autentikasi, RBAC & Manajemen Hak Akses Aplikasi |
| **MAU** | Muhammad Abu Umar | H1D024084 | Dashboard Portal, Popup Kuisioner & Statistik Partisipasi |
| **Bersama** | — | — | Analisis, desain database, integrasi, testing, deployment |

> **Prinsip kerja:** laporan KP dicicil setiap minggu (kolom "Laporan" di tiap fase), bukan ditunda ke akhir. Setiap hari kerja ditutup dengan commit ke Git + update progres singkat.

---

## Fase 0 — Kick-off & Analisis (Rabu 8 Juli – Jumat 10 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Rab, 8 Jul** | Inventarisasi fitur login, sesi, dan alur SSO dari screenshot | Inventarisasi fitur dashboard, popup, profil dari screenshot | Setup repo GitHub, struktur folder, konfirmasi ke pembimbing lapangan: framework yang boleh dipakai, akses server/DB lama, format kuisioner yang diinginkan |
| **Kam, 9 Jul** | Draf kebutuhan fungsional modul auth + RBAC (daftar role & aturan akses) | Draf kebutuhan fungsional dashboard + kuisioner (alur popup, data yang dihitung) | Finalisasi tech stack; buat ERD bersama (users, roles, aplikasi, akses, kuisioner, respon) |
| **Jum, 10 Jul** | Review ERD sisi auth/akses; mockup halaman login & manajemen akses | Review ERD sisi kuisioner; mockup dashboard & popup | Presentasi rencana ke pembimbing lapangan; **Laporan: Bab 1 (Pendahuluan) selesai draf** |

**Deliverable Fase 0:** dokumen kebutuhan, ERD final, mockup, repo siap, stack disepakati.

---

## Fase 1 — Fondasi (Senin 13 Juli – Jumat 17 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 13 Jul** | Setup proyek Laravel, migration `users`, `roles`, `role_user` | Setup frontend (layout utama, navbar, tema warna mengikuti identitas lama) | Seeder data dummy pegawai & aplikasi |
| **Sel, 14 Jul** | Fitur login (NIP + password), hash password, validasi | Halaman dashboard statis: kartu profil, slot statistik pengguna | — |
| **Rab, 15 Jul** | Middleware autentikasi, manajemen sesi, logout, ganti password | Komponen grid aplikasi (Wajib & Pilihan) dari data database | — |
| **Kam, 16 Jul** | Proteksi login: rate limiting + CAPTCHA (Turnstile/alternatif) | Halaman profil pegawai + edit data ringan | — |
| **Jum, 17 Jul** | Uji alur login end-to-end | Uji tampilan responsif (desktop & mobile) | Demo internal mingguan; **Laporan: Bab 2 (Tinjauan Pustaka/Instansi) selesai draf** |

**Deliverable Fase 1:** pengguna bisa login → melihat dashboard dengan grid aplikasi dari DB.

---

## Fase 2 — Fitur Inti (Senin 20 Juli – Jumat 24 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 20 Jul** | Migration & model `applications`, `application_user`/`application_role` (hak akses) | Migration & model `questionnaires`, `questionnaire_responses` | Sepakati kontrak data akses-aplikasi (dipakai grid dashboard) |
| **Sel, 21 Jul** | CRUD admin: manajemen aplikasi & penetapan hak akses per role/user | Popup kuisioner muncul setelah login (modal + link/embed kuisioner) | — |
| **Rab, 22 Jul** | **Filter grid aplikasi berdasarkan hak akses** (aplikasi tanpa akses tidak dirender) + blokir di level route | Pencatatan klik/partisipasi kuisioner ke DB (unik per user) | — |
| **Kam, 23 Jul** | Halaman admin manajemen user & role | Halaman statistik: jumlah & daftar user yang sudah mengisi, persentase partisipasi | — |
| **Jum, 24 Jul** | Uji RBAC dengan ≥3 skenario role berbeda | Uji kuisioner: klik terhitung sekali per user, statistik akurat | Integrasi kedua modul; demo internal; **Laporan: Bab 3 (Metodologi) selesai draf** |

**Deliverable Fase 2:** RBAC berfungsi penuh + popup kuisioner dengan penghitung partisipasi berfungsi penuh.

---

## Fase 3 — Integrasi, Testing & Selesai (Senin 27 Juli – Jumat 31 Juli)

| Tanggal | HNR (Hafizh) | MAU (Umar) | Bersama / Laporan |
|---|---|---|---|
| **Sen, 27 Jul** | Bugfix hasil integrasi sisi auth/akses | Bugfix sisi dashboard/kuisioner + polish UI | Uji integrasi menyeluruh |
| **Sel, 28 Jul** | Blackbox testing modul auth & RBAC (tabel skenario untuk laporan) | Blackbox testing modul dashboard & kuisioner (tabel skenario untuk laporan) | — |
| **Rab, 29 Jul** | Perbaikan hasil testing | Perbaikan hasil testing | Persiapan deployment (server lokal Dinkominfo / hosting demo) |
| **Kam, 30 Jul** | — | — | **Deployment + UAT bersama pembimbing lapangan**; catat masukan |
| **Jum, 31 Jul** | — | — | Perbaikan minor hasil UAT → **SISTEM DINYATAKAN SELESAI** 🎉; screenshot semua halaman baru untuk lampiran laporan |

**Deliverable Fase 3:** sistem ter-deploy, lolos UAT, dokumentasi teknis lengkap.

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

## Aturan Main Tim

1. **Daily sync 15 menit** tiap pagi: apa yang dikerjakan kemarin, hari ini, dan blocker.
2. **Git workflow:** branch `main` (stabil), `dev` (integrasi), `feat/nama-fitur` per pekerjaan. Merge ke `dev` lewat pull request yang direview partner.
3. **Definisi "selesai":** fitur berjalan + sudah diuji + sudah di-merge + tercatat untuk bahan laporan.
4. **Buffer:** jika ada hari libur/kegiatan kantor, geser tugas hari itu, jangan geser deadline fase.
5. **Titik kritis integrasi:** tabel `applications` + hak akses (HNR) adalah sumber data grid dashboard (MAU) — jangan ubah skemanya tanpa diskusi.
