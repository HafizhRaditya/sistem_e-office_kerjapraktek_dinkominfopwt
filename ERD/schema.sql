-- ============================================================
-- schema.sql — Rebuild E-Office Banyumas (ERD v1.1, revisi pembimbing Juli 2026)
-- v1.1: + application_links.is_active; makna AB1 berubah (lihat KF FINAL §14):
--       akses MENANDAI kartu (can_access), bukan menyembunyikan; launch tanpa hak = 403.
-- Target: PostgreSQL 17
-- Catatan: di implementasi nyata, struktur ini dituangkan sebagai
-- Laravel migrations; file ini adalah sumber kebenaran desain ERD
-- dan dapat diuji langsung: psql -f schema.sql
-- ============================================================

BEGIN;

-- ---------- Master ----------

CREATE TABLE opds (
    id      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code    varchar(30)  NOT NULL UNIQUE,          -- DINKES, SETDA, ...
    name    varchar(150) NOT NULL
);

CREATE TABLE users (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    opd_id        bigint       NOT NULL REFERENCES opds(id) ON DELETE RESTRICT,
    nip           varchar(20)  NOT NULL UNIQUE,
    nik           varchar(20)  UNIQUE,             -- login alternatif, nullable
    name          varchar(150) NOT NULL,
    title         varchar(50),                     -- gelar
    email         varchar(150),
    phone         varchar(30),
    photo         varchar(255),
    password      varchar(255) NOT NULL,           -- bcrypt/argon2 hash
    is_active     boolean      NOT NULL DEFAULT true,
    last_login_at timestamptz,
    created_at    timestamptz  NOT NULL DEFAULT now(),
    updated_at    timestamptz  NOT NULL DEFAULT now()
);
CREATE INDEX idx_users_opd ON users(opd_id);

CREATE TABLE roles (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name        varchar(50) NOT NULL UNIQUE,       -- superadmin | admin_opd | pegawai
    description varchar(255)
);

CREATE TABLE role_user (
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id bigint NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

-- ---------- Aplikasi & peluncuran ----------

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
    label          varchar(50)  NOT NULL,          -- BACKEND | FRONTEND | BACKEND V2 | ...
    url            varchar(500) NOT NULL,
    is_active      boolean      NOT NULL DEFAULT true,   -- ERD v1.1 (revisi pembimbing)
    sort_order     integer      NOT NULL DEFAULT 0,
    UNIQUE (application_id, label)
);

CREATE TABLE application_visits (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    application_id      bigint      NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    application_link_id bigint      REFERENCES application_links(id) ON DELETE SET NULL,
    user_id             bigint      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    visited_at          timestamptz NOT NULL DEFAULT now()
);
-- penghitung "pengunjung bulan/tahun ini"
CREATE INDEX idx_visits_app_time ON application_visits(application_id, visited_at);

-- ---------- RBAC (hak akses aplikasi) ----------

CREATE TABLE application_role (
    application_id bigint NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    role_id        bigint NOT NULL REFERENCES roles(id)        ON DELETE CASCADE,
    PRIMARY KEY (application_id, role_id)
);

CREATE TABLE application_user (
    application_id bigint NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    user_id        bigint NOT NULL REFERENCES users(id)        ON DELETE CASCADE,
    PRIMARY KEY (application_id, user_id)
);

-- ---------- Kuisioner (popup) ----------

CREATE TABLE questionnaires (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title       varchar(200) NOT NULL,
    description text,
    image       varchar(255),
    target_url  varchar(500) NOT NULL,             -- asumsi A2: tautan eksternal
    is_active   boolean      NOT NULL DEFAULT true,
    starts_at   timestamptz,
    ends_at     timestamptz,
    created_by  bigint       NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at  timestamptz  NOT NULL DEFAULT now(),
    updated_at  timestamptz  NOT NULL DEFAULT now(),
    CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)
);

CREATE TABLE questionnaire_responses (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    questionnaire_id bigint      NOT NULL REFERENCES questionnaires(id) ON DELETE CASCADE,
    user_id          bigint      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    clicked_at       timestamptz NOT NULL DEFAULT now(),
    UNIQUE (questionnaire_id, user_id)             -- AB3: satu user dihitung sekali
);

-- ---------- Log aktivitas ----------

CREATE TABLE activity_logs (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    bigint REFERENCES users(id) ON DELETE SET NULL,  -- nullable: login gagal
    action     varchar(50) NOT NULL,   -- login_success | login_failed | logout |
                                       -- password_changed | app_launched | quiz_clicked | ...
    ip_address varchar(45),            -- muat IPv6
    user_agent text,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_logs_user_time ON activity_logs(user_id, created_at);

COMMIT;

-- ============================================================
-- Query rujukan AB1 — daftar aplikasi yang boleh diakses user :uid
-- (implementasikan SEKALI sebagai Eloquent scope/service, dipakai
--  oleh grid dashboard DAN middleware /launch/{slug})
-- ============================================================
-- SELECT DISTINCT a.*
-- FROM applications a
-- WHERE a.is_active = true
--   AND (
--     EXISTS (SELECT 1 FROM application_role ar
--             JOIN role_user ru ON ru.role_id = ar.role_id
--             WHERE ar.application_id = a.id AND ru.user_id = :uid)
--     OR EXISTS (SELECT 1 FROM application_user au
--                WHERE au.application_id = a.id AND au.user_id = :uid)
--   );
