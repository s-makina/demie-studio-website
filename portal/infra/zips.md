# ZIP builds (Task 12)

Background "download all / selected" for the client portal. The API never
builds a ZIP while the client waits and never proxies bytes: it records a
`zip_job` row, a worker streams originals S3 → ZIP → S3, and the client
polls the job until the finished object is ready for a presigned download.

## Flow

1. `POST /projects/:id/zip {media_ids?}` (or `POST /p/:token/zip`) →
   `202 + job_id`. Absent/empty `media_ids` means all uploaded media.
   Same project + same selection returns the live job (resume, no duplicate).
2. `ZipScheduler` (every `${ZIP_POLL_MILLIS:15000}`) claims pending jobs
   (`pending → running`, conditional `UPDATE`) and flips past-expiry `ready`
   jobs to `expired`. At most 2 builds run at once; extras wait in `pending`.
3. `GET .../zip/:jobId` polls (`pending → running → ready → expired`,
   plus `failed` with `error_summary`).
4. `GET .../zip/:jobId/download` → `302` to a 10-minute presigned GET with
   `Content-Disposition: attachment` (save-as) + a `download_event` row.
   `409 not_ready` while building; `404` once expired (POST again to rebuild).

## Streaming design

- `ZipWorker` pipes each original (`GET` stream) into a `ZipOutputStream`
  backed by an 8MB part buffer that uploads finished parts via S3 multipart
  (`create → uploadPart* → complete`). Only one 8MB buffer lives in RAM;
  originals and the archive never sit on VPS disk, so 30GB-scale builds
  stream without filling anything.
- Entry names are the original basenames, de-conflicted (`photo (2).jpg`).
  A file that vanished/changed state since the request fails the job loudly
  (`error_summary` names it) — a ZIP always contains exactly the selection.
- Finished objects land at `demie-studio/zips/<job-id>.zip`
  (`application/zip`), `size_bytes` = compressed bytes, `expires_at` =
  `ready_at + 7 days`. Rebuilds mint a new job id (and key).

## Expiry

- Two layers: reads lazily flip past-expiry `ready` → `expired`, and the
  scheduler sweep retires them in bulk. Either way the client rebuilds with
  a fresh POST (idempotent per selection, so a double-click is harmless).
- Object deletion is the bucket lifecycle rule, not the API: prefix `zips/`,
  expire 7 days after creation (console: Buckets → `demie-studio` →
  Lifecycle). See `buckets.md`. Stale keys from rebuilds age out the same way.

## Failure modes

| Situation | Behaviour |
|---|---|
| 3rd concurrent request | stays `pending` until a build finishes |
| S3 error mid-build | multipart aborted, job `failed` + summary, retry via POST |
| Missing/changed media | job `failed` naming the ids, never a silent subset |
| Worker restart mid-build | job stuck `running` stays claimed; next sweep's claim only takes `pending` — restart recovery (re-queue stale `running`) is deferred to hardening (Task 17) |
