# Local setup (no Docker)

Phase 0 runs Postgres 16 and MinIO as manual local processes. The API and web
are configured through env — there is no Docker Compose in this phase.

## 1. Postgres 16

Install Postgres 16 locally (apt, homebrew, etc.) and start the server. Then:

```bash
# Create the database the API will use.
createdb -U postgres demie_portal
# Test database used by the test profile (application-test.yml).
createdb -U postgres portal_test
```

## 2. MinIO (S3-compatible)

The spec says MinIO runs as a manual local process for Phase 0. One option:

```bash
# Start a MinIO server on a local data directory.
minio server /tmp/minio-data --address ":9000" --console-address ":9001"
```

Create the single bucket the API expects (`demie-studio`). Object classes
are key prefixes inside it (`originals/`, `previews/`, `thumbs/`, `zips/`);
the API creates objects under `demie-studio/originals/<project>/<media>...`.
Use the MinIO console or `mc` to create it:

```bash
mc alias set local http://localhost:9000 <access-key> <secret-key>
mc mb --ignore-existing local/demie-studio
```

For prod, bucket policy and lifecycle (7-day expiry on `zips/`) are documented
in `buckets.md`. Local Phase 0 does not enforce lifecycle.

## 3. Environment

Copy `infra/.env.example` to a local `.env` (or export the variables) and fill
in the values that match your local Postgres and MinIO.

### API

The datasource is built from these env vars (defaults target local dev):

```bash
export DB_HOST=localhost
export DB_PORT=5432
export DB_NAME=demie_portal
export DB_USERNAME=postgres
export DB_PASSWORD="<your-postgres-password>"

export S3_ENDPOINT="http://localhost:9000"
export S3_ACCESS_KEY="<access-key>"
export S3_SECRET_KEY="<secret-key>"
export PORT=8080
```

Start the API:

```bash
cd portal
./mvnw spring-boot:run -pl api
```

Spring Boot does **not** read `.env` files, so exporting these variables (or
passing them some other way) is required — editing `infra/.env` on its own has
no effect, and the API will now refuse to start rather than send uploads to
AWS. On Windows `scripts/run-api.ps1` does the exporting for you:

```powershell
.\scripts\run-api.ps1
```

It imports the `S3_*` lines from `infra/.env` only, for the same reason
`scripts/upload-fixture.sh` does: the file also carries DB values that must not
override the `application.yml` defaults.

### Flyway (schema)

The Maven Flyway plugin reads host/port/db/user from Maven properties
(`-Ddb.host=…`, `-Ddb.name=…`) and the password from the `FLYWAY_PASSWORD` env
var. Apply migrations with:

```bash
cd portal
FLYWAY_PASSWORD="<your-postgres-password>" ./mvnw flyway:migrate -pl api
FLYWAY_PASSWORD="<your-postgres-password>" ./mvnw flyway:info -pl api
```

The plugin reads migrations from `src/main/resources/db/migration` directly, so
edits take effect without a prior compile. `flyway:clean -pl api` resets the
schema (the `target`-independent dev workflow).

### Web

```bash
export NEXT_PUBLIC_API_URL="http://localhost:8080"
cd portal/web
npm run dev
```

`NEXT_PUBLIC_API_URL` must point at the running API. Without it, `/login` and
`/projects` render a placeholder note.

> **Security note:** `application.yml` and `application-test.yml` currently
> carry a dev-only Postgres password default so the local verify just works.
> Move it to `DB_PASSWORD` env before any shared deployment.

## 4. Verify (Phase 0 Task 0)

```bash
# 1. Start postgres and minio manually (see above).
# 2. From the repo root:
./mvnw -q -DskipTests package -pl api && npm --prefix web run build
```

Expected: `portal-api-0.1.0-SNAPSHOT.jar` produced and `portal/web/.next`
built with routes `/`, `/login`, `/projects`.

## 5. Useful notes

- The API's `S3Config` forces path-style access so it works with a local
  MinIO endpoint. Prod S3 (path or virtual-hosted) is configured the same way
  via env.
- No Docker is required or expected in Phase 0. Later phases may re-evaluate,
  but the spec currently says "no containers at start."
- Java 21 and Maven 3.8+ are required to run `./mvnw`. The wrapper falls back
  to a system `mvn` if the wrapper distribution has not been unpacked.
