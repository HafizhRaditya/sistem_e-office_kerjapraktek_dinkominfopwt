-- ============================================================
-- schema.sql — Rebuild E-Office Banyumas (ERD v2.0 FINAL, Juli 2026)
-- Target: PostgreSQL 18
-- v2.0 (konsolidasi revisi tim + penyempurnaan):
--   * roles, role_user, application_role DIHAPUS -> users.role varchar + CHECK
--   * nip & nik digabung -> users.nip_nik (satu field login, sesuai situs lama)
--   * application_user -> application_access (surrogate id + UNIQUE + timestamps)
--   * activity_logs: + application_id, questionnaire_id (nullable), action -> activity_type
--   * questionnaires.image -> banner_image
--   * users: + email (UK, NULL), email_verified_at; last_login_at DIPERTAHANKAN (FR-A01)
--   * tabel event (visits, responses) TANPA created/updated_at (model: $timestamps=false)
--   * application_visits: + visit_date + UNIQUE(link,user,tanggal) = 1 kunjungan/tombol/pegawai/hari
-- Aturan kunci:
--   - UNIQUE (questionnaire_id, user_id)            = 1 pegawai 1 klik kuisioner (selamanya)
--   - UNIQUE (link, user_id, visit_date) [COALESCE] = 1 kunjungan per tombol/pegawai/hari
-- Uji: psql -U eoffice -d eoffice_db -f schema.sql
-- ============================================================

BEGIN;

CREATE TABLE opds (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code       varchar(30)  NOT NULL UNIQUE,        -- DINKES, SETDA, ...
    name       varchar(150) NOT NULL,
    is_active  boolean      NOT NULL DEFAULT true,
    created_at timestamptz  NOT NULL DEFAULT now(),
    updated_at timestamptz  NOT NULL DEFAULT now()
);

CREATE TABLE users (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    opd_id            bigint       NOT NULL REFERENCES opds(id) ON DELETE RESTRICT,
    nip_nik           varchar(20)  NOT NULL UNIQUE,  -- identitas login tunggal (NIP atau NIK)
    name              varchar(150) NOT NULL,         -- termasuk gelar, mis. "ADI NUGROHO, S.Kom."
    email             varchar(150) UNIQUE,           -- nullable; UNIQUE PG mengizinkan banyak NULL
    email_verified_at timestamptz,
    password          varchar(255) NOT NULL,
    role              varchar(10)  NOT NULL DEFAULT 'pegawai'
                      CHECK (role IN ('admin','pegawai')),
    is_active         boolean      NOT NULL DEFAULT true,
    last_login_at     timestamptz,                   -- FR-A01
    created_at        timestamptz  NOT NULL DEFAULT now(),
    updated_at        timestamptz  NOT NULL DEFAULT now()
);
CREATE INDEX idx_users_opd ON users(opd_id);

CREATE TABLE applications (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    opd_id      bigint       NOT NULL REFERENCES opds(id) ON DELETE RESTRICT,
    name        varchar(150) NOT NULL,
    slug        varchar(150) NOT NULL UNIQUE,
    description text,
    icon        varchar(255),
    app_group   varchar(20)  NOT NULL
                CHECK (app_group IN ('smartcity','spbe','tools')),
    category    varchar(30)
                CHECK (category IN ('governance','economy','kinerja','gawai',
                                    'rencana','uang','pajak','kesehatan',
                                    'data','wisata','umum')),
    is_active   boolean      NOT NULL DEFAULT true,
    is_new      boolean      NOT NULL DEFAULT false,
    sort_order  integer      NOT NULL DEFAULT 0,
    created_at  timestamptz  NOT NULL DEFAULT now(),
    updated_at  timestamptz  NOT NULL DEFAULT now()
);
CREATE INDEX idx_applications_opd    ON applications(opd_id);
CREATE INDEX idx_applications_filter ON applications(app_group, is_active, category);

CREATE TABLE application_links (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    application_id bigint       NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    label          varchar(50)  NOT NULL,            -- BACKEND | FRONTEND | BACKEND V2 | ...
    url            varchar(500) NOT NULL,
    is_active      boolean      NOT NULL DEFAULT true,
    sort_order     integer      NOT NULL DEFAULT 0,
    created_at     timestamptz  NOT NULL DEFAULT now(),
    updated_at     timestamptz  NOT NULL DEFAULT now(),
    UNIQUE (application_id, label)
);

-- Hak akses per pegawai (dashboard MENANDAI kartu, server memvalidasi 403)
CREATE TABLE application_access (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    application_id bigint      NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    user_id        bigint      NOT NULL REFERENCES users(id)        ON DELETE CASCADE,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (application_id, user_id)
);

