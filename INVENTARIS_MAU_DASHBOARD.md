# Inventarisasi Fitur — Modul Dashboard, Popup & Profil

**Penyusun:** Muhammad Abu Umar (H1D024084)
**Tanggal:** Rabu, 8 Juli 2026 — Fase 0, Hari 1
**Sumber:** 30 screenshot sistem lama E-Office Banyumas (`docs/screenshots/ss_01–ss_30`)
**Repo:** https://github.com/HafizhRaditya/sistem_e-office_kerjapraktek_dinkominfopwt

---

## 1. Struktur Halaman Dashboard (`/site`) — ss_01, ss_05, ss_12, ss_23

Dashboard adalah satu halaman panjang (single page, scroll) dengan urutan seksi:

### 1a. Navbar (sticky, semua halaman)
| Elemen | Detail |
|---|---|
| Logo | "EOB — E-OFFICE banyumas" (kiri) |
| Toggle tema | "🌙 Mode Gelap" / "☀️ Mode Terang" — **dark mode & light mode penuh** (ss_05–ss_11 gelap; ss_12–ss_23 terang) |
| Tombol user | Nama + gelar ("ADI NUGROHO, S.Kom.", biru) → dropdown: Ubah Sandi, Logout |

### 1b. Hero — ss_05, ss_23
| Elemen | Detail |
|---|---|
| Judul | "E-Office Kabupaten Banyumas" |
| Deskripsi | "Sebuah portal aplikasi yang memudahkan para user OPD untuk mengakses aplikasi yang ada di Kabupaten Banyumas" |
| CTA | Tombol "**Mulai Eksplorasi Aplikasi**" (scroll ke grid) |

### 1c. Seksi "Aplikasi Paling Sering Diakses" — ss_05, ss_23
Baris 5 kartu aplikasi terpopuler: Simpatik Integrasi (BKPSDM), SIMPUS (DINKES), Jegos 3.0 (SETDA), SI Organisasi (SETDA), SIMAS (DINKOMINFO). Diurutkan dari data kunjungan → sistem lama **sudah menghitung pengunjung per aplikasi** (modal bagus untuk fitur hitung kuisioner kita).

### 1d. Seksi "Aplikasi Kabupaten Banyumas" — tab grup — ss_01
| Tab | Jumlah |
|---|---|
| **Smart City** (aktif, oranye) | 123 |
| **SPBE** | 26 |
| **Tools** | 6 |
| **Aplikasi Baru** | — |

### 1e. Seksi "Kategori Aplikasi" — filter badge + pencarian — ss_01, ss_12
| Filter status | Semua (131) · Aktif (64) · Tidak Aktif (67) |
|---|---|
| Filter kategori | Governance (56) · Economy (67) · Kinerja (9) · Gawai (5) · Rencana (5) · Uang (3) · Pajak (5) · Kesehatan (7) · Data (4) · Wisata (4) · Umum (9) — tiap badge berwarna beda |
| Pencarian | Search bar "🔍 Cari aplikasi smartcity..." |

### 1f. Grid kartu aplikasi — ss_02, ss_08–ss_22, ss_30
Anatomi satu kartu (konsisten di semua kartu):
1. **Label OPD pemilik** (atas, kapital abu-abu): BKPSDM, DINKES, SETDA, DINKOMINFO, INSPEKTORAT, BAPPEDALITBANG, ARPUSDA, BAPENDA, DKPP, DINPERINDAG, DINPERTAN, DINPORABUDPAR, DINSOS, DINDIK, DINHUB, BPBD, BKAD, RSUD AJIBARANG, KESBANGPOL, DPMPTSP, PEMKAB, dst.
2. **Ikon/logo aplikasi**
3. **Nama aplikasi** (±131 aplikasi; contoh: SIMPUS, SIMLOG, KPIA Kartini, SISDAPORA, RPD, Manajemen Kinerja, SIPANJIMAS, E-Monev, E-Sakip, Jegos 3.0, SIKEP, E-Retribusi, E-Planning, SPIP, SIMANTAP, SIBINTANG LIMA, Satria Pajak New, JDIH, WBS, DIMAS SATRIA, SI-IKANMAS, SIGAOKMAS, SIIMASTER, SIMETRO, SIFEBI, SOLTANMAS, SITANI, Dolan Banyumas, SIMPELKESOS, SPMB Online, Sibesti, SIM PKB, SIMATRA, SIMBEBAS, SIMANDALA, SI MATA AWAS, KKPM, E-Perangmas, SIPADIMAS, LARAS, SIMLABA, Simklinik, dll.)
4. **Tombol tautan** 1–3 buah: BACKEND / FRONTEND / varian (BACKEND V2, dst.)
5. **Status:** AKTIF (hijau)
6. **Statistik kunjungan:** "N Pengunjung bulan ini" + "N Pengunjung tahun ini"

