-- V2__media_upload_derivatives.sql
-- Phase 2 (Tasks 6-7): multipart-upload tracking + derivative worker queue.
--
-- s3_upload_id holds the live multipart upload id between init and complete
-- (NULL otherwise). derivative_status drives the worker queue: rows enter as
-- pending on upload-complete; the worker flips them to ready or, after
-- exhausting retries, failed (poison quarantine).

ALTER TABLE media ADD COLUMN s3_upload_id TEXT;

ALTER TABLE media ADD COLUMN derivative_status TEXT NOT NULL DEFAULT 'pending'
    CHECK (derivative_status IN ('pending', 'ready', 'failed'));
ALTER TABLE media ADD COLUMN derivative_attempts INTEGER NOT NULL DEFAULT 0;
ALTER TABLE media ADD COLUMN derivative_error TEXT;

CREATE INDEX media_derivative_pending_idx ON media (derivative_status, id)
    WHERE derivative_status = 'pending';
