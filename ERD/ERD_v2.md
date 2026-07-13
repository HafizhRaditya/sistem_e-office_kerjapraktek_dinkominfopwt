# Changelog ERD v1.1 → v2.0 FINAL

**Tanggal:** Juli 2026 · **Basis:** revisi ERD Umar (via GPT) + penyempurnaan tim · **Database:** PostgreSQL 18 (bukan 17 — sesuaikan README §4)
**File terdampak:** `schema.sql` (v2.0), `docs/erd/ERD_v2.0_final.png`

## Ringkasan: 12 tabel → 9 tabel

| Dihapus | Alasan |
|---|---|
| `roles`, `role_user` | Pembimbing mengunci 2 role tetap → cukup kolom `users.role varchar CHECK ('admin','pegawai')`. Multi-role tidak dibutuhkan. |
| `application_role` | Akses ditetapkan per pegawai (`application_access`), bukan per role. Bila kelak butuh akses per role/OPD, tabel pivot baru dapat ditambahkan secara aditif. |

## Revisi Umar yang DITERIMA

| Perubahan | Catatan |
|---|---|
| `nip` + `nik` → satu kolom **`nip_nik`** (UK) | Sesuai form login lama: satu field "NIP atau NIK". Setiap akun punya tepat satu identitas login. |
| `application_user` → **`application_access`** (id surrogate + UNIQUE(application_id, user_id) + timestamps) | Konsisten dengan penamaan di KF FINAL; ramah Eloquent. |
| `users` + `email` (UK), `email_verified_at` | Mengikuti konvensi Laravel; berguna bila fitur lupa-password via email dibuat. |
| `activity_logs` + `application_id`, `questionnaire_id` (FK nullable), `action` → `activity_type`, + `description` | Konteks audit lebih kaya (mis. `access_denied` pada aplikasi mana). |
| `questionnaires.image` → **`banner_image`** | Selaras istilah KF FINAL. |
| `opds` + `is_active` + timestamps | Wajar untuk master data. |

## Penyempurnaan atas revisi Umar (koreksi)

| Koreksi | Alasan |
|---|---|
| **`users.last_login_at` dikembalikan** | Hilang di versi GPT, padahal FR-A01 mewajibkan pembaruan waktu login terakhir. |
| **`created_at`/`updated_at` DIHAPUS dari `application_visits` & `questionnaire_responses`** | Baris event tidak pernah di-update; `visited_at`/`clicked_at` vs `created_at` = dua kolom mencatat hal sama (rawan drift). Di model Laravel: `public $timestamps = false;` |
| **`users.email` dibuat nullable** | Email ASN belum tentu tersedia saat seeding; UNIQUE PostgreSQL tetap mengizinkan banyak NULL. |
| Kolom `users.title/phone/photo` resmi dihapus | Mengikuti versi Umar; gelar disimpan sebagai bagian `name` ("ADI NUGROHO, S.Kom."). Dicatat sebagai keputusan, bukan kelalaian. |

## Aturan kunci yang DIPERTAHANKAN (permintaan pembimbing)

1. **Satu pegawai = satu klik kuisioner**, ditegakkan DI DATABASE: `UNIQUE (questionnaire_id, user_id)` pada `questionnaire_responses`. Klik kedua oleh user yang sama ditolak constraint — bukan sekadar if-else di kode. Pola idempoten:
   `INSERT ... ON CONFLICT (questionnaire_id, user_id) DO NOTHING`
   (Laravel: `firstOrCreate`).
2. Semua aplikasi tetap tampil; `application_access` hanya menentukan kartu bertanda/tombol aktif + validasi server (403).
3. Kunjungan valid saja yang masuk `application_visits`.
4. `can_access(user, app) = user.role = 'admin' OR EXISTS application_access(app, user)`.

## Tindak lanjut dokumen
- README §4 & header dokumen: PostgreSQL 17 → **18**.
- KF_AUTH_RBAC_FINAL §1–3: hapus rujukan tabel `roles`; role kini atribut user.
- Mockup tidak berubah (perilaku sama).
