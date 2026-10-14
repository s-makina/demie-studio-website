# S3 buckets (portal)

The portal stores four classes of object. In Phase 0 these are four separate
S3-compatible buckets (local MinIO uses exactly these names; prod S3 uses
unique, environment-scoped names — see "prod naming" below).

| Bucket | Purpose | Who writes | Who reads |
|---|---|---|---|
| `originals` | Photographer-uploaded originals (the source of truth) | API (presigned PUT, browser → S3) | ZIP worker, download presign |
| `previews`   | Photo previews (~1600px) + video poster frames | Derivative worker | Lightbox upgrade |
| `thumbs`     | Thumbs (~400px) served in the grid | Derivative worker | Grid, lightbox |
| `zips`       | Background ZIP builds (close-and-return) | ZIP worker | Client download (presigned) |

Object keys are namespaced under project, e.g.:

```
originals/<project-id>/<media-id>/<filename>
previews/<project-id>/<media-id>.jpg
thumbs/<project-id>/<media-id>.jpg
zips/<job-id>.zip
```

Originals are **never** written to the VPS disk — they land directly in S3 via
presigned PUT and are read back only by the ZIP worker (stream S3 → ZIP → S3)
and by download presigns. The API never proxies bytes.

## Lifecycle

- `zips/` has a 7-day expiry (objects deleted 7 days after creation). On a
  fresh bucket the worker should also handle missing ZIPs by rebuilding
  (Task 12).
- `originals/`, `previews/`, `thumbs/` are retained until a project is deleted
  (cascade in v1; retention policy deferred).

## Local (MinIO)

Phase 0 runs MinIO as a manual local process (no Docker). Create the four
buckets before the API starts uploading:

```bash
mc alias set local http://localhost:9000 <access-key> <secret-key>
mc mb --ignore-existing local/originals
mc mb --ignore-existing local/previews
mc mb --ignore-existing local/thumbs
mc mb --ignore-existing local/zips
```

The API's `S3Service.ensureBucket()` also creates a bucket on first use (idempotent),
so the buckets can be created lazily — but creating them up front keeps the
round-trip test (`S3PresignIT`) deterministic.

## Prod naming

S3 bucket names are globally unique. For prod, use environment-scoped names,
e.g.:

```
demie-portal-originals-prod
demie-portal-previews-prod
demie-portal-thumbs-prod
demie-portal-zips-prod
```

Set these via env (`S3_BUCKET_ORIGINALS`, `S3_BUCKET_PREVIEWS`, …) — the API
defaults to `originals`/`previews`/`thumbs`/`zips` which is correct for local
MinIO. See `infra/.env.example`.

## Access model

- The API holds the S3 access key + secret (env: `S3_ACCESS_KEY`,
  `S3_SECRET_KEY`). It issues **presigned** URLs only; clients never see the
  credentials.
- Upload: browser GETs a presigned PUT from the API, then PUTs directly to S3.
- Download / lightbox: browser GETs a presigned GET, then fetches directly from
  S3.
- The API never reads or proxies object bytes through itself.

## Policy notes (prod, to finalize with infra)

- `originals/`: write-once via presigned PUT from the API's signer; no public
  read. Read only via presigned GET issued by the API.
- `previews/` + `thumbs/`: written by the derivative worker (using the API's
  signer credentials, same IAM role); read via presigned GET.
- `zips/`: written by the ZIP worker; read via presigned GET to the client;
  7-day expiry via bucket lifecycle rule.
- Consider scoping IAM to `s3:PutObject`, `s3:GetObject`, `s3:ListBucket` on
  the four buckets only; deny `s3:DeleteObject` except for the ZIP lifecycle
  and project-deletion paths.
