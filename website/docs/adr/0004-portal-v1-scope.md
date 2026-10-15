# 0004 — Portal v1 scope: invite + token access, S3 originals, background ZIP

**Status:** Accepted
**Date:** 2026-10-14
**Follows:** 0003 (portal leaves WordPress). **Spec:** `docs/specs/portal-spec.md`.

## Decision

Build the portal as a separate Spring Boot 3 + Next.js 14 + Postgres 16 app on a single VPS with S3-compatible storage, per the locked spec:

- Access: invite-only accounts for project list/history, plus long-lived token URLs (`/p/<token>`) that work without login, are shareable, and are instantly revocable/rotatable.
- Media: direct-to-S3 multipart upload; async preview/thumb derivatives; grid serves thumbs, downloads serve originals via presigned URLs; video passthrough (no transcode) in v1.
- ZIP: selected/all originals built by a background worker (stream S3 → ZIP → S3, close-and-return, 7-day expiry, max 1–2 concurrent builds).
- Web on a `portal.` subdomain matched to the Demie theme; WP `portfolio/` links out, no WP↔portal sync in v1.

## Consequences

- 2,000-photo projects must paginate, thumbnail, and stream — no full-list loads, no byte-proxying, no sync ZIP.
- Token security (256-bit, hashed, throttled) and ZIP lifecycle/expiry are load-bearing; quotas deferred (no cap v1).
- Reversing to WP-hosted delivery later means rebuilding ingest + jobs, not migrating theme content.
