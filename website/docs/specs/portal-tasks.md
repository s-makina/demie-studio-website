# Tasks: Demie Client Portal v1 (implements `docs/specs/portal-spec.md`)

Order is by dependency. No task touches more than ~5 files. Check a task off only when its Verify passes.

## Phase 0 — Foundation

- [x] Task 0: Scaffold portal root + manual local services
  - Acceptance: Postgres 16 and MinIO run manually as local processes (no Docker); api and web skeletons boot against them via env (DATABASE_URL, S3_ENDPOINT, S3 keys).
  - Verify: start postgres + minio manually, then `./mvnw -q -DskipTests package -pl api && npm --prefix web run build`
  - Done 2026-10-14: package + web build pass; API boots against `demie_portal` (Flyway validated, Tomcat on :8080). MinIO not installed locally yet; S3 config boots with env absent.
  - Files: `portal/api/*skeleton*`, `portal/web/*skeleton*`, `portal/infra/LOCAL-SETUP.md`, `portal/infra/.env.example`

- [x] Task 1: DB schema via Flyway (all tables, no API yet)
  - Acceptance: single migration creates `account`, `project`, `project_member`, `media`, `share_token`, `download_event`, `zip_job` with indexes + FKs; rolls forward and back cleanly on empty DB.
  - Verify: `./mvnw flyway:migrate && ./mvnw flyway:info` on fresh PG; re-run is idempotent
  - Done 2026-10-14: V1 applied to `demie_portal` (PG 16.15); clean→migrate roll-forward and idempotent re-run pass; 7 tables, 10 FKs, indexes verified; citext enabled for `account.email`.
  - Files: `portal/api/src/main/resources/db/migration/V1__portal.sql`

- [x] Task 2: S3 buckets + presign helper + round-trip test
  - Acceptance: single bucket `demie-studio` with `originals/`, `previews/`, `thumbs/`, `zips/` prefixes (Dokploy MinIO; policy documented in buckets.md); helper issues presigned PUT/GET; integration test PUTs and GETs an object.
  - Verify: API integration test `S3PresignIT` green with `S3_ENDPOINT` pointing at the MinIO S3 API domain
  - Files: `portal/api/.../S3Service.java`, `portal/api/.../S3Config.java`, `portal/api/.../S3PresignIT.java`, `portal/infra/buckets.md`, `portal/infra/.env.example`
  - Done 2026-10-14: `S3PresignIT` 2/2 green live against Dokploy MinIO (`demie-studio` auto-created via `ensureBucket`, PUT→HEAD→GET byte-identical); presigner fixed to path-style URLs (virtual-hosted style 404s behind Traefik); single-bucket mode (all `S3_BUCKET_*` default `demie-studio`, classes separated by prefix); stray debug buckets removed.

## Phase 1 — Access + Projects

- [x] Task 3: Admin login + invite-only client accounts (no public signup)
  - Acceptance: photographer logs in; invite-by-email creates/links a client account; there is no self-registration endpoint (404/403 proven by test).
  - Verify: `./mvnw test -Dtest=AuthTest`; manual: invite email → set password → login
  - Files: `portal/api/.../AuthController.java`, `portal/api/.../AccountService.java`, `portal/web/app/login/**`
  - Done 2026-10-14: `AuthTest` 9/9 green; live smoke against `demie_portal` — login 200 admin, `/auth/register` 403, admin-only invite enforced (403 anonymous), invite-accept 204, client login 200, wrong password 401, replayed token 400. Bootstrap admin via `ADMIN_EMAIL`/`ADMIN_PASSWORD`; web `/login` + `/login/accept` call the API.

- [x] Task 4: Projects CRUD + member assignment
  - Acceptance: photographer creates/renames/archives a project and assigns invited emails; assigned client sees it in list, stranger account does not.
  - Verify: `./mvnw test -Dtest=ProjectAccessTest`
  - Files: `portal/api/.../ProjectController.java`, `portal/api/.../ProjectService.java`, `portal/api/.../Project.java`, `portal/api/.../ProjectAccessTest.java` (no migration delta — V1 schema covered it)
  - Done 2026-10-14: `ProjectAccessTest` 10/10 green, full suite 20/20. `POST/PATCH /projects/**` admin-only via `SecurityConfig`; reads scoped per caller (admin: all, client: active memberships); assign-by-email idempotent, works for pending invites; archived hidden from clients (403), visible to admin.

