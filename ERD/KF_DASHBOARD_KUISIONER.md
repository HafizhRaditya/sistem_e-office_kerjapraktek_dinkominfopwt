# Kebutuhan Fungsional — Modul Dashboard & Kuisioner

**Penyusun:** Muhammad Abu Umar (H1D024084) · **Status:** Draf v1.0 — 10 Juli 2026
**Dasar:** `docs/inventaris/INVENTARIS_MAU_DASHBOARD.md`, `docs/erd/ERD.md` (v1.0), `schema.sql`, desain Stitch `docs/mockup/`
**Kode kebutuhan:** melanjutkan FR-D01…D11 dari inventarisasi.

---

## 1. Dashboard Portal

### FR-D01 — Struktur halaman
Urutan seksi (paritas sistem lama, gaya visual mengikuti design system Stitch "Modern Governance"): navbar → hero + CTA → "Aplikasi Paling Sering Diakses" (5 teratas) → tab grup → filter kategori + pencarian → grid kartu aplikasi → footer.

### FR-D02 — Tab, filter, pencarian
- Tab: **Smart City / SPBE / Tools / Aplikasi Baru** (dari `applications.app_group` + `is_new`), dengan jumlah dinamis.
- Filter status: Semua / Aktif / Tidak Aktif (jumlah dinamis).
- Filter kategori (11): Governance, Economy, Kinerja, Gawai, Rencana, Uang, Pajak, Kesehatan, Data, Wisata, Umum.
- Pencarian nama aplikasi, minimal client-side pada data yang sudah difilter akses.
- **Kriteria:** kombinasi tab+status+kategori+kata kunci bekerja bersamaan; jumlah pada badge SELALU dihitung dari himpunan aplikasi yang boleh diakses user (bukan total sistem) agar tidak membocorkan keberadaan aplikasi lain.

### FR-D03 — Kartu aplikasi
Menampilkan: label OPD (label-caps), ikon, nama, chip status, 1–3 tombol tautan dari `application_links` (Backend/Frontend/varian), statistik "N pengunjung bulan ini" + "N pengunjung tahun ini". Sumber ikon dari penyimpanan lokal (seeder), fallback inisial nama.

### FR-D04 — Penghitung kunjungan
Klik tombol tautan → `POST /launch/{slug}/{link}` → insert `application_visits` → catat `app_launched` → redirect ke URL tujuan (tab baru). Penghitung = agregat `application_visits` bulan/tahun berjalan (AB2). **Kriteria:** angka bertambah tepat 1 per klik; agregasi bulan berganti otomatis saat pergantian bulan.

### FR-D05 — Dark/light mode
Toggle di navbar; preferensi disimpan per peramban; seluruh komponen punya varian gelap (`slate-900/800` sesuai DESIGN.md).

### FR-D06 — Grid terfilter hak akses ⭐
Sumber data grid = `Application::accessibleBy($user)` (scope milik modul RBAC — HNR). Dashboard TIDAK membuat query aksesnya sendiri. **Kriteria:** identik dengan skenario uji FR-A10 dari sisi tampilan.

## 2. Popup Kuisioner ⭐ (permintaan utama atasan)

### FR-D07 — Kemunculan popup (alur lengkap)
```
Login sukses → GET /site
  └─ Ada kuisioner dengan is_active=true DAN now() ∈ [starts_at, ends_at]?
       ├─ Tidak → dashboard tanpa popup
       └─ Ya → user sudah punya baris questionnaire_responses utk kuisioner itu?
            ├─ Ya  → dashboard tanpa popup (sudah berpartisipasi)
            └─ Tidak → tampilkan modal (overlay slate-900/70)
                 ├─ Klik ✕ / "Nanti saja" → tutup; muncul lagi pada login/kunjungan berikutnya
                 └─ Klik "Isi Kuisioner" →
                      1. POST /questionnaires/{id}/click
                      2. INSERT questionnaire_responses (unik questionnaire_id+user_id;
                         duplikat ditelan sebagai idempoten — AB3)
                      3. catat activity_logs (quiz_clicked)
                      4. buka target_url di tab baru + tutup modal
```
Jika ada >1 kuisioner aktif: tampilkan yang `starts_at` terbaru (asumsi A4; carousel = backlog).

### FR-D08 — Isi modal
Judul, deskripsi/gambar (dari `questionnaires`), tombol primer "Isi Kuisioner", tautan sekunder "Nanti saja", dan **baris penghitung partisipasi live**: "N pegawai sudah berpartisipasi".

### FR-D09 — Halaman statistik partisipasi (superadmin)
Per kuisioner: total partisipan (`COUNT(responses)`), persentase (= total ÷ `COUNT(users WHERE is_active)` × 100%), grafik batang partisipasi per hari, tabel daftar partisipan (nama, OPD, waktu klik) berpaginasi + ekspor CSV. **Kriteria:** angka konsisten dengan isi tabel; user yang sama tidak pernah terhitung dua kali (dijamin UNIQUE di DB).

### FR-D10 — CRUD kuisioner (superadmin)
Field: judul, deskripsi, gambar (unggah), target_url, periode aktif, is_active. Validasi `ends_at ≥ starts_at` (juga ditegakkan CHECK constraint).

### FR-D11 — Profil ringan (status: menunggu konfirmasi pembimbing; lihat inventaris §3).

## 3. Data yang Dihitung (ringkasan untuk laporan)

| Metode | Rumus | Sumber |
|---|---|---|
| Pengunjung aplikasi bulan ini | COUNT(visits) WHERE app & bulan berjalan | `application_visits` (idx application_id, visited_at) |
| Pengunjung aplikasi tahun ini | idem, tahun berjalan | idem |
| Aplikasi paling sering diakses | TOP 5 COUNT(visits) 30 hari terakhir | idem |
| Partisipan kuisioner | COUNT(responses) per kuisioner | `questionnaire_responses` |
| Persentase partisipasi | partisipan ÷ user aktif × 100% | + `users.is_active` |
| Partisipasi per hari | COUNT(responses) GROUP BY date(clicked_at) | `questionnaire_responses` |

## 4. Non-Fungsional Terkait
Dashboard responsif (grid 1/2/3 kolom di ≥360/768/1280px); render awal < 2 dtk dengan 131 aplikasi seeder; modal dapat ditutup dengan tombol Esc dan klik overlay; gambar kuisioner lazy-load.

## 5. Ketergantungan
FR-D06 bergantung scope `accessibleBy` (HNR, target 22 Juli); FR-D04 & D07 memakai `activity_logs` (HNR). Kontrak: MAU tidak menulis query akses sendiri; HNR tidak mengubah bentuk data kartu (`applications` + `application_links` + agregat kunjungan) tanpa diskusi.
