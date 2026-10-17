"use client";

import {
  memo,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type Ref,
} from "react";
import TimelineRail, { type TimelineMark } from "@/components/TimelineRail";
import type { MediaView } from "@/lib/api";
import {
  DEFAULT_ASPECT,
  DIVIDER_HEIGHT,
  MasonryLayout,
  columnCountFor,
  type MasonryNode,
} from "@/lib/masonry";

/** Gutter between tiles, matching the old `gap-4` grid. */
const GAP = 16;
/** Pixels of off-screen tiles kept mounted (smooths fast scrolling). */
const OVERSCAN = 1400;
/** Start fetching the next page this far before the packed bottom. */
const APPROACH = 1000;

export interface MasonryGalleryHandle {
  /** Ids of the tiles currently on screen (drives "All visible" selection). */
  visibleIds: () => number[];
  /** Bring the top of the grid back to the top of the viewport (after a seek). */
  scrollToTop: () => void;
}

interface MasonryGalleryProps {
  items: MediaView[];
  /** Absolute index of the window's first item. */
  originOffset: number;
  /** Total media in the project. */
  total: number;
  seeking: boolean;
  selectMode: boolean;
  selectedIds: Set<number>;
  isAdmin: boolean;
  onOpen: (index: number) => void;
  onRemove: (item: MediaView) => void;
  /** Scrolled to the end of the loaded window; fetch the next page. */
  onApproachEnd: () => void;
  onSeek: (offset: number) => void;
  /** A tile's presigned thumb URL failed (expired); refresh that item. */
  onThumbError: (item: MediaView) => void;
  ref?: Ref<MasonryGalleryHandle>;
}

interface TileProps {
  node: MasonryNode;
  item: MediaView;
  absoluteIndex: number;
  checked: boolean;
  selectMode: boolean;
  isAdmin: boolean;
  onOpen: (index: number) => void;
  onRemove: (item: MediaView) => void;
  onThumbError: (item: MediaView) => void;
}

/**
 * One absolutely positioned tile. Memoized so a scroll frame (which re-renders
 * the surface ~60 times a second) only touches the tiles whose inputs changed.
 */
const MasonryTile = memo(function MasonryTile({
  node,
  item,
  absoluteIndex,
  checked,
  selectMode,
  isAdmin,
  onOpen,
  onRemove,
  onThumbError,
}: TileProps) {
  const removable = isAdmin && !selectMode && item.status !== "uploading";

  return (
    <div
      className="group absolute"
      style={{ top: node.top, left: node.left, width: node.width, height: node.height }}
    >
      <button
        type="button"
        onClick={() => onOpen(node.index)}
        aria-pressed={selectMode ? checked : undefined}
        className={`relative block h-full w-full overflow-hidden rounded-xl border bg-surface-2 transition-all duration-300 ${
          checked
            ? "cursor-pointer border-accent ring-2 ring-accent/60 shadow-lg shadow-accent/10"
            : "cursor-pointer border-white/[0.08] hover:border-accent/50 hover:shadow-xl"
        }`}
        aria-label={
          selectMode ? `Select photo ${absoluteIndex}` : `Open photo ${absoluteIndex}`
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
          // Plain <img> (not next/image): thumbs are short-lived presigned URLs
          // on another origin. Only visible tiles are mounted, so the browser
          // never holds more than a screenful of decoded bitmaps.
          <img
            src={item.thumbUrl}
            alt=""
            loading="lazy"
            decoding="async"
            onError={() => onThumbError(item)}
            className="h-full w-full object-cover transition-transform duration-500 will-change-transform group-hover:scale-[1.03]"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center font-serif text-sm italic text-muted">
            {item.derivativeStatus === "failed" ? "Preview failed" : "Processing…"}
          </div>
        )}
      </button>

      {removable && (
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation();
            onRemove(item);
          }}
          aria-label={`Remove photo ${absoluteIndex}`}
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
});

/**
 * Windowed masonry surface (Task 22). Owns measurement, the packer and the
 * scroll-driven visible slice: only tiles intersecting the viewport (plus a
 * small overscan) exist in the DOM, so a 50,000-photo shoot costs the same as a
 * 50-photo one. A sentinel at the packed bottom drives paging ahead.
 */
