-- V3__media_dimensions.sql
-- Intrinsic aspect ratio of each derivative thumb, so the browser can lay out a
-- virtualized masonry before any image decodes (no layout shift). The worker
-- fills these when it flips a row to 'ready'; rows uploaded before this column
-- existed are backfilled by the same worker sweep (see media_dims_backfill_idx).
--
-- 0 is a sentinel meaning "thumb exists but its size could not be read", so such
-- a row leaves the backfill queue and the client falls back to a default aspect.

ALTER TABLE media ADD COLUMN thumb_width INTEGER;
ALTER TABLE media ADD COLUMN thumb_height INTEGER;

CREATE INDEX media_dims_backfill_idx ON media (id)
    WHERE derivative_status = 'ready' AND thumb_width IS NULL;
