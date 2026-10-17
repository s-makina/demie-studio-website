-- V4__media_upload_idempotency.sql
-- Batch-upload idempotency: the browser assigns a stable client_upload_id to
-- every file it queues and re-sends it on every call, so a network blip,
-- double click, or retry converges to one row and one multipart upload per
-- file instead of duplicating media. init is an upsert keyed on
-- (project_id, client_upload_id); complete is a guarded state transition.

ALTER TABLE media ADD COLUMN client_upload_id TEXT;

-- Rows created before this column existed (and rows without a browser key)
-- read as NULL and stay outside the constraint.
CREATE UNIQUE INDEX media_client_upload_uidx
    ON media (project_id, client_upload_id)
    WHERE client_upload_id IS NOT NULL;

-- Reaper sweep over stranded uploads whose browser never came back.
CREATE INDEX media_uploading_stale_idx
    ON media (created_at)
    WHERE status = 'uploading';
