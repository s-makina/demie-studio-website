#!/usr/bin/env bash
# Task 6 verify helper: uploads a 50-file fixture through the real browser flow
# (init -> presigned part PUT -> complete) and checks cursor-paginated listing.
# Needs Postgres (portal_test) and S3 env — both come from infra/.env.
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -f infra/.env ]; then
  # Import S3 settings only: infra/.env may carry DB placeholders that must
  # not override application.yml / application-test.yml defaults.
  set -a
  eval "$(grep -E '^S3_' infra/.env)"
  set +a
fi

: "${S3_ENDPOINT:?S3_ENDPOINT must be set (see portal/infra/.env)}"
: "${S3_ACCESS_KEY:?S3_ACCESS_KEY must be set (see portal/infra/.env)}"
: "${S3_SECRET_KEY:?S3_SECRET_KEY must be set (see portal/infra/.env)}"

exec ./mvnw -pl api test -Dtest='MediaUploadTest' -Dsurefire.failIfNoSpecifiedTests=false
