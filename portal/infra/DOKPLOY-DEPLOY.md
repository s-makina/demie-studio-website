# Dokploy deployment (portal)

The portal deploys as one Dokploy **Compose** service from this repo: API +
web, with Postgres as a shared Dokploy **Database** service (one instance for
all apps on the server) and MinIO from the existing Dokploy install (see
`buckets.md`). `docker-compose.yml` at the repo's `portal/` directory is the
source of truth; this file is the click-order for the Dokploy UI.

## 0. One-time: serve MinIO S3 API over https

The web app is served over https, and the browser fetches presigned objects
directly from `S3_ENDPOINT`. An `http://` endpoint inside an `https://` page is
mixed content and the browser blocks those requests.

In Dokploy: MinIO service → **Domains** tab → add a domain for the S3 API
(anything resolving to the server, e.g. `minio-api.<hash>-102-202-45-205.sslip.io`
like the existing MinIO names) → service `minio` / port `9000` → enable
certificate. Keep the console (port 9001) domain separate; see buckets.md.
The resulting `https://…` URL is the `S3_ENDPOINT` below.

## 1. Push the deployment files

Commit and push `portal/docker-compose.yml`, `portal/api/Dockerfile`,
`portal/web/Dockerfile`, and the two `.dockerignore` files. Dokploy builds from
GitHub, so these must be on the branch you deploy.

## 2. Create the shared Postgres (once for all apps)

Dokploy → **Create Service** → **Database** → **Postgres**. Name it e.g.
`shared-postgres` (16 is fine). This is the *managed* database card — not a
template or compose — so it lands on `dokploy-network` with a stable internal
hostname you can see on its page (Advanced / Internal Information).

Copy from that page now, you'll paste them in step 4: the **internal
hostname**, the **database name / user / password** the service was created
with (or its revealed credentials). Enable its **Backups** tab too —
server-wide databases want backups on a schedule.

One instance, many apps: each app just gets its own **database name** (and
ideally its own user) on this instance. Create extra databases/users via
pgAdmin connected to this service, or let the app's migrations own one DB
(`DB_NAME=demie_portal` here). No second Postgres instance per app.

## 3. Create the service (Compose)

Dokploy → **Create Service** → **Compose**:

- provider GitHub, repo `s-makina/demie-studio-website`, branch `main`
- Compose file path: `portal/docker-compose.yml`

## 4. Environment (Env tab)

Paste and fill; no quotes needed (the API parses values as plain strings).
`DB_*` comes from the shared Postgres created in step 2:

```
DB_HOST=<shared-postgres internal hostname>
DB_PORT=5432
DB_NAME=demie_portal
DB_USERNAME=<shared postgres user>
DB_PASSWORD=<shared postgres password>
S3_ENDPOINT=https://minio-api-...sslip.io
S3_ACCESS_KEY=<from infra/.env>
S3_SECRET_KEY=<from infra/.env>
WEB_ORIGIN=https://<web-domain>
NEXT_PUBLIC_API_URL=https://<api-domain>
ADMIN_EMAIL=<photographer email>
ADMIN_PASSWORD=<generate>
```

The compose file defaults the rest (`S3_BUCKET_*` → `demie-studio`). The
credentials must be able to create tables in `demie_portal` (the service's
default user owns its database, which qualifies; a per-app user created via
pgAdmin must own that database).

## 5. Deploy, then add Domains

Click **Deploy** → the stack builds both images; Flyway applies migrations at
first API start and `S3BucketBootstrap` creates the bucket.

**Domains** tab, two entries on the same service:

| Domain (container) | Port | Notes |
|---|---|---|
| `demie-portal-<hash>-102-202-45-205.sslip.io` | 3000 | the web app |
| `demie-portal-api-<hash>-102-202-45-205.sslip.io` | 8080 | the API |

Enable https for both. `WEB_ORIGIN` must match the web domain exactly
(scheme included), and `NEXT_PUBLIC_API_URL` the API domain.

If you set the domain values after the first deploy: **Redeploy** — the web
image must rebuild so `NEXT_PUBLIC_API_URL` is baked into the client bundle.
Changing it without rebuilding does nothing.

## 6. Verify

1. `api` logs: Flyway ran migrations, `S3BucketBootstrap` did not fail.
2. Open the web domain → log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`, then
   change that password in the portal.
3. Upload a photo in a project → confirm the object lands in MinIO `originals/`
   and the thumbs/preview derivative workers produce it.

## 7. Ongoing updates

Push a commit → **Redeploy** in Dokploy. Images rebuild, the shared Postgres
service and MinIO objects persist, and migrations apply automatically. Keep
API replicas at 1 — the scheduled derivative/ZIP workers and reapers run
in-process and double up if scaled.

## Notes

- Next.js web builds with `output: "standalone"` (`web/next.config.ts`); the
  image runs the self-contained `server.js`, no `next start`.
- `SPRING_PROFILES_ACTIVE=prod` keeps `application-local.yml` (and its
  `infra/.env` import) out of the deployed run; the compose `environment:` block
  is the single source of runtime config.
- Local `infra/.env` stays a dev-only convenience; committed/deployed secrets
  live only in the Dokploy Env tab.