-- Kunjungan VALID saja (lolos can_access + link aktif). Model Laravel: $timestamps = false
-- Aturan pembimbing: 1 kunjungan per (tombol/link + pegawai + tanggal).
--   Klik Backend & Frontend aplikasi sama di hari sama = 2 kunjungan (tombol beda);
--   klik Backend 2x di hari sama = 1 kunjungan; besok klik lagi = kunjungan baru.
CREATE TABLE application_visits (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    application_id      bigint      NOT NULL REFERENCES applications(id)      ON DELETE CASCADE,
    application_link_id bigint      REFERENCES application_links(id)          ON DELETE SET NULL,
    user_id             bigint      NOT NULL REFERENCES users(id)             ON DELETE CASCADE,
    visit_date          date        NOT NULL DEFAULT CURRENT_DATE,   -- tanggal kunjungan (WIB)
    visited_at          timestamptz NOT NULL DEFAULT now()
);
-- Unik per tombol+pegawai+tanggal. COALESCE menyeragamkan NULL (kunjungan tanpa link
-- spesifik) agar tetap 1x/hari, bukan lolos berkali-kali karena NULL dianggap unik.
CREATE UNIQUE INDEX uq_visit_daily
    ON application_visits (COALESCE(application_link_id, -1), user_id, visit_date);
CREATE INDEX idx_visits_app_date ON application_visits(application_id, visit_date);

CREATE TABLE questionnaires (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    created_by   bigint       NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    title        varchar(200) NOT NULL,
    description  text,
    banner_image varchar(255),
    target_url   varchar(500) NOT NULL,              -- link Google Form
    is_active    boolean      NOT NULL DEFAULT true,
    starts_at    timestamptz,
    ends_at      timestamptz,
    created_at   timestamptz  NOT NULL DEFAULT now(),
    updated_at   timestamptz  NOT NULL DEFAULT now(),
    CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)
);

-- Partisipasi = klik tombol "Isi Kuisioner". Model Laravel: $timestamps = false
CREATE TABLE questionnaire_responses (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    questionnaire_id bigint      NOT NULL REFERENCES questionnaires(id) ON DELETE CASCADE,
    user_id          bigint      NOT NULL REFERENCES users(id)          ON DELETE CASCADE,
    clicked_at       timestamptz NOT NULL DEFAULT now(),
    UNIQUE (questionnaire_id, user_id)   -- << 1 pegawai tercatat SEKALI per kuisioner
);

CREATE TABLE activity_logs (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id          bigint REFERENCES users(id)          ON DELETE SET NULL,  -- null: login gagal
    application_id   bigint REFERENCES applications(id)   ON DELETE SET NULL,
    questionnaire_id bigint REFERENCES questionnaires(id) ON DELETE SET NULL,
    activity_type    varchar(50) NOT NULL,  -- login_success | login_failed | logout |
                                            -- password_changed | app_launched |
                                            -- quiz_clicked | access_denied
    description      text,
    ip_address       varchar(45),
    user_agent       text,
    created_at       timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_logs_user_time ON activity_logs(user_id, created_at);

COMMIT;

-- ============================================================
-- Pola pencatatan klik kuisioner yang IDEMPOTEN (klik ke-2 dst. diam-diam diabaikan):
--   INSERT INTO questionnaire_responses (questionnaire_id, user_id)
--   VALUES (:qid, :uid)
--   ON CONFLICT (questionnaire_id, user_id) DO NOTHING;
-- Di Laravel: QuestionnaireResponse::firstOrCreate([...id pasangan...], ['clicked_at'=>now()])
--
-- Pola pencatatan KUNJUNGAN idempoten (klik ke-2 tombol sama di hari sama diabaikan):
--   INSERT INTO application_visits (application_id, application_link_id, user_id)
--   VALUES (:aid, :lid, :uid)
--   ON CONFLICT (COALESCE(application_link_id,-1), user_id, visit_date) DO NOTHING;
--   -- visit_date & visited_at terisi default; besok tanggal berbeda -> baris baru.
--
-- Statistik (tiap baris = 1 pengunjung-tombol-harian, tinggal COUNT):
--   pengunjung SIMPUS bulan ini  : COUNT(*) WHERE application_id=:x AND visit_date >= date_trunc('month',CURRENT_DATE)
--   kunjungan per link           : COUNT(*) ... GROUP BY application_link_id
--
-- can_access(user, app) =
--   user.role = 'admin'
--   OR EXISTS (SELECT 1 FROM application_access aa
--              WHERE aa.application_id = app.id AND aa.user_id = user.id)
-- ============================================================
