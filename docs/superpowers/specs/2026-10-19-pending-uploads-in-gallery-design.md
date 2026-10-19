# Pending uploads lead the gallery grid

**Date:** 2026-10-19
**Status:** Approved (design), spec under review
**Area:** `portal/web` — project detail page, gallery tab

## Problem

On the project detail page the photographer selects files in the `UploadPanel`, which
lives in the 25%-wide sidebar. The queued files render as a thumbnail grid *inside that
sidebar, under the dropzone*. In practice:

- Thumbnails are tiny (2–4 columns squeezed into a quarter-width column).
- The queue duplicates the gallery's job. The photographer already reads thumbnails in
  the gallery column, so the same mental model is split across two places.
- The gallery gives no signal that new photos are on the way.

Requested change: **show the selected photos in the gallery section, not below the
dropzone.**

## Goal

Queued files appear as the leading tiles of the gallery column, with the same column
widths and 16px gutter as the masonry below them, so the two read as one grid. The
sidebar keeps the dropzone and the upload controls only.

## Non-goals

- No upload-pipeline changes. `initUpload` → part PUTs → `completeUpload`, the
  `clientUploadId` idempotency key, sequential ordering and retry semantics are all
  untouched.
- No changes to the virtualized masonry packer (`lib/masonry.ts`) or
  `MasonryGallery.tsx`. Pending tiles do **not** join the packer. They are not
  paginated, not part of `total`, not day-divided, and do not appear on the timeline
  rail.
- No change for clients (`!isAdmin`), who never see the upload panel.
- No new frontend test framework. This repo has none (`portal/web/package.json`
  exposes only `dev`/`build`/`start`/`lint`) and adding one is out of scope; see
  Verification.

## Decisions

| Decision | Choice |
| --- | --- |
| Where pending tiles render | Top of the gallery column, above `MasonryGallery` |
| After a file finishes | Tile stays, marked **Done**, until `Clear finished` |
| Tile interactions | Hover reveals `Remove` (queued) / `Retry` (failed); no lightbox |
| Selection / ZIP | Pending tiles excluded |
| Column alignment | Same breakpoints and gutter as the packer |
| State ownership | New `useUploadQueue` hook, called by the project page |

The "stays until cleared" choice keeps the current queue semantics and avoids a photo
visually jumping from the top of the gallery to the bottom mid-upload. The cost is that
a finished photo is briefly visible twice — once as a leading tile, once at the end of
the masonry (new uploads land at the end). This is the status quo behaviour today and is
accepted.

## Design

### 1. `lib/useUploadQueue.ts` (new)

Extracts the upload state machine from `UploadPanel` verbatim:

- Constants/helpers: `PART_SIZE`, `freshClientKey`, `contentTypeFor`.
- Type: `FileState` (`id`, `clientKey`, `file`, `preview`, `progress`, `status`, `error`).
- `addFiles(incoming)` — filters by `kindForFile`, pushes one toast per rejected file via
  the injected callback, appends accepted entries in selection order.
- `uploadOne` / `startAll` / `retryOne` — unchanged retry and idempotency behaviour. A
  retry re-sends the stored `clientKey` so `init`/`complete` converge instead of
  duplicating.
- `stop` — requests a halt after the current file. Today `UploadPanel` flips its
  `cancelRef` inline; that ref moves into the hook, so the sidebar can no longer reach
  it and the hook must expose it.
- `removeOne` — revokes the preview object URL before dropping the entry.
- `clearDone` — revokes previews for finished entries and drops them; failures stay.

Signature:

```ts
export interface UploadQueue {
  files: FileState[];
  running: boolean;
  queued: number;
  done: number;
  addFiles: (incoming: FileList | File[]) => void;
  startAll: () => void;
  /** Ask the running batch to halt once the current file settles. */
  stop: () => void;
  retryOne: (id: number) => void;
  removeOne: (id: number) => void;
  clearDone: () => void;
}

function useUploadQueue(
  projectId: number,
  options: { onUploaded: () => void; onReject: (fileName: string) => void },
): UploadQueue;
```

`onUploaded` replaces the current direct call, so `refreshKey` still bumps per completed
file. `onReject` keeps the `"<name>" is not a photo or video — skipped.` toast with
whoever renders the toasts.