### 1g. Footer — ss_03, ss_24
Ilustrasi orang bekerja + teks "E-OFFICE BANYUMAS" + "© 2026 E-Office Banyumas — Dinkominfo Kabupaten Banyumas".

---

## 2. Popup Setelah Login — ss_27 ⭐ (target fitur kuisioner)

| Elemen | Detail |
|---|---|
| Bentuk | Modal di tengah layar, latar dashboard digelapkan (overlay) |
| Konten | Banner bergambar (ilustrasi lanskap Banyumas + logo kabupaten) dengan teks "**Selamat Datang di Portal E-Office Kabupaten Banyumas** — Portal satu pintu untuk seluruh layanan pemerintahan, mempermudah akses..." |
| Kontrol | Tombol tutup **✕ merah** di pojok kanan atas modal |
| Perilaku | Muncul setelah login/di kunjungan dashboard; hanya informasi satu arah, **tidak ada tombol aksi, tidak ada pencatatan interaksi** |

### Rencana pengembangan menjadi popup kuisioner (permintaan atasan)
1. Popup dikelola dari database (`questionnaires`): judul, deskripsi/gambar, tautan kuisioner, periode aktif.
2. Tambah tombol aksi "**Isi Kuisioner**" → klik tercatat ke `questionnaire_responses` (**unik per user**) lalu diarahkan ke kuisioner.
3. Popup tidak muncul lagi bagi user yang sudah berpartisipasi (atau bisa ditutup sementara).
4. Halaman **statistik partisipasi**: total klik, daftar user, persentase terhadap seluruh user aktif — meniru pola penghitung "pengunjung bulan/tahun ini" yang sudah ada per aplikasi.

---

## 3. Profil Pengguna — temuan penting ⚠️

Dari 30 screenshot, **tidak ditemukan halaman profil/biodata pegawai**. Identitas user hanya muncul sebagai **nama + gelar di tombol navbar**, dan menu dropdown hanya berisi Ubah Sandi + Logout.

Implikasi:
- Jangan berasumsi ada halaman biodata di sistem lama. **Konfirmasi ke pembimbing hari ini:** apakah memang tidak ada, atau ada tapi belum ter-screenshot?
- Untuk rebuild, tetap diusulkan halaman profil ringan (nama, NIP/NIK, OPD, foto) karena data ini juga dibutuhkan RBAC (penentuan role per OPD) — masuk backlog "peningkatan", bukan "paritas".

---

## 4. Kebutuhan Fungsional Modul Dashboard & Kuisioner (draf untuk Kamis, 9 Juli)

| Kode | Kebutuhan | Asal |
|---|---|---|
| FR-D01 | Dashboard menampilkan hero, seksi aplikasi terpopuler, dan grid aplikasi | Paritas |
| FR-D02 | Grid dapat difilter per tab (Smart City/SPBE/Tools/Baru), status, kategori, dan pencarian nama | Paritas |
| FR-D03 | Kartu aplikasi menampilkan OPD, ikon, nama, tombol tautan (bisa >1), status, statistik kunjungan | Paritas |
| FR-D04 | Klik tautan aplikasi menambah penghitung kunjungan (bulan & tahun) | Paritas |
| FR-D05 | Dark mode / light mode dengan preferensi tersimpan | Paritas |
| FR-D06 | **Grid hanya menampilkan aplikasi yang menjadi hak akses user** (data dari modul RBAC — HNR) | Baru |
| FR-D07 | Popup tampil setelah login, kontennya dikelola admin dari database | Paritas → dikembangkan |
| FR-D08 | Popup memuat kuisioner dengan tombol aksi; klik tercatat unik per user | Baru — permintaan utama |
| FR-D09 | Halaman statistik partisipasi kuisioner: jumlah, daftar, persentase | Baru — permintaan utama |
| FR-D10 | Admin dapat CRUD kuisioner (judul, gambar, tautan, periode aktif) | Baru — pendukung |
| FR-D11 | Halaman profil ringan pengguna | Peningkatan (konfirmasi dulu) |

---

## 5. Pertanyaan untuk Pembimbing Lapangan (hari ini)

1. Kuisioner bentuknya apa: tautan Google Form eksternal (cukup hitung klik) atau form isian di dalam sistem (jawaban ikut disimpan)?
2. Siapa yang boleh melihat statistik partisipasi — semua user atau admin saja?
3. Popup: satu kuisioner aktif pada satu waktu, atau bisa beberapa (carousel seperti pengumuman)?
4. Apakah halaman profil pegawai memang tidak ada di sistem lama? Perlu dibuatkan di sistem baru?
5. Angka kategori (Smart City 123, SPBE 26, Tools 6, total 131) — apakah daftar aplikasi + kategorisasinya bisa kami dapatkan dalam bentuk data (export DB/spreadsheet) agar seeder akurat?
