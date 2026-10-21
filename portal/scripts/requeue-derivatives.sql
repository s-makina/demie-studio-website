-- requeue-derivatives.sql
--
-- One-shot repair for the test-fixture thumbnail pollution (post-mortem:
-- see application-test.yml lines 14-29). DerivativeWorkerTest.testPng()
-- (blue square, yellow circle) was written into the real `demie-studio`
-- bucket at thumbs/<projectId>/<mediaId>.jpg before the S3_BUCKETS_* test
-- guard existed. Because tests ran with RESTART IDENTITY, only project 1's
-- key space collided.
--
-- The DB rows say derivative_status='ready' with correct dims, so
-- verifyReady() never touches them (it only re-queues rows with missing
-- thumbs or 0-dims). The fix is the same reset verifyReady() itself does:
-- flip the rows back to 'pending' and let the DerivativeScheduler sweep
-- (every 30s) regenerate previews/thumbs/ posters from the untouched
-- originals/. No bucket surgery is needed -- regeneration overwrites the
-- same keys.
--
-- Run against the PRODUCTION database, e.g.:
--   psql "<PROD_DB_URL>" -f portal/scripts/requeue-derivatives.sql
--
-- NOTE: requeue the whole project rather than trying to detect "wrong
-- content" rows -- the fixture and real thumbs are indistinguishable from
-- the DB. Rebuilding clean rows from the same originals is a no-op-ish cost.

-- Preview the blast radius first:
SELECT id, kind, derivative_status, derivative_attempts, thumb_width, thumb_height
FROM media
WHERE project_id = 1
ORDER BY id;

-- Re-queue every photo/video of the affected project (project 1 by default;
-- change the id if other projects were ever hit).
UPDATE media
SET derivative_status = 'pending',
    derivative_attempts = 0,
    derivative_error = NULL,
    thumb_width = NULL,
    thumb_height = NULL
WHERE project_id = 1
  AND derivative_status = 'ready';

-- After the sweeps run, rows should return to ready. Check:
--   SELECT derivative_status, count(*) FROM media WHERE project_id = 1 GROUP BY 1;
--
-- FOLLOW-UP: if any tiles STILL show the blue/yellow fixture after this
-- repair, their originals/<projectId>/<mediaId>.jpg objects were ALSO
-- overwritten by the test (fixture bytes uploaded as originals). Those
-- originals are unrecoverable from the bucket -- the only fix is deleting
-- the media row and re-uploading the source file.
