# Paginated project list (admin scale-proofing)

Date: 2026-10-20
Status: approved, design

## Problem

`GET /projects` returns every project the caller can see in one unbounded,
unpaginated array. For clients that slice is inherently tiny (their active
memberships), but for the admin branch it is the whole `project` table — one
row per shoot, forever. The Projects page fires one `?limit=1` media request
per card for covers, so cost grows per project on every page load. The media
listing is already keyset-paginated; the project list is the last unpaginated
read path in the portal.

## Scope

- One endpoint (`GET /projects`), one web page (`app/projects/page.tsx`), one
  API client function (`fetchProjects`). No migration — query-only change.
- **UX decision (user-approved):** "Load more" button appends cursor pages.
- **Filter decision (user-approved):** the active/archived/all tabs move
  server-side (a `status` param) so pagination and filtering see the same set.
- **Coverage decision (user-approved):** clients are paginated through the
  same endpoint and response shape; their pages are just short slices.

## Design

### 1. API — `ProjectService.listFor` + `ProjectController`

`GET /projects?status=&cursor=&limit=` returns a page instead of an array:

```
{ items: [ProjectResponse], nextCursor: string,
  total: number, counts: { active: n, archived: n } }
```

- **Keyset cursor on `id` descending** (mirrors `MediaService.page`, minus the
  `sort` tiebreaker — `id` is unique): `WHERE (id < ?) ORDER BY id DESC LIMIT
  ?+1`. The extra row decides exhaustion. `nextCursor` is the base64url of
  the last returned id, and follows the exact media convention seen by
  `web/lib/api.ts`: the empty string (`""`) when the page ran out. Blank
  cursor = first page; invalid cursor → `IllegalArgumentException` → existing
  `400 invalid_request` mapping.
- **New query param `limit`** (default 24, max 200; `< 1` falls back to the
  default — same convention as media).
- **New query param `status`** (`active` | `archived` | `all`, default `all`):
  an `AND (status = ?)` filter on the admin branch (omitted for `all`).
  Default `all` preserves current behavior for bare API calls.
- **New param `cursor`** on the controller; both branches share one
  `page(...)` helper in `ProjectService`.
- **Client branch** keeps its security semantics unchanged
  (`JOIN project_member ... AND p.status = 'active'`) and adds the same
  `id < ?` cursor clause — so for clients `status` is effectively a no-op
  beyond `active`, and an `archived`/`all` request just returns their (short)
  active memberships. Do not widen client visibility; the join clause stays.
- **`total`** = COUNT of the caller's visible projects matching the requested
  status (one `COUNT(*)` per request).
- **`counts`** = `{ active, archived }` counts of the caller's visible set
  (admin: whole table; client: their memberships, active only → archived is
  always 0 for clients). Powers the web tabs without an extra round trip.
- **Ordering flips to newest first:** previously `ORDER BY id` (oldest first);
  `id DESC` makes new shoots land at the top, matching the existing
  `onCreate` prepend. ProjectResponse shape is unchanged.

### 2. Web

- **`lib/api.ts`**
  - New `ProjectPage` type (`items`, `nextCursor: string` — `""` exhausted,
    same as `MediaPage`, `total`, `counts`).
  - `fetchProjects(status, { cursor?, limit?, signal? })` → `ProjectPage`.
  - Callers that want "all projects" semantics are updated to consume pages;
    today there is exactly one caller (the Projects page).
- **`app/projects/page.tsx`**
  - State: `projects: Project[]` (accumulated pages of the current filter),
    `nextCursor`, `loadingMore`, plus a refetch on filter change.
  - **Load more:** a button under the grid, visible while `nextCursor !== ""`,
    fetches the next cursor for the current `status` and appends. Subtle
    spinner while fetching.
  - **Filter tabs:** refetch page 1 for the new `status` (replace, not
    append), with the AbortController pattern already used on this page.
    Empty states per filter stay, driven off the fetched page + `counts`.
  - **Create flow unchanged:** the created project still prepends to the
    current list and navigates to the detail page (it is guaranteed visible:
    newest first, and the admin just created it).
- Per-card thumbnails keep their unchanged `?limit=1` media fetch.

### 3. Kept deliberately out of scope

- No `offset` seek — a flat shoot list has no scrubber to seek with.
- No `endCursor` resume anchor — nothing concurrently mutates the tail the
  way uploads do for media (an insert shifts nothing for a descending `id`
  keyset; deleting the row under the cursor is handled by the standard
  keyset rule: the next page starts strictly below the cursor id).
- No pagination on `GET /projects/{id}` or the media endpoints (done).
- No numbered pages / infinite scroll.

## Trade-offs accepted

- A breaking API-shape change to `GET /projects` (array → object). Only
  portal web consumes it today; no cache layer. Removed `fetchProjects`'
  bare-array return instead of adding a parallel endpoint.
- Ordering change (oldest → newest first) is intentional; inconsistent only
  with stale browser tabs already open across the deploy, which refetch on
  next page load.
- `counts` costs one extra aggregate per request (two for the default `all`
  path); at project-per-shoot scale this is negligible against the media
  grid's per-card fan-out that actually motivated this change.

## Verification

- `portal/mvnw -q -pl api test` — full suite stays green, plus a DB-backed
  `ProjectService`/`ProjectController` test covering: page progression across
  cursors (no repeats, no gaps), `limit + 1` exhaustion (`nextCursor` null on
  the last page), `status` filter correctness for the admin branch, client
  still sees only active memberships regardless of `status`, `total`/`counts`
  correctness, invalid cursor → 400, and `limit` clamping.
- `portal/web`: `npm run build` + `npm run lint` (existing project gates).
- Manual check: admin loads the Projects page, sees the first page, taps
  Load more, switches tabs (refetch), creates a shoot (prepends + navigates).
