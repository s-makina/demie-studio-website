# Local dev configuration read from `infra/.env`

## Problem

`infra/.env` is the documented home for local dev settings (DB, S3, port), but
nothing reads it. Spring Boot does not parse `.env` files, so an edited `.env`
has no effect unless the variables happen to be exported into the environment of
the process that starts the API. `scripts/upload-fixture.sh` papers over this
for one script by grep-and-`eval`-ing the `S3_*` lines; the API itself had no
equivalent.

The failure mode is quiet and expensive: with no `S3_ENDPOINT` and no keys,
`S3Config` builds an **anonymous** client with no endpoint override, so uploads
go to real AWS S3 in `us-east-1` and fail mid-upload with a bare
`NoSuchBucketException`. That is how a local run without exported variables
presented. `S3BucketBootstrap` (added in `e95fa3b`) now fails fast instead, which
turns the silent misroute into a clear start-up error — but it also means the
API will not start until the settings are present in the environment, which is
exactly the step people forget.

`.env` also mixes syntaxes: `.env.example` is plain `KEY=value`, a developer's
copy may quote values shell-style (`DB_PASSWORD='Forcode123!'`), and one line
relies on shell expansion (`FLYWAY_PASSWORD=${DB_PASSWORD}`). Any import has to
be honest about what it accepts.

## Scope and language

- **In scope:** the API reads its local dev settings from `infra/.env` with no
  export step; the test suite stays isolated from that file; the docs say what
  the file may contain.
- **Out of scope:** production configuration (still environment variables), and
  an explicit S3 defaults block in `application.yml` (S3 defaults keep living in
  `S3Config`'s `@Value` fallbacks).
- No new domain nouns. The change is configuration plumbing only.

## Design

### 1. Where the import is declared

`src/main/resources/application.yml` gains one key:

```yaml
spring:
  profiles:
    default: local
```

`local` is unused elsewhere in the repo and in the test suite. It applies only
when no profile is active, so it describes "a developer's machine" precisely.

A new `src/main/resources/application-local.yml` carries the import and nothing
else:

```yaml
# Local development only (activated by spring.profiles.default in
# application.yml). Spring Boot does not read .env files, but it can import
# one: this is what makes the API pick up portal/infra/.env with no export
# step. Both plausible working directories are listed because a file:
# location is resolved against the working directory - portal/api under
# `mvnw spring-boot:run` and surefire, portal under `java -jar`. optional:
# keeps every other environment working when no .env exists.
spring:
  config:
    import:
      - optional:file:../infra/.env[.properties]
      - optional:file:infra/.env[.properties]
```

`[.properties]` is the extension hint that lets a file without a `.properties`
suffix be parsed as properties.

### 2. What `.env` may contain

The file must stay valid in **both** syntaxes, because Spring parses it as
properties and the shell scripts source it:

- `KEY=value`, one per line, no surrounding quotes.
- No shell expansion inside values. `FLYWAY_PASSWORD=${DB_PASSWORD}` is the one
  exception: only the Flyway Maven plugin (which runs with the file sourced)
  reads it, and Spring storing the literal string is harmless because the
  application never resolves it.
- Properties escaping now applies: a backslash is an escape character, a
  surrounding quote becomes part of the value, and the file is read as
  ISO-8859-1. Values that need any of that must come from a real environment
  variable instead (higher precedence, see below) or be escaped.

The developer's `infra/.env` is corrected from `DB_PASSWORD='Forcode123!'` to
`DB_PASSWORD=Forcode123!`, matching `.env.example`. A short note stating the
two-syntax rule goes into `.env.example`.

### 3. Precedence

Environment variables and system properties **beat** the imported file, which
**beats** `application.yml`. So a real deployment's variables always win over a
stray `.env` on disk, and `scripts/run-api.ps1` (exporting the `S3_*` lines)
still takes effect when used. That ordering also means escaping problems in
`.env` can always be worked around by exporting the value instead.

### 4. Test isolation

The test suite activates a profile (`@ActiveProfiles("test")`, defaulted for the
whole test classpath by `src/test/resources/application.properties` and enforced
by `TestProfileGuardTest`). `spring.profiles.default` does not apply when a
profile is active, so `application-local.yml` — and therefore the import — is
never loaded under test. `application-test.yml` is unchanged and the suite keeps
reading only the classpath and that file.

This matters more than tidiness: `application-test.yml` builds its JDBC URL from
`${TEST_DB_NAME:portal_test}`, so a leaked import of a `.env` that names a
different database could point the suite's `TRUNCATE` at the developer database.

### 5. Unchanged

`S3BucketBootstrap` (start-up validation plus bucket creation), the datasource
defaults in `application.yml`, and production behaviour. `scripts/run-api.ps1`
stays as a convenience; its header comment gains a pointer to the import so the
two mechanisms are not confused.

## Trade-offs accepted

- **`.env` is now a properties file as well as a shell fragment.** Its values
  must avoid quoting and backslashes. Escape hatch: export the value as a real
  environment variable, which takes precedence. Documented in `.env.example`.
- **`local` becomes the default profile.** A deployment that sets no profile
  also gets `local`; the import is `optional:` and dev-relative, so it is inert
  there.
- **Two working-directory candidates** are listed rather than resolved
  dynamically. It is one extra line and it keeps `java -jar` usable from
  `portal/`.
- **The import lives in a profile file**, so readers must follow
  `application.yml` → `application-local.yml` to find it. A comment in both
  files carries that thread.

## Verification

1. Start the API with **no** exported variables, from `portal/`:
   `mvnw spring-boot:run -pl api` reaches
   `Object storage ready at http://salvation-minio-…sslip.io (buckets:
   [demie-studio])`, proving the import supplied the credentials.
2. Full suite (`mvnw -o -pl api test`) stays green (73 tests, 17 skipped) and
   `demie_portal`'s `account.created_at` is unchanged across the run.
3. Isolation proof: temporarily set `DB_PASSWORD=<wrong>` in `.env`, run one
   test class — it must still pass, because a leaked import would fail to
   connect; then revert the line. No data risk: a wrong password only affects
   connection attempts.
4. `scripts/run-api.ps1` still starts the API (its exported values simply take
   precedence).
5. `git status` shows no secret values added to tracked files.
