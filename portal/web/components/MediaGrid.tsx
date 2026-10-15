"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Lightbox from "@/components/Lightbox";
import ZipPanel from "@/components/ZipPanel";
import { fetchMediaPage, type MediaAccess, type MediaView } from "@/lib/api";

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
export default function MediaGrid({ access }: { access: MediaAccess }) {
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
        <p className="text-muted">No photos in this project yet.</p>
      )}

      {error && items.length === 0 && (
        <div className="rounded-lg border border-border bg-surface-2 p-6 text-center">
          <p role="alert" className="text-sm text-red-400">
            {error}
          </p>
          <button
            type="button"
            onClick={loadMore}
            className="mt-4 rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
          >
            Try again
          </button>
        </div>
      )}

      <div className="columns-2 gap-3 sm:columns-3 [&>*]:mb-3">
        {items.map((item, index) => {
          const checked = selectMode && selectedIds.has(item.id);
          return (
            <button
              key={item.id}
              type="button"
              onClick={() => open(index)}
              aria-pressed={selectMode ? checked : undefined}
              className={`relative block w-full break-inside-avoid overflow-hidden rounded-lg border bg-surface-2 transition hover:border-accent ${
                checked
                  ? "cursor-pointer border-accent ring-1 ring-accent"
                  : "cursor-pointer border-border"
              }`}
              aria-label={
                selectMode ? `Select photo ${index + 1}` : `Open photo ${index + 1}`
              }
            >
              {selectMode && (
                <span
                  aria-hidden="true"
                  className={`absolute left-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border ${
                    checked
                      ? "border-accent bg-accent text-white"
                      : "border-border bg-surface text-transparent"
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
              {item.thumbUrl ? (
                // Plain <img> (not next/image): thumbs are short-lived presigned
                // URLs on another origin; native lazy-loading keeps 4G cheap.
                <img
                  src={item.thumbUrl}
                  alt=""
                  loading="lazy"
                  decoding="async"
                  className="w-full object-cover"
                />
              ) : (
                <div className="flex aspect-square w-full items-center justify-center text-sm text-muted">
                  {item.derivativeStatus === "failed" ? "Preview failed" : "Processing…"}
                </div>
              )}
            </button>
          );
        })}
      </div>

      <div ref={sentinelRef} aria-hidden="true" />

      {loading && <p className="mt-4 text-sm text-muted">Loading more…</p>}
      {!loading && exhausted && items.length > 0 && (
        <p className="mt-4 text-sm text-muted">
          {items.length} photo{items.length === 1 ? "" : "s"} — end of gallery.
        </p>
      )}

      {selectedIndex !== null && (
        <Lightbox
          items={items}
          index={selectedIndex}
          access={access}
          onClose={close}
          onIndex={goTo}
        />
      )}
    </>
  );
}
