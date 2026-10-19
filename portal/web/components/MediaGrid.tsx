"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Lightbox from "@/components/Lightbox";
import MasonryGallery, {
  type MasonryGalleryHandle,
} from "@/components/MasonryGallery";
import ZipPanel from "@/components/ZipPanel";
import { ConfirmDialog, ToastStack, useToasts } from "@/components/console-ui";
import {
  deleteMedia,
  fetchMediaItem,
  fetchMediaPage,
  isAbort,
  type MediaAccess,
  type MediaView,
} from "@/lib/api";

const PAGE_SIZE = 60;

function itemIdFromUrl(): number | null {
  if (typeof window === "undefined") {
    return null;
  }
  const raw = new URLSearchParams(window.location.search).get("item");
  const id = raw == null || raw === "" ? NaN : Number(raw);
  return Number.isInteger(id) && id > 0 ? id : null;
}

/** Same page with one query value replaced (keeps e.g. `?zip=` on `?item=`). */
function withQueryParam(key: string, value: string): string {
  const params = new URLSearchParams(window.location.search);
  params.set(key, value);
  return `?${params.toString()}`;
}

function clearItemParam(): void {
  const params = new URLSearchParams(window.location.search);
  params.delete("item");
  const query = params.toString();
  window.history.replaceState(null, "", query ? `?${query}` : window.location.pathname);
}

/**
 * Cursor-paginated, windowed gallery (Tasks 9 + 22).
 *
 * The grid keeps a *window* of items (`originOffset`..`originOffset + length`)
 * instead of the whole shoot: the API is keyset-paged forward from wherever the
 * window starts, and {@link MasonryGallery} only mounts the tiles on screen. A
 * 50,000-photo project therefore costs the same as a 50-photo one, and the
 * timeline rail can seek straight to any position.
 *
 * Selection deep-links through `?item=<id>` so back/forward and shared links
 * work, and it survives a seek so a client can ZIP photos from different regions
 * of a shoot.
 */