export default function MasonryGallery({
  items,
  originOffset,
  total,
  seeking,
  selectMode,
  selectedIds,
  isAdmin,
  onOpen,
  onRemove,
  onApproachEnd,
  onSeek,
  onThumbError,
  ref,
}: MasonryGalleryProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const sentinelRef = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(0);
  const [viewport, setViewport] = useState({ top: 0, height: 0 });

  // ---------------------------------------------------------------- measurement
  useEffect(() => {
    const element = containerRef.current;
    if (!element) {
      return;
    }
    const measure = () => setWidth(element.clientWidth);
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  // ------------------------------------------------------------------- packing
  // Pure and top-down: the packer is a plain function of (items, width), so it
  // is safe under StrictMode and the React Compiler. Packing is O(items) with no
  // global sort (per-column arrays stay top-sorted), which keeps page appends
  // cheap even in a 50,000-photo shoot.
  const layout = useMemo(() => {
    if (width <= 0 || items.length === 0) {
      return null;
    }
    const packed = new MasonryLayout({
      width,
      columns: columnCountFor(width),
      gap: GAP,
      dividerHeight: DIVIDER_HEIGHT,
      fallbackAspect: DEFAULT_ASPECT,
    });
    packed.append(items);
    return packed;
  }, [items, width]);

  const gridHeight = layout?.height ?? 0;
  const visible = layout ? layout.visible(viewport.top, viewport.height, OVERSCAN) : [];

  // ------------------------------------------------------------ scroll tracking
  useEffect(() => {
    let frame = 0;
    const update = () => {
      frame = 0;
      const element = containerRef.current;
      if (!element) {
        return;
      }
      const rect = element.getBoundingClientRect();
      setViewport({ top: -rect.top, height: window.innerHeight });
    };
    const schedule = () => {
      if (frame === 0) {
        frame = requestAnimationFrame(update);
      }
    };
    update();
    window.addEventListener("scroll", schedule, { passive: true });
    window.addEventListener("resize", schedule);
    return () => {
      if (frame !== 0) {
        cancelAnimationFrame(frame);
      }
      window.removeEventListener("scroll", schedule);
      window.removeEventListener("resize", schedule);
    };
  }, [width]);

  // ------------------------------------------------------------- load-ahead
  // The sentinel sits at the packed bottom; when it comes within APPROACH of the
  // viewport the grid pages ahead. On mount it is visible, which is what kicks
  // off the very first fetch.
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) {
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          onApproachEnd();
        }
      },
      { rootMargin: `${APPROACH}px` }
    );
    observer.observe(sentinel);
    return () => observer.disconnect();
  }, [onApproachEnd]);

  useImperativeHandle(
    ref,
    () => ({
      visibleIds() {
        if (!layout) {
          return items.map((item) => item.id);
        }
        return layout
          .visible(viewport.top, viewport.height, 0)
          .filter((node) => node.kind === "tile")
          .map((node) => items[node.index]?.id)
          .filter((id): id is number => typeof id === "number");
      },
      scrollToTop() {
        const element = containerRef.current;
        if (!element) {
          return;
        }
        const top = element.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: Math.max(0, top - GAP) });
      },
    }),
    [layout, viewport, items]
  );

  // -------------------------------------------------------------- day timeline
  // One tick per upload day, straight off the packer — no O(items) rescan and no
  // per-photo `Intl` work, so this is cheap even at 50,000 photos.
  const marks = useMemo<TimelineMark[]>(() => {
    if (!layout) {
      return [];
    }
    return layout.dayMarkers().map((day) => ({
      index: originOffset + day.index,
      label: day.dayLabel ?? "",
    }));
  }, [layout, originOffset]);

  const topTile = visible.find(
    (node) => node.kind === "tile" && node.top + node.height > viewport.top
  );
  const currentAbsolute = originOffset + (topTile?.index ?? 0);
  const chipTop = Math.max(0, Math.min(viewport.top, Math.max(0, gridHeight - 1)));
  const chipDay = layout?.dayAt(viewport.top) ?? null;

  return (
    <>
      <div
        ref={containerRef}
        className="relative"
        style={width > 0 ? { height: gridHeight } : undefined}
      >
        {/* Pinned upload-day chip: in-flow dividers can't be CSS-sticky inside
            an absolutely positioned virtual container. */}
        {chipDay?.dayLabel && viewport.top < gridHeight && (
          <div
            className="pointer-events-none absolute left-0 z-20 -translate-y-1/2"
            style={{ top: chipTop }}
          >
            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface-glass px-3 py-1 text-[10px] font-medium uppercase tracking-widest2 text-foreground shadow-lg backdrop-blur-xl">
              <span className="h-1.5 w-1.5 rounded-full bg-accent" />
              {chipDay.dayLabel}
            </span>
          </div>
        )}

        {width > 0 &&
          visible.map((node) => {
            if (node.kind === "divider") {
              return (
                <div
                  key={node.key}
                  className="absolute flex items-center gap-4"
                  style={{
                    top: node.top,
                    left: node.left,
                    width: node.width,
                    height: node.height,
                  }}
                >
                  <span className="font-serif text-sm italic text-accent">✦</span>
                  <span className="text-[11px] font-medium uppercase tracking-widest2 text-muted">
                    {node.dayLabel}
                  </span>
                  <span className="h-px flex-1 bg-white/[0.08]" />
                </div>
              );
            }
            const item = items[node.index];
            if (!item) {
              return null;
            }
            return (
              <MasonryTile
                key={node.key}
                node={node}
                item={item}
                absoluteIndex={originOffset + node.index + 1}
                checked={selectMode && selectedIds.has(item.id)}
                selectMode={selectMode}
                isAdmin={isAdmin}
                onOpen={onOpen}
                onRemove={onRemove}
                onThumbError={onThumbError}
              />
            );
          })}

        {/* Paging sentinel: also the mount trigger for the first page. */}
        <div
          ref={sentinelRef}
          aria-hidden="true"
          className="absolute left-0 w-px"
          style={{ top: gridHeight, height: 1 }}
        />
      </div>

      <TimelineRail
        total={total}
        offset={originOffset}
        loaded={items.length}
        current={currentAbsolute}
        marks={marks}
        busy={seeking}
        onSeek={onSeek}
      />
    </>
  );
}