### 2. `components/UploadPanel.tsx` (rewritten)

- Takes `queue: UploadQueue` as a prop and uses only `files`, `running`, `queued`, `done`,
  `addFiles`, `startAll`, `stop` and `clearDone`.
- Keeps: the `<section>` shell, `Upload Media` heading, the drag/browse dropzone with its
  `dragOver` state, the `done` of `total` counter, and the
  `Upload N` / `Stop after this file` / `Clear finished` button row.
- Removes: its queue `<ul>`, the per-card Remove/Retry markup, its `useToasts` instance
  and `ToastStack`.
- The counter's wording is adjusted to also surface failures, since the failure detail now
  lives on the tile: `1 of 4 uploaded · 1 failed`.

### 3. `components/PendingUploads.tsx` (new)

Presentational. Takes `queue: UploadQueue` and uses `files`, `retryOne` and `removeOne`.
Renders nothing when `files` is empty.

- Container: `grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4` — matching
  `columnCountFor()` and `GAP = 16` in `lib/masonry.ts`, so tile widths line up with the
  masonry below.
- Tile states: queued (dimmed placeholder, `Remove` on hover), uploading (preview image,
  progress bar pinned to the bottom edge, `%` readout), done (green `Done` badge,
  full-width green bar), failed (rose border, `Failed` badge, `Retry` + `Remove` on
  hover). Failed entries also show their `error` string.
- Images use the object URLs already created in `addFiles`; videos keep the current
  `video` placeholder glyph.
- Deliberately inert otherwise: no `onClick` on the tile body, no checkbox, not referenced
  by `selectMode`/`selectedIds`, and not passed to `ZipPanel`.

### 4. `components/MediaGrid.tsx`

- New optional prop `leading?: React.ReactNode`, rendered above `<MasonryGallery>` inside
  the same column.
- When `leading` is supplied and the gallery window is empty (`items.length === 0 &&
  originOffset === 0 && !loading && !seeking && !error`), the "Awaiting Photographs" panel
  is suppressed — on a brand-new shoot the pending tiles are the content, and the two
  messages would otherwise contradict each other.
- `MediaGrid` gains no knowledge of uploads; it only knows something sits above it.

### 5. `app/projects/[id]/page.tsx`

- `const queue = useUploadQueue(project.id, { onUploaded, onReject })`.
- `onReject` pushes through a page-level `useToasts()` instance rendered in the gallery
  tab (so the standard "not a photo" toast no longer depends on `UploadPanel`).
- Gallery column: `<MediaGrid … leading={<PendingUploads queue={queue} />} />`.
- Sidebar: `<UploadPanel queue={queue} />`.
- Client branch (`!isAdmin`) passes no `leading`.

## Layout behaviour worth noting

- Below `xl` the flex container is `flex-col-reverse`, so the sidebar (dropzone) renders
  above the gallery. Pending tiles therefore still appear under the dropzone on narrow
  screens, in the full-width gallery column. Acceptable and natural.
- The masonry's own upload-day divider (`✦ 15 October 2026`) already separates the
  lead-in tiles from the archive, so no extra heading is added above the pending tiles.
- The sidebar becomes shorter, which incidentally fixes the cramped queue thumbnails.

## Verification

No frontend test runner exists in this repo, so verification is:

1. `npx tsc --noEmit` in `portal/web` — clean.
2. `npx eslint` on the changed files — no new warnings.
3. Manual pass in `next dev`, signed in as the photographer, on a shoot with existing
   media and on an empty shoot:
   - Select a mixed batch (photos, a video, one rejected file) — tiles lead the gallery in
     selection order, the rejection toast fires once.
   - Start the upload — per-tile progress advances; `Stop after this file` halts after the
     current file.
   - Force a failure (offline mid-upload) — the tile shows `Failed` plus its message;
     `Retry` succeeds and does not duplicate media (the `clientKey` is reused).
   - `Remove` on a queued tile drops it and its thumbnail.
   - After completion, `Clear finished` empties the lead-in tiles; the uploaded photos
     remain at the end of the masonry.
   - On an empty shoot, pending tiles show and "Awaiting Photographs" is suppressed.
   - Resize across the `sm`/`lg` breakpoints — leading tiles and masonry keep identical
     column widths.
   - Client view is unchanged.
