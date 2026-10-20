# Dokploy deployment (portal)

The portal deploys as one Dokploy **Compose** service from this repo: API +
web, with Postgres reused from an existing Dokploy **Database** service and
MinIO from the existing Dokploy install (see `buckets.md`). `docker-compose.yml`
at the repo's `portal/` directory is the source of truth; this file is the
click-order for the Dokploy UI.

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

## 2. Create the service

Dokploy → **Create Service** → **Compose**:

- provider GitHub, repo `s-makina/demie-studio-website`, branch `main`
- Compose file path: `portal/docker-compose.yml`

## 3. Environment (Env tab)

Paste and fill; no quotes needed (the API parses values as plain strings).
The `DB_*` values must come out of the existing Postgres service's page in
Dokploy:

```
# From the Postgres service in Dokploy: its internal DNS name (the
# xxx-yyyy host shown as internal hostname; external sslip.io aliases also
# resolve if they were created) and the database/user/password it was created
# with. The API runs migrations on start, so use credentials with CREATE
# rights (the Dokploy template's default user qualifies).
DB_HOST=<postgres-service-internal-hostname>
DB_PORT=5432
DB_NAME=<database name>
DB_USERNAME=<database user>
DB_PASSWORD=<database password>

S3_ENDPOINT=https://minio-api-...sslip.io
S3_ACCESS_KEY=<from infra/.env>
S3_SECRET_KEY=<from infra/.env>
WEB_ORIGIN=https://<web-domain>
NEXT_PUBLIC_API_URL=https://<api-domain>
ADMIN_EMAIL=<photographer email>
ADMIN_PASSWORD=<generate>
```

The compose file defaults the rest (`S3_BUCKET_*` → `demie-studio`). Prefer
the Postgres service's **internal** hostname for `DB_HOST` — it never leaves
`dokploy-network` and keeps the connection off the public interface.

## 4. Deploy, then add Domains

Click **Deploy** → the stack builds both images and starts; Flyway applies
migrations at first API start and `S3BucketBootstrap` creates the bucket.

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

## 5. Verify

1. `api` logs: Flyway ran migrations, `S3BucketBootstrap` did not fail.
2. Open the web domain → log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`, then
   change that password in the portal.
3. Upload a photo in a project → confirm the object lands in MinIO `originals/`
   and the thumbs/preview derivative workers produce it.

## 6. Ongoing updates

Push a commit → **Redeploy** in Dokploy. Images rebuild, the Dokploy Postgres
service and MinIO objects persist (Dokploy manages the DB volume itself), and
migrations apply automatically. Keep API replicas at 1 — the scheduled
derivative/ZIP workers and reapers run in-process and double up if scaled.

## Notes

- Next.js web builds with `output: "standalone"` (`web/next.config.ts`); the
  image runs the self-contained `server.js`, no `next start`.
- `SPRING_PROFILES_ACTIVE=prod` keeps `application-local.yml` (and its
  `infra/.env` import) out of the deployed run; the compose `environment:` block
  is the single source of runtime config.
- Local `infra/.env` stays a dev-only convenience; committed/deployed secrets
  live only in the Dokploy Env tab.
