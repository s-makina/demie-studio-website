# Plan: Paginated project list

Implements `docs/superpowers/specs/2026-10-20-project-list-pagination-design.md`.
Work in `portal/` only. No migration. Verify with `portal/mvnw -q -pl api test`
after the API step and `npm run build && npm run lint` in `portal/web` after the
web step (repo AGENTS.md: consult `node_modules/next/dist/docs/` in
`portal/web` before writing code there).

## Step 1 — API: cursor + page plumbing in `ProjectService`

- New public record in `ProjectService`:
  `record ProjectPage(List<ProjectResponse-source rows>, long total, long activeCount, long archivedCount, String nextCursor)`
  — service-level page carries raw `Project` rows + aggregates; the controller
  maps to its own response record (mirrors `MediaPage` → media controller map).
- New `encodeCursor(long id)` / `decodeCursor(String)` static helpers:
  base64url of the decimal id; blank → first page; malformed →
  `IllegalArgumentException` (existing `400 invalid_request` mapping).
- Replace `listFor(Account)` with
  `public ProjectPage listFor(Account account, String statusFilter,
  String cursor, int limit)`:
  - `statusFilter` accepts `active | archived | all` (case-sensitive; anything
    else → `IllegalArgumentException`). Default when null/blank: `all`.
  - Admin branch SQL:

    ```
    SELECT {COLUMNS} FROM project
    WHERE (?all OR status = ?) AND (?noCursor OR id < ?)
    ORDER BY id DESC LIMIT ?
    ```

    bind `limit + 1` to detect exhaustion (`@RequestParam` unaffected).
    Do all branching in small helper booleans or two string variants — avoid
    a `?all OR ...` trick if it defeats the index; prefer building the WHERE
    text from at most two variants (with/without filter) and with/without
    cursor. Keep it explicit.
  - Client branch SQL (unchanged security semantics + cursor):

    ```
    SELECT p.{COLUMNS} FROM project p
    JOIN project_member m ON m.project_id = p.id
    WHERE m.account_id = ? AND p.status = 'active' AND (?noCursor OR p.id < ?)
    ORDER BY p.id DESC LIMIT ?
    ```
  - Page assembly: rows > limit ⇒ `hasNext`; cut to `limit`;
    `nextCursor = hasNext ? encode(last.id) : null`; `total` = COUNT of the
    visible set matching `statusFilter` (0 for `all` → both counts summed is
    not needed: `all` count = active + archived for admin; for clients only
    active is visible). `counts` = `(activeCount, archivedCount)` via two
    COUNT queries scoped exactly like the list WHERE clauses.
  - Order note: `id DESC` is now canonical; update the class javadoc
    ("admins see everything" list semantics).
- Keep the old single-project methods untouched.

## Step 2 — API: `ProjectController.list` wiring

- `list(Authentication, @RequestParam(defaultValue="all") String status,
  @RequestParam(required=false) String cursor,
  @RequestParam(defaultValue="24") int limit)` with the media conventions:
  `limit < 1` → default, `> 200` → 200 (do the clamping in the controller
  like `MediaService.page` does — either place is fine, but pick the service
  for consistency with `MediaService`).
- Response:

  ```
  Map.of / LinkedHashMap:
    "items":      List<ProjectResponse>,
    "nextCursor": page.nextCursor() == null ? "" : page.nextCursor(),
    "total":      page.total(),
    "counts":     Map.of("active", ..., "archived", ...)
  ```

  Same null→`""` convention the media controllers already apply.
- Update `ProjectController.list` javadoc: cursor-keyset page, newest first.

## Step 3 — API tests

New `portal/api/src/test/java/.../project/ProjectListPaginationTest.java`
(follow the existing suite's DB-backed style; schedulers disabled profile as
elsewhere):

1. Seed ≥ 27 projects as admin (+ one archived, one more) →
   - default call (`status=all`, no cursor): 24 items newest first,
     `nextCursor != ""`, `total = 27`, counts correct;
   - follow the cursor chain to `nextCursor == ""`; concatenated pages have
     no repeats/gaps and cover all 27 in descending id order.
2. `status=active`: only active rows; `status=archived`: only archived;
   totals/counts match.
3. Invalid cursor → 400 (`invalid_request`); `status=bogus` → 400;
   `limit=0` → default page; `limit=100000` → clamped page.
4. Client account: same endpoint, `status=archived`/`all` still returns only
   active memberships; counts show archived = 0.
5. Existing access matrix untouched (anonymous 401 rules unchanged).

Run the full suite (`mvnw -q -pl api test`) — everything else must stay green.

## Step 4 — Web: `lib/api.ts`

- Add `ProjectPage` type next to `MediaPage`:

  ```ts
  export interface ProjectPage {
    items: Project[];
    /** Next keyset cursor; empty string when the page ran out. */
    nextCursor: string;
    total: number;
    counts: { active: number; archived: number };
  }
  ```

- Replace `fetchProjects(signal)` with
  `fetchProjects(status, opts: { cursor?, limit?, signal? } = {}): Promise<ProjectPage>`
  building `URLSearchParams({ status, limit: String(limit ?? 24) })`, setting
  `cursor` when non-empty (same shape as `fetchMediaPage`).
Grep for other `fetchProjects(` callers and migrate each to the page shape.

## Step 5 — Web: `app/projects/page.tsx`

- State: `projects: Project[] | null` (accumulated), `nextCursor: string`,
  `filter` stays (`"active" | "archived" | "all"`, default `"active"` per
  current UI), `loadingMore: bool`. Reuse the AbortController effect pattern;
  abort on unmount and on effect re-runs.
- Initial effect (after `fetchMe`): `fetchProjects(filter, { signal })` →
  replace state.
- `onLoadMore`: fetch with `{ cursor: nextCursor }`, append items, store
  `nextCursor`; guard against double-taps (`loadingMore`), ignore stale
  responses after a filter change (compare a request-generation ref, or rely
  on abort + try/catch `isAbort`).
- Filter tab click: setFilter + refetch page 1 (replace) — put the fetch in
  the main effect keyed on `[filter, me]` so tab switches reuse one code path.
- "Load more" button under the grid, rendered while
  `projects !== null && nextCursor !== ""`; disabled + subtle spinner while
  `loadingMore`.
- `visible` selector simplifies: the server already filtered by `status`, so
  render `projects` directly (keep the admin/client distinction: for clients
  the API ignores the tabs anyway — they see memberships only, and the header
  copy stays as today).
- Empty states: keep all three branches (no projects / no {filter} shoots /
  client empty). Server-side they now trigger on the first page being empty
  for that filter.
- Card thumbnails (`ProjectThumb`'s `?limit=1` fetch) unchanged.

## Step 6 — Web verification + manual pass

- `npm run build`, `npm run lint` in `portal/web`.
- Manual: sign in as admin → first page renders, Load more appends, switches
  tabs refetch, create-shoot prepends and navigates (newest-first ordering);
  client account: only their active shoots, no tabs regressions.

## Notes / pitfalls

- `ProjectResponse` gains `createdAt`/`updatedAt` already present — unchanged.
- Do NOT leave any caller reading `GET /projects` as a bare array; grep
  `fetchProjects(` after the refactor to confirm every caller consumes the
  page shape.
- Keep the diff small: no rename churn, no drive-by refactors elsewhere.
