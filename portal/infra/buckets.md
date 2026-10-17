# S3 buckets (portal)

The portal stores four classes of object. They all live in a **single bucket**,
`demie-studio`, separated by key prefix (single-bucket mode). The API's bucket
settings (`S3_BUCKET_ORIGINALS`, `S3_BUCKET_PREVIEWS`, `S3_BUCKET_THUMBS`,
`S3_BUCKET_ZIPS`) all default to `demie-studio` and can be split per class via
env if buckets ever diverge.

| Prefix (in `demie-studio`) | Purpose | Who writes | Who reads |
|---|---|---|---|
| `originals/` | Photographer-uploaded originals (the source of truth) | API (presigned PUT, browser → S3) | ZIP worker, download presign |
| `previews/`   | Photo previews (~1600px) + video poster frames | Derivative worker | Lightbox upgrade |
| `thumbs/`     | Thumbs (~400px) served in the grid | Derivative worker | Grid, lightbox |
| `zips/`       | Background ZIP builds (close-and-return) | ZIP worker | Client download (presigned) |

Object keys are namespaced under project, e.g.:

```
demie-studio/originals/<project-id>/<media-id>/<filename>
demie-studio/previews/<project-id>/<media-id>.jpg
demie-studio/thumbs/<project-id>/<media-id>.jpg
demie-studio/zips/<job-id>.zip
```

Originals are **never** written to the VPS disk — they land directly in S3 via
presigned PUT and are read back only by the ZIP worker (stream S3 → ZIP → S3)
and by download presigns. The API never proxies bytes.

## Start-up checks

`S3BucketBootstrap` runs on every API start (outside the `test` profile) and:

1. requires `S3_ENDPOINT`, `S3_ACCESS_KEY` and `S3_SECRET_KEY` to be set, and to
   be more than the `change-me` placeholder copied from `.env.example`. Without
   them `S3Config` builds an **anonymous** client with no endpoint override, so
   requests go silently to real AWS S3 in `us-east-1` and only fail much later,
   mid-upload, with `NoSuchBucketException`. That misconfiguration now stops the
   API at start-up with the variable name instead.
2. creates every configured bucket that is missing (`S3Service.ensureBucket()`),
   and fails the start-up when storage rejects the credentials or is
   unreachable.

## Lifecycle

- `zips/` has a 7-day expiry (objects deleted 7 days after creation). In
  single-bucket mode this is a prefix-filtered ILM rule on `zips/` (console:
  Buckets → `demie-studio` → Lifecycle → prefix `zips/`). On a fresh bucket
  the worker should also handle missing ZIPs by rebuilding (Task 12).
- `originals/`, `previews/`, `thumbs/` are retained until a project is deleted
  (cascade in v1; retention policy deferred).

## Live (Dokploy MinIO)

- Console: `https://salvation-minio-bdd8cc-102-202-45-205.sslip.io`
  (Traefik → port 9001).
- S3 API: `http://salvation-minio-7c8f86-102-202-45-205.sslip.io` (Traefik →
  port 9000). This is the `S3_ENDPOINT` value; never use the console domain.
- Bucket: `demie-studio`. The API creates it at start-up
  (`S3BucketBootstrap` → `S3Service.ensureBucket()`), so there is no manual
  step — `mc mb alias/demie-studio` or console → Buckets → Create work too.
  S3 names allow only lowercase, digits, `-` and `.` — underscores are
  rejected with `InvalidBucketName`.
- Credentials are a MinIO service account; keep them in `infra/.env`
  (gitignored), never in committed files.

## Local (MinIO)

Phase 0 runs MinIO as a manual local process (no Docker). Create the single
bucket before the API starts uploading:

```bash
mc alias set local http://localhost:9000 <access-key> <secret-key>
mc mb --ignore-existing local/demie-studio
```

The API creates the bucket itself at start-up, so this step is only needed when
you want it in place before the API runs — the round-trip test (`S3PresignIT`)
creates its own throwaway bucket either way.

## Prod naming

`demie-studio` is the live bucket name (MinIO is not subject to global S3
name uniqueness). If a second environment ever needs isolating, use an
environment-scoped bucket (e.g. `demie-studio-dev`) and set it via env
(`S3_BUCKET_ORIGINALS`, `S3_BUCKET_PREVIEWS`, …) — the API defaults to
`demie-studio` for all four classes, which is correct for local MinIO too.
See `infra/.env.example`.

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
