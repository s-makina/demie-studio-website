-- V1__portal.sql
-- Demie Client Portal v1 schema.
-- Run once on an empty Postgres 16 database via Flyway.
-- Re-running is idempotent: Flyway records applied migrations in flyway_schema_history.
--
-- Naming: tables use singular nouns matching CONTEXT.md (project, media,
-- share_token, zip_job). No new synonyms introduced.

SET statement_timeout = 0;
SET lock_timeout = 0;

-- ----------------------------------------------------------------------
-- account
-- Photographers (admin) and invited client accounts.
-- No public signup in v1 — accounts are created/linked by invite.
-- ----------------------------------------------------------------------
CREATE TABLE account (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email           CITEXT NOT NULL,
    name            TEXT,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'client' CHECK (role IN ('admin', 'client')),
    status          TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('pending', 'active', 'disabled')),
    invite_token    TEXT,
    invite_expires_at TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT account_email_unique UNIQUE (email)
);

CREATE INDEX account_email_idx ON account (email);

-- ----------------------------------------------------------------------
-- project
-- A photographer-owned client project / gallery.
-- ----------------------------------------------------------------------
CREATE TABLE project (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id      BIGINT NOT NULL REFERENCES account (id) ON DELETE CASCADE,
    name            TEXT NOT NULL,
    client_name     TEXT,
    status          TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived')),
    cover_s3_key    TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT project_account_idx FOREIGN KEY (account_id) REFERENCES account (id)
);

CREATE INDEX project_account_id_idx ON project (account_id);
CREATE INDEX project_status_idx ON project (status);

-- ----------------------------------------------------------------------
-- project_member
-- Invite links between accounts and projects. A client account sees a
-- project only if a row exists here.
-- ----------------------------------------------------------------------
CREATE TABLE project_member (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id  BIGINT NOT NULL REFERENCES project (id) ON DELETE CASCADE,
    account_id  BIGINT NOT NULL REFERENCES account (id) ON DELETE CASCADE,
    role        TEXT NOT NULL DEFAULT 'viewer' CHECK (role IN ('owner', 'viewer')),
    joined_at   TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT project_member_unique UNIQUE (project_id, account_id)
);

CREATE INDEX project_member_account_idx ON project_member (account_id);

-- ----------------------------------------------------------------------
-- media
-- One original file stored in S3 under originals/<project>/<media>/....
-- `sort` is the display order within a project; it is stable for pagination.
-- ----------------------------------------------------------------------
CREATE TABLE media (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES project (id) ON DELETE CASCADE,
    s3_key          TEXT NOT NULL,
    size_bytes      BIGINT NOT NULL DEFAULT 0,
    kind            TEXT NOT NULL CHECK (kind IN ('photo', 'video')),
    sort            INTEGER NOT NULL,
    status          TEXT NOT NULL DEFAULT 'uploading' CHECK (status IN ('uploading', 'uploaded', 'derivative_failed')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT media_project_idx FOREIGN KEY (project_id) REFERENCES project (id)
);

CREATE INDEX media_project_sort_idx ON media (project_id, sort);
CREATE INDEX media_project_status_idx ON media (project_id, status);

-- ----------------------------------------------------------------------
-- share_token
-- One 256-bit token per project. The raw token is issued to the client
-- once and never stored; only SHA-256(token) is persisted for lookup.
-- `rotated_at` marks when a rotation happened; `revoked_at` marks
-- revocation (denied instantly by the token guard).
-- ----------------------------------------------------------------------
CREATE TABLE share_token (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES project (id) ON DELETE CASCADE,
    token_hash      BYTEA NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    rotated_at      TIMESTAMPTZ,
    revoked_at      TIMESTAMPTZ,

    CONSTRAINT share_token_project_unique UNIQUE (project_id)
);

CREATE INDEX share_token_token_hash_idx ON share_token (token_hash);

-- ----------------------------------------------------------------------
-- download_event
-- Analytics: each view/download writes a row. `project_id` is always set;
-- `media_id`, `account_id`, and `token_id` are populated where the access
-- path makes them meaningful.
-- ----------------------------------------------------------------------
CREATE TABLE download_event (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES project (id) ON DELETE CASCADE,
    media_id        BIGINT REFERENCES media (id) ON DELETE SET NULL,
    account_id      BIGINT REFERENCES account (id) ON DELETE SET NULL,
    token_id        BIGINT REFERENCES share_token (id) ON DELETE SET NULL,
    event_type      TEXT NOT NULL CHECK (event_type IN ('view', 'download')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX download_event_project_created_idx
    ON download_event (project_id, created_at);
CREATE INDEX download_event_media_idx ON download_event (media_id);
CREATE INDEX download_event_token_idx ON download_event (token_id);

-- ----------------------------------------------------------------------
-- zip_job
-- Background ZIP build. `media_ids` is the selected subset (TEXT[]); when
-- empty/null the job covers all media in the project. `status` drives the
-- state machine: pending -> running -> ready -> expired (or failed).
-- ----------------------------------------------------------------------
CREATE TABLE zip_job (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES project (id) ON DELETE CASCADE,
    status          TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'running', 'ready', 'expired', 'failed')),
    media_ids       TEXT[],
    s3_key          TEXT,
    size_bytes      BIGINT,
    error_summary   TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    started_at      TIMESTAMPTZ,
    ready_at        TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ
);

CREATE INDEX zip_job_project_status_idx ON zip_job (project_id, status);
CREATE INDEX zip_job_expires_idx ON zip_job (expires_at);
CREATE INDEX zip_job_status_idx ON zip_job (status);
