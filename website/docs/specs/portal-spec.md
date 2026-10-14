# Spec: Demie Client Portal (v1)

**Status:** Locked (2026-10-14). Implements ADR-0003 split (WP stays brochure; portal owns delivery).
**Spec file:** `docs/specs/portal-spec.md`. Plan: portal planning thread 2026-10-14.

## Objective

Separate delivery app. Photographer uploads a shoot once; invited clients browse, select, and download on slow connections without re-sending files.

- Photographer: create project → upload originals → invite client email → see views/downloads → rotate/revoke link.
- Client: open token link (no login) **or** log in to see my-projects list + download history → masonry grid + lightbox → per-photo instant download → select subset → background ZIP → close tab, return later to same URL for ready file → forward link to family (counts in analytics).

Out of scope v1: public signup, expiring-links-by-default, WP→portal sync, transcoding, favorites shortlist, quotas.

## Tech Stack

- API: Spring Boot 3 + Java 21 (REST, Flyway migrations).
- Web: Next.js 14 (App Router).
- DB: Postgres 16. Tables: `account`, `project`, `project_member`, `media`, `share_token`, `download_event`, `zip_job`.
- Storage: S3-compatible buckets `originals/`, `previews/`, `thumbs/`, `zips/` (zips lifecycle-expire in 7d). Local S3 = MinIO binary run manually (no Docker at start).
- Hosting: single VPS (api + web + pg + caddy as plain processes, no containers at start) + S3-compatible object storage.
- Domain: `portal.` subdomain; WP `portfolio/` cards link out. WP `?project=` URLs stay disposable (ADR-0003).

## Commands

```
API:  ./mvnw spring-boot:run | ./mvnw test | ./mvnw package
Web:  npm run dev | npm run build | npm run test
Infra: Postgres 16 + MinIO run manually as local processes (no Docker); API/web configured via env (DATABASE_URL, S3_ENDPOINT, S3_ACCESS_KEY, S3_SECRET_KEY)
```

## Project Structure

```
portal/               → new root (sibling of theme/plugin, own repo later)
  api/                → auth, projects, media, tokens, presign, zip jobs, analytics
  web/                → /login, /projects, /p/<token> grid + lightbox + ZIP progress
  infra/              → manual setup notes, bucket policy, systemd units, VPS + TLS notes (no compose, no containers at start)
```

## Code Style

- API: resource controllers (`ProjectController`), service layer, Flyway-first schema changes. Naming follows CONTEXT.md: `project`, `media`, `share_token`, `zip_job` — no new synonyms.
- Web: server components for lists, dark masonry + lightbox matched to Demie theme.
- Media listing is cursor-paginated everywhere; bytes served via presigned S3 URLs, never proxied through the API.

## Testing Strategy

- API: JUnit against a manually-run local PG (`portal_test` DB via DATABASE_URL) for the access matrix (no token / revoked / assigned / stranger) and the ZIP job state machine. No Testcontainers (no Docker at start).
- Web: Playwright invite→open link→download flow on throttled 4G.
- Perf: 2,000-item project listing p95 < 500ms; grid usable on mid-range Android + 4G.

## Boundaries

- Always: paginate everything; presigned URLs; revocable tokens (revoke denies instantly); direct-to-S3 multipart upload.
- Ask first: new dependencies, schema changes, anything touching WP theme/plugin.
- Never: public signup; sync ZIP while waiting; originals on VPS disk; proxying bytes via API.

## Access Model (locked)

- Two paths to one project: assigned **account** (invite-only, sees project list + history) **OR** long-lived token bearer (`/p/<token>`, no login, no expiry, shareable, revocable/rotatable).
- Tokens: 256-bit random, hashed at rest, constant-time compare, probe rate-limited.

## Media + ZIP (locked)

- Upload: browser→S3 multipart (API only signs); API records `media` rows.
- Derivatives (async worker, backlog-safe): photo → preview (~1600px) + thumb (~400px); video → poster thumb only (passthrough, no transcode, no embeds v1).
- Grid serves thumbs, lightbox upgrades to preview, download serves original via presigned URL.
- ZIP: `POST /projects/:id/zip {media_ids?}` → `202 + job_id`; worker streams S3 originals → ZIP → S3; `GET job` polls; same token URL resumes after tab close; 1–2 concurrent builds max.

## Success Criteria

- [ ] 2,000-photo project lists without loading all rows; grid usable on 4G.
- [ ] Per-photo download = instant presigned redirect to original.
- [ ] Download-all/selected returns a job immediately; ZIP ready later at same URL after tab close with exactly the selected files.
- [ ] Revoked token denies instantly; per-project views + downloads visible to photographer.
- [ ] Photographer flow needs no developer after deploy.

## Open Questions (resolved 2026-10-14)

1. Token without login — decided: yes, `/p/<token>` needs no account.
2. ZIP contents — decided: originals.
3. Quota — deferred: no cap in v1.
4. Domain — decided: `portal.` subdomain + WP link-out.