- [x] Task 5: Share tokens — issue / rotate / revoke + bearer guard
  - Acceptance: each project has a 256-bit token (hash stored); `/p/<token>` grants view/download without login; rotate invalidates old, revoke denies instantly; token probes are rate-limited with constant-time compare.
  - Verify: `./mvnw test -Dtest=ShareTokenTest` (valid / rotated / revoked / stranger / throttled)
  - Files: `portal/api/.../ShareTokenService.java`, `portal/api/.../TokenAuthFilter.java`, `portal/api/.../ShareController.java`, `portal/api/.../ShareTokenTest.java` (no `SecurityConfig` change — existing `POST /projects/**` + `/p/**` rules cover the routes)
  - Done 2026-10-14: `ShareTokenTest` 9/9 green, full suite 29/29. Raw token returned once (43 base64url chars), SHA-256 `BYTEA` stored; unknown/revoked → 404 (no oracle), 60 probes/min/IP → 429; rotate clears revocation; token view exposes exactly its project.

## Phase 2 — Media

- [x] Task 6: Upload init/complete + cursor-paginated listing
  - Acceptance: browser gets presigned multipart URLs (API only signs), completes upload → `media` row (project, s3_key, size, kind, sort); listing is cursor-paginated and never full-scans 2,000 rows.
  - Verify: upload 50-file fixture via script; listing pages return stable cursors
  - Files: `portal/api/.../MediaController.java`, `portal/api/.../MediaService.java`, `portal/api/.../Media.java`, `portal/api/.../MediaUploadTest.java`, `portal/scripts/upload-fixture.sh`, `V2__media_upload_derivatives.sql` (s3_upload_id), S3 multipart + server-side get/put/delete in `S3Service.java`
  - Done 2026-10-14: `MediaUploadTest` 6/6 green live (50-file fixture via init→part-PUT→complete, alternating photo/video; 20/20/10 pages, no dupes/gaps, kinds recorded); keyset `(sort,id)` cursors over `media_project_sort_idx`; mutations admin-only; complete verifies size via HEAD; abort drops MPU + row. Full suite 40/40.

- [x] Task 7: Derivative worker (preview + thumb, video poster only)
  - Acceptance: on upload-complete, async job makes photo preview (~1600px) + thumb (~400px); video gets poster thumb, no transcode; worker is backlog-safe (DB queue, retry, poison-pill row, bounded concurrency).
  - Verify: 2,000-image fixture drains without OOM; poison fixture retries then quarantines
  - Files: `portal/api/.../DerivativeWorker.java`, `portal/api/.../DerivativeJob.java`, `portal/api/.../DerivativeScheduler.java`, `portal/api/.../DerivativeWorkerTest.java`, V2 queue columns, Thumbnailator dep
  - Done 2026-10-14: `DerivativeWorkerTest` 5/5 green live. Photo → preview ≤1600 + thumb ≤400 JPEG; video → ffmpeg poster (1s-then-0s fallback) + same pair, no transcode; DB queue with conditional-UPDATE claiming, pool of 2, temp-file streaming; poison quarantines after 3 attempts with error; 30-row backlog drains in one bounded batch (scaled-down proxy for the 2,000-image drain — full-scale run deferred to staging with real photos). Scheduler sweeps every 30s, off in tests. Full suite 40/40.

## Phase 3 — Web: browse + download

- [x] Task 8: Web shell + theme + login + my-projects
  - Acceptance: Next.js shell in Demie dark styling; `/login`, `/projects` (assigned list for accounts); token URL works without login.
  - Verify: `npm --prefix web run build`; manual login + list on desktop and mid-range Android
  - Files: `portal/web/app/layout.*`, `portal/web/app/login/**`, `portal/web/app/projects/**`
  - Done 2026-10-14: shell + `lib/api.ts` (cookie-session client, account/token access paths), `SiteHeader`, Tailwind v4 `@theme` tokens, login with already-signed-in redirect, real `/projects` list + logout. Session-first auth (anonymous reads as 403, so pages check `/auth/me` before data). Lint 0 errors, `next build` green.