export default function MediaGrid({
  access,
  isAdmin,
  refreshKey,
  onRemove,
  leading,
}: {
  access: MediaAccess;
  /** Photographer sees per-photo Remove (Task 21). */
  isAdmin?: boolean;
  /** Bump after uploads: new files land at the end of the shoot. */
  refreshKey?: number;
  /** Fired after a photo is removed (parent clears lightbox state). */
  onRemove?: (id: number) => void;
  /**
   * Rendered directly above the grid, in the same column. Used for the queued
   * uploads so they lead the gallery; this component stays ignorant of what
   * they are. Pass `null` when the slot has nothing to show: a present slot
   * suppresses the "nothing here yet" panel, so a shoot whose only content is
   * pending uploads does not read as empty, while a genuinely empty one does.
   */
  leading?: React.ReactNode;
}) {
  const [items, setItems] = useState<MediaView[]>([]);
  const [originOffset, setOriginOffset] = useState(0);
  const [total, setTotal] = useState(0);
  const [exhausted, setExhausted] = useState(false);
  const [loading, setLoading] = useState(true);
  const [seeking, setSeeking] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Deep-link selection (?item=<id>). Initialized from the URL; back/forward
  // sync through popstate — no render-loop effects involved.
  const [selectedId, setSelectedId] = useState<number | null>(itemIdFromUrl);
  // ZIP selection (Task 13). In select mode thumb clicks toggle instead of
  // opening the lightbox; ZipPanel owns the request + progress below.
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const { toasts, push, dismiss } = useToasts();
  const [confirmRemove, setConfirmRemove] = useState<MediaView | null>(null);
  const [removing, setRemoving] = useState(false);

  const masonryRef = useRef<MasonryGalleryHandle>(null);
  // Pagination state lives in refs: it is only read/written inside async
  // loaders, effects and handlers — never during render. `null` means "already
  // at the end"; `undefined` means "start from the top of the shoot".
  const cursorRef = useRef<string | null | undefined>(undefined);
  const tailCursorRef = useRef<string | null>(null);
  const loadingRef = useRef(false);
  const seekingRef = useRef(false);
  const exhaustedRef = useRef(false);
  // Discards responses belonging to a window that has since been replaced.
  const genRef = useRef(0);
  // Lightbox "next" past the window edge: step forward once the page lands.
  const advanceRef = useRef(false);
  // One URL refresh per photo — a second failure is left alone (no retry loop).
  const retriedRef = useRef<Set<number>>(new Set());

  // Callers rebuild `access` on most renders; keep the latest object in a ref so
  // the loaders stay identity-stable, and key resets on its stable path.
  const accessRef = useRef(access);
  const accessKey = access.listPath;
  useEffect(() => {
    accessRef.current = access;
  }, [access]);

  const itemsRef = useRef(items);
  useEffect(() => {
    itemsRef.current = items;
  }, [items]);

  // --------------------------------------------------------------- pagination

  const loadForward = useCallback(async () => {
    if (loadingRef.current || exhaustedRef.current || cursorRef.current === null) {
      return;
    }
    const cursor = cursorRef.current === undefined ? undefined : cursorRef.current;
    const gen = genRef.current;
    loadingRef.current = true;
    setLoading(true);
    setError(null);
    try {
      const page = await fetchMediaPage(accessRef.current, { cursor, limit: PAGE_SIZE });
      if (gen !== genRef.current) {
        return;
      }
      tailCursorRef.current = page.endCursor === "" ? null : page.endCursor;
      if (page.nextCursor === "") {
        cursorRef.current = null;
        exhaustedRef.current = true;
        setExhausted(true);
      } else {
        cursorRef.current = page.nextCursor;
      }
      setTotal(page.total);
      setItems((prev) => [...prev, ...page.items]);
      // The lightbox asked for "the next photo" while sitting on the last loaded
      // one, so that photo is the first of this page.
      if (advanceRef.current) {
        advanceRef.current = false;
        const next = page.items[0];
        if (next && typeof window !== "undefined") {
          window.history.replaceState(
            null,
            "",
            withQueryParam("item", String(next.id))
          );
          setSelectedId(next.id);
        }
      }
    } catch (e) {
      if (gen === genRef.current && !isAbort(e)) {
        setError(e instanceof Error ? e.message : "Could not load photos.");
      }
    } finally {
      loadingRef.current = false;
      setLoading(false);
    }
  }, []);

  const seek = useCallback(
    async (offset: number) => {
      if (seekingRef.current) {
        return;
      }
      const target = Math.max(0, Math.floor(offset));
      const gen = genRef.current + 1;
      genRef.current = gen;
      seekingRef.current = true;
      loadingRef.current = true;
      advanceRef.current = false;
      setSeeking(true);
      setError(null);
      // The lightbox target is not part of the new window.
      if (typeof window !== "undefined") {
        clearItemParam();
      }
      setSelectedId(null);
      try {
        const page = await fetchMediaPage(accessRef.current, {
          offset: target,
          limit: PAGE_SIZE,
        });
        if (gen !== genRef.current) {
          return;
        }
        tailCursorRef.current = page.endCursor === "" ? null : page.endCursor;
        cursorRef.current = page.nextCursor === "" ? null : page.nextCursor;
        exhaustedRef.current = page.nextCursor === "";
        setExhausted(exhaustedRef.current);
        setItems(page.items);
        setOriginOffset(target);
        setTotal(page.total);
        masonryRef.current?.scrollToTop();
      } catch (e) {
        if (gen === genRef.current && !isAbort(e)) {
          push(e instanceof Error ? e.message : "Could not jump to that photo.");
        }
      } finally {
        if (gen === genRef.current) {
          seekingRef.current = false;
          loadingRef.current = false;
          setSeeking(false);
        }
      }
    },
    [push]
  );

  const approachEnd = useCallback(() => {
    void loadForward();
  }, [loadForward]);

  const handleSeek = useCallback(
    (offset: number) => {
      void seek(offset);
    },
    [seek]
  );

  // New uploads land at the end (sort grows), so resume forward from the tail
  // anchor instead of discarding the window — no scroll jump, no re-download.
  const refreshSeq = useRef(refreshKey);
  useEffect(() => {
    if (refreshSeq.current === refreshKey) {
      return;
    }
    refreshSeq.current = refreshKey;
    exhaustedRef.current = false;
    setExhausted(false);
    if (cursorRef.current === null) {
      cursorRef.current = tailCursorRef.current ?? undefined;
    }
    // Hand the fetch to the next microtask: the effect body only re-arms the
    // refs the loader reads, so nothing cascades out of this render.
    queueMicrotask(() => void loadForward());
  }, [refreshKey, loadForward]);

  // Different project (same component instance): rebuild the window from zero.
  const accessSeq = useRef(accessKey);
  useEffect(() => {
    if (accessSeq.current === accessKey) {
      return;
    }
    accessSeq.current = accessKey;
    genRef.current += 1;
    cursorRef.current = undefined;
    tailCursorRef.current = null;
    loadingRef.current = false;
    seekingRef.current = false;
    exhaustedRef.current = false;
    retriedRef.current = new Set();
    setItems([]);
    setOriginOffset(0);
    setTotal(0);
    setExhausted(false);
    setSeeking(false);
    setSelectedId(null);
    setSelectedIds(new Set());
    queueMicrotask(() => void loadForward());
  }, [accessKey, loadForward]);

  // Follow back/forward through ?item=<id>.
  useEffect(() => {
    const onPopState = () => setSelectedId(itemIdFromUrl());
    window.addEventListener("popstate", onPopState);
    return () => window.removeEventListener("popstate", onPopState);
  }, []);

  // ------------------------------------------------------------- interaction

  const toggle = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const open = useCallback(
    (index: number) => {
      const item = items[index];
      if (!item) {
        return;
      }
      if (selectMode) {
        toggle(item.id);
        return;
      }
      window.history.pushState(null, "", withQueryParam("item", String(item.id)));
      setSelectedId(item.id);
    },
    [items, selectMode, toggle]
  );

  const goTo = useCallback(
    (index: number) => {
      const item = itemsRef.current[index];
      if (!item) {
        return;
      }
      window.history.replaceState(null, "", withQueryParam("item", String(item.id)));
      setSelectedId(item.id);
    },
    []
  );

  const close = useCallback(() => {
    // We pushed an entry on open, so Back semantics close the lightbox.
    window.history.back();
  }, []);

  const requestRemove = useCallback((item: MediaView) => {
    setConfirmRemove(item);
  }, []);

  // Presigned thumb URLs expire (15 min). Refreshing one item keeps a long
  // browsing session from showing broken tiles deep in the window.
  const onThumbError = useCallback((item: MediaView) => {
    if (retriedRef.current.has(item.id)) {
      return;
    }
    retriedRef.current.add(item.id);
    fetchMediaItem(accessRef.current, item.id)
      .then((fresh) => {
        setItems((prev) =>
          prev.map((current) => (current.id === fresh.id ? fresh : current))
        );
      })
      .catch(() => {
        // Leave the tile as-is; a transient failure is not worth a toast.
      });
  }, []);

  const needMoreForLightbox = useCallback(() => {
    if (exhaustedRef.current) {
      return;
    }
    advanceRef.current = true;
    void loadForward();
  }, [loadForward]);

  const loadedIds = useMemo(() => items.map((item) => item.id), [items]);

  const found = selectedId === null
    ? -1
    : items.findIndex((item) => item.id === selectedId);
  const selectedIndex = found >= 0 ? found : null;

  const doRemove = useCallback(async () => {
    if (!confirmRemove) {
      return;
    }
    setRemoving(true);
    try {
      await deleteMedia(confirmRemove.id);
      setItems((prev) => prev.filter((item) => item.id !== confirmRemove.id));
      setTotal((value) => Math.max(0, value - 1));
      setSelectedIds((prev) => {
        if (!prev.has(confirmRemove.id)) {
          return prev;
        }
        const next = new Set(prev);
        next.delete(confirmRemove.id);
        return next;
      });
      if (selectedId === confirmRemove.id && typeof window !== "undefined") {
        clearItemParam();
        setSelectedId(null);
      }
      onRemove?.(confirmRemove.id);
      setConfirmRemove(null);
      push("Photo removed.");
    } catch (e) {
      if (!isAbort(e)) {
        push(e instanceof Error ? e.message : "Could not remove the photo.");
      }
    } finally {
      setRemoving(false);
    }
  }, [confirmRemove, selectedId, onRemove, push]);

  return (
    <>
      <ZipPanel
        access={access}
        loadedIds={loadedIds}
        selectedIds={selectedIds}
        selectMode={selectMode}
        onSelectModeChange={setSelectMode}
        onSelectAllVisible={() => {
          const onScreen = masonryRef.current?.visibleIds() ?? [];
          setSelectedIds(
            new Set(onScreen.length > 0 ? onScreen : items.map((item) => item.id))
          );
        }}
        onClearSelection={() => setSelectedIds(new Set())}
      />

      {items.length === 0 && originOffset === 0 && !loading && !seeking && !error && !leading && (
        <div className="mx-auto my-16 max-w-md rounded-2xl border border-white/[0.08] bg-surface-2/60 p-10 text-center backdrop-blur-xl">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-white/10 bg-surface-3 text-muted">
            <svg
              width="22"
              height="22"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
              <circle cx="8.5" cy="8.5" r="1.5" />
              <polyline points="21 15 16 10 5 21" />
            </svg>
          </div>
          <h3 className="font-serif text-2xl font-light italic text-foreground">
            Awaiting Photographs
          </h3>
          <p className="mt-2 text-xs tracking-wider uppercase text-muted">
            No photos in this project yet.
          </p>
        </div>
      )}

      {error && items.length === 0 && (
        <div className="mx-auto my-12 max-w-lg rounded-2xl border border-red-500/20 bg-surface-2/90 p-8 text-center shadow-2xl backdrop-blur-xl">
          <div className="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full border border-red-500/20 bg-red-500/10 text-red-400">
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          </div>
          <p role="alert" className="text-sm text-red-400">
            {error}
          </p>
          <button
            type="button"
            onClick={() => void loadForward()}
            className="mt-5 rounded-full border border-white/10 bg-surface-3 px-5 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-2"
          >
            Try again
          </button>
        </div>
      )}

      {leading}

      {/* Always mounted: its bottom sentinel also drives the first fetch. */}
      <MasonryGallery
        ref={masonryRef}
        items={items}
        originOffset={originOffset}
        total={total}
        seeking={seeking}
        selectMode={selectMode}
        selectedIds={selectedIds}
        isAdmin={Boolean(isAdmin)}
        onOpen={open}
        onRemove={requestRemove}
        onApproachEnd={approachEnd}
        onSeek={handleSeek}
        onThumbError={onThumbError}
      />

      {loading && (
        <div className="mt-8 flex items-center justify-center gap-2.5 py-4 text-xs font-medium tracking-wider uppercase text-muted">
          <span className="h-2 w-2 rounded-full bg-accent animate-ping" />
          <span>Loading more memories…</span>
        </div>
      )}

      {!loading && exhausted && items.length > 0 && (
        <div className="mt-12 mb-8 flex flex-col items-center">
          <div className="flex items-center gap-4 w-full max-w-xs justify-center">
            <span className="h-px flex-1 bg-white/[0.08]" />
            <span className="font-serif text-sm italic text-accent">✦</span>
            <span className="h-px flex-1 bg-white/[0.08]" />
          </div>
          <p className="mt-3 text-xs tracking-widest2 uppercase text-muted font-medium">
            {total.toLocaleString()} photograph{total === 1 ? "" : "s"} · End of gallery
          </p>
        </div>
      )}

      {selectedIndex !== null && (
        <Lightbox
          items={items}
          index={selectedIndex}
          access={access}
          onClose={close}
          onIndex={goTo}
          isAdmin={isAdmin}
          onRemove={requestRemove}
          position={originOffset + selectedIndex + 1}
          total={total}
          hasMore={!exhausted}
          loading={loading}
          onNeedMore={needMoreForLightbox}
        />
      )}

      <ConfirmDialog
        open={confirmRemove !== null}
        title="Remove this photo?"
        body="The original and its previews are deleted immediately. Downloads already saved by the client keep working — this cannot be undone."
        confirmLabel="Remove photo"
        busy={removing}
        onCancel={() => setConfirmRemove(null)}
        onConfirm={() => void doRemove()}
      />
      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </>
  );
}
