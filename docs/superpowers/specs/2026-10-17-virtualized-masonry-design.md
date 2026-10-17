# Virtualized masonry, seek rail, and seamless lightbox

Date: 2026-10-17
Status: approved, implemented

## Problem

`MediaGrid.tsx` appends every cursor page into one flat `items` array and renders
them all inside a CSS `columns-*` masonry container. Initial load is fast (60
items, keyset paged), but scrolling through a shoot with 10,000+ photos grows the
DOM without bound: the tab stutters, memory climbs, and mobile browsers
eventually kill it. Two follow-on problems: the lightbox can only navigate inside
the already-loaded window, and reaching far into a large archive requires
scrolling through it.

The backend is already scale-proof: keyset pagination on `(sort, id)` over
`media_project_sort_idx`, in-memory presigned URLs, streamed ZIP workers.

## Scope and language

A **Project** is one shoot (see `portal/CONTEXT.md`). There are no albums or
milestones, so the only timeline axis is `created_at`; the rail groups by
**upload day**.

## Design

Three upgrades, no new npm dependency (a row/column virtualizer cannot lay out a
masonry; the packing is custom either way).

### 1. Backend (additive, backwards-compatible)

| Change | Detail |
| --- | --- |
| `V3__media_dimensions.sql` | `thumb_width` / `thumb_height` on `media` + partial index for the backfill queue. |
| `Media.java` | + `thumbWidth`, `thumbHeight`. |
| `DerivativeWorker` | Reads the thumb's pixel size from the JPEG it just wrote (correct for HEIC/video because it reads the derivative, not the original) and records it in the `ready` UPDATE. `backfillDimensions(batch)` re-reads dims for legacy `ready` rows; unreadable rows get `0` so they leave the queue. |
| `DerivativeScheduler` | Also calls `backfillDimensions` each sweep. |
| `MediaService` | Dims in `COLUMNS`/`MAPPER`; `MediaView` + `thumbWidth`, `thumbHeight`, `createdAt`; `MediaPage` + `total`, `endCursor`; `page()` accepts an optional `offset` for seeks; `view`/`viewByToken` for single-item URL recovery. |
| `MediaController` / `ShareController` | Accept `offset`; return `total` + `endCursor`; new `GET /media/{id}` and `GET /p/{token}/media/{id}`. |

Keyset paging stays the default path; `offset` is only used for seeks.
`endCursor` is the cursor positioned past the page's last row even on the final
page, so "resume after upload" needs no client knowledge of the cursor format.

### 2. Frontend

- **`lib/masonry.ts`** — incremental packer. Greedy shortest-column placement
  using the exact aspect ratio (`thumbWidth/thumbHeight`, clamped, fallback 3:2,
  video 16:9). Full-width upload-day dividers reset every column. Per-column
  arrays are top-sorted, so the visible range costs `O(columns·log n + k)`; pages
  append in `O(new items)` with no global re-sort.
- **`components/MasonryGallery.tsx`** — `ResizeObserver` column measurement,
  rAF-throttled window-scroll virtualization (overscan ~1500px), absolutely
  positioned tiles in a container whose height is the packed height, floating
  current-day chip, `onApproachEnd` near the container bottom, `visibleIds()`
  through a ref.
- **`components/TimelineRail.tsx`** — fixed right-edge `role="slider"` rail:
  thumb = loaded window as a share of `total`, position = current absolute index,
  ticks for visited days, drag to preview then seek on release, arrow keys.
- **`components/MediaGrid.tsx`** (rewritten) — data window (`items`,
  `originOffset`, `total`, forward cursor, `exhaustedForward`), `loadForward()`
  (keyset) and `seek(offset)`, selection preserved across seeks, upload refresh
  resumes from `endCursor` without discarding the window, `img onError` refreshes
  an expired presigned URL in place.
- **`components/Lightbox.tsx`** — absolute counter (`originOffset + index + 1` of
  `total`); `next` at the window edge triggers `loadForward` and auto-advances;
  disabled edges follow the real window.
- **`lib/api.ts`** — new types, `fetchMediaPage(access, { cursor, offset, limit,
  signal })`, `fetchMediaItem`.

## Trade-offs accepted

- Masonry distribution changes subtly: greedy left-to-right (Google Photos /
  Masonry.js style) instead of CSS `columns` column-fill. Still a true masonry,
  better chronological reading order, not pixel-identical.
- Dividers are in-flow, not CSS-sticky (impossible inside an absolutely
  positioned virtual container); the pinned day chip gives the sticky feel.
- No layout shift: heights come from real dimensions, so tiles never resize after
  paint.
- Seeked windows are forward-only (the API has no reverse cursor); the rail is the
  way back, plus a "Return to start" affordance when `originOffset > 0`.
- Legacy photos already `ready` have no dimensions until the backfill reaches
  them (`DERIVATIVE_BACKFILL_BATCH`, default 200, every 30s); meanwhile they use
  the fallback aspect. New uploads are immediate.

## Verification

`portal/mvnw -q -pl api test`, `npm run build`, `npm run lint`, plus a DB-backed
paging test for `offset`/`total`/`endCursor`.