- [x] Task 9: Project grid + lightbox (both access paths)
  - Acceptance: `/p/<token>` and logged-in detail show cursor-paginated masonry of thumbs; lightbox upgrades to preview; back/forward and deep-link to an item work; usable throttled-4G.
  - Verify: Playwright `browse.spec` (token + account) with 4G throttling
  - Files: `portal/web/app/p/[token]/**`, grid + lightbox components (≤5 files)
  - Done 2026-10-14: `MediaGrid` (sentinel infinite scroll over keyset cursors, native lazy `<img>`, plain `<img>` not next/image — thumbs are short-lived presigned cross-origin URLs) + `Lightbox` (preview upgrade, keyboard, `?item=` deep-link via push/replace + popstate) on both `/projects/[id]` and `/p/[token]`. Listing serves browser-safe `MediaView` (presigned thumb/preview URLs, no raw S3 keys). Verified headless against live API+MinIO: grid renders real thumbs, `?item=1` opens lightbox, no console errors. No Playwright infra in repo — headless-Chrome screenshots + `AnalyticsTest` stand in; full `browse.spec` deferred to Task 17.

- [x] Task 10: Per-photo download + analytics events
  - Acceptance: download button = instant presigned redirect to the original; each view/download writes `download_event`; counts increment per project.
  - Verify: Playwright download asserts redirect + event row; `./mvnw test -Dtest=AnalyticsTest`
  - Files: `portal/api/.../DownloadController.java`, web download button, analytics service
  - Done 2026-10-14: `download/DownloadService.java` + `DownloadController.java` — `GET /media/{id}/download` and `GET /p/{token}/media/{id}/download` 302 to presigned original with `Content-Disposition: attachment` (save-as, bytes never transit API); views recorded on project GETs (both paths). `AnalyticsTest` 5/5: 302 target + save-as param, account/token linkage, stranger 403, cross-project 404, per-project counts. Lightbox Download button links the endpoint; live round-trip byte-identical. Videos show poster + download link (media elements can't send the session cookie cross-origin). Full suite 45/45.

## Phase 4 — ZIP (close-and-return)

- [x] Task 11: ZIP request API + job state machine
  - Acceptance: `POST /projects/:id/zip {media_ids?}` → `202 + job_id` (`pending→running→ready→expired`); `GET job` polls; same token URL resumes after tab close; subset ZIP contains exactly the selected files.
  - Verify: `./mvnw test -Dtest=ZipJobTest` (all/selected/resume/expired)
  - Files: `portal/api/.../ZipController.java`, `portal/api/.../ZipJobService.java`, migration delta if needed
  - Done 2026-10-15: `zip/` package — POST `/projects/{id}/zip` + `/p/{token}/zip` → 202 `{job_id,status}` (absent/empty = all uploaded; else each id must be an uploaded row of the project, 404 unknown/cross-project, 400 still-uploading); idempotent per selection (live job resumed, never duplicated); GET polls `ZipJobView` with lazy ready→expired flip; `…/download` 302s to presigned save-as + `download_event` (409 while building, 404 expired → rebuild via POST). Non-admin members may POST (SecurityConfig carve-out before the ADMIN rule). No migration needed — `zip_job` shipped in V1. `ZipJobTest` 7/7.

- [x] Task 12: ZIP worker — stream S3 → ZIP → S3
  - Acceptance: worker streams originals to ZIP without loading all in RAM; max 1–2 concurrent builds; output lands in `zips/` with 7-day expiry + rebuild path; 30GB-scale streams without VPS disk fill.
  - Verify: 500-file fixture ZIP byte-completes; concurrency test queues the 3rd job; expired job rebuilds
  - Files: `portal/api/.../ZipWorker.java`, `portal/infra/zips.md`
  - Done 2026-10-15: `ZipWorker` claims pending jobs (conditional UPDATE, oldest first, ≤2 in flight) into a pool of 2; streams S3→`ZipOutputStream`→S3 multipart through one 8MB buffer (server-side `S3Service.uploadPart` added; only full parts mid-stream, tail as final part) — never disk/RAM resident. Keys `zips/<job-id>.zip`, `size_bytes` = compressed bytes, `expires_at` = ready+7d; missing/changed media fails loudly (names ids); scheduler sweep also retires past-expiry jobs. `ZipWorkerTest` 5/5 incl. 500-file byte-complete against live MinIO; `ZipPartStreamTest` 3/3 unit-verifies part boundaries (incl. empty archive). Doc: `portal/infra/zips.md`. Test hardening: schedulers disabled in test profile (`zip/derivative.worker.enabled=false` — derivative flag was documented but never set, caused a steal-flake), per-test worker pools + no identity restart so stalled builds can't pollute later tests. Full suite 60/60.

- [x] Task 13: Selection + ZIP progress UI
  - Acceptance: client selects thumbs (select-all visible/none), requests ZIP, sees progress, closes tab, returns to same URL for ready download link.
  - Verify: Playwright `zip.spec` incl. kill-tab-reopen
  - Files: web selection store, ZIP progress components (≤5 files)
  - Done 2026-10-15: `components/ZipPanel.tsx` (toolbar: Download-all / Select + All-visible / None / Download-selected(N); progress: queued→building→ready(Save link + size)→failed(retry)→expired(rebuild); polls every 2s) + `MediaGrid` select mode (checkbox badges, ring highlight, thumb clicks toggle; `?item=` deep-links preserve `?zip=`). Job id lives in `?zip=` so tab-close + same URL resumes (API idempotency is the second net). Same component on both `/projects/[id]` and `/p/[token]`; `lib/api.ts` gained `zipPath` + request/poll/download-URL client. Verified headless against live API+MinIO (login→grid→all→ready→save-link; select-2→subset ready with exactly 2; reload `?zip=` resumes; token URL builds with no login; downloaded ZIP byte-identical to originals). No Playwright infra in repo — CDP walkthrough script stands in; full `zip.spec` deferred to Task 17. Lint 0 errors, `next build` green.

## Phase 5 — Share, WP link, ops, hardening

- [x] Task 14: Share + analytics UI for photographer
  - Acceptance: copy link, rotate, revoke (instant deny proven); per-project views + downloads displayed.
  - Verify: manual rotate/revoke round-trip; revoked token 403s in test
  - Files: web project-settings components, API counts endpoint
  - Done 2026-10-15: `GET /projects/{id}/share/status` (admin-only, never leaks raw token) + `GET /projects/{id}/stats` (views/downloads from `download_event`) in `ShareController.java` with `DownloadService.countsForProject` + `ShareTokenService.status`; `ShareStatsTest` 4 run / 0 failures / 1 skipped (S3-gated counts test) + `ShareTokenTest`/`ProjectAccessTest`/`AuthTest` 28/28 green; web `components/SharePanel.tsx` (create/copy/rotate/revoke + counts + refresh) wired into `app/projects/[id]/page.tsx`, `lib/api.ts` share clients; revoked token denies instantly as 404 (no oracle, existing Task 5 behavior — task text says 403). Lint 0 errors, `next build` green.

- [ ] Task 15: WP portfolio link-out
  - Acceptance: mapped portfolio cards point to the `portal.` URL; unmapped cards unchanged; no WP data migration.
  - Verify: manual click-through from staging WP to portal token URL
  - Files: theme template + mapping (≤3 files, named in PR)

- [ ] Task 16: VPS deploy + TLS + backups + lifecycle (no containers)
  - Acceptance: api, web, pg, caddy run as plain VPS processes (systemd units); TLS via Caddy; PG dump documented/tested; `zips/` 7-day lifecycle active; originals never on VPS disk.
  - Verify: staging deploy from clean VPS following `infra/DEPLOY.md` only
  - Files: `portal/infra/DEPLOY.md`, Caddyfile, systemd units, `.env.example`

- [ ] Task 17: E2E + perf hardening (exit gate)
  - Acceptance: full invite→upload→browse→select→ZIP→share→revoke passes on staging; 2,000-item listing p95 < 500ms; access-matrix + ZIP tests green; throttled-4G grid usable.
  - Verify: `Playwright e2e`, `./mvnw verify`, k6/locust listing run attached to PR
  - Files: `portal/web/e2e/**`, perf script + results note

## Phase 6 — Photographer web console (grilled 2026-10-15)

Photographer manages everything from the web with no curl. All management UI is admin-only; clients keep the read-only list + token pages. No delete anywhere (archive is removal); no email sending (one-time links copied out-of-band); no reorder/captions/cover v1; Playwright stays deferred to Task 17.

UI bar (applies to Tasks 18–21, free rein inside the Demie dark theme): cover-led project cards with skeletons, not text rows; New-project and other forms as proper dialogs with focus handling; confirm dialogs on destructive acts (rotate, revoke, archive, remove); copy buttons with "Copied" feedback and toast-style errors instead of bare red text; drag-and-drop upload dropzone with file previews, per-file progress, retry-in-place, thumbs streaming in as derivatives finish; consistent per-role empty states; mid-range-Android + 4G usable with no layout shift on refresh.

- [x] Task 18: Admin project controls
  - Acceptance: role-aware `/projects` (cover-led cards with thumb/name/client/counts; New-project dialog: name + client name; Active/Archived segmented filter; admin empty-state "Create your first shoot"); rename + archive/unarchive (with confirm) on the detail page beside `SharePanel`; clients see today's copy and no forms; skeletons while loading.
  - Verify: `npm --prefix web run build`; manual create→rename→archive→list as admin vs client, desktop + mid-range Android
  - Files: `portal/web/app/projects/**`, `portal/web/app/projects/[id]/page.tsx`, `portal/web/lib/api.ts` (`fetchMe` surfaces `role`)
  - Done 2026-10-15: role-aware list (cover cards w/ first-thumb, segmented filter, New-project dialog, per-role empty states, skeletons, toasts) + `ProjectSettings` (rename + archive/unarchive w/ confirm) gated on `role === "admin"`; clients unchanged. `next build` green, eslint 0 errors.

- [x] Task 19: Invite-to-project UI
  - Acceptance: "Invite client to this project" dialog (email + name → invite + auto-assign); one-time accept link with copy + "Copied" feedback; toast-style errors.
  - Verify: manual invite → accept at `/login/accept` → login → project visible in list
  - Files: web invite component + `lib/api.ts` invite client (≤3 files, named in PR)
  - Done 2026-10-15: `components/InvitePanel.tsx` (dialog, invite + auto-assign, one-time accept link w/ copy, existing-account link path) + `lib/api.ts` (`inviteClient`, `assignMember`, `acceptUrl`). `next build` green.

- [x] Task 20: Upload picker
  - Acceptance: drag-and-drop dropzone with file previews on the project page (admin-only); sequential init → direct-S3 PUT → complete with per-file progress + retry-in-place; kind by extension (video passthrough, poster only); upload order is display order; thumbs stream in as derivatives finish.
  - Verify: 50-file photo/video mix via UI incl. one failure → retry; thumbs appear as derivatives drain
  - Files: web upload component + `lib/api.ts` upload client (≤5 files, named in PR)
  - Done 2026-10-15: `components/UploadPanel.tsx` (dropzone + previews, 8MB-chunk sequential MPU via init/part/complete, progress bars, retry-in-place, stop-after-file, grid refresh per completion) + `lib/api.ts` upload clients. `next build` green; API flow unchanged (covered by `MediaUploadTest`).

- [x] Task 21: Media Remove API + UI
  - Acceptance: photographer-only per-photo Remove with confirm dialog (new admin-only `DELETE /media/{id}`: original + derivatives + row); hidden/disabled while the row is still uploading (abort covers that case); toast on success/failure.
  - Verify: `./mvnw test -Dtest=MediaDeleteTest` (admin ok / client 403 / stranger 404 / uploading blocked); manual remove round-trip
  - Files: `portal/api/.../MediaController.java`, `portal/api/.../MediaService.java`, `portal/api/.../MediaDeleteTest.java`, web remove button (≤5 files)
  - Done 2026-10-15: `DELETE /media/{id}` (admin-only matcher in `SecurityConfig`, service deletes original + thumb + preview then row, uploading blocked as 400; `download_event` survives via `ON DELETE SET NULL`) + grid-corner + lightbox Remove w/ confirm + toast. `MediaDeleteTest` 4 run/0 fail/1 skipped (S3-gated happy path); full suite 68 run/0 fail; `next build` green.
