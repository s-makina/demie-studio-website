"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Lightbox from "@/components/Lightbox";
import ZipPanel from "@/components/ZipPanel";
import { ConfirmDialog, ToastStack, useToasts } from "@/components/console-ui";
import {
  deleteMedia,
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

/**
 * Cursor-paginated grid (Task 9). Pages append via an intersection sentinel
 * (no full-list fetch); thumbs are native lazy images. Selection deep-links
 * through `?item=<id>` so back/forward and shared links work.
 */
export default function MediaGrid({
  access,
  isAdmin,
  refreshKey,
  onRemove,
}: {
  access: MediaAccess;
  /** Photographer sees per-photo Remove (Task 21). */
  isAdmin?: boolean;
  /** Bump to reload from the first page (after uploads). */
  refreshKey?: number;
  /** Fired after a photo is removed (parent clears lightbox state). */
  onRemove?: (id: number) => void;
}) {
  const [items, setItems] = useState<MediaView[]>([]);
  const [exhausted, setExhausted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Deep-link selection (?item=<id>). Initialized from the URL; back/forward
  // sync through popstate — no render-loop effects involved.
  const [selectedId, setSelectedId] = useState<number | null>(itemIdFromUrl);
  // ZIP selection (Task 13). In select mode thumb clicks toggle instead of
  // opening the lightbox; ZipPanel owns the request + progress below.
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const sentinelRef = useRef<HTMLDivElement>(null);
  const loadingRef = useRef(false);
  // Pagination cursor lives in a ref: it is only read/written inside the
  // async loader, never during render.
  const cursorRef = useRef<string | null | undefined>(undefined);
  const { toasts, push, dismiss } = useToasts();
  const [confirmRemove, setConfirmRemove] = useState<MediaView | null>(null);
  const [removing, setRemoving] = useState(false);

  const loadMore = useCallback(async () => {
    if (loadingRef.current || cursorRef.current === null) {
      return;
    }
    const cursor = cursorRef.current === undefined ? undefined : cursorRef.current;
    loadingRef.current = true;
    setLoading(true);
    setError(null);
    try {
      const page = await fetchMediaPage(access, cursor, PAGE_SIZE);
      setItems((prev) => [...prev, ...page.items]);
      if (page.nextCursor === "") {
        cursorRef.current = null;
        setExhausted(true);
      } else {
        cursorRef.current = page.nextCursor;
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not load photos.");
    } finally {
      loadingRef.current = false;
      setLoading(false);
    }
  }, [access]);

  // First page + infinite scroll both flow through the sentinel: it is
  // visible on an empty grid, so the observer fires on mount. Event and
  // observer callbacks (never the effect body) trigger the fetches.
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) {
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          void loadMore();
        }
      },
      { rootMargin: "800px" }
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [loadMore]);

  // Follow back/forward through ?item=<id>.
  useEffect(() => {
    const onPopState = () => setSelectedId(itemIdFromUrl());
    window.addEventListener("popstate", onPopState);
    return () => window.removeEventListener("popstate", onPopState);
  }, []);

  // External refresh (after uploads): reset to the first page and reload.
  // State updates are sequenced through the loader, not set during render.
  const refreshSeq = useRef(refreshKey);
  useEffect(() => {
    if (refreshSeq.current === refreshKey) {
      return;
    }
    refreshSeq.current = refreshKey;
    cursorRef.current = undefined;
    loadingRef.current = false;
    setItems([]);
    setExhausted(false);
  }, [refreshKey]);

  const doRemove = useCallback(async () => {
    if (!confirmRemove) {
      return;
    }
    setRemoving(true);
    try {
      await deleteMedia(confirmRemove.id);
      setItems((prev) => prev.filter((item) => item.id !== confirmRemove.id));
      setSelectedIds((prev) => {
        if (!prev.has(confirmRemove.id)) {
          return prev;
        }
        const next = new Set(prev);
        next.delete(confirmRemove.id);
        return next;
      });
      if (selectedId === confirmRemove.id && typeof window !== "undefined") {
        const params = new URLSearchParams(window.location.search);
        params.delete("item");
        const query = params.toString();
        window.history.replaceState(
          null,
          "",
          query ? `?${query}` : window.location.pathname
        );
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

  const found = selectedId === null
    ? -1
    : items.findIndex((item) => item.id === selectedId);
  const selectedIndex = found >= 0 ? found : null;

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

  const close = useCallback(() => {
    // We pushed an entry on open, so Back semantics close the lightbox.
    window.history.back();
  }, []);

  const goTo = useCallback(
    (index: number) => {
      const item = items[index];
      if (!item) {
        return;
      }
      window.history.replaceState(null, "", withQueryParam("item", String(item.id)));
      setSelectedId(item.id);
    },
    [items]
  );

  return (
    <>
      <ZipPanel
        access={access}
        loadedIds={items.map((item) => item.id)}
        selectedIds={selectedIds}
        selectMode={selectMode}
        onSelectModeChange={setSelectMode}
        onSelectAllVisible={() => setSelectedIds(new Set(items.map((item) => item.id)))}
        onClearSelection={() => setSelectedIds(new Set())}
      />

      {items.length === 0 && !loading && !error && (
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
            onClick={loadMore}
            className="mt-5 rounded-full border border-white/10 bg-surface-3 px-5 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-2"
          >
            Try again
          </button>
        </div>
      )}

      <div className="columns-2 gap-4 sm:columns-3 lg:columns-4 [&>*]:mb-4">
        {items.map((item, index) => {
          const checked = selectMode && selectedIds.has(item.id);
          const removable =
            isAdmin && !selectMode && item.status !== "uploading";
          return (
            <div
              key={item.id}
              className="group relative break-inside-avoid"
            >
              <button
                type="button"
                onClick={() => open(index)}
                aria-pressed={selectMode ? checked : undefined}
                className={`relative block w-full overflow-hidden rounded-xl border bg-surface-2 transition-all duration-300 ${
                  checked
                    ? "cursor-pointer border-accent ring-2 ring-accent/60 shadow-lg shadow-accent/10"
                    : "cursor-pointer border-white/[0.08] hover:border-accent/50 hover:shadow-xl"
                }`}
                aria-label={
                  selectMode ? `Select photo ${index + 1}` : `Open photo ${index + 1}`
                }
              >
                {selectMode && (
                  <span
                    aria-hidden="true"
                    className={`absolute left-3 top-3 z-10 flex h-6 w-6 items-center justify-center rounded-full border shadow-md backdrop-blur-md transition-all duration-200 ${
                      checked
                        ? "border-accent bg-accent text-background scale-105"
                        : "border-white/30 bg-surface/80 text-transparent hover:border-accent"
                    }`}
                  >
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                      <path
                        d="M2 6.5 4.8 9 10 3.5"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                  </span>
                )}

                {item.kind === "video" && (
                  <span className="absolute right-3 top-3 z-10 rounded-full bg-accent/90 px-2.5 py-0.5 text-[9px] font-semibold uppercase tracking-widest text-background shadow-md backdrop-blur-md">
                    Film
                  </span>
                )}

                {item.thumbUrl ? (
                  // Plain <img> (not next/image): thumbs are short-lived presigned
                  // URLs on another origin; native lazy-loading keeps 4G cheap.
                  <img
                    src={item.thumbUrl}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="w-full object-cover transition-transform duration-500 will-change-transform group-hover:scale-[1.03]"
                  />
                ) : (
                  <div className="flex aspect-square w-full items-center justify-center font-serif text-sm italic text-muted">
                    {item.derivativeStatus === "failed" ? "Preview failed" : "Processing…"}
                  </div>
                )}
              </button>

              {removable && (
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    setConfirmRemove(item);
                  }}
                  aria-label={`Remove photo ${index + 1}`}
                  title="Remove photo"
                  className="absolute right-2.5 top-2.5 z-10 flex items-center gap-1 rounded-full border border-white/10 bg-black/60 px-2.5 py-1 text-[11px] font-medium text-white/80 opacity-0 shadow-lg backdrop-blur-md transition hover:border-red-500/50 hover:bg-red-500 hover:text-white focus:opacity-100 group-hover:opacity-100"
                >
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                  </svg>
                  <span>Remove</span>
                </button>
              )}
            </div>
          );
        })}
      </div>

      <div ref={sentinelRef} aria-hidden="true" />

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
            {items.length} photograph{items.length === 1 ? "" : "s"} · End of gallery
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
          onRemove={(item) => setConfirmRemove(item)}
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
